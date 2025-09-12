<?php

declare(strict_types=1);

namespace Src\Security;

/**
 * Secure session management with fingerprinting and regeneration
 */
class SessionManager
{
	private const SESSION_LIFETIME = 3600; // 1 hour
	private const SESSION_NAME = 'VETEL_SESSION';
	private const FINGERPRINT_KEY = '_session_fingerprint';
	private const LAST_ACTIVITY_KEY = '_last_activity';
	private const REGENERATE_INTERVAL = 1800; // 30 minutes

	private static ?self $instance = null;
	private bool $started = false;

	/**
	 * Private constructor for singleton pattern
	 */
	private function __construct()
	{
		$this->configure();
	}

	/**
	 * Get singleton instance
	 */
	public static function getInstance(): self
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Configure secure session settings
	 */
	private function configure(): void
	{
		ini_set('session.use_cookies', '1');
		ini_set('session.use_only_cookies', '1');
		ini_set('session.use_strict_mode', '1');
		ini_set('session.cookie_httponly', '1');
		ini_set('session.cookie_secure', $this->isHttps() ? '1' : '0');
		ini_set('session.cookie_samesite', 'Strict');
		ini_set('session.gc_maxlifetime', (string) self::SESSION_LIFETIME);
		ini_set('session.sid_length', '48');
		ini_set('session.sid_bits_per_character', '6');

		session_name(self::SESSION_NAME);
		session_set_cookie_params([
			'lifetime' => 0,
			'path' => '/',
			'domain' => '',
			'secure' => $this->isHttps(),
			'httponly' => true,
			'samesite' => 'Strict'
		]);
	}

	/**
	 * Start secure session
	 */
	public function start(): bool
	{
		if ($this->started) {
			return true;
		}

		if (session_status() === PHP_SESSION_ACTIVE) {
			$this->started = true;
			return true;
		}

		if (!session_start()) {
			throw new \RuntimeException('Failed to start session');
		}

		$this->started = true;

		// Validate session fingerprint
		if (!$this->validateFingerprint()) {
			$this->destroy();
			$this->start();
			return false;
		}

		// Check session timeout
		if ($this->isExpired()) {
			$this->destroy();
			$this->start();
			return false;
		}

		// Regenerate session ID periodically
		if ($this->shouldRegenerate()) {
			$this->regenerate();
		}

		// Update last activity
		$_SESSION[self::LAST_ACTIVITY_KEY] = time();

		// Set fingerprint if not exists
		if (!isset($_SESSION[self::FINGERPRINT_KEY])) {
			$_SESSION[self::FINGERPRINT_KEY] = $this->generateFingerprint();
		}

		return true;
	}

	/**
	 * Regenerate session ID
	 */
	public function regenerate(bool $deleteOldSession = true): bool
	{
		if (!$this->started) {
			return false;
		}

		if (!session_regenerate_id($deleteOldSession)) {
			return false;
		}

		$_SESSION['_last_regeneration'] = time();
		$_SESSION[self::FINGERPRINT_KEY] = $this->generateFingerprint();

		return true;
	}

	/**
	 * Destroy session
	 */
	public function destroy(): bool
	{
		if (!$this->started) {
			return true;
		}

		$_SESSION = [];

		if (ini_get('session.use_cookies')) {
			$params = session_get_cookie_params();
			setcookie(
				session_name(),
				'',
				time() - 42000,
				$params['path'],
				$params['domain'],
				$params['secure'],
				$params['httponly']
			);
		}

		session_destroy();
		$this->started = false;

		return true;
	}

	/**
	 * Set session value
	 */
	public function set(string $key, $value): void
	{
		if (!$this->started) {
			$this->start();
		}

		$_SESSION[$key] = $value;
	}

	/**
	 * Get session value
	 */
	public function get(string $key, $default = null)
	{
		if (!$this->started) {
			$this->start();
		}

		return $_SESSION[$key] ?? $default;
	}

	/**
	 * Check if session has key
	 */
	public function has(string $key): bool
	{
		if (!$this->started) {
			$this->start();
		}

		return isset($_SESSION[$key]);
	}

	/**
	 * Remove session value
	 */
	public function remove(string $key): void
	{
		if (!$this->started) {
			$this->start();
		}

		unset($_SESSION[$key]);
	}

	/**
	 * Flash message system
	 */
	public function flash(string $key, $value = null)
	{
		if (!$this->started) {
			$this->start();
		}

		if ($value === null) {
			// Get and remove flash message
			$value = $_SESSION['_flash'][$key] ?? null;
			unset($_SESSION['_flash'][$key]);
			return $value;
		}

		// Set flash message
		if (!isset($_SESSION['_flash'])) {
			$_SESSION['_flash'] = [];
		}

		$_SESSION['_flash'][$key] = $value;
	}

	/**
	 * Generate session fingerprint
	 */
	private function generateFingerprint(): string
	{
		$data = [
			$_SERVER['HTTP_USER_AGENT'] ?? '',
			$_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
			$_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
			$this->getClientIpSegment()
		];

		return hash('sha256', implode('|', $data));
	}

	/**
	 * Validate session fingerprint
	 */
	private function validateFingerprint(): bool
	{
		if (!isset($_SESSION[self::FINGERPRINT_KEY])) {
			return true; // First time
		}

		return hash_equals(
			$_SESSION[self::FINGERPRINT_KEY],
			$this->generateFingerprint()
		);
	}

	/**
	 * Check if session is expired
	 */
	private function isExpired(): bool
	{
		if (!isset($_SESSION[self::LAST_ACTIVITY_KEY])) {
			return false;
		}

		return (time() - $_SESSION[self::LAST_ACTIVITY_KEY]) > self::SESSION_LIFETIME;
	}

	/**
	 * Check if session should be regenerated
	 */
	private function shouldRegenerate(): bool
	{
		if (!isset($_SESSION['_last_regeneration'])) {
			return true;
		}

		return (time() - $_SESSION['_last_regeneration']) > self::REGENERATE_INTERVAL;
	}

	/**
	 * Get client IP segment for fingerprinting
	 */
	private function getClientIpSegment(): string
	{
		$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

		// For IPv4, use first 3 octets
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			$parts = explode('.', $ip);
			return implode('.', array_slice($parts, 0, 3));
		}

		// For IPv6, use first 4 segments
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			$parts = explode(':', $ip);
			return implode(':', array_slice($parts, 0, 4));
		}

		return $ip;
	}

	/**
	 * Check if connection is HTTPS
	 */
	private function isHttps(): bool
	{
		return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
			|| ($_SERVER['SERVER_PORT'] ?? 80) == 443
			|| (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
	}

	/**
	 * Set user authentication
	 */
	public function authenticate(array $userData): void
	{
		$this->set('authenticated', true);
		$this->set('user', $userData);
		$this->set('auth_time', time());
		$this->regenerate(true);
	}

	/**
	 * Check if user is authenticated
	 */
	public function isAuthenticated(): bool
	{
		return $this->get('authenticated', false) === true;
	}

	/**
	 * Get authenticated user data
	 */
	public function getUser(): ?array
	{
		if (!$this->isAuthenticated()) {
			return null;
		}

		return $this->get('user');
	}

	/**
	 * Logout user
	 */
	public function logout(): void
	{
		$this->remove('authenticated');
		$this->remove('user');
		$this->remove('auth_time');
		$this->regenerate(true);
	}
}
