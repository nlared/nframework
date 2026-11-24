<?php

/**
 * Cliente de ClamAV (clamd) por TCP: PING, VERSION y análisis de archivos o cadenas con INSTREAM.
 *
 * La configuración vive en $config['clamav'] (Admin → Antivirus, guardada en configs/site, o en
 * includes/config.php):
 *
 *   'clamav' => [
 *       'enabled' => true,          // el sitio debe analizar con ClamAV
 *       'host'    => '127.0.0.1',   // servidor clamd (TCPAddr)
 *       'port'    => 3310,          // TCPSocket de clamd.conf
 *       'timeout' => 10,            // segundos para conectar y esperar respuesta
 *       'maxsize' => 25,            // MB; debe ser ≤ StreamMaxLength de clamd.conf
 *       'onerror' => 'reject',      // si clamd falla: 'reject' rechaza la subida, 'accept' la deja pasar
 *   ]
 *
 * Con 'enabled' los endpoints de subida de /nframework (uploadfile.php: inputFile/inputFiles, nftables;
 * tinymceupload.php: imágenes de inputMCE; nfimport.php: importaciones) analizan cada archivo con
 * ClamAV::uploadError() antes de guardarlo o leerlo. Un archivo infectado se borra y se rechaza.
 *
 * Ejemplo de uso en código propio del sitio:
 *
 *   $av = ClamAV::fromConfig();          // null si ClamAV no está habilitado
 *   if ($av !== null) {
 *       $r = $av->scanFile($rutaTemporal);
 *       if ($r['status'] === 'FOUND') {
 *           unlink($rutaTemporal);
 *           $result = ['error' => 'Archivo rechazado: contiene ' . $r['virus']];
 *       } elseif ($r['status'] === 'ERROR') {
 *           error_log('ClamAV: ' . $r['message']);   // decida si rechaza o acepta cuando falla el antivirus
 *       }
 *   }
 *
 * Usa el protocolo "z" de clamd (comandos y respuestas terminados en NUL). Se recomienda que clamd escuche
 * solo en localhost o en una red privada: el protocolo no tiene autenticación ni cifrado.
 */
class ClamAV
{
    /** Puerto TCP por omisión de clamd. */
    public const DEFAULT_PORT = 3310;

    /** Tamaño de cada bloque enviado con INSTREAM. */
    private const CHUNK = 8192;

    /** @var array{host: string, port: int, timeout: int, maxsize: int} */
    public array $options;

    /**
     * @param array{host?: string, port?: int|string, timeout?: int|string, maxsize?: int|string} $options
     */
    public function __construct(array $options = [])
    {
        $this->options = self::normalize($options);
    }

    /**
     * Cliente con $config['clamav'], o null si ClamAV no está habilitado o no tiene servidor.
     *
     * @global class_config $config
     * @return ClamAV|null
     */
    public static function fromConfig(): ?ClamAV
    {
        global $config;
        $options = self::configOptions($config['clamav'] ?? []);
        if (empty($options['enabled']) || trim((string) ($options['host'] ?? '')) === '') {
            return null;
        }
        return new ClamAV($options);
    }

    /**
     * Revisa un archivo recibido si ClamAV está habilitado. Si está infectado lo borra.
     *
     *   if (($error = ClamAV::uploadError($_FILES['doc']['tmp_name'], $_FILES['doc']['name'])) !== null) {
     *       $result = ['error' => $error];
     *       return;
     *   }
     *
     * @param string $path archivo temporal de la subida
     * @param string $name nombre original (solo para el registro)
     * @global class_config $config
     * @return string|null mensaje para el usuario si se rechaza; null si está limpio o ClamAV no está habilitado
     */
    public static function uploadError(string $path, string $name = ''): ?string
    {
        global $config;
        $av = self::fromConfig();
        if ($av === null) {
            return null;
        }
        $scan = $av->scanFile($path);
        $options = self::configOptions($config['clamav'] ?? []);
        $error = self::decide($scan, (string) ($options['onerror'] ?? 'reject'));
        if ($scan['status'] !== 'OK') {
            error_log('ClamAV: ' . $scan['status'] . ' ' . ($scan['virus'] ?? $scan['message']) . ' en ' . ($name !== '' ? $name : basename($path))
                . ($error === null ? ' (aceptado por onerror=accept)' : ''));
        }
        if ($scan['status'] === 'FOUND' && is_file($path)) {
            @unlink($path);
        }
        return $error;
    }

    /**
     * Decide si una subida se rechaza según el resultado del análisis y la política ante fallas.
     *
     * @param array{status: string, virus?: ?string, message?: string} $scan resultado de scanFile()
     * @param string $onerror 'reject' (por omisión) o 'accept' cuando clamd no pudo analizar
     * @return string|null mensaje de rechazo o null si se acepta
     */
    public static function decide(array $scan, string $onerror = 'reject'): ?string
    {
        if ($scan['status'] === 'OK') {
            return null;
        }
        if ($scan['status'] === 'FOUND') {
            return 'Archivo rechazado por el antivirus: ' . ($scan['virus'] ?? 'amenaza detectada') . '.';
        }
        if ($onerror === 'accept') {
            return null;
        }
        // El límite de tamaño se explica tal cual; los errores de conexión no exponen el servidor al usuario.
        $message = (string) ($scan['message'] ?? '');
        return str_starts_with($message, 'El archivo excede')
            ? $message
            : 'No se pudo analizar el archivo con el antivirus; intente más tarde.';
    }

