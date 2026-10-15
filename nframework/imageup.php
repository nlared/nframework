<?php
/**
 * Recibe la imagen de Signature_pad (data URI PNG/JPEG/WEBP), la guarda en `path` y responde en JSON:
 * {error: false|string, onresult: [...], fecha}. Después llama al evento del servidor `onsave` y, si el
 * componente está ligado a un dataset, registra la firma en el documento.
 */
require 'include.php';

$id = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
$conf = $_SESSION['nf5imageup'][$id] ?? null;

if (!$nframework->isAjax()) {
	http_response_code(400);
	return;
}
if (empty($conf['path'])) {
	$result = ['error' => 'La sesión del componente caducó; recargue la página.'];
	return;
}
// Solo se acepta un data URI de imagen válido.
if (!is_string($_POST['image'] ?? null) || !preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$#', $_POST['image'], $match)) {
	$result = ['error' => 'La imagen no es válida.'];
	return;
}
$data = base64_decode($match[2], true);
if ($data === false || @getimagesizefromstring($data) === false) {
	$result = ['error' => 'La imagen no es válida.'];
	return;
}
$dir = dirname($conf['path']);
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
	$result = ['error' => 'No se pudo crear la carpeta de la firma.'];
	return;
}
if (@file_put_contents($conf['path'], $data) === false) {
	error_log('nframework imageup: no se pudo escribir ' . $conf['path']);
	$result = ['error' => 'No se pudo guardar la firma.'];
	return;
}

$now = new MongoDB\BSON\UTCDateTime();
try {
	// Registro en el documento del dataset (lo que dataset->save() del formulario no sobrescribe).
	if (!empty($conf['dataset'])) {
		$ds = $conf['dataset'];
		$docId = $ds['simpleid'] ? $ds['_id'] : (isValidObjectId($ds['_id']) ? toMongoId($ds['_id']) : null);
		if ($docId !== null && $docId !== '') {
			$m->{$ds['db']}->{$ds['collection']}->updateOne(['_id' => $docId], ['$set' => [$ds['field'] => [
				'archivo' => basename($conf['path']),
				'fecha' => $now,
				'usuario' => $user->isLoggedIn() && isValidObjectId($user->_id) ? toMongoId($user->_id) : null,
				'sha256' => hash('sha256', $data),
			]]], ['upsert' => true]);
		}
	}

	// Evento del servidor: igual que onupload de inputFiles (archivo extension + nombre de función).
	$onresult = [];
	if (!empty($conf['onsave'])) {
		if (!empty($conf['extension']) && is_file($conf['extension'])) {
			require_once $conf['extension'];
		}
		if (!is_callable($conf['onsave'])) {
			throw new RuntimeException('onsave no es una función válida');
		}
		$onresult[] = call_user_func($conf['onsave'], $conf['path'], $conf);
	}
	$result = ['error' => false, 'onresult' => $onresult, 'fecha' => date('Y-m-d H:i:s')];
} catch (MongoDB\Driver\Exception\Exception $e) {
	error_log('nframework imageup: ' . $e->getMessage());
	$result = ['error' => 'La firma se guardó, pero no se pudo registrar en el documento.'];
} catch (Throwable $e) {
	// Mensaje de onsave: así la función del sitio puede rechazar la firma con un texto para el usuario.
	error_log('nframework imageup: ' . $e->getMessage());
	$result = ['error' => $e->getMessage()];
}
