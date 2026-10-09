<?php
require '../common2.php';
require_once __DIR__ . '/sat_ext.php';
$nframework->usecommon = true;

// Directorio del almacén de CAs del SAT (fuera de la carpeta pública). No se usa /etc porque
// PHP-FPM suele ejecutarse con ProtectSystem=full, que lo deja en solo lectura.
$satCaDir = '/var/lib/nframework/sat';
// Paquete oficial de certificados de producción del SAT (solo se publica por HTTP).
$satCaUrl = $config['sat_ca_url'] ?? 'http://omawww.sat.gob.mx/tramitesyservicios/Paginas/documentos/Cert_Prod.zip';
$csrf = csrfToken('/admin/sat/');
$validPost = $_SERVER['REQUEST_METHOD'] === 'POST' && is_string($_POST['CSRFToken'] ?? null) && hash_equals($csrf, $_POST['CSRFToken']);

// sat/validate.php usa sat_ca_bundle; se apunta a este directorio.
if (($config['sat_ca_bundle'] ?? '') !== $satCaDir) {
    $m->{$config['sitedb']}->configs->updateOne(
        ['_id' => 'site'],
        ['$set' => ['sat_ca_bundle' => $satCaDir]],
        ['upsert' => true]
    );
    $config['sat_ca_bundle'] = $satCaDir;
}

$dirExists = is_dir($satCaDir);
$dirWritable = $dirExists && is_writable($satCaDir);
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

// Descarga del paquete del SAT: primero se muestra el contenido y el administrador confirma.
$downloadMessage = '';
$candidates = $_SESSION['sat_ca_candidates'] ?? [];
if ($validPost && ($_POST['op'] ?? '') === 'download') {
    try {
        $candidates = satCaDownload($satCaUrl, $satCaDir);
        $_SESSION['sat_ca_candidates'] = $candidates;
        addVarToGarbage('sat_ca_candidates', time() + 1800);
    } catch (Exception $e) {
        $candidates = [];
        $downloadMessage = '<div class="remark alert">' . $h($e->getMessage()) . '</div>';
    }
} elseif ($validPost && ($_POST['op'] ?? '') === 'install' && $dirWritable) {
    $installedNow = [];
    foreach ((array) ($_POST['fp'] ?? []) as $fingerprint) {
        if (is_string($fingerprint) && isset($candidates[$fingerprint]) && $candidates[$fingerprint]['status'] === 'new') {
            try {
                if (satCaInstall($satCaDir, $candidates[$fingerprint]['pem']) !== null) {
                    $installedNow[] = $candidates[$fingerprint]['subject'] . ' (' . $candidates[$fingerprint]['file'] . ')';
                }
            } catch (Exception $e) {
                $downloadMessage .= '<div class="remark alert">' . $h($e->getMessage()) . '</div>';
            }
        }
    }
    unset($_SESSION['sat_ca_candidates']);
    $candidates = [];
    $downloadMessage .= '<div class="remark success">Instalados: ' . count($installedNow) . '<br>' . implode('<br>', array_map($h, $installedNow)) . '</div>';
} elseif ($validPost && ($_POST['op'] ?? '') === 'cancel') {
    unset($_SESSION['sat_ca_candidates']);
    $candidates = [];
}

