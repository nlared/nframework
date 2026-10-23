<?php
/**
 * Gráficas a partir del pipeline de TableF (includes/class.Tablef.php) o de una consulta guardada de TablefIA
 * (includes/class.TablefIA.php). Ambos componentes abren esta página en otra pestaña:
 *
 *   /nframework/tablefg.php?source=tablef&id=<id de la Table>
 *   /nframework/tablefg.php?source=tablefia&id=<id del componente>&token=<token>&query=<_id en nftablesfia>
 *
 * La consulta sale de la sesión (el navegador solo elige cuál): TableF usa el pipeline ya filtrado por
 * datatablef.php; TablefIA vuelve a validar el pipeline guardado con su configuración actual. La página carga
 * las filas por AJAX y las agrupa en el navegador (Chart.js): tipo de gráfica, categoría, valores y agregación.
 */
set_time_limit(120);
require 'include.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

const TABLEFG_LIMIT = 5000;

$source = is_string($_GET['source'] ?? null) ? $_GET['source'] : '';
$id = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';

/** Valor de una celda para la gráfica: números como número, fechas como texto ISO, lo demás como texto. */
function tablefgValue($value)
{
	if ($value instanceof MongoDB\BSON\UTCDateTime) {
		// En la zona del sitio, para que agrupar por día o mes no dependa de UTC.
		return $value->toDateTime()->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s');
	}
	$value = normalizeBsonValue($value);
	if ($value === null || is_int($value) || is_float($value) || is_string($value)) {
		return $value;
	}
	if (is_bool($value)) {
		return $value ? 'Sí' : 'No';
	}
	return json_encode(mongoToArray($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Datos de la gráfica: ['title', 'subtitle', 'columns' => [['field', 'title']], 'rows', 'truncated'].
 * Lanza RuntimeException si la sesión no tiene la consulta.
 */
function tablefgLoad(string $source, string $id): array
{
	global $m, $user;
	if ($source === 'tablef') {
		$info = $_SESSION['datatable'][$id] ?? null;
		if (empty($info) || empty($info['columns'])) {
			throw new RuntimeException('La sesión de la tabla caducó; recargue la página de origen.');
		}
		$columns = [];
		$project = ['_id' => 0];
		foreach (array_values($info['columns']) as $i => $field) {
			$columns[] = ['field' => (string) $field, 'title' => $info['titles'][$i] ?? (string) $field];
			$project[$field] = $field === '_id' ? ['$toString' => '$_id'] : 1;
		}
		$pipeline = (array) ($info['pipeline'] ?? []);
		$pipeline[] = ['$limit' => TABLEFG_LIMIT + 1];
		$pipeline[] = ['$project' => $project];
		$rows = [];
		$cursor = $m->{$info['db']}->{$info['collection']}->aggregate($pipeline, [
			'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
			'maxTimeMS' => 30000,
		]);
		foreach ($cursor as $doc) {
			$row = [];
			foreach ($columns as $column) {
				$row[] = tablefgValue(getNestedValue($doc, $column['field']));
			}
			$rows[] = $row;
		}
		return [
			'title' => 'Gráfica',
			'subtitle' => (string) $info['collection'],
			'columns' => $columns,
			'rows' => array_slice($rows, 0, TABLEFG_LIMIT),
			'truncated' => count($rows) > TABLEFG_LIMIT,
		];
	}

	if ($source === 'tablefia') {
		$cfg = $_SESSION['tablefia'][$id] ?? null;
		$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
		if (empty($cfg) || $token === '' || !hash_equals($cfg['token'], $token)) {
			throw new RuntimeException('La sesión del componente caducó; recargue la página de origen.');
		}
		if ($cfg['requireauth'] && !$user->isLoggedIn()) {
			throw new RuntimeException('Inicie sesión.');
		}
		$queryId = $_GET['query'] ?? null;
		if (!is_string($queryId) || !isValidObjectId($queryId)) {
			throw new RuntimeException('La consulta no existe.');
		}
		$filter = ['_id' => toMongoId($queryId), 'uso' => $cfg['uso']];
		if (!$cfg['shared']) {
			$filter['usuario'] = isValidObjectId($user->_id) ? toMongoId($user->_id) : null;
		}
		$db = $m->{$cfg['db']};
		$doc = $db->nftablesfia->findOne($filter, ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
		if (!$doc) {
			throw new RuntimeException('La consulta no existe.');
		}
		// Se valida de nuevo: la configuración del componente pudo cambiar desde que se guardó.
		$stages = TablefIA::parsePipeline((string) $doc['pipeline']);
		$pipeline = TablefIA::buildPipeline($cfg, $doc['coleccion'], $stages, TABLEFG_LIMIT + 1);
		$rows = [];
		$cursor = $db->{$doc['coleccion']}->aggregate($pipeline, [
			'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
			'maxTimeMS' => 30000,
		]);
		foreach ($cursor as $row) {
			$values = [];
			foreach ($doc['columnas'] as $column) {
				$values[] = tablefgValue($row[$column] ?? null);
			}
			$rows[] = $values;
		}
		return [
			'title' => (string) $doc['prompt'],
			'subtitle' => (string) $doc['explicacion'],
			'columns' => array_map(fn($c) => ['field' => (string) $c, 'title' => (string) $c], $doc['columnas']),
			'rows' => array_slice($rows, 0, TABLEFG_LIMIT),
			'truncated' => count($rows) > TABLEFG_LIMIT,
		];
	}

	throw new RuntimeException('Origen no válido.');
}

if ($nframework->isAjax()) {
	try {
		$result = ['error' => false] + tablefgLoad($source, $id);
	} catch (RuntimeException | InvalidArgumentException $e) {
		$result = ['error' => $e->getMessage()];
	} catch (MongoDB\Driver\Exception\Exception $e) {
		error_log('tablefg: ' . $e->getMessage());
		$result = ['error' => 'MongoDB rechazó la consulta' . ($developermode ? ': ' . $e->getMessage() : '.')];
	}
	return;
}

$nframework->usecommon = true;
$nframework->metas['title'] = 'Gráfica';
$nframework->jss['150'] = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';

$javas->addjs(<<<'JS'
	(function(){
		var $root = $('#tablefg'), data = null, chart = null, types = [], slots = {};
		// Paleta categórica en orden fijo (clara / oscura); el color sigue a la serie, nunca a su posición en un filtro.
		var PALETTE = {
			light: ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'],
			dark: ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767']
		};
		var OTHER = {light: '#a3a29b', dark: '#6b6a64'};
		var AGG = {sum: 'Suma', avg: 'Promedio', min: 'Mínimo', max: 'Máximo'};
		function dark() { return document.documentElement.classList.contains('dark-side'); }
		function mode() { return dark() ? 'dark' : 'light'; }
		function num(v) {
			if (typeof v === 'number') { return v; }
			if (typeof v === 'string' && /^\s*-?\d+(\.\d+)?([eE][-+]?\d+)?\s*$/.test(v)) { return parseFloat(v); }
			return null;
		}
		function isDate(v) { return typeof v === 'string' && /^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2})?)?/.test(v); }
		// Tipo de cada columna según sus valores no vacíos: number, date o text.
		function detectTypes() {
			types = data.columns.map(function(c, i){
				var n = 0, d = 0, total = 0;
				data.rows.forEach(function(r){
					var v = r[i];
					if (v === null || v === '') { return; }
					total++;
					if (num(v) !== null) { n++; } else if (isDate(v)) { d++; }
				});
				if (total > 0 && n === total) { return 'number'; }
				if (total > 0 && d === total) { return 'date'; }
				return 'text';
			});
		}
		function fillControls() {
			var $x = $root.find('[name=x]').empty(), $ys = $root.find('.tablefg-ys').empty();
			var firstX = types.indexOf('date') >= 0 ? types.indexOf('date') : types.indexOf('text');
			var numbers = 0, checked = 0;
			data.columns.forEach(function(c, i){
				$('<option>').val(i).text(c.title).prop('selected', i === firstX).appendTo($x);
				if (types[i] === 'number') {
					// Cada columna numérica tiene su color fijo: no cambia al marcar o desmarcar otras.
					slots[i] = numbers;
					var $l = $('<label class="tablefg-chip">');
					// Identificadores (folio, id, número) no se marcan por defecto: sumarlos no tiene sentido.
					var ident = /^(_?id|folio|n[uú]m(ero)?\.?|no\.?)$/i.test($.trim(c.title));
					$('<input type="checkbox">').val(i).prop('checked', !ident && checked < 3).appendTo($l);
					if (!ident) { checked++; }
					$('<span>').text(c.title).appendTo($l);
					$ys.append($l);
					numbers++;
				}
			});
			if (numbers === 0) {
				$ys.append($('<p class="tablefg-hint">').text('No hay columnas numéricas: se grafica el conteo de filas.'));
				$root.find('[name=agg]').val('count');
			}
			$root.find('.tablefg-dategroup').toggle(types[$x.val()] === 'date');
		}
		function dateKey(v, group) {
			var s = String(v);
			return group === 'year' ? s.substr(0, 4) : group === 'month' ? s.substr(0, 7) : group === 'day' ? s.substr(0, 10) : s;
		}
		// Agrupa las filas por la categoría y calcula cada serie. Devuelve {labels, series: [{label, values}]}.
		function aggregate(maxTop) {
			var x = parseInt($root.find('[name=x]').val(), 10);
			var agg = $root.find('[name=agg]').val();
			var group = types[x] === 'date' ? $root.find('[name=dategroup]').val() : '';
			var ys = $root.find('.tablefg-ys input:checked').map(function(){ return parseInt(this.value, 10); }).get();
			if (agg === 'count' || ys.length === 0) { agg = 'count'; ys = [null]; }

			var labels = [], series = ys.map(function(y){
				return {col: y, label: y === null ? 'Conteo de filas' : data.columns[y].title + (agg === 'none' ? '' : ' (' + AGG[agg] + ')'), values: []};
			});
			if (agg === 'none') {
				data.rows.forEach(function(r){
					labels.push(r[x] === null ? '' : (group ? dateKey(r[x], group) : String(r[x])));
					ys.forEach(function(y, s){ series[s].values.push(num(r[y])); });
				});
			} else {
				var index = {}, acc = [];
				data.rows.forEach(function(r){
					var key = r[x] === null || r[x] === '' ? '(vacío)' : (group ? dateKey(r[x], group) : String(r[x]));
					if (!(key in index)) {
						index[key] = labels.length;
						labels.push(key);
						acc.push(ys.map(function(){ return {sum: 0, n: 0, min: null, max: null}; }));
					}
					ys.forEach(function(y, s){
						var a = acc[index[key]][s];
						if (y === null) { a.n++; return; }
						var v = num(r[y]);
						if (v === null) { return; }
						a.sum += v; a.n++;
						a.min = a.min === null ? v : Math.min(a.min, v);
						a.max = a.max === null ? v : Math.max(a.max, v);
					});
				});
				acc.forEach(function(row){
					row.forEach(function(a, s){
						series[s].values.push(agg === 'count' ? a.n : agg === 'sum' ? a.sum : agg === 'avg' ? (a.n ? a.sum / a.n : null) : a[agg]);
					});
				});
			}

			// Orden y límite de categorías; el resto se suma en «Otros» (solo con agregaciones aditivas).
			var order = labels.map(function(l, i){ return i; });
			var sort = $root.find('[name=sort]').val();
			if (sort === 'label') {
				order.sort(function(a, b){
					var na = num(labels[a]), nb = num(labels[b]);
					return na !== null && nb !== null ? na - nb : labels[a].localeCompare(labels[b], 'es', {numeric: true});
				});
			} else if (sort === 'value') {
				order.sort(function(a, b){ return (series[0].values[b] || 0) - (series[0].values[a] || 0); });
			}
			var top = parseInt($root.find('[name=top]').val(), 10) || 0, other = false;
			if (maxTop && (top === 0 || top > maxTop)) { top = maxTop; }
			if (top > 0 && order.length > top) {
				var rest = order.slice(top);
				order = order.slice(0, top);
				if (agg === 'sum' || agg === 'count') {
					other = true;
					series.forEach(function(s){
						s.restTotal = rest.reduce(function(t, i){ return t + (s.values[i] || 0); }, 0);
					});
				}
			}
			var out = {labels: order.map(function(i){ return labels[i]; }), other: other, series: series.map(function(s){
				var values = order.map(function(i){ return s.values[i]; });
				if (other) { values.push(s.restTotal); }
				return {col: s.col, label: s.label, values: values};
			})};
			if (other) { out.labels.push('Otros'); }
			return out;
		}
		function format(v) {
			return v === null || v === undefined ? '' : Number(v).toLocaleString('es-MX', {maximumFractionDigits: 2});
		}
		function renderTable(agg) {
			var $t = $root.find('.tablefg-table').empty();
			var $tr = $('<tr>').append($('<th>').text($root.find('[name=x] option:selected').text()));
			agg.series.forEach(function(s){ $tr.append($('<th class="text-right">').text(s.label)); });
			$t.append($('<thead>').append($tr));
			var $tb = $('<tbody>').appendTo($t);
			agg.labels.forEach(function(l, i){
				var $r = $('<tr>').append($('<td>').text(l));
				agg.series.forEach(function(s){ $r.append($('<td class="text-right">').text(format(s.values[i]))); });
				$tb.append($r);
			});
		}
		function draw() {
			if (!data) { return; }
			var type = $root.find('[name=type]:checked').val(), m = mode();
			var round = type === 'pie' || type === 'doughnut';
			// Pastel y dona: un color por categoría sin repetir; más de 8 se pliegan en «Otros».
			var agg = aggregate(round ? 7 : 0), palette = PALETTE[m];
			// Texto, rejilla y fondo con las variables de Metro (las redefine .dark-side).
			var css = getComputedStyle($root[0]);
			var ink = $.trim(css.getPropertyValue('--tfg-muted')) || '#a2a5b1';
			var grid = $.trim(css.getPropertyValue('--tfg-border')) || '#e8e8e8';
			var surface = $.trim(css.getPropertyValue('--tfg-bg')) || '#fff';
			Chart.defaults.font.family = $.trim(css.getPropertyValue('--font-name')) || Chart.defaults.font.family;
			$root.find('.tablefg-chip input').each(function(){
				$(this).parent().css('--tfg-dot', palette[slots[this.value] % palette.length]);
			});
			if (round) { agg.series = agg.series.slice(0, 1); }
			var datasets = agg.series.map(function(s){
				var color = palette[(s.col === null ? 0 : slots[s.col]) % palette.length];
				var ds = {label: s.label, data: s.values, borderColor: color, backgroundColor: color};
				if (round) {
					ds.backgroundColor = agg.labels.map(function(l, j){ return agg.other && j === agg.labels.length - 1 ? OTHER[m] : palette[j % palette.length]; });
					ds.borderColor = surface;
					ds.borderWidth = 2;
				} else if (type === 'line' || type === 'area') {
					ds.borderWidth = 2;
					ds.pointRadius = agg.labels.length > 60 ? 0 : 4;
					ds.pointHoverRadius = 6;
					ds.tension = 0.2;
					ds.fill = type === 'area';
					ds.backgroundColor = color + '33';
				} else {
					ds.borderRadius = 4;
					ds.borderSkipped = 'start';
					ds.maxBarThickness = 48;
				}
				return ds;
			});
			if (chart) { chart.destroy(); }
			chart = new Chart($root.find('canvas')[0], {
				type: type === 'area' ? 'line' : type === 'hbar' ? 'bar' : type,
				data: {labels: agg.labels, datasets: datasets},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					indexAxis: type === 'hbar' ? 'y' : 'x',
					interaction: {mode: round ? 'nearest' : 'index', intersect: round},
					plugins: {
						legend: {display: round || datasets.length > 1, labels: {color: ink}},
						tooltip: {callbacks: {label: function(c){ return ' ' + (round ? c.label : c.dataset.label) + ': ' + format(c.raw); }}}
					},
					scales: round ? {} : {
						x: {ticks: {color: ink, maxRotation: 60}, grid: {color: grid, display: type === 'hbar'}},
						y: {ticks: {color: ink}, grid: {color: grid, display: type !== 'hbar'}, beginAtZero: true}
					}
				}
			});
			renderTable(agg);
			$root.find('.tablefg-rows').text(data.rows.length.toLocaleString('es-MX'));
			$root.find('.tablefg-cats').text(agg.labels.length.toLocaleString('es-MX'));
			$root.find('.tablefg-truncated').toggle(!!data.truncated);
		}

		function error(msg) {
			$root.find('.tablefg-error-text').text(msg);
			$root.find('.tablefg-error').css('display', 'flex');
		}
		$root.on('change', 'select, input', function(){
			if (this.name === 'x') { $root.find('.tablefg-dategroup').toggle(types[this.value] === 'date'); }
			draw();
		});
		$root.find('.tablefg-png').on('click', function(e){
			e.preventDefault();
			if (chart) {
				var a = document.createElement('a');
				a.href = chart.toBase64Image('image/png', 1);
				a.download = 'grafica.png';
				a.click();
			}
		});
		// Redibuja al cambiar el tema (ThemeSwitcher cambia la clase dark-side de <html>).
		new MutationObserver(draw).observe(document.documentElement, {attributes: true, attributeFilter: ['class']});

		$.ajax({url: location.href, dataType: 'json'}).done(function(r){
			$root.find('.tablefg-busy').hide();
			if (r.error) {
				error(r.error);
				return;
			}
			data = r;
			$root.find('.tablefg-title').text(r.title);
			$root.find('.tablefg-subtitle').text(r.subtitle);
			if (r.rows.length === 0) {
				error('La consulta no devolvió filas.');
				return;
			}
			detectTypes();
			fillControls();
			$root.find('.tablefg-body, .tablefg-png').show();
			draw();
		}).fail(function(xhr){
			$root.find('.tablefg-busy').hide();
			error(xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'No se pudieron cargar los datos.');
		});
	})();
JS, 'ready');
?>
<style>
	/*
	 * Estilos de la página de gráficas. Usan los tokens --nf-* de /nframework/css/nframework.css (tema global
	 * sobre Metro UI 5) y, si el sitio lo desactivó (modern_css => false), las variables de Metro.
	 */
	#tablefg {
		--tfg-bg: var(--nf-surface, var(--default-background, #fff));
		--tfg-ink: var(--nf-ink, var(--default-color, #191919));
		--tfg-muted: var(--nf-muted, var(--body-color-secondary, #a2a5b1));
		--tfg-border: var(--nf-line, var(--border-color, #e8e8e8));
		--tfg-accent: var(--nf-accent, var(--link-color, #5a87cb));
		--tfg-soft: var(--nf-surface-2, color-mix(in srgb, var(--tfg-ink) 4%, var(--tfg-bg)));
		--tfg-radius: var(--nf-radius-lg, calc(var(--border-radius, 6px) * 2));
		--tfg-control: var(--control-height-normal, 36px);
		--tfg-shadow: var(--nf-shadow-lg, 0 1px 2px rgb(0 0 0 / .04), 0 12px 32px -16px rgb(0 0 0 / .18));
		--tfg-ease: var(--nf-ease, cubic-bezier(.3, .8, .6, 1));
		padding-top: 24px;
		padding-bottom: 48px;
		color: var(--tfg-ink);
	}

	/* Encabezado */
	#tablefg .tablefg-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 16px;
		margin-bottom: 20px;
	}
	#tablefg .tablefg-eyebrow {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		font-size: .75rem;
		font-weight: 600;
		letter-spacing: .06em;
		text-transform: uppercase;
		color: var(--tfg-accent);
	}
	#tablefg .tablefg-title {
		margin: 4px 0 2px;
		font-size: 1.5rem;
		font-weight: 600;
		line-height: 1.25;
		text-wrap: balance;
	}
	#tablefg .tablefg-subtitle {
		margin: 0;
		max-width: 72ch;
		color: var(--tfg-muted);
	}

	/* Tarjetas */
	#tablefg .tablefg-card {
		background: var(--tfg-bg);
		border: 1px solid var(--tfg-border);
		border-radius: var(--tfg-radius);
		box-shadow: var(--tfg-shadow);
	}
	#tablefg .tablefg-panel {
		padding: 18px;
		position: sticky;
		top: 16px;
	}
	#tablefg .tablefg-section + .tablefg-section {
		margin-top: 16px;
		padding-top: 16px;
		border-top: 1px dashed var(--tfg-border);
	}
	#tablefg .tablefg-label {
		display: block;
		margin: 0 0 6px;
		font-size: .75rem;
		font-weight: 600;
		letter-spacing: .04em;
		text-transform: uppercase;
		color: var(--tfg-muted);
	}
	#tablefg .tablefg-field + .tablefg-field {
		margin-top: 12px;
	}

	/* Selects nativos con la altura de los controles de Metro */
	#tablefg select {
		width: 100%;
		height: var(--tfg-control);
		padding: 0 34px 0 12px;
		font: inherit;
		color: var(--tfg-ink);
		background-color: var(--tfg-soft);
		background-image:
			linear-gradient(45deg, transparent 50%, var(--tfg-muted) 50%),
			linear-gradient(135deg, var(--tfg-muted) 50%, transparent 50%);
		background-position: calc(100% - 18px) 52%, calc(100% - 13px) 52%;
		background-size: 5px 5px;
		background-repeat: no-repeat;
		border: 1px solid var(--tfg-border);
		border-radius: calc(var(--tfg-radius) * .75);
		appearance: none;
		cursor: pointer;
		transition: border-color .2s var(--tfg-ease), box-shadow .2s var(--tfg-ease);
	}
	#tablefg select:hover {
		border-color: color-mix(in srgb, var(--tfg-accent) 45%, var(--tfg-border));
	}
	#tablefg select:focus-visible,
	#tablefg .tablefg-types input:focus-visible + span,
	#tablefg .tablefg-chip input:focus-visible + span {
		outline: none;
		border-color: var(--tfg-accent);
		box-shadow: 0 0 0 3px color-mix(in srgb, var(--tfg-accent) 25%, transparent);
	}

	/* Tipo de gráfica: control segmentado con íconos */
	#tablefg .tablefg-types {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 6px;
		padding: 4px;
		background: var(--tfg-soft);
		border: 1px solid var(--tfg-border);
		border-radius: var(--tfg-radius);
	}
	#tablefg .tablefg-types label {
		margin: 0;
		cursor: pointer;
	}
	#tablefg .tablefg-types input,
	#tablefg .tablefg-chip input {
		position: absolute;
		opacity: 0;
		pointer-events: none;
	}
	#tablefg .tablefg-types span {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 4px;
		padding: 8px 4px;
		font-size: .7rem;
		color: var(--tfg-muted);
		border: 1px solid transparent;
		border-radius: calc(var(--tfg-radius) * .75);
		transition: background-color .2s var(--tfg-ease), color .2s var(--tfg-ease);
	}
	#tablefg .tablefg-types span [class^="mif-"] {
		font-size: 1.25rem;
	}
	#tablefg .tablefg-types label:hover span {
		color: var(--tfg-ink);
	}
	#tablefg .tablefg-types input:checked + span {
		color: var(--tfg-accent);
		background: var(--tfg-bg);
		border-color: var(--tfg-border);
		box-shadow: 0 1px 3px rgb(0 0 0 / .08);
	}

	/* Series: chips con el color de cada columna */
	#tablefg .tablefg-ys {
		display: flex;
		flex-wrap: wrap;
		gap: 6px;
	}
	#tablefg .tablefg-chip {
		margin: 0;
		cursor: pointer;
	}
	#tablefg .tablefg-chip span {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		min-height: 32px;
		padding: 4px 12px 4px 10px;
		font-size: .875rem;
		color: var(--tfg-muted);
		background: var(--tfg-bg);
		border: 1px solid var(--tfg-border);
		border-radius: 999px;
		transition: all .2s var(--tfg-ease);
	}
	#tablefg .tablefg-chip span::before {
		content: "";
		width: 10px;
		height: 10px;
		border-radius: 50%;
		background: transparent;
		box-shadow: inset 0 0 0 2px var(--tfg-dot, var(--tfg-muted));
		transition: background-color .2s var(--tfg-ease);
	}
	#tablefg .tablefg-chip input:checked + span {
		color: var(--tfg-ink);
		background: var(--tfg-soft);
		border-color: color-mix(in srgb, var(--tfg-dot, var(--tfg-accent)) 50%, var(--tfg-border));
	}
	#tablefg .tablefg-chip input:checked + span::before {
		background: var(--tfg-dot, var(--tfg-accent));
	}
	#tablefg .tablefg-hint {
		margin: 0;
		font-size: .875rem;
		color: var(--tfg-muted);
	}

	/* Gráfica */
	#tablefg .tablefg-chart {
		padding: 20px 20px 12px;
	}
	#tablefg .tablefg-canvas {
		position: relative;
		height: clamp(320px, 58vh, 560px);
	}
	#tablefg .tablefg-stats {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
		margin-bottom: 12px;
	}
	#tablefg .tablefg-stat {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		padding: 4px 10px;
		font-size: .8125rem;
		color: var(--tfg-muted);
		background: var(--tfg-soft);
		border-radius: 999px;
	}
	#tablefg .tablefg-stat strong {
		color: var(--tfg-ink);
		font-weight: 600;
		font-variant-numeric: tabular-nums;
	}
	#tablefg .tablefg-stat.warning {
		color: #9a6700;
		background: color-mix(in srgb, #fab219 18%, var(--tfg-bg));
	}
	.dark-side #tablefg .tablefg-stat.warning {
		color: #fab219;
	}

	/* Tabla de datos */
	#tablefg .tablefg-data {
		margin-top: 20px;
		overflow: hidden;
	}
	#tablefg .tablefg-data summary {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 14px 18px;
		font-weight: 600;
		cursor: pointer;
		list-style: none;
		user-select: none;
	}
	#tablefg .tablefg-data summary::-webkit-details-marker {
		display: none;
	}
	#tablefg .tablefg-data summary::after {
		content: "";
		margin-left: auto;
		width: 8px;
		height: 8px;
		border-right: 2px solid var(--tfg-muted);
		border-bottom: 2px solid var(--tfg-muted);
		transform: rotate(45deg);
		transition: transform .2s var(--tfg-ease);
	}
	#tablefg .tablefg-data[open] summary::after {
		transform: rotate(-135deg);
	}
	#tablefg .tablefg-scroll {
		max-height: 420px;
		overflow: auto;
		border-top: 1px solid var(--tfg-border);
	}
	#tablefg .tablefg-table {
		margin: 0;
		font-variant-numeric: tabular-nums;
	}
	#tablefg .tablefg-table thead th {
		position: sticky;
		top: 0;
		z-index: 1;
		background: var(--tfg-soft);
		font-size: .75rem;
		letter-spacing: .04em;
		text-transform: uppercase;
		color: var(--tfg-muted);
	}

	/* Carga y error */
	#tablefg .tablefg-skeleton {
		display: grid;
		grid-template-columns: 280px 1fr;
		gap: 20px;
	}
	#tablefg .tablefg-skeleton div {
		height: 420px;
		border-radius: var(--tfg-radius);
		background: linear-gradient(90deg, var(--tfg-soft) 25%, color-mix(in srgb, var(--tfg-ink) 9%, var(--tfg-bg)) 50%, var(--tfg-soft) 75%);
		background-size: 200% 100%;
		animation: tablefg-shimmer 1.4s linear infinite;
	}
	@keyframes tablefg-shimmer {
		from { background-position: 200% 0; }
		to { background-position: -200% 0; }
	}
	#tablefg .tablefg-error {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 16px 18px;
		color: var(--tfg-ink);
		border-left: 4px solid #e34948;
	}
	#tablefg .tablefg-error [class^="mif-"] {
		font-size: 1.5rem;
		color: #e34948;
	}

	@media (max-width: 991px) {
		#tablefg .tablefg-panel {
			position: static;
			margin-bottom: 20px;
		}
		#tablefg .tablefg-skeleton {
			grid-template-columns: 1fr;
		}
		#tablefg .tablefg-skeleton div:first-child {
			height: 160px;
		}
	}
	@media (max-width: 575px) {
		#tablefg .tablefg-header {
			flex-direction: column;
		}
		#tablefg .tablefg-header .button {
			width: 100%;
		}
	}
	@media (prefers-reduced-motion: reduce) {
		#tablefg *,
		#tablefg *::before,
		#tablefg *::after {
			animation: none !important;
			transition: none !important;
		}
	}
