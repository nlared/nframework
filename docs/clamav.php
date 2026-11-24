<?php
require 'common.php';

$clamav = ClamAV::configOptions($config['clamav'] ?? []);
$mostrar = [];
foreach (['enabled', 'host', 'port', 'timeout', 'maxsize', 'onerror'] as $k) {
	$mostrar[$k] = $clamav[$k] ?? null;
}
?>
<div class="container">
	<?= docHeader('Antivirus (ClamAV)', '<code>ClamAV</code> analiza archivos con el servicio <a href="https://www.clamav.net/" target="_blank" rel="noopener">clamd</a> por TCP. Está en <code>includes/class.ClamAV.php</code>: funciona en páginas, rutas y <a href="jobs.php">jobs</a>. Se configura en Admin → Antivirus.') ?>

	<h3>Subidas de /nframework</h3>
	<p>Con ClamAV habilitado se analizan automáticamente, antes de guardarse o leerse: <code>inputFile</code>/<code>inputFiles</code> y archivos de nftables (<code>nframework/uploadfile.php</code>), imágenes de <code>inputMCE</code> (<code>tinymceupload.php</code>) e importaciones de nftables (<code>nfimport.php</code>). Un archivo infectado se borra y el usuario recibe el error. Los certificados y llaves de <a href="efirma.php">e.firma</a> no se envían al antivirus.</p>

	<h3>Analizar un archivo en código propio</h3>
	<?= docCode(<<<'PHP'
// Lo mismo que hacen los endpoints de /nframework (respeta enabled y onerror, borra el archivo infectado):
if (($error = ClamAV::uploadError($_FILES['doc']['tmp_name'], $_FILES['doc']['name'])) !== null) {
    $result = ['error' => $error];
    return;
}

// Control completo del resultado:
$av = ClamAV::fromConfig();                 // null si ClamAV no está habilitado en Admin → Antivirus
if ($av !== null) {
    $r = $av->scanFile($rutaTemporal);      // ['status' => 'OK'|'FOUND'|'ERROR', 'virus' => ?string, 'message' => string]
    if ($r['status'] === 'FOUND') {
        unlink($rutaTemporal);
        $result = ['error' => 'Archivo rechazado: contiene ' . $r['virus']];
        return;
    }
    if ($r['status'] === 'ERROR') {
        error_log('ClamAV: ' . $r['message']);   // el sitio decide si rechaza el archivo cuando falla el antivirus
    }
}

// Contenido en memoria (un PDF generado, un adjunto de correo…):
$r = $av->scanString($contenido);

// Otro servidor sin tocar $config['clamav']:
$av = new ClamAV(['host' => '10.0.0.5', 'port' => 3310, 'timeout' => 30, 'maxsize' => 100]);
$av->ping();       // true si responde PONG
$av->version();    // "ClamAV 1.4.1/27100/..."; lanza RuntimeException sin conexión
PHP) ?>
	<p>El archivo viaja por la conexión (comando <code>INSTREAM</code>), así que clamd no necesita permisos sobre él y puede estar en otro equipo.</p>

	<h3>Configuración</h3>
	<p>Se toma de <code>$config['clamav']</code>: Admin → Antivirus (guardado en MongoDB, con botón de prueba) o <code>includes/config.php</code>.</p>
	<table class="table striped compact">
		<thead><tr><th>Clave</th><th>Descripción</th><th>En este sitio</th></tr></thead>
		<tbody>
			<?php
			$desc = [
				'enabled' => '<code>true</code> para que <code>ClamAV::fromConfig()</code> devuelva el cliente.',
				'host' => 'Servidor clamd (<code>TCPAddr</code> de <code>clamd.conf</code>).',
				'port' => 'Puerto (<code>TCPSocket</code>); 3310 por omisión.',
				'timeout' => 'Segundos para conectar y esperar el resultado; 10 por omisión.',
				'maxsize' => 'MB máximos por archivo; no debe superar <code>StreamMaxLength</code> (25 MB por omisión).',
				'onerror' => '<code>reject</code> (por omisión) rechaza la subida si clamd falla; <code>accept</code> la deja pasar y lo registra.',
			];
			foreach ($mostrar as $k => $v) {
				echo '<tr><td><code>' . $k . '</code></td><td>' . $desc[$k] . '</td><td><code>' . htmlspecialchars(var_export($v, true)) . '</code></td></tr>';
			}
			?>
		</tbody>
	</table>
	<?= docCode(<<<'BASH'
# Debian/Ubuntu: instalar y escuchar por TCP solo en localhost
apt install clamav-daemon
printf 'TCPSocket 3310\nTCPAddr 127.0.0.1\n' >> /etc/clamav/clamd.conf
systemctl restart clamav-daemon
printf 'zPING\0' | nc -q1 127.0.0.1 3310     # → PONG
BASH) ?>
	<div class="remark warning">El protocolo de clamd no tiene autenticación ni cifrado: no lo exponga a Internet.</div>

	<?= docSource(__FILE__) ?>
</div>