    /**
     * Convierte $config['clamav'] (arreglo o documento de MongoDB) en arreglo.
     *
     * @param mixed $value
     * @return array<string, mixed>
     */
    public static function configOptions($value): array
    {
        if (is_object($value)) {
            $value = function_exists('mongoToArray') ? mongoToArray($value) : (array) $value;
        }
        return is_array($value) ? $value : [];
    }

    /**
     * Limpia y completa las opciones con sus valores por omisión (sin validar el host).
     *
     * @param array<string, mixed> $options
     * @return array{host: string, port: int, timeout: int, maxsize: int}
     */
    public static function normalize(array $options): array
    {
        $int = static fn($v, int $default): int => is_numeric($v) && (int) $v > 0 ? (int) $v : $default;
        return [
            'host' => is_string($options['host'] ?? null) ? trim($options['host']) : '',
            'port' => $int($options['port'] ?? null, self::DEFAULT_PORT),
            'timeout' => $int($options['timeout'] ?? null, 10),
            'maxsize' => $int($options['maxsize'] ?? null, 25),
        ];
    }

    /**
     * Valida las opciones capturadas en el panel. Devuelve el mensaje de error o '' si son correctas.
     *
     * @param array<string, mixed> $options host, port, timeout y maxsize como llegan del formulario
     * @param bool $requireHost exige el servidor (al habilitar o al probar)
     * @return string
     */
    public static function validationError(array $options, bool $requireHost = true): string
    {
        $text = static fn(string $k): string => is_scalar($options[$k] ?? null) ? trim((string) $options[$k]) : '';
        $errors = [];
        $host = $text('host');
        if ($host === '') {
            if ($requireHost) {
                $errors[] = 'Indique el servidor de ClamAV.';
            }
        } elseif (!filter_var(trim($host, '[]'), FILTER_VALIDATE_IP)
            && !preg_match('/^(?=.{1,253}$)[A-Za-z0-9]([A-Za-z0-9\-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9\-]{0,61}[A-Za-z0-9])?)*$/', $host)) {
            $errors[] = 'El servidor debe ser un nombre de host o una IP, sin tcp:// ni puerto.';
        }
        $ranges = ['port' => ['El puerto', 1, 65535], 'timeout' => ['El tiempo de espera', 1, 120], 'maxsize' => ['El tamaño máximo', 1, 4095]];
        foreach ($ranges as $key => [$label, $min, $max]) {
            $value = $text($key);
            if ($value !== '' && (!ctype_digit($value) || (int) $value < $min || (int) $value > $max)) {
                $errors[] = "$label debe ser un entero entre $min y $max.";
            }
        }
        return implode('<br>', $errors);
    }

    /**
     * Interpreta la respuesta de clamd a un análisis ("stream: OK", "stream: Eicar-Signature FOUND",
     * "INSTREAM size limit exceeded. ERROR").
     *
     * @param string $raw respuesta tal como llega (puede traer NUL o salto de línea al final)
     * @return array{status: 'OK'|'FOUND'|'ERROR', virus: ?string, message: string}
     */
    public static function parseResponse(string $raw): array
    {
        $line = trim(rtrim($raw, "\0\r\n"));
        // Con IDSESSION la respuesta trae un número de petición al inicio: "1: stream: OK".
        $line = (string) preg_replace('/^\d+:\s*/', '', $line);
        if (preg_match('/^(?:.*?:\s*)?OK$/', $line)) {
            return ['status' => 'OK', 'virus' => null, 'message' => $line];
        }
        if (preg_match('/^(?:.*?:\s*)?(.+?)\s+FOUND$/', $line, $m)) {
            return ['status' => 'FOUND', 'virus' => $m[1], 'message' => $line];
        }
        $message = (string) preg_replace('/\s*ERROR$/', '', $line);
        return ['status' => 'ERROR', 'virus' => null, 'message' => $message !== '' ? $message : 'Respuesta vacía de clamd.'];
    }

    /**
     * Firma de prueba EICAR: un archivo inofensivo que todo antivirus reporta como virus.
     * Se arma al vuelo para que el propio código fuente no sea detectado.
     *
     * @return string
     */
    public static function eicar(): string
    {
        return 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$' . 'EICAR-STANDARD-ANTIVIRUS' . '-TEST-FILE!$H+H*';
    }

    /**
     * Comprueba que clamd responde (PING → PONG).
     *
     * @return bool
     */
    public function ping(): bool
    {
        try {
            return $this->command('PING') === 'PONG';
        } catch (RuntimeException $e) {
            return false;
        }
    }

