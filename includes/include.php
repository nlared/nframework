<?php
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['REQUEST_SCHEME'] = str_replace('http', 'https', $_SERVER['REQUEST_SCHEME']);
    $_SERVER['SERVER_PROTOCOL'] = str_replace('HTTP', 'HTTPS', $_SERVER['SERVER_PROTOCOL']);
    $_SERVER['HTTPS'] = 'on';
}

//TODO: HTTP_CF_IPCOUNTRY
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $ip = $ipList[0]; // First IP is usually the client
} elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
    $ip = $_SERVER['HTTP_X_REAL_IP'];
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
}



if (php_sapi_name() != 'cli') {
    /*if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === "off") {
        $location = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $location);
        exit;
    }*/
    if (empty($_SERVER['HTTP_USER_AGENT'])) {
        http_response_code(403);
        exit("Access denied.");
    }
}
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/class.UIManager.php';

use FontLib\Table\Type\head;
use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectId;

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

    public function __construct()
    {
        require __DIR__ . '/config.php';
        $this->contenedor = (array) $config;

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
        $dbconf = $m->{$this->contenedor['sitedb']}->configs->findOne(['_id' => 'site']);
        $themeconf = $m->{$this->contenedor['sitedb']}->configs->findOne(['_id' => 'theme']);

        $conf = array_merge(
            $this->contenedor,
            mongoToArray($dbconf)
        );
        $conf['theme'] = mongoToArray($themeconf);
        if (empty($conf['manifest']['theme_color'])) {
            $conf['manifest']['theme_color'] = '#1ba1e2';
        }
        if (empty($conf['manifest']['background_color'])) {
            $conf['manifest']['background_color'] = '#ffffff';
        }

        $this->contenedor = $conf;
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

            $this->csss['050'] = 'https://cdn.metroui.org.ua/current/metro.css';
            $this->csss['051'] = 'https://cdn.metroui.org.ua/current/icons.css';
            $this->csss['200'] = '/nframework/templates/panda/css.css';


            $this->jss['050'] = 'https://cdn.metroui.org.ua/current/metro.js';
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
        $headers = null;
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
        $_SESSION['nf']['Anti-CSRF'] = uniqid();
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
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Dompdf');
            $writer->save('php://output');
        }
        if ($converter == 'unoconv') {
            $tmpfname = tempnam(sys_get_temp_dir(), 'xlsxpdf');
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($tmpfname . '.xlsx'); // This line will force the file to download
            shell_exec('unoconv -f pdf ' . $tmpfname . '.xlsx');
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
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        $word->save('php://output');
    }

    public function wordOutPdf($word, $filename)
    {
        global $config;
        $filename = clean_filename($filename);
        if ($config['word_pdf_converter'] == 'Dompdf' || empty($config['word_pdf_converter'])) {
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($word, 'Dompdf');
            $writer->save('php://output');
        } else {
            $tmpfname = tempnam(sys_get_temp_dir(), 'xlsxpdf');
            $word->saveAs($tmpfname . '.docx');
            if ($config['word_pdf_converter'] == 'unoconv') {
                shell_exec('unoconv -f pdf ' . $tmpfname . '.docx');
            } else {
                shell_exec("unoconv -f pdf --connection 'socket,host=127.0.0.1,port=2002;urp;' " . $tmpfname . '.docx');
            }
        }
        if (file_exists($tmpfname . '.pdf')) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
            $size = filesize($tmpfname . '.pdf');
            header("Content-length: $size");
            readfile($tmpfname . '.pdf');
            unlink($tmpfname . '.pdf');
        }
        if (file_exists($tmpfname . '.docx')) {
            unlink($tmpfname . '.docx');
        }
    }
    public function wordTemplateOutPdf(PhpOffice\PhpWord\TemplateProcessor $template, $filename)
    {
        global $config;
        $filename = clean_filename($filename);
        $tmpfname = tempnam(sys_get_temp_dir(), 'templatepdf');
        $template->saveAs($tmpfname . '.docx');

        if ($config['word_pdf_converter'] == 'Dompdf' || empty($config['word_pdf_converter'])) {
            $phpWord = \PhpOffice\PhpWord\IOFactory::load($tmpfname . '.docx');
            $this->wordOutPdf($phpWord, $filename);
        } else {
            if ($config['word_pdf_converter'] == 'unoconv') {
                shell_exec('unoconv -f pdf ' . $tmpfname . '.docx');
            } else {
                shell_exec("unoconv -f pdf --connection 'socket,host=127.0.0.1,port=2002;urp;StarOffice.ComponentContext' " . $tmpfname . '.docx');
            }
        }
        if (file_exists($tmpfname . '.pdf')) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
            $size = filesize($tmpfname . '.pdf');
            header("Content-length: $size");
            readfile($tmpfname . '.pdf');
            unlink($tmpfname . '.pdf');
        }
        if (file_exists($tmpfname . '.docx')) {
            unlink($tmpfname . '.docx');
        }
    }
    function testcache()
    {

        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            $id = trim($_SERVER['HTTP_IF_NONE_MATCH']);
            if (substr($id, 0, 2) == "W/") {
                $id = substr($id, 2);
            }
            $id = str_replace('"', '', $id);
            $match = ($id == $this->etag);
        } else {
            $match = false;
        }
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
        $notModifiedByDate = ($ifModifiedSince !== '' && $ifModifiedSince === $this->lastmodified);
        if ($match || $notModifiedByDate) {
            // Unchanged → return 304 without body
            http_response_code(304);
            exit();
        }
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
    echo 'Excepción capturada: ', $e->getMessage(), "\n";
    phpinfo();
}

