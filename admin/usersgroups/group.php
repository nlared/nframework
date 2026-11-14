<?php
if (empty($_GET['_id']) || !is_string($_GET['_id']) || !preg_match('/^[a-f0-9]{24}$/i', $_GET['_id'])) {
	header('Location: ?_id=' . new MongoDB\BSON\ObjectId());
	exit();
}
require '../common2.php';

$db = $m->{$config['sitedb']};
$dataset = new dataset([
	'collection' => $db->usersgroups,
	'_id' => $_GET['_id'],
	'simpleid' => false,
	'nameprefix' => 'data',
]);

// Grupos con significado para el framework: su nombre no se cambia desde aquí.
$gruposEspeciales = [
	'admins' => 'Acceso al panel de administración (/admin/) y a nftables.',
	'developers' => 'Modo desarrollador: errores en pantalla además del errorlog.',
];
$nombreActual = (string) ($dataset->name ?? '');
$esEspecial = isset($gruposEspeciales[$nombreActual]);
$esNuevo = $nombreActual === '';

$name = new inputText([
	'dataset' => &$dataset,
	'field' => 'name',
	'caption' => 'Nombre',
	'required' => true,
	'readonly' => $esEspecial,
	'backreadonly' => $esEspecial,
]);
$description = new inputText([
	'dataset' => &$dataset,
	'field' => 'description',
	'caption' => 'Descripción',
]);

if ($nframework->isAjax()) {
	if (!csrfValidate()) {
		$result = ['error' => 'El formulario caducó, recargue la página.'];
	} elseif (($_POST['op'] ?? '') === 'save') {
		// Miembros: arreglo JSON de _id; solo cuentan los usuarios que existen (y nunca guest).
		$ids = json_decode(is_string($_POST['members'] ?? null) ? $_POST['members'] : '[]', true);
		$ids = is_array($ids) ? array_values(array_filter($ids, fn($id) => is_string($id) && isValidObjectId($id))) : [];
		$miembros = [];
		if ($ids) {
			foreach ($db->users->find(
				['_id' => ['$in' => toMongoIds($ids)], 'username' => ['$ne' => 'guest']],
				['projection' => ['_id' => 1]]
			) as $u) {
				$miembros[] = $u->_id;
			}
		}

		$nuevoNombre = $esEspecial ? $nombreActual : trim(is_string($_POST['data']['name'] ?? null) ? $_POST['data']['name'] : '');
		$duplicado = $nuevoNombre !== '' && $db->usersgroups->countDocuments([
			'name' => $nuevoNombre,
			'_id' => ['$ne' => toMongoId($dataset->_id)],
		], ['limit' => 1]) > 0;

		if ($nuevoNombre === '') {
			$result = ['error' => 'Escriba el nombre del grupo.'];
		} elseif ($duplicado) {
			$result = ['error' => 'Ya existe un grupo llamado "' . htmlspecialchars($nuevoNombre) . '".'];
		} elseif ($nuevoNombre === 'admins' && !in_array((string) $user->_id, array_map('strval', $miembros), true)) {
			$result = ['error' => 'No puede quitarse a sí mismo del grupo admins: perdería el acceso a este panel.'];
		} elseif (($errores = $dataset->save()) !== false && $errores !== '') {
			$result = ['error' => $errores];
		} else {
			$dataset->users = $miembros;
			$result = [
				'error' => false,
				'js' => 'nfGroupSaved(' . count($miembros) . ');',
			];
		}
	}
	return;
}

$nframework->usecommon = true;
$nframework->metas['title'] = $esNuevo ? 'Nuevo grupo' : 'Grupo ' . $nombreActual;

