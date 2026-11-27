<?php
require '../common2.php';

/*
 * Admin → IA: proveedores de IA del sitio (configs/site → ai_providers) que usan TablefIA y nfAI::json().
 * Se agregan desde el catálogo de nfAI::catalog() (Anthropic, OpenAI, Gemini, Ollama, LM Studio, vLLM,
 * OpenRouter, Groq, Mistral, DeepSeek u otro compatible con OpenAI); uno es el predeterminado.
 * Las llaves se guardan cifradas con ai_key de config.php, o como env:VARIABLE. Ver docs/ai.php.
 */

$configs = $m->{$config['sitedb']}->configs;
$catalog = nfAI::catalog();
$hasSecret = nfAI::hasSecret();

/** Proveedores guardados tal cual (sin el legado de config.php). */
function aiStored(): array
{
	global $config;
	$raw = json_decode((string) json_encode($config['ai_providers'] ?? []), true);
	return array_values(array_filter(is_array($raw) ? $raw : [], fn ($p) => is_array($p) && is_string($p['id'] ?? null)));
}

/**
 * Proveedor armado con lo que envió el formulario (data[...]) y la llave nueva en claro ('' = no se escribió:
 * se conserva la guardada).
 *
 * @param array<string, mixed> $data
 * @param array<string, mixed>|null $previous Proveedor que se edita.
 * @return array{0: array<string, mixed>, 1: string}
 */
function aiFromPost(array $data, ?array $previous): array
{
	global $catalog;
	$str = fn ($k) => is_string($data[$k] ?? null) ? trim($data[$k]) : '';
	$preset = isset($catalog[$str('preset')]) ? $str('preset') : 'compatible';
	$newKey = !empty($data['clear_key']) ? '' : $str('api_key');
	$p = nfAI::normalize([
		'id' => strtolower($str('id')),
		'name' => $str('name'),
		'preset' => $preset,
		'type' => $catalog[$preset]['type'],
		'base_url' => $str('base_url'),
		'model' => $str('model'),
		'api_key' => !empty($data['clear_key']) ? '' : (string) ($previous['api_key'] ?? ''),
		'effort' => $str('effort'),
		'json_mode' => $str('json_mode'),
		'max_tokens' => (int) $str('max_tokens'),
		'timeout' => (int) $str('timeout'),
		'default' => !empty($data['default']),
	]);
	if ($newKey !== '') {
		// Para validar que haya llave; al guardar se sustituye por la cifrada.
		$p['api_key'] = str_starts_with($newKey, 'env:') ? $newKey : 'nueva';
	}
	return [$p, $newKey];
}

/** Guarda la lista con un solo predeterminado (el primero marcado; si ninguno, el primero). */
function aiSave(array $list): void
{
	global $configs;
	$hasDefault = false;
	foreach ($list as &$p) {
		$p['default'] = !empty($p['default']) && !$hasDefault;
		$hasDefault = $hasDefault || $p['default'];
		unset($p['legacy']);
	}
	unset($p);
	if ($list && !$hasDefault) {
		$list[0]['default'] = true;
	}
	$configs->updateOne(['_id' => 'site'], ['$set' => ['ai_providers' => array_values($list)]], ['upsert' => true]);
	nfCacheForget('configs');
}

