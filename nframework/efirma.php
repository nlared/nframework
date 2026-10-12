<?php
/*
 * Endpoint del componente Efirma (includes/class.Efirma.php).
 * op=inspect  revisa un .cer (tipo, RFC, vigencia, emitido por el SAT); no guarda nada.
 * op=list     e.firmas del servidor del usuario.
 * op=store    guarda .cer + .key + contraseña cifrados; exige la firma autógrafa de Signature_pad.
 * op=delete   elimina una e.firma del servidor del usuario.
 * Nunca firma datos enviados por el navegador con una llave del servidor.
 */
require 'include.php';

use PhpCfdi\Credentials\Certificate;
use PhpCfdi\Credentials\Credential;

$nframework->usecommon = false;
$result = [];

$id = is_string($_POST['id'] ?? null) ? $_POST['id'] : '';
$op = is_string($_POST['op'] ?? null) ? $_POST['op'] : '';
$conf = $_SESSION['nfefirma'][$id] ?? null;

if (!$nframework->isAjax() || !is_array($conf) || !csrfValidate()) {
	$result = ['error' => 'Solicitud inválida, recargue la página.'];
	return;
}
// Solo cuentan las operaciones que prueban certificados o contraseñas.
if (in_array($op, ['inspect', 'store'], true) && !nflogAttempt('efirma:' . $ip, 30, 900)) {
	$result = ['error' => 'Demasiados intentos, espere unos minutos.'];
	return;
}

function nfEfirmaUploaded(string $field, int $max = 65536): ?string
{
	$file = $_FILES[$field] ?? null;
	if (!is_array($file) || !is_string($file['tmp_name'] ?? null) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK
		|| $file['size'] <= 0 || $file['size'] > $max || !is_uploaded_file($file['tmp_name'])) {
		return null;
	}
	return file_get_contents($file['tmp_name']);
}

function nfEfirmaCertificate(array $conf, ?string $contents, ?string &$error): ?Certificate
{
	try {
		$certificate = new Certificate((string) $contents);
	} catch (Throwable $e) {
		$error = 'El archivo .cer no es un certificado válido.';
		return null;
	}
	$error = Efirma::checkCertificate($certificate, $conf['tipo'], $conf['rfc']);
	if ($error === null && !Efirma::satIssued($certificate)) {
		$error = 'El certificado no fue emitido por el SAT (o falta configurar sat_ca_bundle en Admin → SAT).';
	}
	return $error === null ? $certificate : null;
}

$serverOnly = ['list', 'store', 'delete'];
if (in_array($op, $serverOnly, true) && ($conf['storage'] !== 'server' || !$user->isLoggedIn())) {
	$result = ['error' => 'Inicie sesión para usar e.firmas guardadas en el servidor.'];
	return;
}
$efirmas = $m->{$config['sitedb']}->{Efirma::COLLECTION};

switch ($op) {
	case 'inspect':
		$certificate = nfEfirmaCertificate($conf, nfEfirmaUploaded('cer'), $error);
		$result = $certificate ? ['error' => false, 'info' => Efirma::certificateInfo($certificate)] : ['error' => $error];
		break;

	case 'list':
		$result = ['error' => false, 'items' => Efirma::listFor($user->_id, $conf['tipo'], $conf['rfc'])];
		break;

	case 'store':
		$cer = nfEfirmaUploaded('cer');
		$key = nfEfirmaUploaded('key');
		$pass = is_string($_POST['pass'] ?? null) ? $_POST['pass'] : '';
		$certificate = nfEfirmaCertificate($conf, $cer, $error);
		if (!$certificate) {
			$result = ['error' => $error];
			break;
		}
		try {
			Credential::create((string) $cer, (string) $key, $pass);
		} catch (Throwable $e) {
			$result = ['error' => 'Contraseña incorrecta o la llave privada no corresponde al certificado.'];
			break;
		}
		// La firma autógrafa la sube Signature_pad a firma_path (fuera del alcance del navegador).
		$firmaPath = $conf['firma_path'];
		$firma = is_file($firmaPath) ? file_get_contents($firmaPath) : '';
		if ($firma === '' || @getimagesizefromstring($firma) === false) {
			$result = ['error' => 'Falta la firma autógrafa: firme y presione Guardar en el recuadro de firma.'];
			break;
		}
		try {
			$secret = Efirma::encrypt(json_encode(['key' => base64_encode((string) $key), 'pass' => $pass]), Efirma::secretKey());
		} catch (Throwable $e) {
			$result = ['error' => $e->getMessage()];
			break;
		}
		$info = Efirma::certificateInfo($certificate);
		$now = new MongoDB\BSON\UTCDateTime();
		$efirmas->updateOne(['owner' => $user->_id, 'nocert' => $info['nocert']], [
			'$set' => [
				'rfc' => strtoupper($info['rfc']),
				'nombre' => $info['nombre'],
				'sucursal' => $info['sucursal'],
				'tipo' => $info['tipo'],
				'vence' => new MongoDB\BSON\UTCDateTime($certificate->validToDateTime()->getTimestamp() * 1000),
				'cer' => base64_encode((string) $cer),
				'secret' => $secret,
				'autorizacion' => [
					'texto' => $conf['consent'],
					'firma' => new MongoDB\BSON\Binary($firma, MongoDB\BSON\Binary::TYPE_GENERIC),
					'ip' => $ip,
					'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
					'fecha' => $now,
				],
				'updated_at' => $now,
			],
			'$setOnInsert' => ['created_at' => $now],
		], ['upsert' => true]);
		@unlink($firmaPath);
		$doc = $efirmas->findOne(['owner' => $user->_id, 'nocert' => $info['nocert']]);
		$result = ['error' => false, 'item' => Efirma::publicDoc($doc)];
		break;

	case 'delete':
		$efirma = is_string($_POST['efirma'] ?? null) ? $_POST['efirma'] : '';
		if (!isValidObjectId($efirma)) {
			$result = ['error' => 'e.firma inválida.'];
			break;
		}
		$efirmas->deleteOne(['_id' => toMongoId($efirma), 'owner' => $user->_id]);
		$result = ['error' => false];
		break;

	default:
		$result = ['error' => 'Operación desconocida.'];
}
