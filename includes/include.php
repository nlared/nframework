<?php
/**
 * Las cabeceras X-Forwarded-* solo se aceptan si la petición llega desde un proxy de confianza
 * (red privada/loopback o una IP listada en $config['trusted_proxies']); de lo contrario
 * cualquier cliente podría falsificar su IP y evadir las listas negras y el límite de intentos.
 */
function isTrustedProxy(array $trustedProxies = []): bool
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
        return false;
    }
    foreach ($trustedProxies as $proxy) {
        if (nfIpMatches($remoteAddr, $proxy)) {
            return true;
        }
    }
    return !filter_var($remoteAddr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

function normalizeRequestScheme(bool $trustedProxy): void
{
    if ($trustedProxy && !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $_SERVER['REQUEST_SCHEME'] = str_replace('http', 'https', $_SERVER['REQUEST_SCHEME'] ?? 'https');
        $_SERVER['SERVER_PROTOCOL'] = str_replace('HTTP', 'HTTPS', $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1');
        $_SERVER['HTTPS'] = 'on';
    }
}

function resolveClientIp(bool $trustedProxy): string
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!$trustedProxy) {
        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '127.0.0.1';
    }

    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        $ipList = array_map('trim', explode(',', $forwarded));
        foreach ($ipList as $candidate) {
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
    }

    $realIp = $_SERVER['HTTP_X_REAL_IP'] ?? '';
    if ($realIp !== '' && filter_var($realIp, FILTER_VALIDATE_IP)) {
        return $realIp;
    }

    return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '127.0.0.1';
}

if (php_sapi_name() != 'cli') {
    if (empty($_SERVER['HTTP_USER_AGENT'])) {
        http_response_code(403);
        exit("Access denied.");
    }
}
require __DIR__ . '/vendor/autoload.php';

// Backward compatibility for legacy serialized/content references.
if (!class_exists(\PhpOffice\PhpWord\Element\Paragraph::class) && class_exists(\PhpOffice\PhpWord\Element\TextRun::class)) {
    class_alias(\PhpOffice\PhpWord\Element\TextRun::class, \PhpOffice\PhpWord\Element\Paragraph::class);
}

require __DIR__ . '/functions.php';
require __DIR__ . '/class.UIManager.php';

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

use Dompdf\Dompdf;
use Dompdf\Options;

class nFrameworkException extends Exception
{
    public function errorMessage()
    {
        // Error message
        return "Error [{$this->getCode()}]: {$this->getMessage()} at {$this->getFile()}:{$this->getLine()}";
    }
}


class class_config implements ArrayAccess
{
    private array $contenedor;
    private array $fileConfig;

    public function __construct()
    {
        require __DIR__ . '/config.php';
        $this->contenedor = (array) $config;
        $this->fileConfig = $this->contenedor;

        $this->contenedor['images']['config']['logo'] = (empty($this->contenedor['image']) ? 'https://www.nlared.com/img/nlaredlogo5.png' : $this->contenedor['image']);
        if (empty($this->contenedor['users']['algos'])) {
            $this->contenedor['users']['algos'] = ['sha512'];
        }

        if (empty($this->contenedor['users']['collection'])) {
            $this->contenedor['users']['collection'] = 'users';
        }
    }

    public function loadfromdb(): void
    {
        global $m;
        $db = $this->contenedor['sitedb'];
        // Una sola consulta para ambos documentos, guardada en la caché local (ver nfCacheRemember).
        $ttl = isset($this->contenedor['cache_ttl']) ? max(0, (int) $this->contenedor['cache_ttl']) : 60;
        [$dbconf, $themeconf] = nfCacheRemember('configs', $ttl, function () use ($m, $db) {
            $docs = ['site' => [], 'theme' => []];
            foreach ($m->{$db}->configs->find(['_id' => ['$in' => ['site', 'theme']]]) as $doc) {
                $docs[$doc['_id']] = mongoToArray($doc);
            }
            return [$docs['site'], $docs['theme']];
        });
        $conf = array_merge($this->contenedor, $dbconf);
        // Las listas se suman: lo definido en config.php (infraestructura) no se puede quitar desde el panel.
        foreach (['trusted_proxies', 'allowed_redirect_hosts'] as $listKey) {
            $conf[$listKey] = array_values(array_unique(array_merge(
                nfConfigList($this->contenedor[$listKey] ?? []),
                nfConfigList($dbconf[$listKey] ?? [])
            )));
        }
        $conf['theme'] = $themeconf;
        if (empty($conf['session_key'])) {
            // Clave para cifrar los tokens de redirección entre sitios; se genera una sola vez.
            $conf['session_key'] = bin2hex(random_bytes(32));
            $m->{$this->contenedor['sitedb']}->configs->updateOne(
                ['_id' => 'site'],
                ['$set' => ['session_key' => $conf['session_key']]],
                ['upsert' => true]
            );
            nfCacheForget('configs');
        }
        if (empty($conf['manifest']['theme_color'])) {
            $conf['manifest']['theme_color'] = '#1ba1e2';
        }
        if (empty($conf['manifest']['background_color'])) {
            $conf['manifest']['background_color'] = '#ffffff';
        }

        $this->contenedor = $conf;
    }

    /**
     * Valor tal como está en config.php (sin lo que venga de la base de datos).
     */
    public function fileValue(string $key): mixed
    {
        return $this->fileConfig[$key] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $valor): void
    {

        if (is_null($offset)) {
            $this->contenedor[] = $valor;
        } else {
            $this->contenedor[$offset] = $valor;
        }
    }

    public function offsetExists($offset): bool
    {
        return isset($this->contenedor[$offset]);
    }