</style>
<div class="container" id="tablefg">
	<div class="tablefg-header">
		<div>
			<span class="tablefg-eyebrow"><span class="mif-bar-chart"></span> Gráfica</span>
			<h1 class="tablefg-title">Gráfica</h1>
			<p class="tablefg-subtitle"></p>
		</div>
		<button type="button" class="button tablefg-png" style="display:none"><span class="mif-download"></span>&nbsp;PNG</button>
	</div>

	<div class="tablefg-skeleton tablefg-busy" aria-label="Cargando datos"><div></div><div></div></div>
	<div class="tablefg-card tablefg-error" style="display:none"><span class="mif-warning"></span><span class="tablefg-error-text"></span></div>

	<div class="tablefg-body" style="display:none">
		<div class="row">
			<div class="cell-lg-3">
				<div class="tablefg-card tablefg-panel">
					<div class="tablefg-section">
						<span class="tablefg-label">Tipo</span>
						<div class="tablefg-types" role="radiogroup" aria-label="Tipo de gráfica">
							<label><input type="radio" name="type" value="bar" checked><span><span class="mif-bar-vertical-chart"></span>Barras</span></label>
							<label><input type="radio" name="type" value="hbar"><span><span class="mif-bar-horizontal-chart"></span>Horizontal</span></label>
							<label><input type="radio" name="type" value="line"><span><span class="mif-line-chart"></span>Líneas</span></label>
							<label><input type="radio" name="type" value="area"><span><span class="mif-area-chart"></span>Área</span></label>
							<label><input type="radio" name="type" value="pie"><span><span class="mif-pie-chart"></span>Pastel</span></label>
							<label><input type="radio" name="type" value="doughnut"><span><span class="mif-donut-chart"></span>Dona</span></label>
						</div>
					</div>
					<div class="tablefg-section">
						<div class="tablefg-field">
							<label class="tablefg-label" for="tablefg-x">Categoría (eje X)</label>
							<select name="x" id="tablefg-x"></select>
						</div>
						<div class="tablefg-field tablefg-dategroup" style="display:none">
							<label class="tablefg-label" for="tablefg-dategroup">Agrupar fechas por</label>
							<select name="dategroup" id="tablefg-dategroup">
								<option value="day">Día</option>
								<option value="month" selected>Mes</option>
								<option value="year">Año</option>
								<option value="">Sin agrupar</option>
							</select>
						</div>
						<div class="tablefg-field">
							<label class="tablefg-label" for="tablefg-agg">Agregación</label>
							<select name="agg" id="tablefg-agg">
								<option value="sum">Suma</option>
								<option value="avg">Promedio</option>
								<option value="min">Mínimo</option>
								<option value="max">Máximo</option>
								<option value="count">Conteo de filas</option>
								<option value="none">Sin agrupar (cada fila)</option>
							</select>
						</div>
					</div>
					<div class="tablefg-section">
						<span class="tablefg-label">Valores</span>
						<div class="tablefg-ys"></div>
					</div>
					<div class="tablefg-section">
						<div class="tablefg-field">
							<label class="tablefg-label" for="tablefg-sort">Ordenar</label>
							<select name="sort" id="tablefg-sort">
								<option value="label">Por categoría</option>
								<option value="value">Por valor (mayor a menor)</option>
								<option value="">Como llegan los datos</option>
							</select>
						</div>
						<div class="tablefg-field">
							<label class="tablefg-label" for="tablefg-top">Máximo de categorías</label>
							<select name="top" id="tablefg-top">
								<option value="0">Todas</option>
								<option value="5">5</option>
								<option value="8">8</option>
								<option value="10">10</option>
								<option value="20">20</option>
								<option value="50">50</option>
							</select>
						</div>
					</div>
				</div>
			</div>
			<div class="cell-lg-9">
				<div class="tablefg-card tablefg-chart">
					<div class="tablefg-stats">
						<span class="tablefg-stat"><strong class="tablefg-rows">0</strong> filas</span>
						<span class="tablefg-stat"><strong class="tablefg-cats">0</strong> categorías</span>
						<span class="tablefg-stat warning tablefg-truncated" style="display:none"><span class="mif-warning"></span> Se usan las primeras filas</span>
					</div>
					<div class="tablefg-canvas"><canvas role="img" aria-label="Gráfica"></canvas></div>
				</div>
				<details class="tablefg-card tablefg-data">
					<summary><span class="mif-table"></span> Datos de la gráfica</summary>
					<div class="tablefg-scroll"><table class="table compact striped tablefg-table"></table></div>
				</details>
			</div>
		</div>
	</div>
</div>
