<?
require_once __DIR__ . '/vendor/autoload.php';
require 'config.php';
$m = new MongoDB\Client($config['mongo_connection_string']);
use Altmetric\MongoSessionHandler;
$sessions = $m->{$config['sitedb']}->sessions;
$handler = new MongoSessionHandler($sessions);
session_set_save_handler($handler);
session_name(str_replace('.','_',$config['cookie_domain']));
session_set_cookie_params(0, '/', $config['cookie_domain'],$config['forcehttps'],false);
session_start();
session_destroy();
use Microsoft\Graph\Connect\Constants;
$provider = new \League\OAuth2\Client\Provider\GenericProvider([
    'clientId'                => Constants::CLIENT_ID,
    'clientSecret'            => Constants::CLIENT_SECRET,
    'redirectUri'             => Constants::REDIRECT_URI,
    'urlAuthorize'            => Constants::AUTHORITY_URL . Constants::AUTHORIZE_ENDPOINT,
    'urlAccessToken'          => Constants::AUTHORITY_URL . Constants::TOKEN_ENDPOINT,
    'urlResourceOwnerDetails' => '',
    'scopes'                  => Constants::SCOPES
]);
$return_to = 'https://' . $_SERVER['HTTP_HOST'].'/account/logout.php';
$uri=urlencode('https://login.microsoftonline.com/common/oauth2/nativeclient?logout_hint='.$_SESSION['access_token']);
$logout_url = sprintf('%s/oauth2/v2.0/logout?client_id=%s&post_logout_redirect_uri=%s', Constants::AUTHORITY_URL, Constants::CLIENT_ID, $uri);

$protocol = (
	!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' 
|| 
	$_SERVER['SERVER_PORT'] == 443
||
	$_SERVER['HTTP_X_FORWARDED_PROTO']=='https'
) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$uri=urlencode($protocol.$host.'/account/logout.php');
$logout_url = sprintf('%s/oauth2/logout?redirect_uri=%s&post_logout_redirect_uri=%s',Constants::AUTHORITY_URL,$uri,$uri);
header('Location: ' . $logout_url);