    public function offsetUnset($offset): void
    {
        unset($this->contenedor[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return isset($this->contenedor[$offset]) ? $this->contenedor[$offset] : null;
    }
}

$config = new class_config;

class class_nframework
{
    public string $title;
    public string $image;
    public array $language;
    public bool $isAjax = false;
    public bool $https = false;
    public string $lang;
    public string $lang_;
    public string $langshort;
    public array $languages;
    public $shutdown;



    // public String $language;
    private array $config;
    private array $counters = [];
    public array $errores = [];
    public array $csss = [];
    public array $jss = [];
    public array $javas = [];
    public array $javasonce = [];
    public array $docend = [];
    public bool $usecommon = false;
    public string $include_path;
    public string $api_path;
    public string $body_addtag = '';
    public string $html_addtag = '';
    public array $onces = [];
    public UIManager $ui;

    // Explicitly declare optional runtime properties to avoid "Undefined property" errors
    public ?string $etag = null;
    public ?int $lastmodified = null;
    public ?int $expiretime = null;
    /** Cache-Control public (CDN y proxies pueden guardar la respuesta) en lugar de private. */
    public bool $cachepublic = false;
    /** La respuesta ya se envió directamente (serveFile/serveContent/304): nfshutdown no la procesa. */
    public bool $streamed = false;
    public int $streamedBytes = 0;
    public array $metas = [];

    public function __construct()
    {
        $this->shutdown = true;
        $this->include_path = __DIR__;
        $this->api_path = $_SERVER['DOCUMENT_ROOT'] . '/nframework';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            if ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
                $this->https = true;
            }
        } else {
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
                $this->https = true;
            }
        }
        $this->ui = new UIManager();

