<?php
if (empty($_GET['_id']) || !preg_match('/^[a-f\d]{24}$/i', $_GET['_id'])) {
	header('Location: ?_id=' . new MongoDB\BSON\ObjectId());
	exit();
}

require '../common2.php';

$menus = $m->{$config['sitedb']}->menus;
$menuId = toMongoId($_GET['_id']);
$doc = $menus->findOne(['_id' => $menuId], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);

if ($nframework->isAjax()) {
	$nframework->usecommon = false;
	if (!csrfValidate()) {
		$result = ['error' => 'Formulario caducado, recargue la página.'];
	} elseif (($_POST['op'] ?? '') === 'save') {
		$name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');
		$code = is_string($_POST['code'] ?? null) ? $_POST['code'] : '';
		try {
			if (!preg_match('/^[A-Za-z0-9_-]{1,50}$/', $name)) {
				throw new InvalidArgumentException('El nombre es obligatorio y solo admite letras, números, guion y guion bajo (p. ej. _nav).');
			}
			if ($menus->countDocuments(['name' => $name, '_id' => ['$ne' => $menuId]]) > 0) {
				throw new InvalidArgumentException('Ya existe otro menú llamado ' . $name . '.');
			}
			$nodes = nfNormalizeMenuNodes(json_decode($code, true));
			if ($nodes === []) {
				throw new InvalidArgumentException('Agregue al menos un elemento con texto.');
			}
			$menus->updateOne(['_id' => $menuId], ['$set' => [
				'name' => $name,
				'code' => json_encode($nodes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
				'updated' => new MongoDB\BSON\UTCDateTime(),
				'updatedby' => $user->username,
			]], ['upsert' => true]);
			nfCacheForget('menu:' . $name);
			if (!empty($doc['name']) && $doc['name'] !== $name) {
				nfCacheForget('menu:' . $doc['name']);
			}
			$result = [
				'error' => false,
				'ids' => ['menuEstado' => 'Guardado a las ' . date('H:i:s') . ' · ' . nfCountMenuNodes($nodes) . ' elementos'],
				'js' => 'window.menuDirty = false;',
			];
		} catch (InvalidArgumentException $e) {
			$result = ['error' => $e->getMessage()];
		}
	}
	return;
}

// Árbol guardado → formato de jstree. Un código dañado no impide abrir el editor: se avisa y se puede corregir.
$rawCode = (string) ($doc['code'] ?? '');
$loadError = '';
$nodes = [];
if ($rawCode !== '') {
	try {
		$nodes = nfNormalizeMenuNodes(json_decode($rawCode, true));
	} catch (InvalidArgumentException $e) {
		$loadError = 'El código guardado de este menú no es un JSON válido (' . json_last_error_msg() . '). Corríjalo en «JSON avanzado» o vuelva a armar el menú.';
	}
}
function menuToJstree(array $nodes): array
{
	return array_map(fn($n) => [
		'text' => $n['text'],
		'icon' => $n['data']['icon'] !== '' ? $n['data']['icon'] : (!empty($n['children']) ? 'mif-folder' : 'mif-link'),
		'data' => $n['data'],
		'state' => ['opened' => true],
		'children' => menuToJstree($n['children'] ?? []),
	], $nodes);
}

$paginas = ['' => '— Elegir una página —', '/' => 'Inicio (/)'];
foreach ($m->{$config['sitedb']}->pages->find([], ['projection' => ['path' => 1, 'title' => 1], 'sort' => ['path' => 1]]) as $p) {
	$path = (string) ($p['path'] ?? '');
	if ($path !== '' && $path[0] !== '_') {
		$paginas[$path] = ($p['title'] ?? '') !== '' ? $p['title'] . ' (' . $path . ')' : $path;
	}
}

$nframework->usecommon = true;
$nframework->metas['title'] = 'Menú ' . ($doc['name'] ?? 'nuevo');
$nframework->csss['9991'] = 'https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.16/themes/default/style.min.css';
$nframework->jss['9991'] = 'https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.16/jstree.min.js';
$noobfuscate = true;

$javas->addjs('var menuInicial = ' . json_encode(menuToJstree($nodes), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';', 'general');
$javas->addjs(<<<'JS'
window.menuDirty = false;   // global: la respuesta de guardar ejecuta menuDirty = false
var $tree = $('#menuTree'), tree = null;
// Antes de crear el árbol: con datos en línea jstree lanza 'ready' durante la inicialización.
$tree.on('ready.jstree', function (e, d) { tree = d.instance; sync(false); });
$tree.jstree({
	core: {
		data: menuInicial,
		check_callback: true,
		force_text: true,   // el texto de los nodos es texto, no HTML (evita XSS con menús guardados)
		multiple: false,
		themes: { stripes: true, dots: true }
	},
	types: { '#': { max_depth: 4 } },
	dnd: { is_draggable: true },
	contextmenu: {
		items: function (node) {
			return {
				sub: { label: 'Agregar subelemento', icon: 'mif-plus', action: function () { addNode(node.id); } },
				ren: { label: 'Renombrar', icon: 'mif-pencil', action: function () { tree.edit(node); } },
				del: { label: 'Eliminar', icon: 'mif-bin', separator_before: true, action: function () { removeNode(node); } }
			};
		}
	},
	plugins: ['dnd', 'contextmenu', 'types', 'wholerow']
});
tree = $tree.jstree(true);

function iconFor(node) {
	var d = node.data || {};
	return d.icon ? d.icon : (node.children && node.children.length ? 'mif-folder' : 'mif-link');
}
// Solo lo que usa buildMetroMenu(): texto, datos e hijos.
function clean(n) {
	var d = n.data || {}, o = { text: n.text, data: { link: d.link || '#', icon: d.icon || '', onfunction: d.onfunction || '', target: d.target || '' } };
	if (n.children && n.children.length) { o.children = n.children.map(clean); }
	return o;
}
function nodes() { return tree.get_json('#', { no_state: true, no_li_attr: true, no_a_attr: true }).map(clean); }

function preview(list, cls) {
	var $ul = $('<ul>').addClass(cls);
	if (cls !== 'h-menu') { $ul.attr('data-role', 'dropdown'); }
	list.forEach(function (n) {
		var $a = $('<a href="#">');
		if (n.data.icon) { $a.append($('<span>').addClass(n.data.icon + ' icon'), ' '); }
		$a.append(document.createTextNode(n.text));
		if (n.data.onfunction) { $a.append(' ', $('<span class="badge inline bg-orange fg-white" title="Se muestra solo si se cumple la condición">?</span>')); }
		var $li = $('<li>').append($a);
		if (n.children) { $a.addClass('dropdown-toggle'); $li.append(preview(n.children, 'd-menu')); }
		$ul.append($li);
	});
	return $ul;
}

function sync(dirty) {
	var list = nodes(), json = JSON.stringify(list);
	$('#menuCode').val(json);
	if (!$('#advancedCode').is(':focus')) { $('#advancedCode').val(JSON.stringify(list, null, 2)); }
	$('#menuPreview').empty().append(list.length ? preview(list, 'h-menu') : $('<span class="text-muted">').text('El menú está vacío.'));
	$('#menuCount').text(tree.get_json('#', { flat: true }).length);
	if (dirty) { window.menuDirty = true; $('#menuEstado').text('Cambios sin guardar'); }
}

var shownId = null;   // nodo que muestra el panel: lo que se edita
function showNode(node) {
	var d = node.data || {};
	shownId = node.id;
	$('#nodePanel').show();
	$('#nodeEmpty').hide();
	$('#nodeText').val(node.text);
	$('#nodeLink').val(d.link || '#');
	$('#nodePage').val('');
	$('#nodeIcon').val(d.icon || '');
	$('#nodeIconPreview').attr('class', (d.icon || 'mif-link') + ' mif-2x');
	$('#nodeTarget').prop('checked', d.target === '_blank');
	$('#nodeCond').val(d.onfunction || '');
	$('#nodeLinkRow').toggle(!(node.children && node.children.length));
}
function hideNode() { shownId = null; $('#nodePanel').hide(); $('#nodeEmpty').show(); }

function current() { var node = shownId ? tree.get_node(shownId) : false; return node ? node : null; }
function setData(key, value) {
	var node = current();
	if (!node) { return; }
	node.data = node.data || {};
	node.data[key] = value;
	if (key === 'icon') { tree.set_icon(node, iconFor(node)); $('#nodeIconPreview').attr('class', (value || 'mif-link') + ' mif-2x'); }
	sync(true);
}
function addNode(parent) {
	var id = tree.create_node(parent || '#', { text: 'Nuevo elemento', icon: 'mif-link', data: { link: '#', icon: '', onfunction: '', target: '' } }, 'last');
	if (parent && parent !== '#') { tree.open_node(parent); tree.set_icon(parent, iconFor(tree.get_node(parent))); }
	tree.deselect_all();
	tree.select_node(id);
	$('#nodeText').trigger('focus').trigger('select');
}
function removeNode(node) {
	var hijos = node.children.length ? ' y sus ' + node.children_d.length + ' subelementos' : '';
	Swal.fire({ icon: 'warning', text: '¿Eliminar «' + node.text + '»' + hijos + '?', showCancelButton: true, confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar' })
		.then(function (r) { if (r.isConfirmed) { var parent = node.parent; tree.delete_node(node); if (parent !== '#') { tree.set_icon(parent, iconFor(tree.get_node(parent))); } hideNode(); } });
}

$tree.on('create_node.jstree rename_node.jstree delete_node.jstree move_node.jstree copy_node.jstree', function (e, d) {
	if (e.type === 'move_node' && d.old_parent !== '#') { tree.set_icon(d.old_parent, iconFor(tree.get_node(d.old_parent))); }
	if (e.type === 'move_node' && d.parent !== '#') { tree.set_icon(d.parent, iconFor(tree.get_node(d.parent))); }
	sync(true);
	var node = current();
	// Al mover cambia si el elemento tiene hijos (y por lo tanto si lleva enlace). Al escribir no se repinta el panel.
	if (node && e.type === 'move_node') { showNode(node); }
});
$tree.on('select_node.jstree', function (e, d) { showNode(d.node); });
$tree.on('deselect_all.jstree', hideNode);

$('#nodeText').on('input', function () { var node = current(); if (node && $.trim(this.value) !== '') { tree.rename_node(node, this.value); } });
$('#nodeLink').on('input', function () { setData('link', this.value); });
$('#nodePage').on('change', function () { if (this.value !== '') { $('#nodeLink').val(this.value); setData('link', this.value); } });
$('#nodeIcon').on('input', function () { setData('icon', this.value.replace(/[^A-Za-z0-9 _-]/g, '')); });
$('#nodeTarget').on('change', function () { setData('target', this.checked ? '_blank' : ''); });
$('#nodeCond').on('input', function () { setData('onfunction', this.value); });

$('#addRoot').on('click', function () { addNode('#'); });
$('#addChild').on('click', function () { var n = current(); if (n) { addNode(n.id); } });
$('#removeNode').on('click', function () { var n = current(); if (n) { removeNode(n); } });
$('#expandAll').on('click', function () { tree.open_all(); });

// JSON avanzado: pegar o corregir el árbol a mano.
$('#applyJson').on('click', function () {
	var list;
	try { list = JSON.parse($('#advancedCode').val()); } catch (e) { Swal.fire({ icon: 'error', text: 'JSON inválido: ' + e.message }); return; }
	if (!Array.isArray(list)) { Swal.fire({ icon: 'error', text: 'El JSON debe ser una lista de elementos: [{"text": ..., "data": {...}, "children": [...]}]' }); return; }
	function toTree(n) {
		var d = n.data || {}, node = { text: String(n.text || ''), data: { link: d.link || '#', icon: d.icon || '', onfunction: d.onfunction || '', target: d.target || '' }, state: { opened: true } };
		node.children = Array.isArray(n.children) ? n.children.filter(function (c) { return c && c.text; }).map(toTree) : [];
		node.icon = node.data.icon || (node.children.length ? 'mif-folder' : 'mif-link');
		return node;
	}
	tree.settings.core.data = list.filter(function (n) { return n && n.text; }).map(toTree);
	tree.refresh();
	hideNode();
	$tree.one('refresh.jstree', function () { sync(true); });
});

$('#saveMenu').on('click', function () { sync(false); });
window.addEventListener('beforeunload', function (e) { if (window.menuDirty) { e.preventDefault(); e.returnValue = ''; } });
JS, 'ready');

$name = (string) ($doc['name'] ?? '');
$opciones = '';
foreach ($paginas as $value => $label) {
	$opciones .= '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
}
?>
<style>
	#menuTree { min-height: 320px; max-height: 60vh; overflow: auto; border: 1px solid var(--border-color, #ddd); border-radius: 4px; padding: 8px; }
	#menuTree .jstree-anchor > .jstree-themeicon { background: none; font-size: 15px; line-height: 24px; }
	#menuPreview { min-height: 52px; border: 1px dashed var(--border-color, #ccc); border-radius: 4px; padding: 4px 8px; }
</style>
<div class="container p-5">
	<div class="d-flex flex-align-center flex-wrap">
		<h3 class="m-0"><span class="mif-dropdown-menu"></span> Menú <?= htmlspecialchars($name ?: 'nuevo') ?></h3>
		<a href="./" class="button ml-auto"><span class="mif-arrow-left"></span> Menús</a>
	</div>

	<?php if ($loadError !== '') { ?>
		<div class="remark alert mt-4"><?= htmlspecialchars($loadError) ?></div>
	<?php } ?>

	<?= secureform() ?>
		<input type="hidden" name="code" id="menuCode" value="">
		<div class="grid mt-4">
			<div class="row">
				<div class="cell-md-6">
					<label>Nombre</label>
					<input type="text" name="name" data-role="input" required maxlength="50" pattern="[A-Za-z0-9_\-]+" value="<?= htmlspecialchars($name) ?>" placeholder="_nav">
					<small class="text-muted">Úselo en el sitio con <code>nfMetroMenu('<?= htmlspecialchars($name ?: 'nombre') ?>')</code>. <code>_nav</code> es el menú de las páginas del CMS.</small>
				</div>
				<div class="cell-md-6 d-flex flex-align-end flex-justify-end">
					<span id="menuEstado" class="text-muted mr-3"><?= !empty($doc['updated']) ? 'Guardado el ' . htmlspecialchars($doc['updated']->toDateTime()->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('d/m/Y H:i')) . (!empty($doc['updatedby']) ? ' por ' . htmlspecialchars($doc['updatedby']) : '') : '' ?></span>
					<button id="saveMenu" class="button success secureop" value="save"><span class="mif-floppy-disk"></span> Guardar</button>
				</div>
			</div>

			<div class="row mt-4">
				<div class="cell">
					<label>Vista previa</label>
					<div id="menuPreview"></div>
				</div>
			</div>

			<div class="row mt-4">
				<div class="cell-md-6">
					<div class="d-flex flex-wrap mb-2">
						<button type="button" id="addRoot" class="button small primary mr-1 mb-1"><span class="mif-plus"></span> Elemento</button>
						<button type="button" id="addChild" class="button small mr-1 mb-1"><span class="mif-tree"></span> Subelemento</button>
						<button type="button" id="removeNode" class="button small alert mr-1 mb-1"><span class="mif-bin"></span> Eliminar</button>
						<button type="button" id="expandAll" class="button small flat-button mb-1">Expandir todo</button>
					</div>
					<div id="menuTree"></div>
					<small class="text-muted"><span id="menuCount">0</span> elementos · arrastre para reordenar o anidar (hasta 4 niveles) · clic derecho para más opciones</small>
				</div>
				<div class="cell-md-6">
					<div id="nodeEmpty" class="text-muted p-4 text-center border bd-default">Seleccione un elemento del árbol para editarlo.</div>
					<div id="nodePanel" class="border bd-default p-4" style="display:none">
						<label>Texto</label>
						<input type="text" id="nodeText" data-role="input" maxlength="100">
						<div id="nodeLinkRow" class="mt-2">
							<label>Enlace</label>
							<div class="d-flex">
								<input type="text" id="nodeLink" data-role="input" placeholder="/ruta o https://...">
							</div>
							<select id="nodePage" class="mt-1"><?= $opciones ?></select>
							<div class="mt-1"><input type="checkbox" id="nodeTarget" data-role="checkbox" data-caption="Abrir en otra pestaña"></div>
						</div>
						<label class="mt-2 d-block">Ícono</label>
						<div class="d-flex flex-align-center">
							<input type="text" id="nodeIcon" data-role="input" placeholder="mif-home">
							<span id="nodeIconPreview" class="mif-link mif-2x ml-2"></span>
						</div>
						<small class="text-muted">Clase de <a href="https://metroui.org.ua/icons.html" target="_blank" rel="noopener">Metro Icons</a>, p. ej. <code>mif-home</code>.</small>
						<label class="mt-2 d-block">Condición (opcional)</label>
						<textarea id="nodeCond" data-role="textarea" rows="2" placeholder="$user->in('admins')"></textarea>
						<small class="text-muted">Código PHP: el elemento se muestra solo si devuelve verdadero. Tiene disponibles <code>$user</code>, <code>$config</code>, <code>$m</code> y <code>$nframework</code>. Ej.: <code>$user->isLoggedIn()</code>, <code>!$user->isLoggedIn()</code>.</small>
					</div>
				</div>
			</div>

			<div class="row mt-4">
				<div class="cell">
					<details>
						<summary class="c-pointer">JSON avanzado</summary>
						<textarea id="advancedCode" class="mt-2" style="width:100%; min-height:220px; font-family:monospace; font-size:12px"><?= htmlspecialchars($loadError !== '' ? $rawCode : '') ?></textarea>
						<button type="button" id="applyJson" class="button small mt-1">Aplicar al árbol</button>
					</details>
				</div>
			</div>
		</div>
	</form>
</div>
