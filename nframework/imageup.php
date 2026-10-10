<?php
require 'vendor/autoload.php';
require 'config.php';
$m = new MongoDB\Client($config['mongo_connection_string']);
use Nlared\MongoSessionHandler;
$sessions = $m->{$config['sitedb']}->sessions;
$handler = new MongoSessionHandler($sessions);
session_set_save_handler($handler);
session_name(preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $config['cookie_domain']));
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'domain' => $config['cookie_domain'], 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
session_start();

$id=(string)($_GET['id'] ?? '');
if (isset($_SESSION['nf5imageup'][$id])) {
	$conf=$_SESSION['nf5imageup'][$id];
	// Solo se acepta un data URI de imagen PNG/JPEG/WEBP válido.
	if(is_string($_POST['image'] ?? null) && preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=]+)$#', $_POST['image'], $match)){
		$data = base64_decode($match[2], true);
		if ($data !== false && @getimagesizefromstring($data) !== false) {
			file_put_contents($conf['path'], $data);
		}
	}
}
