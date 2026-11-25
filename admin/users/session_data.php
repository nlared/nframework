<?php
// Datos guardados en una sesión de un usuario. La sesión se identifica por usuario + token público
// (User::sessionToken()): el id real es la credencial de la sesión y no viaja en la URL.
require '../common2.php';

$uid = is_string($_GET['user'] ?? null) ? $_GET['user'] : '';
$token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
$row = null;
if (isValidObjectId($uid) && $token !== '') {
	$target = new User(['_id' => $uid]);
	foreach ($target->sessionsReport() as $r) {
		if (hash_equals($r['token'], $token)) {
			$row = $r;
			break;
		}
	}
}

$data = null;
if ($row !== null) {
	$doc = $m->{$config['sitedb']}->sessions->findOne(['_id' => $row['id']]);
	if ($doc) {
		// session.serialize_handler = php: «clave|valor;» por cada variable de primer nivel.
		$raw = $doc['data']->getData();
		$data = [];
		$offset = 0;
		if (ini_get('session.serialize_handler') === 'php_serialize') {
			$data = (array) @unserialize($raw, ['allowed_classes' => false]);
			$offset = strlen($raw);
		}
		while ($offset < strlen($raw) && preg_match('/\G([^|]+)\|/', $raw, $mt, 0, $offset)) {
			$offset += strlen($mt[0]);
			$rest = substr($raw, $offset);
			$value = @unserialize($rest, ['allowed_classes' => false]);
			if ($value === false && !str_starts_with($rest, 'b:0;')) {
				break;
			}
			$data[$mt[1]] = $value;
			$offset += strlen(serialize($value));
		}
		// Secretos de la sesión: no se muestran.
		if (isset($data['nf']['Anti-CSRF'])) {
			$data['nf']['Anti-CSRF'] = '••••••';
		}
	}
}
$nframework->metas['title'] = 'Datos de sesión';
?>
<div class="container p-4" style="max-width:1000px">
	<a href="user.php?_id=<?= urlencode($uid) ?>" class="button mb-3"><span class="mif-arrow-left"></span>&nbsp;Volver al usuario</a>
	<div class="box shadow p-5" style="border-radius:var(--nf-radius-lg,12px)">
		<?php if ($row === null) { ?>
			<div class="remark alert">La sesión no existe o no pertenece a este usuario.</div>
		<?php } else { ?>
			<div class="h4 mb-2"><span class="mif-display"></span>&nbsp;<?= htmlspecialchars($row['device']) ?></div>
			<p class="fg-gray">IP <code><?= htmlspecialchars($row['ip'] ?: '—') ?></code> ·
				Inicio <?= $row['since'] ? date('Y-m-d H:i', $row['since']) : '—' ?> ·
				Último uso <?= $row['last'] ? date('Y-m-d H:i', $row['last']) : '—' ?></p>
			<?php if ($data === null) { ?>
				<div class="remark warning">La sesión ya no tiene datos (terminó).</div>
			<?php } else { ?>
				<pre style="max-height:70vh;overflow:auto;background:var(--nf-surface-2,#f6f7f9);padding:1rem;border-radius:var(--nf-radius,8px)"><?= htmlspecialchars(print_r($data, true)) ?></pre>
			<?php } ?>
		<?php } ?>
	</div>
</div>
