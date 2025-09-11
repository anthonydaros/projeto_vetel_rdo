<?php
declare(strict_types=1);

namespace Auth;

/**
 * CSRF Protection Class
 * Provides Cross-Site Request Forgery protection for forms
 */
class CSRF
{
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_LENGTH = 32;
    
    /**
     * Generate a new CSRF token
     */
    public static function generateToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION[self::TOKEN_NAME] = $token;
        
        return $token;
    }
    
    /**
     * Get the current CSRF token or generate a new one
     */
    public static function getToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return self::generateToken();
        }
        
        return $_SESSION[self::TOKEN_NAME];
    }
    
    /**
     * Validate a CSRF token
     */
    public static function validateToken(string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }
        
        return hash_equals($_SESSION[self::TOKEN_NAME], $token);
    }
    
    /**
     * Generate HTML input field with CSRF token
     */
    public static function getTokenField(): string
    {
        $token = self::getToken();
        return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Verify CSRF token from POST request
     */
    public static function verifyPost(): bool
    {
        if (!isset($_POST[self::TOKEN_NAME])) {
            return false;
        }
        
        return self::validateToken($_POST[self::TOKEN_NAME]);
    }
    
    /**
     * Clear the CSRF token
     */
    public static function clearToken(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        unset($_SESSION[self::TOKEN_NAME]);
    }
}