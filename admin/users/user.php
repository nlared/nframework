<?php
if (empty($_GET['_id']) || !is_string($_GET['_id']) || !preg_match('/^[a-f\d]{24}$/i', $_GET['_id'])) {
	header('Location: ?_id=' . new MongoDB\BSON\ObjectId());
	exit();
}
require '../common2.php';

$db = $m->{$config['sitedb']};
$userId = toMongoId($_GET['_id']);
$dataset = new dataset(
	[
		'collection' => $db->users,
		'_id' => $_GET['_id'],
		'simpleid' => false,
		'nameprefix' => 'data'
	]
);

$password = new inputText(['id' => 'password', 'name' => 'password', 'type' => 'password', 'caption' => $nframework->language['password'] . ':', 'required' => true]);
$username = new inputText(['dataset' => &$dataset, 'field' => 'username', 'caption' => $nframework->language['username'] . ':', 'required' => true, 'prepend' => '<span class="mif-user"></span>']);
$disabled = new inputCheckbox(['dataset' => &$dataset, 'field' => 'disabled', 'caption' => 'Cuenta deshabilitada (no puede entrar)']);
$twofarequired = new inputCheckbox(['dataset' => &$dataset, 'field' => 'twofa_required', 'caption' => 'Requerir la verificación en dos pasos (2FA) a este usuario']);

// Usuario editado (vacío si todavía no existe). El secreto del 2FA nunca se muestra ni viaja al navegador.
$target = new User(['_id' => $userId]);
$exists = !empty($target->_id);
$twofaActive = $exists && !empty($db->users->findOne(['_id' => $userId], ['projection' => ['twofa_secret' => 1]])['twofa_secret'] ?? null);

/**
 * Fecha y hora local de un timestamp Unix; '—' si no hay.
 *
 * @param int|null $ts
 * @return string
 */
function nfUserDate($ts): string
{
	return $ts ? date('Y-m-d H:i', (int) $ts) : '—';
}

/**
 * Tiempo relativo en español («hace 5 min», «en 2 h»).
 *
 * @param int|null $ts
 * @return string
 */
function nfUserAgo($ts): string
{
	if (!$ts) {
		return '';
	}
	$d = (int) $ts - time();
	$a = abs($d);
	if ($a < 60) {
		return $d >= 0 ? 'en menos de 1 min' : 'hace un momento';
	}
	foreach ([86400 => 'd', 3600 => 'h', 60 => 'min'] as $s => $u) {
		if ($a >= $s) {
			$n = (int) floor($a / $s);
			return ($d >= 0 ? 'en ' : 'hace ') . $n . ' ' . $u;
		}
	}
	return '';
}

/**
 * Bloque de sesiones (se reemplaza completo tras cada operación AJAX).
 *
 * @param User $target usuario editado
 * @return string HTML
 */
