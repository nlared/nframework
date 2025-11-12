<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from command line.');
}

// Alternative check using PHP_SAPI constant
if (PHP_SAPI !== 'cli') {
    exit('CLI only script');
}

// More comprehensive check
if (!defined('STDIN') || !is_resource(STDIN)) {
    exit('This script must be run from command line');
}

$_SERVER['HTTP_HOST'] = $argc > 1 ? $argv[1] : 'localhost';
require 'vendor/autoload.php';
require 'config.php';
$m = new MongoDB\Client($config['mongo_connection_string']);
foreach ($m->{$config['sitedb']}->configs->find() as $conf) {
    print_r($conf);
}