        if (
            isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        ) {
            $this->isAjax = true;
            //	$this->usecommon=false;
        } else {

            $this->csss = [
                '004' => 'https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.min.css',
                '050' => 'https://cdn.nlared.com/metro4/metro.min.css',
                '051' => 'https://cdn.nlared.com/metro4/icons.min.css',
                '100' => 'https://cdn.nlared.com/nframework/4.5.1/nframework.min.css',
            ];

            $this->jss = [
                '000' => '/main.js',
                '001' => 'https://ajax.aspnetcdn.com/ajax/jQuery/jquery-3.7.1.min.js',
                '004' => 'https://cdn.jsdelivr.net/npm/sweetalert2@11.7.5/dist/sweetalert2.all.min.js',
                '050' => 'https://cdn.nlared.com/metro4/metro.min.js',
                '100' => 'https://cdn.nlared.com/nframework/4.5.1/nframework.min.js',
            ];

            $this->csss['050'] = 'https://cdn.metroui.org.ua/5.1.20/metro.css';
            $this->csss['051'] = 'https://cdn.metroui.org.ua/5.1.20/icons.css';
            $this->csss['200'] = '/nframework/templates/panda/css.css';


            $this->jss['050'] = 'https://cdn.metroui.org.ua/5.1.20/metro.js';
            $this->jss['100'] = 'https://cdn.nlared.com/nframework/6.0.1/nframework.min.js';

            /*
                $this->csss['050']='https://cdn.nlared.com/metrodev/metro.css';
                $this->csss['051']='https://cdn.nlared.com/metrodev/icons.css';
                $this->jss['050']='https://cdn.nlared.com/metrodev/metro.js';
                $this->jss['100']='https://cdn.nlared.com/nframework/4.5.1/nframework.js';
            //*/
        }
    }

    public function addfileupload()
    {
        $this->jss['049'] = 'https://cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/10.32.0/js/jquery.fileupload.min.js';
        $this->csss['049'] = 'https://cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/10.32.0/css/jquery.fileupload.min.css';
    }

    public function addjqueryui()
    {
        $this->jss['002'] = 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.14.0/jquery-ui.min.js';
        $this->csss['000'] = 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.14.0/themes/smoothness/jquery-ui.min.css';
    }

    public function getAuthorizationHeader(): string
    {
        $headers = '';
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) { // Nginx or fast CGI
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            // print_r($requestHeaders);
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }

        return $headers;
    }

    public function counters(string $v): int
    {
        if (!array_key_exists($v, $this->counters)) {
            $this->counters[$v] = 0;
        } else {
            $this->counters[$v]++;
        }

        return $this->counters[$v];
    }

    public function themeSwitcher(): ThemeSwitcher
    {
        return new ThemeSwitcher();
    }

    public function isAjax(): bool
    {
        return $this->isAjax;
    }

    public function loadBrowserInfo(): void
    {
        require 'Browser.php'; // TODO: composer
        $b = new Browser;
        $_SESSION['nf']['browser'] = [
            'browser' => $b->getBrowser(),
            'version' => $b->getVersion(),
            'platform' => $b->getPlatform(),
            'mobile' => $b->isMobile(),
        ];
        $languages = [
            'es' => 'es-MX',
            'es-ES' => 'es-MX',
            'es-MX' => 'es-MX',
            'en-US' => 'en-US',
            'en' => 'en-US',
        ];
        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $_SESSION['nf']['browser']['language'] = 'en-US';
        } else {
            $_SESSION['nf']['browser']['language'] = $languages[Locale::lookup(array_keys($languages), $_SERVER['HTTP_ACCEPT_LANGUAGE'], true, 'en-US')];
        }
        $_SESSION['nf']['Anti-CSRF'] = bin2hex(random_bytes(16));
    }

    public function excelOut($spreadsheet, $filename)
    {
        $filename = clean_filename($filename);
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        $writer->save('php://output');
    }

    public function excelOutPdf($spreadsheet, $filename, $converter = 'Dompdf', $disposition = 'inline') // attachment
    {
        $filename = clean_filename($filename);
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '.pdf"');
        if ($converter == 'Dompdf') {
            // PhpSpreadsheet registra su propio writer 'Dompdf' (Settings de PhpWord no aplica aquí).
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Dompdf');
            $writer->save('php://output');
        }
        if ($converter == 'unoconv') {
            $tmpfname = tempnam(sys_get_temp_dir(), 'xlsxpdf');
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($tmpfname . '.xlsx'); // This line will force the file to download
            shell_exec('unoconv -f pdf ' . escapeshellarg($tmpfname . '.xlsx'));
            $size = filesize($tmpfname . '.pdf');
            header("Content-length: $size");
            readfile($tmpfname . '.pdf');
            unlink($tmpfname . '.xlsx');
            unlink($tmpfname . '.pdf');
        }
    }

    public function wordOut($word, $filename)
    {
        $filename = clean_filename($filename);
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: inline; filename="' . $filename . '.docx"');
        $word->save('php://output');
    }

    public function wordOutPdf($word, $filename)
    {
        global $config;
        $filename = clean_filename($filename);
        $tmpfname = null;
        if ($config['word_pdf_converter'] == 'Dompdf' || empty($config['word_pdf_converter'])) {
            Settings::setPdfRendererName(Settings::PDF_RENDERER_DOMPDF);
            // Optional since PHPWord can usually locate it via Composer autoload,
            // but harmless to set explicitly:
            Settings::setPdfRendererPath(__DIR__ . '/vendor/dompdf/dompdf');

            header("Content-type: application/pdf; charset=utf-8");
            header('Content-Disposition: inline; filename="' . $filename . '.pdf"');
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($word, 'PDF');
            $writer->save('php://output');
        } else {
            $tmpfname = tempnam(sys_get_temp_dir(), 'docxtpdf');
            $word->saveAs($tmpfname . '.docx');
            if ($config['word_pdf_converter'] == 'unoconv') {
                shell_exec('unoconv -f pdf ' . escapeshellarg($tmpfname . '.docx'));
            } else {
                shell_exec("unoconv -f pdf --connection 'socket,host=127.0.0.1,port=2002;urp;' " . escapeshellarg($tmpfname . '.docx'));
            }
        }
        if ($tmpfname === null) {
            return;
        }
        if (file_exists($tmpfname . '.pdf')) {
            header("Content-type: application/pdf; charset=utf-8");
            header('Content-Disposition: inline; filename="' . $filename . '.pdf"');
            $size = filesize($tmpfname . '.pdf');
            header("Content-length: $size");
            readfile($tmpfname . '.pdf');
            unlink($tmpfname . '.pdf');
        }
        if (file_exists($tmpfname . '.docx')) {
            unlink($tmpfname . '.docx');
        }
        @unlink($tmpfname);
    }
    public function wordTemplateOutPdf(PhpOffice\PhpWord\TemplateProcessor $template, $filename)
    {
        global $config;
        $filename = clean_filename($filename);
        $tmpfname = tempnam(sys_get_temp_dir(), 'templatepdf');
        $template->saveAs($tmpfname . '.docx');
        // Las plantillas se convierten con unoconv salvo que el sitio pida Dompdf explícitamente
        // (Dompdf pierde buena parte del formato de las plantillas).
        $converter = empty($config['word_pdf_converter']) ? 'unoconv' : $config['word_pdf_converter'];
        if ($converter == 'Dompdf') {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($tmpfname . '.docx');
            $this->wordOutPdf($phpWord, $filename);
        } else {
            if ($converter == 'unoconv') {
                shell_exec('unoconv -f pdf ' . escapeshellarg($tmpfname . '.docx'));
            } else {
                shell_exec("unoconv -f pdf --connection 'socket,host=127.0.0.1,port=2002;urp;StarOffice.ComponentContext' " . escapeshellarg($tmpfname . '.docx'));
            }
        }
        if (file_exists($tmpfname . '.pdf')) {
            header("Content-type: application/pdf; charset=utf-8");
            header('Content-Disposition: inline; filename="' . $filename . '.pdf"');
            $size = filesize($tmpfname . '.pdf');
            header("Content-length: $size");
            readfile($tmpfname . '.pdf');
            unlink($tmpfname . '.pdf');
        }
        if (file_exists($tmpfname . '.docx')) {
            unlink($tmpfname . '.docx');
        }
        @unlink($tmpfname);
    }
    /**
     * Si el navegador ya tiene esta versión (If-None-Match con el ETag, o If-Modified-Since sin
     * ETag) responde 304 sin cuerpo y termina. Asigne antes etag y/o lastmodified.
     */
    public function testcache(): void
    {
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
        $match = false;
        if ($ifNoneMatch !== null) {
            // Puede ser una lista ("a", W/"b") o "*"; si viene, If-Modified-Since se ignora (RFC 9110).
            if ($this->etag !== null) {
                foreach (explode(',', $ifNoneMatch) as $tag) {
                    $tag = trim($tag);
                    if ($tag === '*' || trim(str_starts_with($tag, 'W/') ? substr($tag, 2) : $tag, '"') === $this->etag) {
                        $match = true;
                        break;
                    }
                }
            }
        } elseif ($this->lastmodified !== null && isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $since = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
            $match = $since !== false && $this->lastmodified <= $since;
        }
        if ($match) {
            http_response_code(304);
            $this->sendCacheHeaders();
            $this->startStream();
            exit();
        }
    }

    /**
     * Cabeceras de caché según etag, lastmodified, expiretime y cachepublic:
     *  - expiretime: el navegador reutiliza la respuesta sin preguntar hasta esa hora.
     *  - solo etag/lastmodified: la guarda pero la revalida siempre (304 si no cambió).
     *  - nada: no se guarda (no-store).
     */
    public function sendCacheHeaders(): void
    {
        if ($this->etag !== null) {
            header('ETag: "' . $this->etag . '"');
        }
        if ($this->lastmodified !== null) {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $this->lastmodified) . ' GMT');
        }
        $scope = $this->cachepublic ? 'public' : 'private';
        if ($this->expiretime !== null) {
            header('Expires: ' . gmdate('D, d M Y H:i:s', $this->expiretime) . ' GMT');
            header('Cache-Control: ' . $scope . ', max-age=' . max(0, $this->expiretime - time()));
            header_remove('Pragma');
        } elseif ($this->etag !== null || $this->lastmodified !== null) {
            // session_start() envía Expires/Pragma de "nocache"; se quitan para que el navegador guarde la respuesta.
            header('Cache-Control: ' . $scope . ', no-cache');
            header_remove('Expires');
            header_remove('Pragma');
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
        }
    }

    /**
     * Envía un archivo con caché HTTP: ETag y Last-Modified a partir de la fecha y tamaño, 304 si
     * el navegador ya lo tiene, y el contenido directo al cliente (sin cargarlo en memoria ni pasar
     * por el buffer del framework).
     * $maxAge: segundos que el navegador lo usa sin preguntar; 0 = revalida siempre; null = sin caché.
     */
    public function serveFile(string $path, string $mime, ?int $maxAge = 0, bool $public = false): void
    {
        clearstatcache(true, $path);
        $size = (int) filesize($path);
        if ($maxAge !== null) {
            $this->lastmodified = (int) filemtime($path);
            $this->etag = md5($path . '|' . $this->lastmodified . '|' . $size);
            $this->expiretime = $maxAge > 0 ? time() + $maxAge : null;
            $this->cachepublic = $public;
            $this->testcache();
        }
        $this->sendCacheHeaders();
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $size);
        $this->startStream();
        $this->streamedBytes = $size;
        readfile($path);
    }

    /**
     * Igual que serveFile() para contenido generado (JS, JSON, XML): el ETag es el hash del contenido.
     */
    public function serveContent(string $content, string $mime, ?int $maxAge = 0, bool $public = false): void
    {
        if ($maxAge !== null) {
            $this->etag = md5($content);
            $this->expiretime = $maxAge > 0 ? time() + $maxAge : null;
            $this->cachepublic = $public;
            $this->testcache();
        }
        $this->sendCacheHeaders();
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($content));
        $this->startStream();
        $this->streamedBytes = strlen($content);
        echo $content;
    }

    /**
     * Descarta el buffer del framework: lo que siga se envía tal cual y nfshutdown no lo procesa.
     */
    private function startStream(): void
    {
        header('X-Content-Type-Options: nosniff');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $this->streamed = true;
    }

    public function downloadfrom($filename): void
    {
        header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        if (file_exists($filename)) {
            header('Content-Description: File Transfer');
            header('Content-Type: ' . mime_content_type($filename));
            header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
            header('Content-Length: ' . filesize($filename));
            readfile($filename);
            exit;
        } else {
            throw new nFrameworkException("File not found: " . $filename);
        }
    }

    public function language()
    {
        return $this->languages[$this->lang];
    }
}
$nframework = new class_nframework;
require 'class.Base.php';
try {
    $m = new MongoDB\Client($config['mongo_connection_string']);

    $config->loadfromdb();
} catch (Exception $e) {
    error_log('nframework: ' . $e->getMessage());
    if (php_sapi_name() == 'cli') {
        echo 'Excepción capturada: ', $e->getMessage(), "\n";
    } else {
        http_response_code(503);
        exit('Servicio no disponible.');
    }
}
if (!defined('SESSION_KEY')) {
    define('SESSION_KEY', (string) $config['session_key']);
}