function nfUserSessionsHtml(User $target): string
{
	$rows = $target->sessionsReport();
	$count = ['active' => 0, 'idle' => 0, 'gone' => 0];
	foreach ($rows as $r) {
		$count[$r['status']]++;
	}
	$life = (int) ini_get('session.gc_maxlifetime') ?: 1440;
	$tag = [
		'active' => '<span class="nfu-status active"><span class="dot"></span>Activa</span>',
		'idle' => '<span class="nfu-status idle"><span class="dot"></span>Inactiva</span>',
		'gone' => '<span class="nfu-status gone"><span class="dot"></span>Terminada</span>',
	];
	$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
	$uid = urlencode((string) $target->_id);

	$out = '<div class="nfu-stats">'
		. '<div class="nfu-stat"><span class="n fg-green">' . $count['active'] . '</span><span>Activas</span></div>'
		. '<div class="nfu-stat"><span class="n fg-orange">' . $count['idle'] . '</span><span>Inactivas</span></div>'
		. '<div class="nfu-stat"><span class="n fg-gray">' . $count['gone'] . '</span><span>Terminadas</span></div>'
		. '</div>'
		. '<p class="nfu-muted">Activa: usada en los últimos ' . round($life / 60) . ' min. Inactiva: pasó ese tiempo sin uso pero '
		. 'sigue valiendo hasta la próxima limpieza del servidor; ciérrela si no la reconoce. Terminada: ya no existe '
		. '(expiró o se cerró) y solo queda en el historial del usuario.</p>';

	if ($rows === []) {
		return $out . '<div class="nfu-empty"><span class="mif-display mif-3x"></span><div>Sin sesiones registradas.</div></div>';
	}

	$out .= '<div class="nfu-sessions">';
	foreach ($rows as $r) {
		$open = $r['status'] !== 'gone';
		$out .= '<div class="nfu-session ' . $r['status'] . '">'
			. '<div class="icon"><span class="' . (preg_match('/iPhone|Android/', $r['device']) ? 'mif-mobile' : 'mif-display') . '"></span></div>'
			. '<div class="main">'
			. '<div class="title">' . $h($r['device']) . ' ' . $tag[$r['status']]
			. ($r['current'] ? ' <span class="tag info">Esta sesión</span>' : '') . '</div>'
			. '<div class="meta">'
			. '<span title="IP"><span class="mif-earth"></span> <code>' . $h($r['ip'] ?: '—') . '</code></span>'
			. '<span title="Inicio"><span class="mif-enter"></span> ' . nfUserDate($r['since']) . '</span>'
			. ($r['last'] ? '<span title="Último uso: ' . nfUserDate($r['last']) . '"><span class="mif-history"></span> ' . nfUserAgo($r['last']) . '</span>' : '')
			. ($open && $r['expires'] ? '<span title="Caduca: ' . nfUserDate($r['expires']) . '"><span class="mif-hour-glass"></span> '
				. ($r['status'] === 'idle' ? 'caducó ' . nfUserAgo($r['expires']) : 'caduca ' . nfUserAgo($r['expires'])) . '</span>' : '')
			. '</div>'
			. ($r['agent'] !== '' ? '<div class="agent" title="User-Agent">' . $h($r['agent']) . '</div>' : '')
			. '</div>'
			. '<div class="actions">'
			. ($open ? '<a class="button small" title="Ver datos de la sesión" href="session_data.php?user=' . $uid . '&amp;token=' . $h($r['token']) . '"><span class="mif-eye"></span></a>' : '')
			. ($open && !$r['current'] ? '<button type="button" class="button small alert" title="Cerrar sesión" onclick="nfSesOp(\'sesclose\', \'' . $h($r['token']) . '\')"><span class="mif-exit"></span></button>' : '')
			. '</div>'
			. '</div>';
	}
	$out .= '</div><div class="nfu-toolbar">';
	if ($count['active'] + $count['idle'] > 0) {
		$out .= '<button type="button" class="button alert" onclick="nfSesOp(\'sesall\')"><span class="mif-exit"></span>&nbsp;Cerrar todas las sesiones</button>';
	}
	if ($count['idle'] > 0) {
		$out .= '<button type="button" class="button warning" onclick="nfSesOp(\'sesidle\')"><span class="mif-hour-glass"></span>&nbsp;Cerrar las inactivas</button>';
	}
	if ($count['gone'] > 0) {
		$out .= '<button type="button" class="button" onclick="nfSesOp(\'sesclean\')"><span class="mif-bin"></span>&nbsp;Limpiar historial</button>';
	}
	return $out . '</div>';
}

if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/admins/users/user.php')) {
	include $_SERVER['DOCUMENT_ROOT'] . '/admins/users/user.php';
}

