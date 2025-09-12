<?php

declare(strict_types=1);

/**
 * Application Bootstrap
 * Initializes the new architecture alongside legacy code
 */

// Autoload dependencies
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	// Manual autoload for new classes if Composer not available
	spl_autoload_register(function ($class) {
		$prefix = 'Src\\';
		$baseDir = __DIR__ . '/src/';

		$len = strlen($prefix);
		if (strncmp($prefix, $class, $len) !== 0) {
			return;
		}

		$relativeClass = substr($class, $len);
		$file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

		if (file_exists($file)) {
			require $file;
		}
	});
}

// Load legacy startup
require_once __DIR__ . '/startup.php';

use Src\Database\DatabaseManager;
use Src\Repository\ObraRepository;
use Src\Repository\EmpresaRepository;
use Src\Repository\FuncionarioRepository;
use Src\Repository\DiarioObraRepository;
use Src\Repository\ImageRepository;
use Src\Service\ObraService;
use Src\Service\ImageProcessorService;
use Src\Service\ImageUploadService;
use Src\Cache\CacheManager;
use Src\Cache\FileCache;
use Src\Security\SessionManager;
use Models\Connection;

/**
 * Service Container - Simple dependency injection
 */
class ServiceContainer
{
	private array $services = [];
	private array $singletons = [];

	public function register(string $name, callable $factory): void
	{
		$this->services[$name] = $factory;
	}

	public function singleton(string $name, callable $factory): void
	{
		$this->register($name, function () use ($factory, $name) {
			if (!isset($this->singletons[$name])) {
				$this->singletons[$name] = $factory();
			}
			return $this->singletons[$name];
		});
	}

	public function get(string $name)
	{
		if (!isset($this->services[$name])) {
			throw new InvalidArgumentException("Service '$name' not found");
		}

		return $this->services[$name]();
	}

	public function has(string $name): bool
	{
		return isset($this->services[$name]);
	}
}

// Create service container
$container = new ServiceContainer();

// Register core services
$container->singleton('pdo', function () {
	return Connection::getPDO();
});

$container->singleton('db', function () use ($container) {
	return new DatabaseManager($container->get('pdo'));
});

$container->singleton('cache', function () {
	$cacheDir = __DIR__ . '/storage/cache';
	return new CacheManager(new FileCache($cacheDir));
});

$container->singleton('session', function () {
	return SessionManager::getInstance();
});

// Register repositories
$container->singleton('obra.repository', function () use ($container) {
	return new ObraRepository($container->get('db'));
});

$container->singleton('empresa.repository', function () use ($container) {
	return new EmpresaRepository($container->get('db'));
});

$container->singleton('funcionario.repository', function () use ($container) {
	return new FuncionarioRepository($container->get('db'));
});

$container->singleton('diario.repository', function () use ($container) {
	return new DiarioObraRepository($container->get('db'));
});

$container->singleton('image.repository', function () use ($container) {
	return new ImageRepository($container->get('db'));
});

// Register services
$container->singleton('obra.service', function () use ($container) {
	return new ObraService(
		$container->get('obra.repository'),
		$container->get('empresa.repository'),
		$container->get('diario.repository')
	);
});

$container->singleton('image.processor', function () use ($container) {
	return new ImageProcessorService([
		'driver' => extension_loaded('imagick') ? 'imagick' : 'gd',
		'quality' => 80,
		'max_width' => 1920,
		'max_height' => 1080
	]);
});

$container->singleton('image.upload', function () use ($container) {
	return new ImageUploadService(
		$container->get('image.processor'),
		$container->get('image.repository'),
		[
			'base_path' => __DIR__ . '/img/album',
			'max_files_per_diario' => 20,
			'max_size' => 10 * 1024 * 1024
		]
	);
});

/**
 * Helper functions for accessing services globally
 */
function app(?string $service = null)
{
	global $container;

	if ($service === null) {
		return $container;
	}

	return $container->get($service);
}

function cache(): CacheManager
{
	return app('cache');
}

function session(): SessionManager
{
	return app('session');
}

// Initialize session
session()->start();

// Create storage directories if needed
$directories = [
	__DIR__ . '/storage',
	__DIR__ . '/storage/cache',
	__DIR__ . '/storage/logs',
	__DIR__ . '/storage/uploads'
];

foreach ($directories as $dir) {
	if (!is_dir($dir)) {
		mkdir($dir, 0755, true);
	}
}

/**
 * Enhanced error handler for development
 */
if (Config\Config::get('APP_DEBUG', false)) {
	set_error_handler(function ($severity, $message, $file, $line) {
		if (!(error_reporting() & $severity)) {
			return false;
		}

		throw new ErrorException($message, 0, $severity, $file, $line);
	});

	set_exception_handler(function ($exception) {
		$error = [
			'message' => $exception->getMessage(),
			'file' => $exception->getFile(),
			'line' => $exception->getLine(),
			'trace' => $exception->getTraceAsString()
		];

		if (php_sapi_name() === 'cli') {
			echo 'Error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'] . "\n";
			echo $error['trace'] . "\n";
		} else {
			echo '<pre>';
			print_r($error);
			echo '</pre>';
		}
	});
}

/**
 * Legacy compatibility helpers
 */
function getNewDao(): DatabaseManager
{
	return app('db');
}

function getObraService(): ObraService
{
	return app('obra.service');
}
