<?php
/**
 * Las cabeceras X-Forwarded-* solo se aceptan si la petición llega desde un proxy de confianza
 * (red privada/loopback o una IP listada en $config['trusted_proxies']); de lo contrario
 * cualquier cliente podría falsificar su IP y evadir las listas negras y el límite de intentos.
 *
 * @param string[] $trustedProxies IPs o rangos CIDR adicionales a las redes privadas.
 * @return bool
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

/**
 * Detrás de un proxy de confianza que termina TLS (X-Forwarded-Proto: https) ajusta REQUEST_SCHEME,
 * SERVER_PROTOCOL y HTTPS en $_SERVER para que la petición se trate como https.
 *
 * @param bool $trustedProxy Resultado de isTrustedProxy().
 * @return void
 */
function normalizeRequestScheme(bool $trustedProxy): void
{
    if ($trustedProxy && !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $_SERVER['REQUEST_SCHEME'] = str_replace('http', 'https', $_SERVER['REQUEST_SCHEME'] ?? 'https');
        $_SERVER['SERVER_PROTOCOL'] = str_replace('HTTP', 'HTTPS', $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1');
        $_SERVER['HTTPS'] = 'on';
    }
}

/**
 * IP real del cliente: REMOTE_ADDR o, si la petición viene de un proxy de confianza, la primera IP válida
 * de X-Forwarded-For o X-Real-IP. '127.0.0.1' si ninguna es válida. include.php la guarda en $ip.
 *
 * @param bool $trustedProxy Resultado de isTrustedProxy().
 * @return string
 */
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

/**
 * Excepción propia del framework (p. ej. downloadfrom() con un archivo inexistente).
 */
class nFrameworkException extends Exception
{
    /**
     * Mensaje con código, texto, archivo y línea.
     *
     * @return string
     */
    public function errorMessage()
    {
        // Error message
        return "Error [{$this->getCode()}]: {$this->getMessage()} at {$this->getFile()}:{$this->getLine()}";
    }
}


/**
 * Configuración del sitio, disponible como $config. Une el bloque de includes/config.php que corresponde
 * al HTTP_HOST con el documento {_id: 'site'} de la colección configs (gana MongoDB; trusted_proxies y
 * allowed_redirect_hosts se suman). Se usa como arreglo; una llave inexistente devuelve null sin aviso.
 *
 *   $db = $m->{$config['sitedb']};
 *   $host = $config['smtp']['host'] ?? '';
 *   $url = $config->fileValue('url');   // solo lo de config.php
 *
 * @implements ArrayAccess<string, mixed>
 */
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
        // Archivo del logotipo del sitio (Admin → Sitio lo sube aquí); si no existe se usa el de nframework.
        if (empty($this->contenedor['logo'])) {
            $this->contenedor['logo'] = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/img/nf/logo.png';
        }
        if (empty($this->contenedor['users']['algos'])) {
            $this->contenedor['users']['algos'] = ['sha512'];
        }

        if (empty($this->contenedor['users']['collection'])) {
            $this->contenedor['users']['collection'] = 'users';
        }
    }

    /**
     * Mezcla la configuración de MongoDB (configs/site y configs/theme, con caché local) sobre la de
     * config.php. Genera session_key la primera vez y fija valores por omisión del manifest.
     * include.php la llama tras conectar.
     *
     * @global MongoDB\Client $m
     * @return void
     */
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
        // Es una ruta del servidor donde Admin escribe: solo se define en config.php, nunca desde la BD.
        $conf['logo'] = $this->contenedor['logo'];
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
     *
     * @param string $key
     * @return mixed null si config.php no la define.
     */
    public function fileValue(string $key): mixed
    {
        return $this->fileConfig[$key] ?? null;
    }

    /**
     * Cambia un valor solo en memoria (no se guarda en MongoDB).
     *
     * @param string|null $offset
     * @param mixed $valor
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $valor): void
    {

        if (is_null($offset)) {
            $this->contenedor[] = $valor;
        } else {
            $this->contenedor[$offset] = $valor;
        }
    }

    /**
     * @param string $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->contenedor[$offset]);
    }

    /**
     * @param string $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->contenedor[$offset]);
    }

    /**
     * @param string $offset
     * @return mixed null si no existe.
     */
    public function offsetGet($offset): mixed
    {
        return isset($this->contenedor[$offset]) ? $this->contenedor[$offset] : null;
    }
}