if ($nframework->isAjax()) {
	$op = is_string($_POST['op'] ?? null) ? $_POST['op'] : '';
	if (!csrfValidate()) {
		$result = ['error' => 'Formulario caducado, recargue la página.'];
	} elseif ($op === 'save') {
		try {
			$result = ['error' => $dataset->save()];
		} catch (Exception $e) {
			$result = ['error' => $e->getMessage()];
		}
	} elseif ($op === 'password') {
		$new = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
		if (mb_strlen($new) < NF_PASSWORD_MIN_LENGTH) {
			$result = ['error' => 'La contraseña debe tener al menos ' . NF_PASSWORD_MIN_LENGTH . ' caracteres.'];
		} else {
			$dataset->password = nfPasswordHash($new);
			$result = ['error' => false, 'js' => 'toast("Contraseña cambiada.");'];
		}
	} elseif ($op === 'twofareset') {
		// El usuario pierde su teléfono: se borra el secreto y, si el 2FA es obligatorio, lo activa de nuevo al entrar.
		if (!$exists) {
			$result = ['error' => 'Usuario inválido.'];
		} else {
			$db->users->updateOne(['_id' => $userId], ['$unset' => ['twofa_secret' => '']]);
			$result = ['error' => false, 'ids' => ['twofaStatus' => '<span class="tag">No activada</span>']];
		}
	} elseif (in_array($op, ['sesclose', 'sesall', 'sesidle', 'sesclean'], true)) {
		if (!$exists) {
			$result = ['error' => 'Usuario inválido.'];
		} else {
			$token = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';
			$ids = [];
			foreach ($target->sessionsReport() as $r) {
				if ($r['current']) {
					continue;   // la sesión del propio administrador se cierra con «Salir»
				}
				if (($op === 'sesclose' && $token !== '' && hash_equals($r['token'], $token))
					|| $op === 'sesall'
					|| ($op === 'sesidle' && $r['status'] === 'idle')
					|| ($op === 'sesclean' && $r['status'] === 'gone')) {
					$ids[] = $r['id'];
				}
			}
			$n = $target->closeSessions($ids);
			$msg = $op === 'sesclean' ? $n . ' registro(s) quitado(s) del historial.' : $n . ' sesión(es) cerrada(s).';
			$result = ['error' => false, 'ids' => ['nfSessions' => nfUserSessionsHtml($target)], 'js' => 'toast(' . json_encode($msg) . ');'];
		}
	}
	return;
}

$nframework->usecommon = true;
$nframework->metas['title'] = $exists ? $target->username : $nframework->language['user'];

