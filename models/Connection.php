<?php
declare(strict_types=1);

namespace Models;

use Config\Config;

class Connection
{
	public static function getPDO()
	{
		try 
		{
			// Load configuration
			Config::load();
			
			// Get database configuration from environment variables
			$host = Config::get('DB_HOST', 'localhost');
			$port = Config::get('DB_PORT', '3306');
			$database = Config::get('DB_DATABASE', 'formulario_bd');
			$username = Config::get('DB_USERNAME', 'root');
			$password = Config::get('DB_PASSWORD', '');
			$charset = Config::get('DB_CHARSET', 'utf8');
			
			// Build DSN with correct format
			$dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
			
			// Create PDO instance with error mode set to exceptions
			$pdo = new \PDO($dsn, $username, $password, [
				\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
				\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
				\PDO::ATTR_EMULATE_PREPARES => false
			]);

			return $pdo;
		}
		catch (\PDOException $e)
		{
			// Log error in production, display in development
			if (Config::get('APP_DEBUG', false)) {
				throw $e;
			}
			
			error_log('Database connection failed: ' . $e->getMessage());
			return NULL;
		}
	}
}


?>