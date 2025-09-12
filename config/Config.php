<?php

declare(strict_types=1);

namespace Config;

/**
 * Configuration loader class
 * Loads environment variables from .env file
 */
class Config
{
	private static array $config = [];
	private static bool $loaded = false;

	/**
	 * Load environment variables from .env file
	 */
	public static function load(): void
	{
		if (self::$loaded) {
			return;
		}

		$envFile = dirname(__DIR__) . '/.env';

		// In production/Docker, we use environment variables directly
		if (!file_exists($envFile)) {
			// Don't throw error if running in container with environment variables
			if (getenv('DB_HOST') !== false) {
				self::$loaded = true;
				return;
			}
			throw new \RuntimeException('.env file not found. Please copy .env.example to .env and configure it.');
		}

		$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		foreach ($lines as $line) {
			// Skip comments
			if (strpos(trim($line), '#') === 0) {
				continue;
			}

			// Parse key=value
			if (strpos($line, '=') !== false) {
				list($key, $value) = explode('=', $line, 2);
				$key = trim($key);
				$value = trim($value);

				// Remove quotes if present
				if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
					(substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
					$value = substr($value, 1, -1);
				}

				// Convert string booleans to actual booleans
				if ($value === 'true') {
					$value = true;
				} elseif ($value === 'false') {
					$value = false;
				}

				self::$config[$key] = $value;

				// Also set as environment variable for compatibility
				putenv("$key=$value");
				$_ENV[$key] = $value;
			}
		}

		self::$loaded = true;
	}

	/**
	 * Get configuration value
	 *
	 * @param string $key Configuration key
	 * @param mixed $default Default value if key not found
	 * @return mixed Configuration value
	 */
	public static function get(string $key, $default = null)
	{
		if (!self::$loaded) {
			self::load();
		}

		return self::$config[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
	}

	/**
	 * Check if configuration key exists
	 *
	 * @param string $key Configuration key
	 * @return bool
	 */
	public static function has(string $key): bool
	{
		if (!self::$loaded) {
			self::load();
		}

		return isset(self::$config[$key]) || isset($_ENV[$key]) || getenv($key) !== false;
	}

	/**
	 * Get all configuration values
	 *
	 * @return array
	 */
	public static function all(): array
	{
		if (!self::$loaded) {
			self::load();
		}

		return self::$config;
	}
}