// Después de loadfromdb(): trusted_proxies puede venir de config.php y/o del panel (Admin → Seguridad).
$nfTrustedProxy = isTrustedProxy(nfConfigList($config['trusted_proxies'] ?? []));
normalizeRequestScheme($nfTrustedProxy);
$ip = resolveClientIp($nfTrustedProxy);
$nframework->https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off';
$block_reason = '';
if (isset($config['security_user_agents_blacklist'])) {
    $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
    foreach ($config['security_user_agents_blacklist'] as $bot) {
        if (str_contains($userAgent, $bot) !== false) {
            $block_reason = 'User agent is blacklisted';
        }
    }
}
/*
if(isset($config['security_ip_ranges_blacklist'])){	
    foreach($config['security_ip_ranges_blacklist'] as $tmp){    
        if(ip_in_range($ip,$tmp['from'],$tmp['to'])){    http_response_code(403);
            exit("Access denied.");
        }
    }
}*/
if (isset($config['security_ip_blacklist'])) {
    foreach ($config['security_ip_blacklist'] as $tmp) {
        if ($ip == $tmp['ip']) {
            if (!empty($tmp['end'])) {
                if (time() < $tmp['end']->toDateTime()->getTimestamp()) {
                    $block_reason = 'IP is blacklisted until ' . $tmp['end']->toDateTime()->format('Y-m-d H:i:s');
                } else {
                    // remove expired IP from blacklist
                    $m->{$config['sitedb']}->configs->updateOne(['_id' => 'site'], ['$pull' => ['security_ip_blacklist' => ['ip' => $tmp['ip']]]]);
                    nfCacheForget('configs');
                }
            } else {
                $block_reason = 'IP is blacklisted';
            }
        }
    }
}
if (isset($config['security_host_blacklist'])) {
    foreach ($config['security_host_blacklist'] as $tmp) {
        if ($_SERVER['HTTP_HOST'] == $tmp['host']) {
            $block_reason = 'Host is blacklisted';
        }
    }
}
if (isset($config['security_path_blacklist'])) {
    foreach ($config['security_path_blacklist'] as $tmp) {
        if ($_SERVER['REQUEST_URI'] == $tmp['path']) {
            $block_reason = 'Path is blacklisted';
        }
    }
}