    /**
     * Versión del motor y de las firmas, p. ej. "ClamAV 1.0.7/27100/Mon Nov 23 09:00:00 2026".
     *
     * @return string
     * @throws RuntimeException si no hay conexión
     */
    public function version(): string
    {
        return $this->command('VERSION');
    }

    /**
     * Analiza un archivo local enviándolo por la conexión (el archivo no necesita ser legible por clamd).
     *
     * @param string $path ruta del archivo
     * @return array{status: 'OK'|'FOUND'|'ERROR', virus: ?string, message: string}
     */
    public function scanFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return ['status' => 'ERROR', 'virus' => null, 'message' => 'No se puede leer el archivo.'];
        }
        $handle = fopen($path, 'rb');
        try {
            return $this->instream($handle, (int) filesize($path));
        } finally {
            fclose($handle);
        }
    }

    /**
     * Analiza el contenido de una cadena (p. ej. un archivo generado en memoria).
     *
     * @param string $data contenido a analizar
     * @return array{status: 'OK'|'FOUND'|'ERROR', virus: ?string, message: string}
     */
    public function scanString(string $data): array
    {
        $handle = fopen('php://memory', 'w+b');
        fwrite($handle, $data);
        rewind($handle);
        try {
            return $this->instream($handle, strlen($data));
        } finally {
            fclose($handle);
        }
    }

    /**
     * Envía un flujo con INSTREAM: bloques de 4 bytes de longitud (big endian) + datos, y un bloque vacío.
     *
     * @param resource $handle flujo abierto para lectura
     * @param int $size bytes que se enviarán
     * @return array{status: 'OK'|'FOUND'|'ERROR', virus: ?string, message: string}
     */
    private function instream($handle, int $size): array
    {
        if ($size > $this->options['maxsize'] * 1048576) {
            return ['status' => 'ERROR', 'virus' => null, 'message' => 'El archivo excede el tamaño máximo configurado (' . $this->options['maxsize'] . ' MB).'];
        }
        try {
            $socket = $this->connect();
            try {
                $this->write($socket, "zINSTREAM\0");
                while (!feof($handle)) {
                    $chunk = fread($handle, self::CHUNK);
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    $this->write($socket, pack('N', strlen($chunk)) . $chunk);
                }
                $this->write($socket, pack('N', 0));
                return self::parseResponse($this->read($socket));
            } finally {
                fclose($socket);
            }
        } catch (RuntimeException $e) {
            return ['status' => 'ERROR', 'virus' => null, 'message' => $e->getMessage()];
        }
    }

    /**
     * Envía un comando simple y devuelve la respuesta sin el NUL final.
     *
     * @param string $command PING, VERSION, RELOAD…
     * @return string
     * @throws RuntimeException
     */
    private function command(string $command): string
    {
        $socket = $this->connect();
        try {
            $this->write($socket, 'z' . $command . "\0");
            return trim(rtrim($this->read($socket), "\0"));
        } finally {
            fclose($socket);
        }
    }

    /**
     * @return resource
     * @throws RuntimeException
     */
    private function connect()
    {
        $host = $this->options['host'];
        if ($host === '') {
            throw new RuntimeException('ClamAV no tiene servidor configurado.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $host = '[' . $host . ']';
        }
        $socket = @stream_socket_client('tcp://' . $host . ':' . $this->options['port'], $errno, $errstr, $this->options['timeout']);
        if ($socket === false) {
            throw new RuntimeException('No se pudo conectar a clamd en ' . $host . ':' . $this->options['port'] . ': ' . ($errstr !== '' ? $errstr : 'error ' . $errno));
        }
        stream_set_timeout($socket, $this->options['timeout']);
        return $socket;
    }

    /**
     * @param resource $socket
     * @param string $data
     * @return void
     * @throws RuntimeException
     */
    private function write($socket, string $data): void
    {
        while ($data !== '') {
            $written = @fwrite($socket, $data);
            if ($written === false || $written === 0) {
                // clamd cierra la conexión al rebasar StreamMaxLength; su respuesta explica el motivo.
                $reply = @fread($socket, 1024);
                throw new RuntimeException($reply ? self::parseResponse($reply)['message'] : 'clamd cerró la conexión.');
            }
            $data = substr($data, $written);
        }
    }

    /**
     * Lee hasta el NUL final o el cierre de la conexión.
     *
     * @param resource $socket
     * @return string
     * @throws RuntimeException
     */
    private function read($socket): string
    {
        $response = '';
        while (!feof($socket) && !str_contains($response, "\0")) {
            $part = fread($socket, 4096);
            if ($part === false || ($part === '' && stream_get_meta_data($socket)['timed_out'])) {
                throw new RuntimeException('clamd no respondió en ' . $this->options['timeout'] . ' s.');
            }
            $response .= $part;
        }
        if ($response === '') {
            throw new RuntimeException('clamd cerró la conexión sin responder.');
        }
        return $response;
    }
}