// Prueba de un certificado de e.firma contra el almacén.
$testResult = '';
if ($validPost && isset($_FILES['testcert']) && is_uploaded_file($_FILES['testcert']['tmp_name'])) {
    $certs = satCaSplitCertificates((string) file_get_contents($_FILES['testcert']['tmp_name']));
    $info = @openssl_x509_parse($certs[0] ?? '');
    if ($info === false) {
        $testResult = '<div class="remark alert">El archivo no es un certificado válido.</div>';
    } else {
        $valid = $dirExists && openssl_x509_checkpurpose($certs[0], X509_PURPOSE_ANY, [$satCaDir]) === true;
        $expired = ($info['validTo_time_t'] ?? 0) < time();
        $who = $h($info['subject']['name'] ?? $info['subject']['CN'] ?? $info['name']);
        $uid = $h($info['subject']['x500UniqueIdentifier'] ?? '');
        $testResult = $valid && !$expired
            ? '<div class="remark success"><b>Válido.</b> ' . $who . ' (' . $uid . ') — emitido por una CA del almacén, vigente hasta ' . date('Y-m-d', $info['validTo_time_t']) . '.</div>'
            : '<div class="remark alert"><b>No válido.</b> ' . $who . ' — ' . ($expired ? 'certificado vencido.' : 'no se encontró la cadena de confianza; falta la CA emisora (' . $h($info['issuer']['CN'] ?? $info['issuer']['O'] ?? '') . ') o su raíz.') . '</div>';
    }
}

// Detalle de los certificados instalados.
$rows = '';
$count = 0;
if ($dirExists) {
    foreach (glob($satCaDir . '/*') ?: [] as $file) {
        if (!preg_match('/^[0-9a-f]{8}\.\d+$/', basename($file))) {
            continue;
        }
        $count++;
        $pem = (string) file_get_contents($file);
        $info = @openssl_x509_parse($pem);
        if ($info === false) {
            $rows .= '<tr><td>' . $h(basename($file)) . '</td><td colspan="4" class="fg-red">Archivo ilegible</td></tr>';
            continue;
        }
        $selfSigned = $info['subject'] == $info['issuer'];
        $expired = $info['validTo_time_t'] < time();
        $rows .= '<tr>
            <td><code>' . $h(basename($file)) . '</code></td>
            <td>' . $h($info['subject']['CN'] ?? $info['name']) . '</td>
            <td>' . ($selfSigned ? '<span class="tag">Raíz</span>' : $h($info['issuer']['CN'] ?? '')) . '</td>
            <td class="' . ($expired ? 'fg-red' : '') . '">' . date('Y-m-d', $info['validTo_time_t']) . ($expired ? ' (vencido)' : '') . '</td>
            <td><code style="font-size:.75em">' . $h(substr(openssl_x509_fingerprint($pem, 'sha256'), 0, 16)) . '…</code></td>
        </tr>';
    }
}