$config = new class_config;

/**
 * Estado de la respuesta, disponible como $nframework: plantilla (usecommon), CSS y JS de la página
 * (csss/jss ordenados por clave '001'…'200'), metas, idioma, caché HTTP y salidas Excel/Word/PDF.
 * nfshutdown() lo lee al final de la petición para armar la respuesta.
 *
 *   $nframework->usecommon = true;
 *   $nframework->metas['title'] = 'Productos';
 *   $nframework->jss['150'] = '/js/productos.js';
 *   if ($nframework->isAjax()) { ... }
 */
class class_nframework
{
    /** Título del sitio ($config['title']). */
    public string $title;
    /** Imagen para metas Open Graph. */
    public string $image;
    /** @var array<string, string> Textos del idioma actual (includes/i18n/<lang>.php). */
    public array $language;
    /** true si llegó X-Requested-With: XMLHttpRequest; use isAjax(). */
    public bool $isAjax = false;
    public bool $https = false;
    /** Idioma actual: 'es-MX' o 'en-US'. */
    public string $lang;
    /** Idioma con guion bajo (es_MX). */
    public string $lang_;
    /** Idioma corto (es, en). */
    public string $langshort;
    /** @var array<string, mixed> */
    public array $languages;
    /** @var bool */
    public $shutdown;



    // public String $language;
    private array $config;
    /** @var array<string, int> */
    private array $counters = [];
    /** @var list<array{type: int, file: string, number: int, desc: string, trace?: string}> Errores capturados en modo desarrollador. */
    public array $errores = [];
    /** @var array<string, string> Hojas de estilo por clave de orden ('050' Metro, '090' nframework.css, '101'+ el sitio). */
    public array $csss = [];
    /** @var array<string, string> Scripts por clave de orden ('001' jQuery, '050' Metro, '150'+ el sitio). */
    public array $jss = [];
    /** @var list<string> Código de los <script> inline extraídos del buffer. */
    public array $javas = [];
    /** @var array<string, mixed> */
    public array $javasonce = [];
    /** @var array<string, string> HTML que se agrega al final del <body>. */
    public array $docend = [];
    /** true = envolver la salida en la plantilla HTML completa. */
    public bool $usecommon = false;
    /** Carpeta includes/ del framework. */
    public string $include_path;
    /** Carpeta /nframework del document root. */
    public string $api_path;
    /** Atributos extra para <body>. */
    public string $body_addtag = '';
    /** Atributos extra para <html>. */
    public string $html_addtag = '';
    /** @var array<string, bool> */
    public array $onces = [];
    public UIManager $ui;

    // Explicitly declare optional runtime properties to avoid "Undefined property" errors
    /** ETag de la respuesta (ver testcache()). */
    public ?string $etag = null;
    /** Marca de tiempo Unix de la última modificación (ver testcache()). */
    public ?int $lastmodified = null;
    /** Marca de tiempo Unix hasta la que el navegador reutiliza la respuesta. */
    public ?int $expiretime = null;
    /** Cache-Control public (CDN y proxies pueden guardar la respuesta) en lugar de private. */
    public bool $cachepublic = false;
    /** La respuesta ya se envió directamente (serveFile/serveContent/304): nfshutdown no la procesa. */
    public bool $streamed = false;
    public int $streamedBytes = 0;
    /** @var array<string, string> Metas de la página: 'title', 'description', 'image'... */
    public array $metas = [];