$miembrosIds = array_map('strval', (array) ($dataset->users ?? []));
$usuarios = [];
foreach ($db->users->find(
	['username' => ['$ne' => 'guest']],
	['projection' => ['username' => 1, 'name' => 1, 'nombre' => 1, 'razonsocial' => 1, 'disabled' => 1], 'sort' => ['username' => 1]]
) as $u) {
	$detalle = $u->name ?? $u->nombre ?? $u->razonsocial ?? '';
	$usuarios[] = [
		'id' => (string) $u->_id,
		'username' => (string) ($u->username ?? ''),
		'detail' => is_string($detalle) ? $detalle : '',
		'disabled' => !empty($u->disabled),
		'self' => (string) $u->_id === (string) $user->_id,
	];
}
// _id en el grupo cuyo usuario ya no existe (p. ej. un admin borrado): se avisa y se quitan al guardar.
$existentes = array_column($usuarios, 'id');
$huerfanos = count(array_diff($miembrosIds, $existentes));
?>
<div class="container nf-group py-6">
	<a href="./" class="nf-group-back"><span class="mif-arrow-left"></span> Grupos</a>

	<header class="nf-group-head">
		<div>
			<div class="nf-eyebrow"><?= $esNuevo ? 'Nuevo grupo' : 'Grupo' ?></div>
			<h1 class="nf-group-title" id="nfGroupTitle"><?= $esNuevo ? 'Sin nombre' : htmlspecialchars($nombreActual) ?></h1>
			<?php if ($esEspecial) { ?>
				<p class="nf-muted m-0"><span class="mif-lock"></span> Grupo del sistema. <?= htmlspecialchars($gruposEspeciales[$nombreActual]) ?></p>
			<?php } ?>
		</div>
		<div class="nf-group-stat">
			<span class="nf-group-stat-num" id="nfGroupCount"><?= count(array_intersect($miembrosIds, $existentes)) ?></span>
			<span class="nf-muted">miembros</span>
		</div>
	</header>

	<?= secureform() ?>
	<input type="hidden" name="members" id="nfGroupMembers" value="">

	<section class="nf-card mb-4">
		<h2 class="nf-group-h2">Datos</h2>
		<div class="nf-group-fields">
			<div><?= $name ?></div>
			<div><?= $description ?></div>
		</div>
	</section>

	<section class="nf-card">
		<div class="nf-group-h2-row">
			<h2 class="nf-group-h2">Miembros</h2>
			<span class="nf-muted">Haga clic en un usuario para moverlo de una lista a la otra.</span>
		</div>
		<?php if ($huerfanos > 0) { ?>
			<div class="nf-group-note">
				<span class="mif-warning"></span>
				El grupo tiene <?= $huerfanos ?> referencia(s) a usuarios que ya no existen; se quitarán al guardar.
			</div>
		<?php } ?>

		<div class="nf-group-lists">
			<div class="nf-group-panel" data-side="in">
				<div class="nf-group-panel-head">
					<strong>En el grupo</strong>
					<span class="nf-group-badge" data-count="in">0</span>
				</div>
				<input type="search" class="nf-group-search" data-filter="in" placeholder="Buscar miembro…" aria-label="Buscar miembro" data-nf-noautofill>
				<ul class="nf-group-list" data-list="in" aria-label="Miembros del grupo"></ul>
				<button type="button" class="button small flat nf-group-bulk" data-bulk="in">Quitar los mostrados</button>
			</div>
			<div class="nf-group-panel" data-side="out">
				<div class="nf-group-panel-head">
					<strong>Disponibles</strong>
					<span class="nf-group-badge" data-count="out">0</span>
				</div>
				<input type="search" class="nf-group-search" data-filter="out" placeholder="Buscar usuario…" aria-label="Buscar usuario" data-nf-noautofill>
				<ul class="nf-group-list" data-list="out" aria-label="Usuarios disponibles"></ul>
				<button type="button" class="button small flat nf-group-bulk" data-bulk="out">Agregar los mostrados</button>
			</div>
		</div>
	</section>

	<footer class="nf-group-actions">
		<span class="nf-group-dirty" id="nfGroupDirty" hidden><span class="mif-pencil"></span> Cambios sin guardar</span>
		<a href="./" class="button">Cerrar</a>
		<button class="button primary secureop" value="save"><span class="mif-floppy-disk"></span>&nbsp;Guardar</button>
	</footer>
	</form>
