<?php
/*
 * Extensión de /nframework/uploadfile.php para el almacén de certificados del SAT.
 * Cada certificado se guarda en PEM con el nombre <hash>.<n> (formato de `openssl rehash`),
 * así el directorio funciona directamente como CApath para openssl_x509_checkpurpose().
 */

/**
 * Separa un archivo (DER o PEM, uno o varios certificados) en certificados PEM.
 */
function satCaSplitCertificates(string $content): array
{
    if (str_contains($content, '-----BEGIN CERTIFICATE-----')) {
        preg_match_all('/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s', $content, $matches);
        return $matches[0];
    }
    return ["-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($content), 64, "\n") . "-----END CERTIFICATE-----\n"];
}

function satCaIsAuthority(array $info): bool
{
    return stripos($info['extensions']['basicConstraints'] ?? '', 'CA:TRUE') !== false;
}

/**
 * Explica por qué el servidor web no puede escribir en el directorio del almacén.
 * Devuelve una lista de [problema, comandos para resolverlo].
 */
function satCaDirDiagnose(string $dir): array
{
    $user = function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? 'www-data') : 'www-data';
    $group = function_exists('posix_getegid') ? (posix_getgrgid(posix_getegid())['name'] ?? $user) : $user;
    $arg = escapeshellarg($dir);
    $problems = [];

    $basedir = (string) ini_get('open_basedir');
    if ($basedir !== '') {
        $allowed = false;
        foreach (explode(PATH_SEPARATOR, $basedir) as $base) {
            if ($base !== '' && str_starts_with(rtrim($dir, '/') . '/', rtrim($base, '/') . '/')) {
                $allowed = true;
            }
        }
        if (!$allowed) {
            $problems[] = ['PHP tiene <code>open_basedir</code> activo (' . $basedir . ') y no incluye el directorio.',
                "Agrega {$dir} a open_basedir en php.ini o en el pool de PHP-FPM y reinicia PHP-FPM."];
        }
    }

    // Punto de montaje del directorio (o de su ancestro existente) visto por este proceso.
    $path = $dir;
    while (!file_exists($path) && $path !== '/' && $path !== '.') {
        $path = dirname($path);
    }
    $mount = '';
    $readOnly = false;
    foreach (@file('/proc/self/mountinfo', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $fields = explode(' ', $line);
        $point = str_replace('\\040', ' ', $fields[4] ?? '');
        if ($point !== '' && strlen($point) >= strlen($mount) && ($point === '/' || $path === $point || str_starts_with($path, rtrim($point, '/') . '/'))) {
            $mount = $point;
            $readOnly = in_array('ro', explode(',', $fields[5] ?? ''), true);
        }
    }
    if ($readOnly) {
        $service = 'php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '-fpm';
        $problems[] = ["Para PHP, <code>{$mount}</code> está montado como <b>solo lectura</b>. Normalmente se debe a "
            . "<code>ProtectSystem</code> en el servicio systemd de PHP-FPM; cambiar el dueño o los permisos no basta. "
            . 'Usa un directorio fuera de <code>/etc</code> y <code>/usr</code> (p. ej. <code>/var/lib/nframework/sat</code>) o permite la ruta en el servicio:',
            "sudo mkdir -p {$arg} /etc/systemd/system/{$service}.service.d\n"
            . "sudo chown {$user}:{$group} {$arg}\nsudo chmod 750 {$arg}\n"
            . "printf '[Service]\\nReadWritePaths={$dir}\\n' | sudo tee /etc/systemd/system/{$service}.service.d/nframework-sat.conf\n"
            . "sudo systemctl daemon-reload\nsudo systemctl restart {$service}"];
    } elseif (!is_dir($dir)) {
        $problems[] = ['El directorio no existe.',
            "sudo mkdir -p {$arg}\nsudo chown {$user}:{$group} {$arg}\nsudo chmod 750 {$arg}"];
    } else {
        $owner = function_exists('posix_getpwuid') ? (posix_getpwuid(fileowner($dir))['name'] ?? fileowner($dir)) : fileowner($dir);
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        $problems[] = ["El directorio pertenece a <code>{$owner}</code> con permisos <code>{$perms}</code> y PHP se ejecuta como <code>{$user}</code>.",
            "sudo chown {$user}:{$group} {$arg}\nsudo chmod 750 {$arg}"];
    }
    return $problems;
}

/**
 * Guarda un certificado de CA en el almacén. Devuelve el nombre del archivo creado,
 * o null si ya estaba instalado.
 */
function satCaInstall(string $dir, string $pem): ?string
{
    $info = @openssl_x509_parse($pem);
    if ($info === false) {
        throw new RuntimeException('El archivo no es un certificado X.509 válido.');
    }
    // Solo autoridades certificadoras: un certificado de usuario aquí pasaría a ser "de confianza".
    if (!satCaIsAuthority($info)) {
        throw new RuntimeException('"' . ($info['subject']['CN'] ?? $info['name']) . '" no es un certificado de autoridad certificadora (CA).');
    }
    openssl_x509_export($pem, $normalized);
    $fingerprint = openssl_x509_fingerprint($pem, 'sha256');
    for ($n = 0; ; $n++) {
        $target = rtrim($dir, '/') . '/' . $info['hash'] . '.' . $n;
        if (!file_exists($target)) {
            file_put_contents($target, $normalized);
            chmod($target, 0644);
            return basename($target);
        }
        if (openssl_x509_fingerprint(file_get_contents($target), 'sha256') === $fingerprint) {
            return null; // ya existe
        }
    }
}

/**
 * Huellas SHA-256 de los certificados ya instalados.
 */
function satCaInstalledFingerprints(string $dir): array
{
    $fingerprints = [];
    foreach (glob(rtrim($dir, '/') . '/*') ?: [] as $file) {
        if (preg_match('/^[0-9a-f]{8}\.\d+$/', basename($file))) {
            $fingerprints[openssl_x509_fingerprint((string) file_get_contents($file), 'sha256')] = basename($file);
        }
    }
    return $fingerprints;
}

/**
 * Descarga el paquete de certificados del SAT y devuelve los certificados que contiene,
 * clasificados. No instala nada.
 */
function satCaDownload(string $url, string $dir): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'satca');
    $fh = fopen($tmp, 'w');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_MAXFILESIZE => 10 * 1024 * 1024,
        CURLOPT_USERAGENT => 'nframework',
    ]);
    $ok = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    fclose($fh);
    if (!$ok || $status !== 200) {
        unlink($tmp);
        throw new RuntimeException('No se pudo descargar ' . $url . ' (' . ($error ?: 'HTTP ' . $status) . ').');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp) !== true) {
        unlink($tmp);
        throw new RuntimeException('El archivo descargado no es un ZIP válido.');
    }
    $installed = satCaInstalledFingerprints($dir);
    $candidates = [];
    // Se lee en memoria: nada del ZIP se extrae al disco.
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if (!preg_match('/\.(cer|crt|pem)$/i', $entry)) {
            continue;
        }
        foreach (satCaSplitCertificates((string) $zip->getFromIndex($i)) as $pem) {
            $info = @openssl_x509_parse($pem);
            if ($info === false) {
                continue;
            }
            $fingerprint = openssl_x509_fingerprint($pem, 'sha256');
            if (isset($candidates[$fingerprint])) {
                continue; // mismo certificado en .cer y .crt
            }
            $isCa = satCaIsAuthority($info);
            $expired = $info['validTo_time_t'] < time();
            $candidates[$fingerprint] = [
                'file' => basename($entry),
                'pem' => $pem,
                'subject' => $info['subject']['CN'] ?? $info['subject']['OU'] ?? $info['name'],
                'issuer' => $info['issuer']['CN'] ?? $info['issuer']['OU'] ?? '',
                'root' => $info['subject'] == $info['issuer'],
                'validTo' => $info['validTo_time_t'],
                'status' => isset($installed[$fingerprint]) ? 'installed' : (!$isCa ? 'notca' : ($expired ? 'expired' : 'new')),
            ];
        }
    }
    $zip->close();
    unlink($tmp);
    uasort($candidates, fn($a, $b) => [$b['root'], $a['subject']] <=> [$a['root'], $b['subject']]);
    return $candidates;
}

function onupload($filename, $upload)
{
    $content = (string) file_get_contents($filename);
    unlink($filename);
    $saved = [];
    foreach (satCaSplitCertificates($content) as $pem) {
        if (($name = satCaInstall($upload['dir'], $pem)) !== null) {
            $saved[] = $name;
        }
    }
    return ['saved' => $saved];
}

function ondelete($filename, $upload)
{
    $name = basename($filename);
    if (!preg_match('/^[0-9a-f]{8}\.\d+$/', $name)) {
        throw new RuntimeException('Nombre de archivo inválido.');
    }
    unlink(rtrim($upload['dir'], '/') . '/' . $name);
    return ['deleted' => $name];
}

function onlist($upload)
{
    $ret = [];
    foreach (glob(rtrim($upload['dir'], '/') . '/*') ?: [] as $index => $file) {
        if (preg_match('/^[0-9a-f]{8}\.\d+$/', basename($file))) {
            $ret[] = ['id' => $index, 'name' => basename($file), 'length' => filesize($file)];
        }
    }
    return $ret;
}
