<?php

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/models/Connection.php';
require_once __DIR__ . '/models/DAO.php';
require_once __DIR__ . '/models/Empresa.php';
require_once __DIR__ . '/models/Imagem.php';
require_once __DIR__ . '/models/Funcionario.php';
require_once __DIR__ . '/models/DiarioObra.php';
require_once __DIR__ . '/models/FuncionarioDiarioObra.php';
require_once __DIR__ . '/models/Obra.php';
require_once __DIR__ . '/models/Servico.php';

use Config\Config;
use Models\Connection;
use Models\DAO;

// Load configuration
Config::load();

// Set timezone from configuration
date_default_timezone_set(Config::get('TIMEZONE', 'America/Sao_Paulo'));

// Set execution time limit from configuration
$maxExecutionTime = Config::get('MAX_EXECUTION_TIME', 300);
set_time_limit((int)$maxExecutionTime);

// Configure error reporting based on environment
if (Config::get('APP_DEBUG', false)) {
	ini_set('display_errors', '1');
	ini_set('display_startup_errors', '1');
	error_reporting(E_ALL);
} else {
	ini_set('display_errors', '0');
	ini_set('display_startup_errors', '0');
	error_reporting(E_ALL & ~E_DEPRECATED);
	ini_set('log_errors', '1');
	ini_set('error_log', __DIR__ . '/logs/error.log');
}

// Set photo album path from configuration
$pathAlbum = __DIR__ . '/' . Config::get('PHOTO_STORAGE_PATH', 'img/album');

// Initialize database connection
$pdo = Connection::getPDO();

if (!$pdo) {
	// More user-friendly error message
	$errorMessage = Config::get('APP_DEBUG', false)
		? "Database connection failed. Please check your configuration."
		: "We're experiencing technical difficulties. Please try again later.";
	
	die($errorMessage);
}

$dao = new DAO($pdo);
