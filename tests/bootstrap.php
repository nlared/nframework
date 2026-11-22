<?php

/**
 * PHPUnit Bootstrap File
 * This file is executed before running tests
 */

// Define test environment
define('TESTING', true);

// Load Composer autoloader
require_once __DIR__ . '/../includes/vendor/autoload.php';

// Mock global variables for testing
$GLOBALS['config'] = [
    'sitedb' => 'test_db',
    'users' => [
        'algos' => ['sha256', 'sha512']
    ]
];

// Mock MongoDB connection for tests
$GLOBALS['m'] = null;

// Load framework functions
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/class.Base.php';
require_once __DIR__ . '/../includes/class.embededArray.php';
require_once __DIR__ . '/../includes/class.User.php';
require_once __DIR__ . '/../includes/class.Notifications.php';

// Copias de toMongoId()/toMongoIds() de include.php (que no se carga en los tests: conecta a MongoDB).
if (!function_exists('toMongoId')) {
    function toMongoId($item): MongoDB\BSON\ObjectId
    {
        return new MongoDB\BSON\ObjectId($item);
    }
}
if (!function_exists('toMongoIds')) {
    function toMongoIds(array $items): array
    {
        return array_map('toMongoId', $items);
    }
}