if ($nframework->isAjax()) {
	$op = is_string($_POST['op'] ?? null) ? $_POST['op'] : '';
	$data = is_array($_POST['data'] ?? null) ? $_POST['data'] : [];
	$stored = aiStored();
	$ids = array_column($stored, 'id');
	$original = is_string($_POST['original'] ?? null) ? $_POST['original'] : '';
	$previous = null;
	foreach ($stored as $s) {
		if ($original !== '' && $s['id'] === $original) {
			$previous = $s;
		}
	}
	if (!csrfValidate()) {
		$result = ['error' => 'Formulario caducado, recargue la página.', 'ok' => false, 'message' => 'Formulario caducado, recargue la página.'];
	} elseif ($op === 'save') {
		[$p, $newKey] = aiFromPost($data, $previous);
		$error = nfAI::validationError($p, array_values(array_diff($ids, [$previous['id'] ?? ''])));
		if ($error === '' && $newKey !== '') {
			try {
				$p['api_key'] = nfAI::encryptKey($newKey);
			} catch (RuntimeException $e) {
				$error = $e->getMessage();   // falta ai_key en config.php
			}
		}
		if ($error === '') {
			// Se edita en su lugar; si queda como predeterminado, los demás dejan de serlo.
			$list = [];
			$placed = false;
			foreach ($stored as $s) {
				if ($previous !== null && $s['id'] === $previous['id']) {
					$list[] = $p;
					$placed = true;
				} else {
					$list[] = $p['default'] ? ['default' => false] + $s : $s;
				}
			}
			if (!$placed) {
				$list[] = $p;
			}
			aiSave($list);
			$result = ['error' => false, 'js' => 'setTimeout(function(){ location.href = location.pathname; }, 600)'];
		} else {
			$result = ['error' => $error];
		}
	} elseif ($op === 'test') {
		[$p, $newKey] = aiFromPost($data, $previous);
		$error = nfAI::validationError($p);
		if ($error !== '') {
			$result = ['ok' => false, 'message' => $error];
		} elseif (!nflogAttempt('aitest:' . $user->_id, 20, 900)) {
			$result = ['ok' => false, 'message' => 'Demasiadas pruebas seguidas; espere 15 minutos.'];
		} else {
			// Una llave recién escrita se prueba sin cifrarla ni guardarla.
			$plain = $newKey === '' ? null : (str_starts_with($newKey, 'env:') ? (string) getenv(substr($newKey, 4)) : $newKey);
			$result = nfAI::test($p, $plain);
		}
	} elseif ($op === 'delete' || $op === 'default') {
		$id = is_string($_POST['id'] ?? null) ? $_POST['id'] : '';
		if (!in_array($id, $ids, true)) {
			$result = ['error' => 'Proveedor desconocido.'];
		} else {
			$list = $op === 'delete'
				? array_values(array_filter($stored, fn ($s) => $s['id'] !== $id))
				: array_map(fn ($s) => ['default' => $s['id'] === $id] + $s, $stored);
			aiSave($list);
			$result = ['error' => false, 'js' => 'setTimeout(function(){ location.reload(); }, 500)'];
		}
	}
	return;
}

$nframework->usecommon = true;
$nframework->metas['title'] = 'Proveedores de IA';
$e = fn ($s) => htmlspecialchars((string) $s);
$providers = nfAI::providers();
// Para el formulario de edición: todo menos la llave.
$editable = array_map(fn ($p) => array_diff_key($p, ['api_key' => 1]) + ['key_hint' => nfAI::maskKey((string) $p['api_key'])], $providers);