if (isset($config['security_user_agents_blacklist'])) {
    $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
    foreach ($config['security_user_agents_blacklist'] as $bot) {
        if (str_contains($userAgent, $bot) !== false) {
            header("HTTP/1.1 403 Forbidden");
            exit("Access denied.");
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
                    http_response_code(403);
                    exit("Access denied." . $tmp['end']->toDateTime()->format('Y-m-d H:i:s'));
                } else {
                    // remove expired
                    $m->{$config['sitedb']}->configs->updateOne(['_id' => 'site'], ['$pull' => ['security_ip_blacklist' => ['ip' => $tmp['ip']]]]);
                }
            } else {
                http_response_code(403);
                exit("Access denied.");
            }
        }
    }
}
if (isset($config['security_host_blacklist'])) {
    foreach ($config['security_host_blacklist'] as $tmp) {
        if ($_SERVER['HTTP_HOST'] == $tmp['host']) {
            http_response_code(403);
            exit("Access denied.");
        }
    }
}
if (isset($config['security_path_blacklist'])) {
    foreach ($config['security_path_blacklist'] as $tmp) {
        if ($_SERVER['REQUEST_URI'] == $tmp['path']) {
            http_response_code(403);
            exit("Access denied.");
        }
    }
}

if (!empty($config['timezone'])) {
    date_default_timezone_set($config['timezone']);
}

$m->{$config['sitedb']}->nfuristats->insertOne([
    'created_at' => new MongoDB\BSON\UTCDateTime(time() * 1000), // use PHP DateTime; the MongoDB driver will convert it to BSON UTC datetime
    'ip' => $ip,
    'host' => $_SERVER['HTTP_HOST'],
    'path' => $_SERVER['REQUEST_URI'],
    'agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
]);

$rules = [['host' => ['$exists' => false]]];
foreach ($m->{$config['sitedb']}->nfsecurityrules->find() as $rule) {
    if (!empty($rule->rule) && !empty($rule->enabled) && $rule->enabled === true) {
        $rules[] = fixSingleQuery(json_decode($rule->rule, true));
    }
}

if ($attempts = $m->{$config['sitedb']}->nfuristats->count([
    'ip' => $ip,
    'created_at' => ['$gt' => new MongoDB\BSON\UTCDateTime((time() - (isset($config['windowSeconds']) ? $config['windowSeconds'] : 900)) * 1000)], // use DateTime for comparison; driver converts to BSON UTC datetime
    '$or' => $rules,
])) {
} else {
    $attempts = 0;
}

if ($attempts > 10) {
    $doc = [
        'ip' => $ip,
        'end' => new MongoDB\BSON\UTCDateTime((time() + (isset($config['windowSeconds']) ? $config['windowSeconds'] : 900)) * 1000)
    ];
    $m->{$config['sitedb']}->configs->updateOne(['_id' => 'site'], ['$addToSet' => ['security_ip_blacklist' => $doc]]);
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

        return false;
    } else {
        $nframework->errores[] = [
            'type' => $errno,
            'file' => $errfile,
            'number' => $errline,
            'desc' => $errstr,
        ];

        return false;
    }
}
$original = set_error_handler('nferrorhandler');
function nframework_autoload($class_name): bool
{
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
session_name(str_replace('.', '_', $config['cookie_domain']));
session_set_cookie_params(0, '/', $config['cookie_domain'], $nframework->https, false);
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
} else {
    if (isset($requiresession)) {
        header('Location: /');
    } else {
        $user = new User(['username' => 'guest']);
    }
}