$groups = [];
if ($exists) {
	foreach ($db->usersgroups->find(['users' => $userId], ['projection' => ['name' => 1], 'sort' => ['name' => 1]]) as $g) {
		$groups[] = (string) ($g['name'] ?? '');
	}
}
$csrf = csrfToken(NF_AJAX_FORM_ACTION);
?>
<style>
	.nfu { max-width: 1100px; margin: 0 auto; padding: 1.5rem 1rem; display: grid; gap: 1.25rem; }
	.nfu-card { background: var(--nf-surface, #fff); border: 1px solid var(--nf-line, #e5e7eb); border-radius: var(--nf-radius-lg, 12px);
		box-shadow: var(--nf-shadow, 0 1px 3px rgba(0,0,0,.08)); padding: 1.25rem 1.5rem; }
	.nfu-card > h3 { margin: 0 0 1rem; font-size: 1.05rem; font-weight: 600; display: flex; align-items: center; gap: .5rem; }
	.nfu-head { display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap; }
	.nfu-head img { width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid var(--nf-line, #e5e7eb); }
	.nfu-head .who { flex: 1; min-width: 220px; }
	.nfu-head .who .name { font-size: 1.4rem; font-weight: 600; word-break: break-all; }
	.nfu-head .who .tags { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .35rem; }
	.nfu-head .back { align-self: flex-start; }
	.nfu-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem; }
	.nfu-muted { color: var(--nf-muted, #6b7280); font-size: .85rem; margin: .25rem 0 1rem; }
	.nfu-actions { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: flex-end; margin-top: 1rem; }
	.nfu-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: .75rem; }
	.nfu-stat { border: 1px solid var(--nf-line, #e5e7eb); border-radius: var(--nf-radius, 8px); padding: .75rem; text-align: center; }
	.nfu-stat .n { display: block; font-size: 1.8rem; font-weight: 700; line-height: 1.1; }
	.nfu-stat span:last-child { font-size: .8rem; color: var(--nf-muted, #6b7280); }
	.nfu-sessions { display: grid; gap: .6rem; }
	.nfu-session { display: flex; gap: 1rem; align-items: flex-start; border: 1px solid var(--nf-line, #e5e7eb);
		border-left: 4px solid #16a34a; border-radius: var(--nf-radius, 8px); padding: .75rem 1rem; }
	.nfu-session.idle { border-left-color: #f59e0b; }
	.nfu-session.gone { border-left-color: #9ca3af; opacity: .7; }
	.nfu-session .icon { font-size: 1.6rem; color: var(--nf-accent, #2563eb); padding-top: .15rem; }
	.nfu-session .main { flex: 1; min-width: 0; }
	.nfu-session .title { font-weight: 600; display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; }
	.nfu-session .meta { display: flex; flex-wrap: wrap; gap: .25rem 1rem; font-size: .85rem; margin-top: .25rem; color: var(--nf-muted, #6b7280); }
	.nfu-session .agent { font-size: .75rem; color: var(--nf-muted, #6b7280); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: .2rem; }
	.nfu-session .actions { display: flex; gap: .35rem; }
	.nfu-status { display: inline-flex; align-items: center; gap: .3rem; font-size: .75rem; font-weight: 600; padding: .1rem .5rem; border-radius: 999px; }
	.nfu-status .dot { width: .5rem; height: .5rem; border-radius: 50%; background: currentColor; }
	.nfu-status.active { color: #15803d; background: rgba(22,163,74,.12); }
	.nfu-status.idle { color: #b45309; background: rgba(245,158,11,.15); }
	.nfu-status.gone { color: #6b7280; background: rgba(156,163,175,.18); }
	.nfu-toolbar { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: flex-end; margin-top: 1rem; }
	.nfu-empty { text-align: center; padding: 1.5rem; color: var(--nf-muted, #6b7280); }
	#dialogPass { width: min(480px, calc(100vw - 2rem)); padding: 1.5rem; border: 0; border-radius: var(--nf-radius-lg, 12px); }
	#dialogPass[open] { inset: 0; margin: auto; }
	#dialogPass::backdrop { background: rgba(0, 0, 0, .45); backdrop-filter: blur(3px); }
	@media (max-width: 600px) { .nfu-session { flex-wrap: wrap; } .nfu-session .actions { width: 100%; justify-content: flex-end; } }
</style>

<div class="nfu">
	<div class="nfu-card nfu-head">
		<img src="<?= $exists ? htmlspecialchars($target->gravatar(144)) : '/images/pngto' . nfImageFormat() . '/users/144/144/none.' . nfImageFormat() ?>" alt="">
		<div class="who">
			<div class="name"><?= $exists ? htmlspecialchars((string) $target->username) : 'Nuevo usuario' ?></div>
			<?php if ($exists) { ?>
				<div class="tags">
					<?= $target->disabled ? '<span class="tag alert">Deshabilitado</span>' : '<span class="tag success">Habilitado</span>' ?>
					<?= $twofaActive ? '<span class="tag success">2FA activa</span>' : '<span class="tag">Sin 2FA</span>' ?>
					<?php foreach ($groups as $g) { ?><span class="tag info"><?= htmlspecialchars($g) ?></span><?php } ?>
				</div>
				<div class="nfu-muted mb-0">Alta: <?= date('Y-m-d H:i', $userId->getTimestamp()) ?> · Id <code><?= htmlspecialchars((string) $target->_id) ?></code></div>
			<?php } ?>
		</div>
		<a href="./" class="button back"><span class="mif-arrow-left"></span>&nbsp;<?= $nframework->language['users'] ?></a>
	</div>

	<div class="nfu-grid">
		<div class="nfu-card">
			<h3><span class="mif-user"></span><?= $nframework->language['user'] ?></h3>
			<?= secureform() ?>
				<?= $username ?>
				<?= $permisos ?? '' ?>
				<?php
				if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/admins/users/user.ui.php')) {
					include $_SERVER['DOCUMENT_ROOT'] . '/admins/users/user.ui.php';
				}
				?>
				<div class="mt-3"><?= $disabled ?></div>
				<div class="mt-2"><?= $twofarequired ?></div>
				<small class="nfu-muted d-block">Hasta activarla solo podrá abrir <code>/account/twofa-setup</code> o salir.<?= !empty($config['twofa_required']) ? ' El sitio ya la exige a todos (Admin → Sitio).' : '' ?></small>
				<div class="nfu-actions">
					<button class="button success secureop" value="save"><span class="mif-floppy-disk"></span>&nbsp;<?= $nframework->language['save'] ?></button>
				</div>
			</form>
		</div>

		<div class="nfu-card">
			<h3><span class="mif-lock"></span>Seguridad</h3>
			<div class="d-flex flex-align-center flex-justify-between flex-wrap" style="gap:.5rem">
				<div><strong><?= $nframework->language['password'] ?></strong><div class="nfu-muted m-0">Asignar una contraseña nueva.</div></div>
				<button type="button" class="button primary" onclick="dialogpass_open();"><span class="mif-key"></span>&nbsp;<?= $nframework->language['changepassword'] ?></button>
			</div>
			<hr>
			<div class="d-flex flex-align-center flex-justify-between flex-wrap" style="gap:.5rem">
				<div><strong>Verificación en dos pasos</strong><div class="m-0" id="twofaStatus"><?= $twofaActive ? '<span class="tag success">Activa</span>' : '<span class="tag">No activada</span>' ?></div></div>
				<?php if ($twofaActive) { ?>
					<button type="button" class="button warning" onclick="nfTwofaReset();"><span class="mif-key"></span>&nbsp;Restablecer 2FA</button>
				<?php } ?>
			</div>
		</div>
	</div>

	<?php if ($exists) { ?>
		<div class="nfu-card">
			<h3><span class="mif-display"></span>Sesiones</h3>
			<div id="nfSessions"><?= nfUserSessionsHtml($target) ?></div>
		</div>
	<?php } ?>
</div>

<dialog id="dialogPass">
	<div class="dialog-title mb-2"><?= $nframework->language['changepassword'] ?></div>
	<div class="dialog-content">
		<?= $password ?>
		<small class="nfu-muted">Mínimo <?= NF_PASSWORD_MIN_LENGTH ?> caracteres.</small>
	</div>
	<div class="nfu-actions">
		<button type="button" class="button" onclick="dialogpass_close();"><?= $nframework->language['buttons']['cancel'] ?></button>
		<button type="button" class="button primary" onclick="cambiar();"><?= $nframework->language['buttons']['ok'] ?></button>
	</div>
</dialog>

<script>
	const nfUserCsrf = <?= json_encode($csrf) ?>;
	const dialogpass = document.querySelector('#dialogPass');

	function nfUserPost(data) {
		data.CSRFToken = nfUserCsrf;
		return $.post(location.pathname + location.search, data).done(function (r) {
			nAjaxFormDone(r);
		}).fail(function (x) {
			Swal.fire('Error', 'El servidor respondió ' + x.status + '.', 'error');
		});
	}

	function dialogpass_open() {
		$('#password').val('');
		dialogpass.showModal();
	}

	function dialogpass_close() {
		dialogpass.close();
	}

	function cambiar() {
		nfUserPost({op: 'password', password: $('#password').val()}).always(dialogpass_close);
	}

	function nfTwofaReset() {
		Swal.fire({
			title: '¿Restablecer la verificación en dos pasos?',
			text: 'El usuario tendrá que volver a escanear el código QR.',
			icon: 'warning', showCancelButton: true, confirmButtonText: 'Restablecer', cancelButtonText: 'Cancelar'
		}).then(function (r) {
			if (r.isConfirmed) {
				nfUserPost({op: 'twofareset'});
			}
		});
	}

	function nfSesOp(op, token) {
		const texts = {
			sesclose: ['¿Cerrar esta sesión?', 'El dispositivo tendrá que iniciar sesión de nuevo.'],
			sesall: ['¿Cerrar todas las sesiones?', 'El usuario saldrá en todos sus dispositivos<?= $exists && (string) $target->_id === (string) $user->_id ? ' (excepto esta sesión)' : '' ?>.'],
			sesidle: ['¿Cerrar las sesiones inactivas?', 'Se cierran las que pasaron el tiempo de inactividad y aún no se purgan.'],
			sesclean: ['¿Limpiar el historial?', 'Se quitan de la lista las sesiones que ya terminaron.']
		};
		Swal.fire({
			title: texts[op][0], text: texts[op][1], icon: 'warning', showCancelButton: true,
			confirmButtonColor: op === 'sesclean' ? undefined : '#d33', confirmButtonText: 'Sí', cancelButtonText: 'Cancelar'
		}).then(function (r) {
			if (r.isConfirmed) {
				nfUserPost({op: op, token: token || ''});
			}
		});
	}
</script>