$uploader = '';
if ($dirWritable) {
    $files = new inputFiles([
        'id' => 'satcerts',
        'name' => 'satcerts',
        'caption' => 'Subir certificados de las AC del SAT (.cer, .crt, .pem)',
        'dir' => $satCaDir,
        'extension' => __DIR__ . '/sat_ext.php',
        'onupload' => 'onupload',
        'ondelete' => 'ondelete',
        'onlist' => 'onlist',
        'accept' => '.cer,.crt,.pem',
        'delete' => true,
        'download' => false,
        'preview' => false,
        'mode' => 'drop',
    ]);
    // Mostrar errores y recargar la tabla de detalle tras subir o eliminar.
    $files->onDone = "if (data.error) { Swal.fire('Error', data.error, 'error'); return; }
        if (data.onresult && data.onresult.length) { location.reload(); return; }\n" . $files->onDone;
    $uploader = (string) $files;
}
?>
<div class="container p-5">
    <div class="box shadow-large">
        <div class="box-title">SAT — Certificados de confianza para e.firma</div>
        <p>
            El inicio de sesión con e.firma (<code>/sat/validate.php</code>) solo acepta certificados emitidos por
            una autoridad certificadora registrada aquí. Sube la CA raíz del SAT y las CA intermedias que emiten la e.firma
            (archivos <code>.cer</code> en formato DER o PEM; un archivo PEM puede traer varios).
        </p>
        <p>
            Directorio: <code><?= $h($satCaDir) ?></code> —
            <code>sat_ca_bundle</code> configurado: <code><?= $h($config['sat_ca_bundle']) ?></code> —
            Certificados instalados: <b><?= $count ?></b>
        </p>

        <? if ($dirWritable) { ?>
            <form method="POST" class="mb-4">
                <input type="hidden" name="CSRFToken" value="<?= $h($csrf) ?>">
                <button class="button primary" name="op" value="download"><span class="mif-download"></span> Descargar certificados del SAT</button>
                <small class="ml-2">Fuente: <code><?= $h($satCaUrl) ?></code></small>
            </form>
            <?= $downloadMessage ?>
            <? if (!empty($candidates)) {
                $labels = [
                    'new' => '<span class="tag success">Nuevo</span>',
                    'installed' => '<span class="tag">Ya instalado</span>',
                    'expired' => '<span class="tag warning">Vencido</span>',
                    'notca' => '<span class="tag">No es CA (OCSP)</span>',
                ];
            ?>
                <div class="remark warning">
                    El SAT publica este paquete solo por HTTP, sin cifrado. Antes de instalar, compara las huellas SHA-256
                    con una fuente confiable (por ejemplo, descargando el mismo archivo desde otra red).
                    Solo se pueden instalar certificados de autoridad vigentes.
                </div>
                <form method="POST">
                    <input type="hidden" name="CSRFToken" value="<?= $h($csrf) ?>">
                    <table class="table striped compact">
                        <thead><tr><th></th><th>Archivo</th><th>Certificado</th><th>Emitido por</th><th>Vigencia</th><th>Estado</th><th>SHA-256</th></tr></thead>
                        <tbody>
                        <? foreach ($candidates as $fingerprint => $c) { ?>
                            <tr>
                                <td><? if ($c['status'] === 'new') { ?><input type="checkbox" name="fp[]" value="<?= $h($fingerprint) ?>" checked><? } ?></td>
                                <td><?= $h($c['file']) ?></td>
                                <td><?= $h($c['subject']) ?></td>
                                <td><?= $c['root'] ? '<span class="tag">Raíz</span>' : $h($c['issuer']) ?></td>
                                <td><?= date('Y-m-d', $c['validTo']) ?></td>
                                <td><?= $labels[$c['status']] ?></td>
                                <td><code style="font-size:.7em;word-break:break-all"><?= $h($fingerprint) ?></code></td>
                            </tr>
                        <? } ?>
                        </tbody>
                    </table>
                    <button class="button success" name="op" value="install"><span class="mif-checkmark"></span> Instalar seleccionados</button>
                    <button class="button" name="op" value="cancel">Cancelar</button>
                </form>
            <? } ?>
        <? } ?>

        <? if (!$dirWritable) { ?>
            <div class="remark alert">
                <b>El servidor web no puede escribir en <code><?= $h($satCaDir) ?></code>.</b>
                <? foreach (satCaDirDiagnose($satCaDir) as [$problem, $commands]) { ?>
                    <p><?= $problem ?> Ejecuta en el servidor:</p>
                    <pre><?= $h($commands) ?></pre>
                <? } ?>
                <p>Después recarga esta página.</p>
            </div>
        <? } else { ?>
            <?= $uploader ?>
        <? } ?>

        <? if ($count > 0) { ?>
            <table class="table striped compact mt-4">
                <thead>
                    <tr><th>Archivo</th><th>Certificado</th><th>Emitido por</th><th>Vigencia</th><th>SHA-256</th></tr>
                </thead>
                <tbody><?= $rows ?></tbody>
            </table>
        <? } elseif ($dirExists && $count === 0) { ?>
            <div class="remark warning mt-4">No hay certificados instalados: el inicio de sesión con e.firma será rechazado.</div>
        <? } ?>
    </div>

    <div class="box shadow-large mt-4">
        <div class="box-title">Probar una e.firma</div>
        <p>Sube el <code>.cer</code> de una e.firma para comprobar que su cadena es reconocida. El archivo no se guarda.</p>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="CSRFToken" value="<?= $h($csrf) ?>">
            <input type="file" name="testcert" accept=".cer,.crt,.pem" data-role="file" required>
            <button class="button primary mt-2" type="submit"><span class="mif-checkmark"></span> Verificar</button>
        </form>
        <?= $testResult ?>
    </div>
</div>