if (!empty($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

/**
 * Las estadísticas (nfuristats) se escriben sin esperar confirmación de MongoDB (w=0):
 * así no suman una ida y vuelta al servidor en cada petición. El _id se genera aquí.
 */
function nfStatOptions(): array
{
    static $options = null;
    return $options ??= ['writeConcern' => new MongoDB\Driver\WriteConcern(0)];
}
function nfStatUpdate(array $set): void
{
    global $m, $config, $nfStatId;
    try {
        $m->{$config['sitedb']}->nfuristats->updateOne(['_id' => $nfStatId], ['$set' => $set], nfStatOptions());
    } catch (Throwable $e) {
        error_log('nframework nfuristats: ' . $e->getMessage());
    }
}

// Datos de la petición: se guardan en nfuristats y contra ellos se evalúan las reglas de seguridad.
$nfRequestDoc = [
    'ip' => $ip,
    'host' => $_SERVER['HTTP_HOST'] ?? '',
    'path' => $_SERVER['REQUEST_URI'] ?? '',
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
];
$nfStatId = new MongoDB\BSON\ObjectId();
$eventAt = new MongoDB\BSON\UTCDateTime(time() * 1000);
try {
    $m->{$config['sitedb']}->nfuristats->insertOne(
        ['_id' => $nfStatId, 'created_at' => $eventAt, 'createdAt' => $eventAt] + $nfRequestDoc
            + ['block_reason' => $block_reason] + ($block_reason != '' ? ['status_code' => 403] : []),
        nfStatOptions()
    );
} catch (Throwable $e) {
    error_log('nframework nfuristats: ' . $e->getMessage());
}
if ($block_reason != "") {
    http_response_code(403);
    exit("Access denied.");
}

/*
 * Reglas de Admin → Seguridad: una petición que coincide con alguna es sospechosa. Se evalúan
 * aquí en PHP (matchesQuery) y solo para las sospechosas se cuentan en MongoDB las de esta IP
 * dentro de la ventana; más de 10 bloquean la IP durante windowSeconds.
 */
$nfSecurityRules = nfCacheRemember('securityrules', nfCacheTtl(), function () use ($m, $config) {
    $rules = [];
    foreach ($m->{$config['sitedb']}->nfsecurityrules->find(['enabled' => true, 'rule' => ['$ne' => null]]) as $rule) {
        $query = json_decode((string) $rule->rule, true);
        if (is_array($query) && $query !== []) {
            $rules[] = fixSingleQuery($query);
        }
    }
    return $rules;
});
$nfSuspicious = false;
foreach ($nfSecurityRules as $rule) {
    if (matchesQuery($nfRequestDoc, $rule)) {
        $nfSuspicious = true;
        break;
    }
}
if ($nfSuspicious) {
    $window = (int) ($config['windowSeconds'] ?? 900);
    // Las anteriores de esta IP + la actual (escrita sin confirmación, puede no verse todavía).
    $attempts = 1 + $m->{$config['sitedb']}->nfuristats->countDocuments([
        '_id' => ['$ne' => $nfStatId],
        'ip' => $ip,
        'created_at' => ['$gt' => new MongoDB\BSON\UTCDateTime((time() - $window) * 1000)],
        '$or' => $nfSecurityRules,
    ]);
    if ($attempts > 10) {
        $m->{$config['sitedb']}->configs->updateOne(['_id' => 'site'], ['$addToSet' => ['security_ip_blacklist' => [
            'ip' => $ip,
            'end' => new MongoDB\BSON\UTCDateTime((time() + $window) * 1000),
        ]]]);
        nfCacheForget('configs');
        nfStatUpdate(['block_reason' => 'IP is blacklisted', 'status_code' => 403]);
        http_response_code(403);
        exit("Access denied.");
    }
}

// CSRF: un POST/PUT/DELETE originado en otro sitio se rechaza antes de llegar a cualquier página.
if (php_sapi_name() != 'cli' && !nfIsTrustedRequestOrigin()) {
    nfStatUpdate([
        'csrf_rejected' => true,
        'origin' => (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? ''),
        'status_code' => 403,
    ]);
    http_response_code(403);
    exit("Access denied.");
}

$nframework->title = (!empty($config['title']) ? $config['title'] : 'nframework 5');
$nframework->image = (!empty($config['image']) ? $config['image'] : '/images/config///logo.png');

function toMongoId($item): ObjectId
{
    return new ObjectId($item);
}
function toMongoIds(array $items): array
{
    $r = [];
    // return array_map('toMongoId',$items);
    foreach ($items as $item) {
        $r[] = toMongoId($item);
    }

    return $r;
}
// error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
define('E_FATAL', E_ERROR | E_USER_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);

function nferrorhandler(int $errno, string $errstr, string $errfile, int $errline, array $errcontext = []): bool
{
    global $developermode, $m, $nframework, $config;
    if (!$developermode) {
        if ($errno ^ E_NOTICE && $errno ^ E_WARNING) {

            $result = $m->{$config['sitedb']}->errorlog->updateOne([
                'desc' => $errstr,
            ], [
                '$inc' => ['tries' => 1],
                '$set' => ['lasttime' => date('Y-m-d H:i:s')],
                '$setOnInsert' => ['type' => $errno, 'file' => $errfile, 'number' => $errline],
            ], ['upsert' => true]);
            if ($errno & E_FATAL) {
                http_response_code(200);
                echo 'ocurrio una incidencia en el programa, reportando el problema para su solucion, disculpe las molestias ';

                if (isset($result->upserted)) {
                    /*$mail = new PHPMailer();
                    $mail->isSMTP();                                      // Set mailer to use SMTP
                       $mail->Host=$config['mailhost'];
                    $mail->Port=$config['mailport'];
                    $mail->SMTPAuth=$config['mailsmtpauth'];
                    $mail->Username=$config['mailusername'];
                    $mail->Password=$config['mailpassword'];
                    $mail->Subject = 'Incidencia critica '.$result['upserted']  ;
                    $mail->From = 'contacto@hmail.nlared.com';
                    $mail->FromName = 'Incidencia critica';
                    $mail->addAddress('quique@nlared.com', 'Enrique Flores'); // Add a recipient
                    $mail->WordWrap = 50;                                 // Set word wrap to 50 characters
                    $mail->IsHTML(true);
                    $mail->Body    = 'A ocurrido una incidencia critica #'.$result['upserted'];
                    $mail->AltBody = 'A ocurrido una incidencia critica #'.$result['upserted'];
                    if(!$mail->send()) {
                       echo 'Error enviando correo';
                    }*/
                }
            }
        }

        //return false;
    } else {
        $nframework->errores[] = [
            'type' => $errno,
            'file' => $errfile,
            'number' => $errline,
            'desc' => $errstr,
            'trace' => implode("\n", array_map(function ($trace) {
                return isset($trace['file'], $trace['line'], $trace['function']) ? "{$trace['file']}({$trace['line']}): {$trace['function']}()" : '';
            }, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS))),
        ];
        //return false;
    }
    if ($errno & E_FATAL) {
        http_response_code(500);
    }
    nfStatUpdate([
        'response_time_ms' => (microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]) * 1000,
        'session_id' => session_id(),
        'size_bytes' => ob_get_length(),
        'status_code' => http_response_code(),
    ]);
    return false;
}
$original = set_error_handler('nferrorhandler');
function nframework_autoload($class_name): bool
{
    // Clases cuyo archivo no sigue la convención class.<Clase>.php
    $aliases = ['inputaddress' => 'address'];
    $class_name = $aliases[strtolower($class_name)] ?? $class_name;
    $ipaths = get_include_path();
    $iarray = array_merge([(string) __DIR__], explode(PATH_SEPARATOR, $ipaths));
    foreach ($iarray as $ipath) {
        if (file_exists($ipath . '/class.' . $class_name . '.php')) {
            require_once $ipath . '/class.' . $class_name . '.php';

            return true;
        }
    }

    return false;
}
spl_autoload_register('nframework_autoload');