$javas->addjs('window.aiCatalog = ' . json_encode($catalog, JSON_UNESCAPED_UNICODE) . '; window.aiProviders = ' . json_encode($editable, JSON_UNESCAPED_UNICODE) . ';', 'general');
$javas->addjs(<<<'JS'
window.aiOp = function (data, confirmText) {
	var send = function () {
		data.CSRFToken = $('#aiform [name=CSRFToken]').val();
		$.post(location.pathname, data, null, 'json').done(nAjaxFormDone)
			.fail(function (x) { Swal.fire({icon: 'error', title: 'Error', text: 'El servidor respondió ' + x.status + '.'}); });
	};
	if (!confirmText) { send(); return; }
	Swal.fire({icon: 'question', text: confirmText, showCancelButton: true, confirmButtonText: 'Sí', cancelButtonText: 'Cancelar'})
		.then(function (r) { if (r.isConfirmed) { send(); } });
};
function aiField(name) { return $('#aiform [name="data[' + name + ']"]'); }
// Ajusta el formulario al preset: URL, modelos sugeridos, esfuerzo, modos JSON y si pide llave.
function aiPreset(preset, keepValues) {
	var c = window.aiCatalog[preset] || window.aiCatalog.compatible;
	if (!keepValues || !aiField('base_url').val()) { aiField('base_url').val(c.base_url); }
	aiField('base_url').attr('placeholder', c.base_url || 'https://servidor/v1');
	$('#ai-models').empty().append($.map(c.models, function (m) { return $('<option>').val(m); }));
	if (!keepValues && c.models.length) { aiField('model').val(c.models[0]); }
	var effort = aiField('effort'), current = effort.val();
	effort.empty().append($('<option value="">').text('El del modelo'));
	$.each(c.effort, function (i, v) { effort.append($('<option>').val(v).text(v)); });
	effort.val(keepValues ? current : '').closest('.ai-effort').toggleClass('d-none', !c.effort.length);
	var mode = aiField('json_mode'), currentMode = mode.val(), names = {schema: 'Esquema estricto', object: 'Objeto JSON libre', prompt: 'Solo instrucciones'};
	mode.empty();
	$.each(c.json_modes, function (i, v) { mode.append($('<option>').val(v).text(names[v])); });
	mode.val(keepValues && c.json_modes.indexOf(currentMode) >= 0 ? currentMode : c.json_mode);
	$('#ai-key-required').text(c.key === 'required' ? '(obligatoria)' : (c.key === 'none' ? '(no necesita)' : '(opcional)'));
	$('#ai-help').text(c.help);
}
window.aiNew = function (preset) {
	var c = window.aiCatalog[preset];
	$('#aiform')[0].reset();
	$('#aiform [name=original]').val('');
	aiField('preset').val(preset);
	aiField('id').val(preset === 'compatible' ? '' : preset);
	aiField('name').val(c.label);
	aiField('base_url').val('');
	$('#ai-key-hint').text('');
	$('#ai-form-title').text('Agregar: ' + c.label);
	aiPreset(preset, false);
	$('#ai-form-box').removeClass('d-none')[0].scrollIntoView({behavior: 'smooth'});
};
window.aiEdit = function (id) {
	var p = window.aiProviders.filter(function (x) { return x.id === id; })[0];
	$('#aiform')[0].reset();
	$('#aiform [name=original]').val(p.id);
	$.each(['preset', 'id', 'name', 'base_url', 'model', 'max_tokens', 'timeout'], function (i, k) { aiField(k).val(p[k]); });
	aiPreset(p.preset, true);
	aiField('effort').val(p.effort);
	aiField('json_mode').val(p.json_mode);
	aiField('default').prop('checked', !!p['default']);
	$('#ai-key-hint').text(p.key_hint ? 'Guardada: ' + p.key_hint + '. Déjela vacía para conservarla.' : '');
	$('#ai-form-title').text('Editar: ' + p.name);
	$('#ai-form-box').removeClass('d-none')[0].scrollIntoView({behavior: 'smooth'});
};
$(function () {
	aiField('preset').on('change', function () { aiPreset($(this).val(), false); });
	$('#ai-test').on('click', function () {
		var btn = $(this).prop('disabled', true), log = $('#ai-test-log');
		var data = $('#aiform').serializeArray().filter(function (f) { return f.name !== 'op'; });
		data.push({name: 'op', value: 'test'});
		log.addClass('d-none');
		$.post(location.pathname, $.param(data), null, 'json').done(function (r) {
			Swal.fire({icon: r.ok ? 'success' : 'error', title: r.ok ? 'El proveedor responde' : 'Prueba fallida', text: (r.message || '') + (r.ms ? ' (' + r.ms + ' ms)' : '')});
		}).fail(function (x) {
			Swal.fire({icon: 'error', title: 'Error', text: 'El servidor respondió ' + x.status + '.'});
		}).always(function () { btn.prop('disabled', false); });
	});
});
JS, 'general');
?>
<div class="container">
	<div class="bo">
		<h1 class="text-weight-10 text-center gradient gr-text-blue">Proveedores de IA</h1>
		<p class="text-center fg-gray">Modelos que usa el sitio para las consultas en lenguaje natural (<code>TablefIA</code>) y para <code>nfAI::json()</code>. El predeterminado se usa cuando el componente no indica <code>'provider'</code>.</p>
	</div>

	<?php if (!$hasSecret): ?>
		<div class="remark warning">
			Para guardar llaves de API escritas aquí, agregue <code>'ai_key'</code> al bloque de este dominio en <code>includes/config.php</code>
			(las llaves se cifran con ella y nunca quedan en claro en MongoDB):
			<pre class="mt-2">php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'</pre>
			Mientras tanto puede usar <code>env:NOMBRE_VARIABLE</code> como llave (se lee del entorno de PHP-FPM) o proveedores locales sin llave.
		</div>
	<?php endif; ?>

	<div class="box shadow-large-extra p-5 mb-5">
		<h4 class="mt-0">Configurados</h4>
		<?php if ($providers): ?>
			<table class="table compact striped">
				<thead><tr><th>Nombre</th><th>Id</th><th>Tipo</th><th>Modelo</th><th>Llave</th><th></th></tr></thead>
				<tbody>
				<?php foreach ($providers as $p): ?>
					<tr>
						<td><?= $e($p['name']) ?><?= $p['default'] ? ' <span class="tag success">Predeterminado</span>' : '' ?></td>
						<td><code><?= $e($p['id']) ?></code></td>
						<td><?= $e($catalog[$p['preset']]['label'] ?? $p['type']) ?><br><small class="fg-gray"><?= $e($p['base_url']) ?></small></td>
						<td><code><?= $e($p['model']) ?></code><?= $p['effort'] ? ' <small>(' . $e($p['effort']) . ')</small>' : '' ?></td>
						<td><code><?= $p['legacy'] ? 'config.php' : $e(nfAI::maskKey((string) $p['api_key']) ?: '—') ?></code></td>
						<td class="text-nowrap">
							<?php if ($p['legacy']): ?>
								<small class="fg-gray">De <code>anthropic_api_key</code>; agregue un proveedor para administrarlo aquí.</small>
							<?php else: ?>
								<button type="button" class="button mini" onclick="aiEdit(<?= $e(json_encode($p['id'])) ?>)">Editar</button>
								<?php if (!$p['default']): ?><button type="button" class="button mini" onclick="aiOp({op: 'default', id: <?= $e(json_encode($p['id'])) ?>})">Predeterminado</button><?php endif; ?>
								<button type="button" class="button mini alert" onclick="aiOp({op: 'delete', id: <?= $e(json_encode($p['id'])) ?>}, '¿Eliminar el proveedor <?= $e($p['name']) ?>? Los TablefIA que lo indiquen dejarán de funcionar.')">Eliminar</button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else: ?>
			<p class="fg-gray">No hay proveedores. Agregue uno de las configuraciones disponibles.</p>
		<?php endif; ?>
	</div>

	<div class="box shadow-large-extra p-5 mb-5">
		<h4 class="mt-0">Configuraciones disponibles</h4>
		<div class="d-flex flex-wrap" style="gap:.75rem">
			<?php foreach ($catalog as $key => $c): ?>
				<div class="border bd-default p-3" style="flex:1 1 230px;max-width:320px">
					<b><?= $e($c['label']) ?></b>
					<p class="text-small fg-gray mt-1 mb-2"><?= $e($c['help']) ?></p>
					<button type="button" class="button small primary" onclick="aiNew(<?= $e(json_encode($key)) ?>)"><span class="mif-plus"></span> Agregar</button>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div id="ai-form-box" class="box shadow-large-extra p-5 mb-5 d-none">
		<h4 class="mt-0" id="ai-form-title">Proveedor</h4>
		<p id="ai-help" class="fg-gray"></p>
		<?= secureform('', false, 'aiform') ?>
		<input type="hidden" name="original" value="">
		<div class="grid">
			<div class="row">
				<div class="cell-md-4"><label>Configuración</label>
					<select name="data[preset]"><?php foreach ($catalog as $key => $c): ?><option value="<?= $e($key) ?>"><?= $e($c['label']) ?></option><?php endforeach; ?></select></div>
				<div class="cell-md-3"><label>Identificador</label><input type="text" name="data[id]" maxlength="32" pattern="[a-z0-9][a-z0-9_\-]{0,31}" required data-nf-noautofill>
					<small class="fg-gray">Lo usa <code>'provider' =&gt; '…'</code> en TablefIA.</small></div>
				<div class="cell-md-5"><label>Nombre</label><input type="text" name="data[name]" maxlength="80" data-nf-noautofill></div>
			</div>
			<div class="row">
				<div class="cell-md-7"><label>URL base de la API</label><input type="url" name="data[base_url]" maxlength="300" data-nf-noautofill></div>
				<div class="cell-md-5"><label>Modelo</label><input type="text" name="data[model]" list="ai-models" maxlength="200" required data-nf-noautofill><datalist id="ai-models"></datalist></div>
			</div>
			<div class="row">
				<div class="cell-md-7"><label>Llave de la API <span id="ai-key-required" class="fg-gray"></span></label>
					<input type="password" name="data[api_key]" autocomplete="new-password" data-nf-noautofill placeholder="sk-…  o  env:NOMBRE_VARIABLE">
					<small id="ai-key-hint" class="fg-gray d-block"></small>
					<label class="d-block"><input type="checkbox" name="data[clear_key]" value="1"> Quitar la llave guardada</label></div>
				<div class="cell-md-5 ai-effort"><label>Esfuerzo de razonamiento</label><select name="data[effort]"></select></div>
			</div>
			<div class="row">
				<div class="cell-md-4"><label>Respuesta JSON</label><select name="data[json_mode]"></select>
					<small class="fg-gray">Si el servidor rechaza el esquema, use «objeto» o «instrucciones».</small></div>
				<div class="cell-md-4"><label>Tokens de salida máximos</label><input type="number" name="data[max_tokens]" min="256" max="128000" step="1" value="16000"></div>
				<div class="cell-md-4"><label>Tiempo de espera (s)</label><input type="number" name="data[timeout]" min="5" max="600" step="1" value="120"></div>
			</div>
			<div class="row">
				<div class="cell"><label><input type="checkbox" name="data[default]" value="1"> Proveedor predeterminado</label></div>
			</div>
			<div class="row mt-2">
				<div class="cell">
					<button class="button primary secureop" value="save"><span class="mif-floppy-disk"></span>&nbsp;Guardar</button>
					<button type="button" id="ai-test" class="button info"><span class="mif-lab"></span>&nbsp;Probar sin guardar</button>
				</div>
			</div>
		</div>
		</form>
	</div>
</div>
