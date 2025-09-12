<?php

/**
 * PHPUnit Bootstrap File
 */

// Load composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load project configuration
require_once __DIR__ . '/../config/Config.php';

use Config\Config;

// Set up test environment
putenv('APP_ENV=testing');
putenv('APP_DEBUG=true');

// Use test database configuration
putenv('DB_HOST=localhost');
putenv('DB_PORT=3306');
putenv('DB_DATABASE=test_formulario_bd');
putenv('DB_USERNAME=root');
putenv('DB_PASSWORD=');

// Load configuration
Config::load();

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start session for testing
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}