if ($config['cookie_domain'] == '') {
    $config['cookie_domain'] = $_SERVER['HTTP_HOST'];
}

use Nlared\MongoSessionHandler;

$sessions = $m->{$config['sitedb']}->sessions;
$handler = new MongoSessionHandler($sessions);
session_set_save_handler($handler);
$cookieName = preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $config['cookie_domain']);
session_name($cookieName !== '' ? $cookieName : 'nframework_session');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $config['cookie_domain'],
    'secure' => (bool) $nframework->https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (empty($_SESSION['nf']['browser']['language'])) {
    $nframework->loadBrowserInfo();
}

// Ensure tracking exists
if (!isset($_SESSION['_gc_tracker'])) {
    $_SESSION['_gc_tracker'] = [];
}

$currentTime = time();
foreach ($_SESSION['_gc_tracker'] as $key => $timestamp) {
    // Remove expired session variables
    if ($currentTime > $timestamp) {
        unsetNestedKey($_SESSION, $key);
        unset($_SESSION['_gc_tracker'][$key]);
    }
}

$nframework->lang = (empty($_SESSION['nf']['browser']['language']) ? 'en-US' : $_SESSION['nf']['browser']['language']);
$nframework->lang_ = str_replace('-', '_', $nframework->lang);
$nframework->langshort = substr($nframework->lang, 0, 2);
require $nframework->include_path . '/i18n/' . $nframework->lang . '.php';
$nframework->language = $nframework->languages[$nframework->lang];
if (!empty($_SESSION['user']) && is_string($_SESSION['user']) && preg_match('/^[a-f\d]{24}$/i', $_SESSION['user'])) {
    $user = new User(['_id' => new MongoDB\BSON\ObjectID($_SESSION['user'])]);
    if (empty($user->_id) || $user->disabled == true) {
        unset($_SESSION['user']);
        if ($user->disabled == true) {
            header('location: /account/disabled.php');
        } else {
            header('location: /'); // expulsar
        }
        exit();
    } else {

        if (empty($user->sessions) || !in_array(session_id(), (array) $user->sessions)) {
            $tmp = (array) $user->sessions;
            $tmp[] = session_id();
            $user->sessions = array_values(array_unique($tmp));
        }
    }

    if ($user->in('developers')) {
        $developermode = true;
    }
    // Un POST del panel puede cambiar configuración, reglas, páginas o menús: se vacía la caché
    // local al terminar la petición, después de que la página guardó.
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && preg_match('#^/(admin|nftables)/#', (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH))
        && $user->in('admins')) {
        register_shutdown_function('nfCacheForget');
    }
} else {
    if (isset($requiresession)) {
        header('Location: /');
        exit();
    } else {
        $user = new User(['username' => 'guest']);
    }
}


