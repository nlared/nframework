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
require_once __DIR__ . '/../includes/class.User.php';
require_once __DIR__ . '/../includes/class.Notifications.php';

echo "PHPUnit Bootstrap: Test environment initialized\n";