$javas = new Javas;
$themeswitcher = new ThemeSwitcher();

function speak($text)
{
    global $javas;
    $javas->addjs("
	speak('$text');
", 'ready');
}
// TODO> Other options
function notify($title = 'nlared.com', $text = '', $options = [])
{
    global $javas;
    $javas->addjs("
	toast('$text');
", 'ready');
}


function nfshutdown()
{
    global $nframework, $noobfuscate, $buffer, $developermode, $javas, $result, $config;
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
                $datatable->header = '<th>Tipo</th><th>Archivo</th><th>Linea</th><th>Descripcion</th>';
                $datatable->data = $nframework->errores;
                echo '<link rel="stylesheet" href="https://cdn.metroui.org.ua/v4.3.5/css/metro-all.min.css"/>
<link rel="stylesheet" href="//cdn.nlared.com/datatables.net-responsive-dt/css/responsive.dataTables.min.css"/>
<div class="container"><h4>Developer mode active</h4>' . $datatable . '</div>';
            }
        }
    }

    ob_end_flush();
    $javasstr = '';
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
    if (isset($nframework->etag)) {
        header('ETag: "' . $nframework->etag . '"');
    }

    if (isset($nframework->lastmodified)) {
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $nframework->lastmodified) . ' GMT');
    }
    if (isset($nframework->expiretime)) {
        header('Expires: ' . date('D, d M Y H:i:s', $nframework->expiretime) . ' GMT');
        header('Cache-Control: max-age=' . ($nframework->expiretime - time()) . ', public');
        header('Pragma: cache');
    } else {
        //	header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        // header('Cache-Control: post-check=0, pre-check=0', FALSE);
        header('Pragma: no-cache');
    }
    // header('Content-Language: '.$nframework->lang);
    // header('P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');
    //	if($xframe!='remove')	header('X-Frame-Options: '.$xframe);
    // header('Referrer-Policy ""'); nunca usar
    // header( 'X-XSS-Protection: 1;mode=block' );
    // header( 'X-Content-Type-Options: nosniff' );

    if ($nframework->isAjax()) {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode($result);
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

            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1;mode=block');
            header('Content-Type:text/html; charset=utf-8');
            echo '<!DOCTYPE html>
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
            echo $buffer . $javasstr;
        }
    }
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

function csrfValidate()
{
    return $_POST['CSRFToken'] ==
        hash('sha256', $_SESSION['nf']['Anti-CSRF'] . $_SERVER['HTTP_USER_AGENT'] . $_SERVER['REQUEST_URI']);
}
function csrfToken($action): string
{
    return hash('sha256', $_SESSION['nf']['Anti-CSRF'] . $_SERVER['HTTP_USER_AGENT'] . $action);
}
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
        $action = 'javascript:" data-on-submit="nAjaxOnSubmit';
    }
    $addEnctype = $files ? ' enctype="multipart/form-data"' : '';

    return '<form method="POST" id="' . $id . '" data-role="validator" action="' . $action . '"' .
        $addEnctype . ' data-interactive-check="true" 
data-on-error-form="
var log = arguments[0];
var msg=\'' . $lng['error_capture'] . '<br>\';
console.log(log);
$.each(log, function(){
	var label=$(\'label[for=\\\'\'+this.input.id+\'\\\']\').text();
	var minput=this.input;
	msg+=label  +\'<br>\';
	for (const [i, value] of this.errors.entries()){
		console.log(value);
		switch(value){
			case \'pattern\':
				msg+=\'-' . $lng['pattern'] . '\'+minput.pattern+\'<br>\';
				break;
			case \'required\':
				msg+=\'-' . $lng['required'] . '<br>\';
				break;
		}
	};
	msg+=\'<br>\';
});
toast(msg,null,5000);
"' . ($onbeforesubmit == '' ? '' : ' data-on-before-submit="' . $onbeforesubmit . '"') .
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