$javas = new Javas;
$themeswitcher = new ThemeSwitcher();

function speak($text)
{
    global $javas;
    $javas->addjs('speak(' . json_encode((string) $text) . ');', 'ready');
}
// TODO> Other options
function notify($title = 'nlared.com', $text = '', $options = [])
{
    global $javas;
    $javas->addjs('toast(' . json_encode((string) $text) . ');', 'ready');
}


function nfshutdown()
{
    global $nframework, $noobfuscate, $buffer, $developermode, $javas, $result, $config, $m;
    $last_error = error_get_last();
    if (!empty($last_error) && ($last_error['type'] === E_ERROR || $last_error['type'] === E_USER_ERROR)) {
        nferrorhandler(E_ERROR, $last_error['message'], $last_error['file'], $last_error['line']);
        if ($developermode) {
            if (php_sapi_name() == 'cli') {
                foreach ($nframework->errores as $row) {
                    $result .= '|' . implode('|', $row) . '|';
                }

                return $result;
            } else {
                $datatable = new Table;
                $datatable->header = '<th>Tipo</th><th>Archivo</th><th>Linea</th><th>Descripcion</th><th>Traza</th>';
                $datatable->data = $nframework->errores;
                echo '<link rel="stylesheet" href="https://cdn.metroui.org.ua/v4.3.5/css/metro-all.min.css"/>
<link rel="stylesheet" href="//cdn.nlared.com/datatables.net-responsive-dt/css/responsive.dataTables.min.css"/>
<div class="container"><h4>Developer mode active</h4>' . $datatable . '</div>';
            }
        }
    }

    if ($nframework->streamed) {
        // serveFile/serveContent/304 ya enviaron la respuesta completa.
        nfStatUpdate([
            'response_time_ms' => (microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]) * 1000,
            'session_id' => session_id(),
            'size_bytes' => $nframework->streamedBytes,
            'status_code' => http_response_code(),
        ]);
        return;
    }
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    $javasstr = '';
    $content = '';
    if (count($nframework->javas) > 0) {
        if (empty($noobfuscate)) {
            $packer = new Tholu\Packer\Packer(implode(";\n", $nframework->javas), 'Normal', true, false, true);
            $packed_js = $packer->pack();
            $javasstr .= '
	<script>' . $packed_js . '</script>';
        } else {
            $javasstr .= '
	<script>' . implode(";\n", $nframework->javas) . '</script>';
        }
    }
    $nframework->sendCacheHeaders();
    // header('Content-Language: '.$nframework->lang);
    // header('P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');
    //	if($xframe!='remove')	header('X-Frame-Options: '.$xframe);
    // header('Referrer-Policy ""'); nunca usar
    header('X-Content-Type-Options: nosniff');
    if ($nframework->https && !empty($config['hsts_max_age'])) {
        // Opcional: una vez enviado, el navegador no volverá a aceptar HTTP en este host durante max-age.
        header('Strict-Transport-Security: max-age=' . (int) $config['hsts_max_age']);
    }

    if ($nframework->isAjax()) {
        // Se respeta el código ya fijado (403 de requireGroup, 500 por error fatal, etc.).
        header('Content-Type: application/json');
        $content = json_encode($result);
        // end();
    } else {

        if ($nframework->usecommon) {
            $metas = $nframework->metas;

            // Sync legacy arrays to UIManager
            $nframework->ui->syncFromArrays($nframework->csss, $nframework->jss);

            // Add meta tags to UIManager
            if (isset($metas['title']))
                $nframework->ui->setTitle($metas['title']);

            $defaultMetas = [
                'viewport' => 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no',
                'charset' => 'utf-8',
                'metro4:jquery' => 'true',
                'X-UA-Compatible' => 'IE=edge', // http-equiv
                'google-site-verification' => $config['google-site-verification'] ?? '',
                'Title' => $nframework->title . ' ' . ($metas['title'] ?? ''),
                'Author' => $config['author'] ?? '',
                'Subject' => $metas['title'] ?? '',
                'Description' => $metas['description'] ?? '',
                'theme-color' => '#005696',
                'metro4:init' => 'true',
                'metro4:locale' => $nframework->lang,
                'metro4:week_start' => '1',
                'og:url' => $metas['url'] ?? '',
                'og:type' => 'article',
                'og:title' => $nframework->title . ' ' . ($metas['title'] ?? ''),
                'og:description' => $metas['description'] ?? '',
                'og:image' => $nframework->image,
                'twitter:card' => '/images/config/1200/628/logo.png',
                'twitter:url' => $config['url'] ?? '',
                'twitter:title' => $nframework->title . ' ' . ($metas['title'] ?? ''),
                'twitter:description' => $metas['description'] ?? '',
                'twitter:image' => '/images/config///logo.png',
                'mobile-web-app-capable' => 'yes',
                'apple-mobile-web-app-capable' => 'yes',
                'application-name' => $nframework->title,
                'apple-mobile-web-app-status-bar-style' => 'default',
                'apple-mobile-web-app-title' => $nframework->title,
                'msapplication-starturl' => '/',
            ];

            foreach ($defaultMetas as $key => $val) {
                if ($val)
                    $nframework->ui->addMeta($key, $val);
            }

            // Specific Meta Handling for http-equiv
            // Note: UIManager::addMeta handles simple name/content. Future: improve for http-equiv vs name. 
            // checks if property attribute is needed (og:...)

            // ... manual fix for now, simplest integration

            $tmpkeyworsd2 = [];
            /*$tmpkeywords[]=array_merge(
                explode(',',$metas['keywords']),
                explode(',',$config['keywords'])
                );

            foreach($tmpkeywords as $tmpkeyword){
                $tmpkeyworsd2[]=trim($tmpkeyword);
            }//*/

            header('X-Frame-Options: SAMEORIGIN');
            header('Content-Type:text/html; charset=utf-8');
            $content = '<!DOCTYPE html>
<html lang="' . $nframework->lang . '"' . $nframework->html_addtag . '>
<link rel="apple-touch-icon" sizes="57x57" href="/images/config/57/logo.png" />
<link rel="apple-touch-icon" sizes="144x144" href="/images/config/144/logo.png" />
<title>' . $nframework->title . ' ' . ($metas['title'] ?? '') . '</title>
    ' . $nframework->ui->renderHead() . '
  </head>
  <body' . $nframework->body_addtag . '>
  <dialog id="dialogLoading">
		<center>
			<span class="mif-spinner2 ani-spin"></span>
			<div autofocus id="#dialogCancel" class="button">Cancelar</div>
		</center>
	</dialog>'
                . $buffer . implode('', $nframework->docend) . $nframework->ui->renderFooter() . $javas . $javasstr . '
	
</body>
</html>';
        } else {
            $content = $buffer . $javasstr;
        }
    }
    echo $content;
    nfStatUpdate([
        'response_time_ms' => (microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]) * 1000,
        'session_id' => session_id(),
        'size_bytes' => strlen($content),
        'status_code' => http_response_code(),
    ]);
}
// $buffer='';
if (php_sapi_name() != 'cli' && empty($nfshutdowndisable)) {
    register_shutdown_function('nfshutdown');
}
function nfjavaobfuscate($mbuffer): string
{
    global $nframework, $buffer;
    if ($_SESSION['nf']['browser']['platform'] == 'Android') {
        $mbuffer = str_replace('href="javascript:', 'href="#" onclick="javascript:', $mbuffer);
    }
    preg_match_all('/<script((?:(?!src=).)*?)>(.*?)<\/script>/smix', $mbuffer, $matches, PREG_SET_ORDER);
    $oo[] = $matches;
    foreach ($matches as $match) {
        $nframework->javas[] = $match[2];
        $mbuffer = str_replace($match[0], '', $mbuffer);
    }
    $buffer .= $mbuffer;

    return '';
}
if (php_sapi_name() != 'cli' && empty($nfjavaobfuscatedisable)) {
    ob_start('nfjavaobfuscate');
}

