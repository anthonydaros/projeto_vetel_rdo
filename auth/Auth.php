<?php
declare(strict_types=1);

namespace Auth;

require_once __DIR__ . '/../config/Config.php';
require_once __DIR__ . '/../models/Connection.php';

use Models\Connection;
use Config\Config;

/**
 * Basic Authentication System
 * Provides user authentication and session management
 */
class Auth
{
    private static ?Auth $instance = null;
    private ?\PDO $db = null;
    
    private function __construct()
    {
        $this->db = Connection::getPDO();
        $this->initSession();
    }
    
    /**
     * Get Auth instance (Singleton pattern)
     */
    public static function getInstance(): Auth
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize session with secure settings
     */
    private function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session parameters
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_strict_mode', '1');
            
            session_set_cookie_params([
                'lifetime' => (int)Config::get('SESSION_LIFETIME', 120) * 60,
                'path' => '/',
                'domain' => '',
                'secure' => Config::get('SESSION_SECURE_COOKIE', false),
                'httponly' => Config::get('SESSION_HTTP_ONLY', true),
                'samesite' => 'Lax'
            ]);
            
            session_start();
            
            // Regenerate session ID periodically for security
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) {
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
        }
    }
    
    /**
     * Authenticate user with username and password
     * 
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function login(string $username, string $password): bool
    {
        if (!$this->db) {
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT id_usuario, nome, senha, nivel_acesso, ativo 
                FROM usuario 
                WHERE login = :username AND ativo = 1
            ");
            
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['senha'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id_usuario'];
                $_SESSION['user_name'] = $user['nome'];
                $_SESSION['user_level'] = $user['nivel_acesso'];
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                
                // Log successful login
                $this->logAccess($user['id_usuario'], 'login', true);
                
                return true;
            }
            
            // Log failed login attempt
            $this->logAccess(null, 'login_failed', false, $username);
            
        } catch (\PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Logout user and destroy session
     */
    public function logout(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->logAccess($_SESSION['user_id'], 'logout', true);
        }
        
        // Unset all session variables
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Check if user is logged in
     * 
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            return false;
        }
        
        // Check session timeout
        $sessionLifetime = (int)Config::get('SESSION_LIFETIME', 120) * 60;
        if (time() - $_SESSION['last_activity'] > $sessionLifetime) {
            $this->logout();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Check if user has required access level
     * 
     * @param int $requiredLevel
     * @return bool
     */
    public function hasAccessLevel(int $requiredLevel): bool
    {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        return isset($_SESSION['user_level']) && $_SESSION['user_level'] >= $requiredLevel;
    }
    
    /**
     * Get current user ID
     * 
     * @return int|null
     */
    public function getUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current user name
     * 
     * @return string|null
     */
    public function getUserName(): ?string
    {
        return $_SESSION['user_name'] ?? null;
    }
    
    /**
     * Create new user (admin function)
     * 
     * @param string $username
     * @param string $password
     * @param string $name
     * @param int $accessLevel
     * @return bool
     */
    public function createUser(string $username, string $password, string $name, int $accessLevel = 1): bool
    {
        if (!$this->db) {
            return false;
        }
        
        try {
            // Check if username already exists
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM usuario WHERE login = :username");
            $stmt->execute(['username' => $username]);
            
            if ($stmt->fetchColumn() > 0) {
                return false; // Username already exists
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $stmt = $this->db->prepare("
                INSERT INTO usuario (login, senha, nome, nivel_acesso, ativo, data_criacao) 
                VALUES (:username, :password, :name, :level, 1, NOW())
            ");
            
            return $stmt->execute([
                'username' => $username,
                'password' => $hashedPassword,
                'name' => $name,
                'level' => $accessLevel
            ]);
            
        } catch (\PDOException $e) {
            error_log('Create user error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log access attempts
     * 
     * @param int|null $userId
     * @param string $action
     * @param bool $success
     * @param string|null $details
     */
    private function logAccess(?int $userId, string $action, bool $success, ?string $details = null): void
    {
        if (!$this->db) {
            return;
        }
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO log_acesso (fk_id_usuario, acao, sucesso, detalhes, ip_address, user_agent, data_hora) 
                VALUES (:user_id, :action, :success, :details, :ip, :agent, NOW())
            ");
            
            $stmt->execute([
                'user_id' => $userId,
                'action' => $action,
                'success' => $success ? 1 : 0,
                'details' => $details,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (\PDOException $e) {
            error_log('Access log error: ' . $e->getMessage());
        }
    }
    
    /**
     * Require authentication for a page
     * Redirects to login if not authenticated
     * 
     * @param string $loginUrl
     */
    public static function requireAuth(string $loginUrl = '/login.php'): void
    {
        $auth = self::getInstance();
        
        if (!$auth->isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header("Location: $loginUrl");
            exit;
        }
    }
    
    /**
     * Require specific access level for a page
     * 
     * @param int $level
     * @param string $errorUrl
     */
    public static function requireAccessLevel(int $level, string $errorUrl = '/403.php'): void
    {
        $auth = self::getInstance();
        
        if (!$auth->hasAccessLevel($level)) {
            header("Location: $errorUrl");
            exit;
        }
    }
}