</div>

<style>
	.nf-group { max-width: 1040px; }
	/* Metro aplica `section, header, footer { margin: 0 auto }` y su <form> es flex en columna: los márgenes automáticos
	   encogen las tarjetas al ancho de su contenido y las centran. */
	.nf-group section, .nf-group header, .nf-group footer { margin-left: 0; margin-right: 0; }
	.nf-group form > * { width: 100%; min-width: 0; }
	/* Cuadrículas según el ancho disponible (la barra lateral lo reduce), no según el viewport. */
	.nf-group-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)); gap: 0 1rem; }
	.nf-group-fields > * { min-width: 0; }
	.nf-group-back { display: inline-flex; align-items: center; gap: .35rem; color: var(--nf-muted); text-decoration: none; margin-bottom: 1rem; }
	.nf-group-back:hover { color: var(--nf-accent); }
	.nf-group-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
	.nf-group-title { font-size: 1.75rem; font-weight: 650; margin: .15rem 0 .25rem; line-height: 1.2; word-break: break-word; }
	.nf-group-stat { display: flex; flex-direction: column; align-items: flex-end; }
	.nf-group-stat-num { font-size: 2rem; font-weight: 650; line-height: 1; font-variant-numeric: tabular-nums; }
	.nf-group-h2 { font-size: 1rem; font-weight: 600; margin: 0 0 .75rem; }
	.nf-group-h2-row { display: flex; justify-content: space-between; align-items: baseline; gap: 1rem; flex-wrap: wrap; margin-bottom: .75rem; }
	.nf-group-h2-row .nf-group-h2 { margin: 0; }
	.nf-group-note { display: flex; gap: .5rem; align-items: center; padding: .6rem .8rem; margin-bottom: .75rem; border-radius: var(--nf-radius-sm);
		background: color-mix(in srgb, var(--nf-warning) 12%, var(--nf-surface)); color: var(--nf-ink); }
	.nf-group-lists { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); gap: 1rem; }
	.nf-group-panel { display: flex; flex-direction: column; gap: .5rem; min-width: 0; padding: .75rem; border: 1px solid var(--nf-line);
		border-radius: var(--nf-radius); background: var(--nf-surface-2); }
	.nf-group-panel[data-side="in"] { background: var(--nf-accent-soft); border-color: color-mix(in srgb, var(--nf-accent) 30%, var(--nf-line)); }
	.nf-group-panel-head { display: flex; justify-content: space-between; align-items: center; }
	.nf-group-badge { min-width: 1.75rem; padding: .1rem .5rem; border-radius: 999px; text-align: center; font-size: .8rem; font-weight: 600;
		background: var(--nf-surface); border: 1px solid var(--nf-line); font-variant-numeric: tabular-nums; }
	.nf-group-search { width: 100%; padding: .45rem .7rem; border: 1px solid var(--nf-line); border-radius: var(--nf-radius-sm);
		background: var(--nf-surface); color: var(--nf-ink); }
	.nf-group-search:focus { outline: none; box-shadow: var(--nf-ring); border-color: var(--nf-accent); }
	.nf-group-list { list-style: none; margin: 0; padding: 0; height: clamp(220px, 45vh, 420px); overflow-y: auto; display: flex; flex-direction: column; gap: .25rem; }
	.nf-group-item { width: 100%; height: auto; min-height: 0; line-height: 1.3; display: flex; align-items: center; gap: .6rem; padding: .45rem .6rem; text-align: left; cursor: pointer;
		border: 1px solid transparent; border-radius: var(--nf-radius-sm); background: var(--nf-surface); color: var(--nf-ink);
		transition: border-color .15s var(--nf-ease), transform .15s var(--nf-ease); }
	.nf-group-item:hover { border-color: var(--nf-line-strong); }
	.nf-group-item:focus-visible { outline: none; box-shadow: var(--nf-ring); }
	.nf-group-item:disabled { cursor: not-allowed; opacity: .75; }
	.nf-group-avatar { flex: none; width: 2rem; height: 2rem; border-radius: 50%; display: grid; place-items: center; font-size: .8rem; font-weight: 600;
		background: var(--nf-accent-soft); color: var(--nf-accent); text-transform: uppercase; }
	.nf-group-who { flex: 1; min-width: 0; display: flex; flex-direction: column; }
	.nf-group-who b, .nf-group-who small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
	.nf-group-who b { font-weight: 500; }
	.nf-group-who small { color: var(--nf-muted); }
	.nf-group-tag { flex: none; font-size: .7rem; padding: .05rem .4rem; border-radius: 999px; border: 1px solid var(--nf-line); color: var(--nf-muted); }
	.nf-group-tag.is-off { color: var(--nf-danger); border-color: color-mix(in srgb, var(--nf-danger) 40%, var(--nf-line)); }
	.nf-group-arrow { flex: none; color: var(--nf-muted); }
	.nf-group-item:hover .nf-group-arrow { color: var(--nf-accent); }
	.nf-group-item.is-new { border-color: var(--nf-accent); }
	.nf-group-empty { padding: 1.5rem .5rem; text-align: center; color: var(--nf-muted); }
	.nf-group-bulk { align-self: flex-start; }
	/* Panel angosto: las etiquetas (usted, deshabilitado) bajan a una segunda línea para no aplastar el nombre. */
	.nf-group-panel { container-type: inline-size; }
	@container (max-width: 380px) {
		.nf-group-item { flex-wrap: wrap; row-gap: .3rem; }
		.nf-group-who { flex: 1 1 calc(100% - 5rem); }
		.nf-group-tag { order: 3; }
		.nf-group-who + .nf-group-tag { margin-left: 2.6rem; }
	}
	.nf-group-actions { position: sticky; bottom: 0; z-index: 2; display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: .5rem;
		margin-top: 1rem; padding: .75rem 0; background: linear-gradient(to top, var(--nf-surface-2) 75%, transparent); }
	.nf-group-dirty { margin-right: auto; color: var(--nf-warning); font-size: .9rem; }
	@media (max-width: 480px) {
		.nf-group-title { font-size: 1.4rem; }
		.nf-group-stat { flex-direction: row; align-items: baseline; gap: .4rem; }
		.nf-group-stat-num { font-size: 1.5rem; }
		.nf-group .nf-card { padding: 1rem; }
		.nf-group-dirty { flex-basis: 100%; }
		.nf-group-actions .button { flex: 1; }
	}