function mongoToArray($obj)
{
    $m = (array) $obj;
    foreach ($m as $k => $val) {
        if ($val instanceof \MongoDB\Model\BSONArray) {
            $m[$k] = mongoToArray($val);
        }
    }

    return $m;
}

/**
 * Valida el CSRFToken de un formulario generado con secureform(). El token se calcula con la
 * acción del formulario: la URL actual o, para formularios AJAX (acción vacía), la acción interna.
 */
function csrfValidate(): bool
{
    $token = $_POST['CSRFToken'] ?? '';
    if (!is_string($token) || $token === '' || empty($_SESSION['nf']['Anti-CSRF'])) {
        return false;
    }
    foreach ([$_SERVER['REQUEST_URI'] ?? '', parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), NF_AJAX_FORM_ACTION] as $action) {
        if (is_string($action) && hash_equals(csrfToken($action), $token)) {
            return true;
        }
    }
    return false;
}
function csrfToken($action): string
{
    return hash('sha256', ($_SESSION['nf']['Anti-CSRF'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? '') . $action);
}
const NF_AJAX_FORM_ACTION = 'javascript:" data-on-submit="nAjaxOnSubmit';
function secureform(
    string $action = '',
    bool $files = false,
    string $id = '',
    string $onvalidateform = '',
    string $onbeforesubmit = '',
    string $class = ''
): string {
    global $nframework;
    $lng = $nframework->language;
    //	$csrftoken = csrfToken($arg['action']);
    if (empty($id)) {
        $id = 'secureform' . ($nframework->counters('secureform'));
    }
    if ($action == '') {
        $action = NF_AJAX_FORM_ACTION;
    }
    $addEnctype = $files ? ' enctype="multipart/form-data"' : '';

    return '<form method="POST" id="' . $id . '" data-role="validator" action="' . $action . '"' .
        $addEnctype . ' data-interactive-check="true" 
        data-on-error-form="nfonFormError"' . ($onbeforesubmit == '' ? '' : ' data-on-before-submit="' . $onbeforesubmit . '"') .
        ($onvalidateform == '' ? '' : ' data-on-validate-form="' . $onvalidateform . '"') . ' class="' . $class . '">
<input type="hidden" name="op" value="">
<input type="hidden" name="CSRFToken" value="' . csrfToken($action) . '">';
    // $nframework['secureformcounter']++;
}

function _setNotification(array $users, $content)
{
    global $m, $config;
    foreach ($users as $_user) {
        $_user = trim((string) $_user);
        if ($_user != null) {
            /** @noinspection PhpUndefinedClassInspection */
            $nuevo = new MongoDB\BSON\ObjectID;
            $m->{$config['sitedb']}->registros->updateOne(['_id' => $nuevo], [
                '$set' => [
                    'user' => $_user,
                    'content' => $content,
                    'date' => date('Y-m-d H:i:s'),
                ],
            ], ['upsert' => true]);
        }
    }
}