    /**
     * Detecta HTTPS y AJAX y registra el CSS/JS base (Metro UI, jQuery, SweetAlert2, nframework.css).
     *
     * @global class_config $config
     */
    public function __construct()
    {
        global $config;
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
            // Tema moderno sobre las variables de Metro: después de metro.css y antes del CSS del sitio ('101'…),
            // para que el sitio pueda personalizarlo.
            if (($config['modern_css'] ?? true) !== false) {
                $this->csss['090'] = '/nframework/css/nframework.css?v=' . @filemtime(dirname(__DIR__) . '/nframework/css/nframework.css');
            }
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

    /**
     * Agrega a la página jQuery File Upload (JS y CSS).
     *
     * @return void
     */
    public function addfileupload()
    {
        $this->jss['049'] = 'https://cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/10.32.0/js/jquery.fileupload.min.js';
        $this->csss['049'] = 'https://cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/10.32.0/css/jquery.fileupload.min.css';
    }

    /**
     * Agrega a la página jQuery UI (JS y tema smoothness).
     *
     * @return void
     */
    public function addjqueryui()
    {
        $this->jss['002'] = 'https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.14.0/jquery-ui.min.js';
        $this->csss['000'] = 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.14.0/themes/smoothness/jquery-ui.min.css';
    }

    /**
     * Valor de la cabecera Authorization (busca en $_SERVER y en apache_request_headers()); '' si no llegó.
     *
     * @return string
     */
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

    /**
     * Contador por nombre que empieza en 0 y aumenta con cada llamada; sirve para generar ids únicos en la
     * página (secureform0, secureform1...).
     *
     * @param string $v Nombre del contador.
     * @return int
     */
    public function counters(string $v): int
    {
        if (!array_key_exists($v, $this->counters)) {
            $this->counters[$v] = 0;
        } else {
            $this->counters[$v]++;
        }

        return $this->counters[$v];
    }

    /**
     * @return ThemeSwitcher
     */
    public function themeSwitcher(): ThemeSwitcher
    {
        return new ThemeSwitcher();
    }

    /**
     * true si la petición es AJAX (X-Requested-With: XMLHttpRequest). La misma página sirve el HTML y
     * responde el AJAX asignando $result.
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->isAjax;
    }

    /**
     * Detecta navegador, versión, plataforma, si es móvil e idioma preferido, los guarda en
     * $_SESSION['nf']['browser'] y genera la llave Anti-CSRF de la sesión.
     *
     * @return void
     */
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

    /**
     * Envía una hoja de cálculo como descarga .xlsx. Úsela con $nfshutdowndisable = true (sin plantilla ni buffer).
     *
     * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet
     * @param string $filename Nombre sin extensión (se limpia con clean_filename()).
     * @return void
     */
    public function excelOut($spreadsheet, $filename)
    {
        $filename = clean_filename($filename);
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        $writer->save('php://output');
    }

    /**
     * Envía una hoja de cálculo como PDF, convertida con Dompdf o con unoconv (LibreOffice en el servidor).
     *
     * @param \PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet
     * @param string $filename Nombre sin extensión.
     * @param 'Dompdf'|'unoconv' $converter
     * @param 'inline'|'attachment' $disposition
     * @return void
     */
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

    /**
     * Envía un documento de PhpWord como .docx.
     *
     * @param \PhpOffice\PhpWord\PhpWord $word
     * @param string $filename Nombre sin extensión.
     * @return void
     */
    public function wordOut($word, $filename)
    {
        $filename = clean_filename($filename);
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: inline; filename="' . $filename . '.docx"');
        $word->save('php://output');
    }

    /**
     * Envía un documento de PhpWord como PDF. $config['word_pdf_converter']: 'Dompdf' (por omisión),
     * 'unoconv', o cualquier otro valor = unoconv contra un LibreOffice escuchando en 127.0.0.1:2002.
     *
     * @global class_config $config
     * @param \PhpOffice\PhpWord\PhpWord $word
     * @param string $filename Nombre sin extensión.
     * @return void
     */
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
    /**
     * Envía una plantilla de PhpWord ya llenada como PDF. Por omisión convierte con unoconv (conserva mejor
     * el formato); con $config['word_pdf_converter'] = 'Dompdf' usa wordOutPdf().
     *
     * @global class_config $config
     * @param \PhpOffice\PhpWord\TemplateProcessor $template
     * @param string $filename Nombre sin extensión.
     * @return void
     */
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
     *
     * @return void
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
     *
     * @return void
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
     *
     * @param string $path Ruta en disco (con datos de la petición use basename() y un directorio fijo).
     * @param string $mime Content-Type.
     * @param int|null $maxAge
     * @param bool $public Cache-Control public en lugar de private.
     * @return void
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
     *
     * @param string $content
     * @param string $mime Content-Type.
     * @param int|null $maxAge Igual que en serveFile().
     * @param bool $public
     * @return void
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
     *
     * @return void
     */
    private function startStream(): void
    {
        header('X-Content-Type-Options: nosniff');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $this->streamed = true;
    }

    /**
     * Envía un archivo como descarga (attachment, sin caché) y termina la petición.
     *
     * @param string $filename Ruta en disco; con datos de la petición use basename() y un directorio fijo.
     * @return void
     * @throws nFrameworkException Si el archivo no existe.
     */
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

    /**
     * Datos del idioma actual ($this->languages[$this->lang]).
     *
     * @return mixed
     */
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
    // Con versión en la URL main.js se guarda un año en el navegador (antes se revalidaba en cada página).
    $nframework->jss['000'] = '/main.js?v=' . nfMainJsVersion();
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
// Código, configuración, logs y archivos ocultos (nfIsBlockedPath()): 404, registrado como evento de seguridad.
$nfBlockedPath = PHP_SAPI !== 'cli' && nfIsBlockedPath(
    $_SERVER['REQUEST_URI'] ?? '/',
    realpath($_SERVER['DOCUMENT_ROOT'] ?? '') === dirname(__DIR__)
);
if ($nfBlockedPath && $block_reason === '') {
    $block_reason = 'Path is protected';
}

if (!empty($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

/**
 * Las estadísticas (nfuristats) se escriben sin esperar confirmación de MongoDB (w=0):
 * así no suman una ida y vuelta al servidor en cada petición. El _id se genera aquí.
 *
 * @return array{writeConcern: MongoDB\Driver\WriteConcern}
 */
function nfStatOptions(): array
{
    static $options = null;
    return $options ??= ['writeConcern' => new MongoDB\Driver\WriteConcern(0)];
}
/**
 * Actualiza el registro de esta petición en nfuristats (tiempo de respuesta, código, bloqueo...).
 * Los errores solo se anotan en el log.
 *
 * @global MongoDB\Client $m
 * @global class_config $config
 * @global MongoDB\BSON\ObjectId $nfStatId
 * @param array<string, mixed> $set Campos para $set.
 * @return void
 */
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
            + ['block_reason' => $block_reason] + ($block_reason != '' ? ['status_code' => $nfBlockedPath ? 404 : 403] : []),
        nfStatOptions()
    );
} catch (Throwable $e) {
    error_log('nframework nfuristats: ' . $e->getMessage());
}
if ($block_reason != "") {
    http_response_code($nfBlockedPath ? 404 : 403);
    exit($nfBlockedPath ? '' : "Access denied.");
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
$nframework->image = (!empty($config['image']) ? $config['image'] : '/images/config/1200/630/logo.png');

/**
 * Convierte un id en texto a ObjectId. Valide antes con isValidObjectId() los ids que llegan de la petición.
 *
 * @param string|MongoDB\BSON\ObjectId|null $item null genera un ObjectId nuevo.
 * @return MongoDB\BSON\ObjectId
 * @throws MongoDB\Driver\Exception\InvalidArgumentException Si el texto no es un ObjectId válido.
 */
function toMongoId($item): ObjectId
{
    return new ObjectId($item);
}
/**
 * toMongoId() sobre cada elemento (para filtros $in).
 *
 * @param array<string|MongoDB\BSON\ObjectId> $items
 * @return list<MongoDB\BSON\ObjectId>
 * @throws MongoDB\Driver\Exception\InvalidArgumentException Si algún elemento no es válido.
 */
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

/**
 * Manejador de errores de PHP. En producción guarda los errores (salvo avisos) en la colección errorlog,
 * agrupados por mensaje; con $developermode los acumula en $nframework->errores para mostrarlos en
 * pantalla. Registra el resultado en nfuristats.
 *
 * @global bool $developermode
 * @global MongoDB\Client $m
 * @global class_nframework $nframework
 * @global class_config $config
 * @param int $errno
 * @param string $errstr
 * @param string $errfile
 * @param int $errline
 * @param array<string, mixed> $errcontext
 * @return bool Siempre false: PHP continúa con su manejo normal.
 */
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
                    $mail->addAddress($destinatario); // Add a recipient
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
/**
 * Autoload: carga class.<Clase>.php desde includes/ o cualquier carpeta del include_path (así los sitios
 * agregan sus clases y Jobs). inputaddress vive en class.address.php.
 *
 * @param string $class_name
 * @return bool
 */
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
nfDetectImageSupport();

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

/**
 * Lee $text en voz alta en el navegador (Web Speech) cuando la página termina de cargar.
 *
 * @global Javas $javas
 * @param string $text
 * @return void
 */
function speak($text)
{
    global $javas;
    $javas->addjs('speak(' . json_encode((string) $text) . ');', 'ready');
}
// TODO> Other options
/**
 * Muestra $text como toast() al cargar la página. $title y $options se aceptan por compatibilidad pero
 * hoy no se usan.
 *
 * @global Javas $javas
 * @param string $title Sin uso actualmente.
 * @param string $text
 * @param array<string, mixed> $options Sin uso actualmente.
 * @return void
 */
function notify($title = 'nlared.com', $text = '', $options = [])
{
    global $javas;
    $javas->addjs('toast(' . json_encode((string) $text) . ');', 'ready');
}


/**
 * Función de cierre (register_shutdown_function): arma la respuesta. Con usecommon envuelve el buffer en
 * la plantilla; en AJAX envía json_encode($result); empaqueta el JS de $javas, envía cabeceras de
 * seguridad y caché, y guarda estadísticas. No se registra con $nfshutdowndisable.
 *
 * @global class_nframework $nframework
 * @global bool $noobfuscate
 * @global string $buffer
 * @global bool $developermode
 * @global Javas $javas
 * @global mixed $result
 * @global class_config $config
 * @global MongoDB\Client $m
 * @return mixed
 */
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

            // Open Graph y Twitter exigen URL absoluta para la imagen.
            $socialImage = (string) $nframework->image;
            if (str_starts_with($socialImage, '/') && !str_starts_with($socialImage, '//')) {
                $socialImage = (!empty($config['url']) ? rtrim($config['url'], '/') : 'https://' . nfSiteHost()) . $socialImage;
            }
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
                'og:image' => $socialImage,
                'twitter:card' => 'summary_large_image',
                'twitter:url' => $config['url'] ?? '',
                'twitter:title' => $nframework->title . ' ' . ($metas['title'] ?? ''),
                'twitter:description' => $metas['description'] ?? '',
                'twitter:image' => $socialImage,
                'mobile-web-app-capable' => 'yes',
                'apple-mobile-web-app-capable' => 'yes',
                'application-name' => $nframework->title,
                'apple-mobile-web-app-status-bar-style' => 'default',
                'apple-mobile-web-app-title' => $nframework->title,
                'msapplication-starturl' => '/',
            ];

            foreach ($defaultMetas as $key => $val) {
                if ($val)
                    $nframework->ui->addMeta($key, $val, str_starts_with($key, 'og:') ? ['property' => $key] : []);
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
<link rel="icon" href="/favicon.ico" sizes="48x48" />
<link rel="icon" type="image/png" sizes="192x192" href="/images/config/192/logo.png" />
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
/**
 * Callback de ob_start(): extrae los <script> inline del HTML a $nframework->javas (se emiten al final de
 * la página) y acumula el resto en $buffer. Devuelve '' porque nfshutdown() envía la salida.
 *
 * @global class_nframework $nframework
 * @global string $buffer
 * @param string $mbuffer
 * @return string
 */
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

/**
 * Convierte un documento de MongoDB (BSONDocument) en arreglo; los BSONArray anidados en el primer
 * nivel también se convierten.
 *
 * @param array<mixed>|object $obj
 * @return array<mixed>
 */
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
 *
 * @return bool
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
/**
 * Token CSRF para la acción $action, ligado a la sesión (Anti-CSRF) y al User-Agent.
 *
 * @param string $action
 * @return string
 */
function csrfToken($action): string
{
    return hash('sha256', ($_SESSION['nf']['Anti-CSRF'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? '') . $action);
}
const NF_AJAX_FORM_ACTION = 'javascript:" data-on-submit="nAjaxOnSubmit';
/**
 * Abre un <form> con validación de Metro y token CSRF; ciérrelo con </form>. Sin $action envía por AJAX a
 * la misma página (un botón con clase secureop y value="op" llena $_POST['op']); con $action hace un POST
 * normal. En el servidor valide con csrfValidate().
 *
 * @global class_nframework $nframework
 * @param string $action URL destino; '' = AJAX a la misma página.
 * @param bool $files true = multipart/form-data.
 * @param string $id Id del form; '' = secureform<n>.
 * @param string $onvalidateform Función JS para data-on-validate-form.
 * @param string $onbeforesubmit Función JS para data-on-before-submit.
 * @param string $class Clases CSS del form.
 * @return string HTML de apertura del form con los campos ocultos op y CSRFToken.
 */
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

/**
 * Guarda una notificación en la colección registros para cada usuario de $users.
 *
 * @global MongoDB\Client $m
 * @global class_config $config
 * @param array<string> $users Usuarios destino.
 * @param mixed $content Contenido de la notificación.
 * @return void
 */
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