</style>

<script>
	(function() {
		const usuarios = <?= json_encode($usuarios, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
		const esAdmins = <?= json_encode($nombreActual === 'admins') ?>;
		const inicial = new Set(<?= json_encode(array_values(array_intersect($miembrosIds, $existentes))) ?>);
		const miembros = new Set(inicial);
		const filtros = {in: '', out: ''};
		const hidden = document.getElementById('nfGroupMembers');

		function norm(s) {
			return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
		}

		function item(u, lado) {
			const li = document.createElement('li');
			const b = document.createElement('button');
			b.type = 'button';
			b.className = 'nf-group-item' + (miembros.has(u.id) !== inicial.has(u.id) ? ' is-new' : '');
			b.dataset.id = u.id;
			const bloqueado = esAdmins && u.self && lado === 'in';
			b.disabled = bloqueado;
			b.title = bloqueado ? 'No puede quitarse a sí mismo de admins' : (lado === 'in' ? 'Quitar del grupo' : 'Agregar al grupo');

			const av = document.createElement('span');
			av.className = 'nf-group-avatar';
			av.textContent = (u.username || '?').slice(0, 2);
			const who = document.createElement('span');
			who.className = 'nf-group-who';
			const nombre = document.createElement('b');
			nombre.textContent = u.username;
			who.append(nombre);
			if (u.detail) {
				const det = document.createElement('small');
				det.textContent = u.detail;
				who.append(det);
			}
			b.append(av, who);
			if (u.self) {
				const t = document.createElement('span');
				t.className = 'nf-group-tag';
				t.textContent = 'usted';
				b.append(t);
			}
			if (u.disabled) {
				const t = document.createElement('span');
				t.className = 'nf-group-tag is-off';
				t.textContent = 'deshabilitado';
				b.append(t);
			}
			const flecha = document.createElement('span');
			flecha.className = 'nf-group-arrow ' + (bloqueado ? 'mif-lock' : (lado === 'in' ? 'mif-arrow-right' : 'mif-arrow-left'));
			b.append(flecha);
			li.append(b);
			return li;
		}

		function visibles(lado) {
			const f = norm(filtros[lado]);
			return usuarios.filter(u => (miembros.has(u.id) === (lado === 'in')) &&
				(!f || norm(u.username + ' ' + u.detail).includes(f)));
		}

		function render() {
			for (const lado of ['in', 'out']) {
				const ul = document.querySelector('[data-list="' + lado + '"]');
				const lista = visibles(lado);
				ul.replaceChildren(...lista.map(u => item(u, lado)));
				if (!lista.length) {
					const li = document.createElement('li');
					li.className = 'nf-group-empty';
					li.textContent = filtros[lado] ? 'Sin coincidencias' : (lado === 'in' ? 'El grupo no tiene miembros' : 'Todos los usuarios están en el grupo');
					ul.append(li);
				}
				const total = usuarios.filter(u => miembros.has(u.id) === (lado === 'in')).length;
				document.querySelector('[data-count="' + lado + '"]').textContent = total;
				document.querySelector('[data-bulk="' + lado + '"]').disabled = !lista.some(u => !(esAdmins && u.self && lado === 'in'));
			}
			document.getElementById('nfGroupCount').textContent = miembros.size;
			hidden.value = JSON.stringify([...miembros]);
			const sucio = miembros.size !== inicial.size || [...miembros].some(id => !inicial.has(id));
			document.getElementById('nfGroupDirty').hidden = !sucio && !formSucio;
		}

		let formSucio = false;
		document.querySelectorAll('[data-list]').forEach(ul => ul.addEventListener('click', e => {
			const b = e.target.closest('.nf-group-item');
			if (!b || b.disabled) return;
			miembros.has(b.dataset.id) ? miembros.delete(b.dataset.id) : miembros.add(b.dataset.id);
			render();
		}));
		document.querySelectorAll('[data-filter]').forEach(inp => inp.addEventListener('input', () => {
			filtros[inp.dataset.filter] = inp.value;
			render();
		}));
		document.querySelectorAll('[data-bulk]').forEach(btn => btn.addEventListener('click', () => {
			const lado = btn.dataset.bulk;
			for (const u of visibles(lado)) {
				if (lado === 'in') {
					if (!(esAdmins && u.self)) miembros.delete(u.id);
				} else {
					miembros.add(u.id);
				}
			}
			render();
		}));
		document.querySelectorAll('input[name^="data["]').forEach(inp => inp.addEventListener('input', () => {
			formSucio = true;
			if (inp.name === 'data[name]') {
				document.getElementById('nfGroupTitle').textContent = inp.value.trim() || 'Sin nombre';
			}
			render();
		}));

		// Llamada desde la respuesta del servidor ($result['js']) al guardar.
		window.nfGroupSaved = function(n) {
			inicial.clear();
			miembros.forEach(id => inicial.add(id));
			formSucio = false;
			render();
		};
		window.addEventListener('beforeunload', e => {
			if (!document.getElementById('nfGroupDirty').hidden) {
				e.preventDefault();
				e.returnValue = '';
			}
		});

		render();
	})();
</script>
