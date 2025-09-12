<?php

declare(strict_types=1);

namespace Models;

use Config\Config;
use PDO;
use PDOException;

/**
 * Database Connection Manager
 * Handles PDO connection creation with proper configuration
 */
class Connection
{
	/**
	 * Create and return a PDO database connection
	 *
	 * @return PDO|null Returns PDO instance on success, null on failure
	 */
	public static function getPDO(): ?PDO
	{
		try {
			Config::load();

			$connectionParams = self::getDatabaseParameters();
			$dsn = self::buildDSN($connectionParams);

			return self::createPDOInstance(
				$dsn,
				$connectionParams['username'],
				$connectionParams['password']
			);
		} catch (PDOException $exception) {
			self::handleConnectionError($exception);
			return null;
		}
	}

	/**
	 * Get database connection parameters from configuration
	 */
	private static function getDatabaseParameters(): array
	{
		return [
			'host' => Config::get('DB_HOST', 'localhost'),
			'port' => Config::get('DB_PORT', '3306'),
			'database' => Config::get('DB_DATABASE', 'formulario_bd'),
			'username' => Config::get('DB_USERNAME', 'root'),
			'password' => Config::get('DB_PASSWORD', ''),
			'charset' => Config::get('DB_CHARSET', 'utf8')
		];
	}

	/**
	 * Build MySQL DSN string from parameters
	 */
	private static function buildDSN(array $params): string
	{
		return sprintf(
			'mysql:host=%s;port=%s;dbname=%s;charset=%s',
			$params['host'],
			$params['port'],
			$params['database'],
			$params['charset']
		);
	}

	/**
	 * Create PDO instance with secure defaults
	 */
	private static function createPDOInstance(
		string $dsn,
		string $username,
		string $password
	): PDO {
		return new PDO($dsn, $username, $password, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false
		]);
	}

	/**
	 * Handle database connection errors appropriately
	 */
	private static function handleConnectionError(PDOException $exception): void
	{
		$errorMessage = sprintf(
			'Database connection failed: %s',
			$exception->getMessage()
		);

		if (Config::get('APP_DEBUG', false)) {
			throw $exception;
		}

		error_log($errorMessage);
	}
}
