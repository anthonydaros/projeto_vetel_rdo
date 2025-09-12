<?php
/**
 * Initial Setup Script
 * Run this once to create the first admin user
 * DELETE THIS FILE AFTER SETUP!
 */

require_once dirname(__DIR__) . '/config/Config.php';
require_once dirname(__DIR__) . '/auth/Auth.php';
require_once dirname(__DIR__) . '/models/Connection.php';

use Config\Config;
use Auth\Auth;
use Models\Connection;

Config::load();

// Security check - only run in CLI or with setup token
$setupToken = 'CHANGE_THIS_TOKEN_' . date('Ymd');
$isCliMode = php_sapi_name() === 'cli';
$hasValidToken = isset($_GET['token']) && $_GET['token'] === $setupToken;

if (!$isCliMode && !$hasValidToken) {
	die('Access denied. Run from CLI or provide valid setup token.');
}

$message = '';
$error = '';

// Create tables
try {
	$pdo = Connection::getPDO();

	if (!$pdo) {
		throw new Exception('Database connection failed');
	}

	// Read and execute SQL file
	$sqlFile = dirname(__DIR__) . '/sql/auth_tables.sql';

	if (!file_exists($sqlFile)) {
		// Create tables directly if SQL file doesn't exist
		$sql = "
        CREATE TABLE IF NOT EXISTS `usuario` (
            `id_usuario` INT PRIMARY KEY AUTO_INCREMENT,
            `login` VARCHAR(50) UNIQUE NOT NULL,
            `senha` VARCHAR(255) NOT NULL,
            `nome` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100),
            `nivel_acesso` INT DEFAULT 1 COMMENT '1=User, 2=Supervisor, 3=Admin',
            `ativo` TINYINT DEFAULT 1,
            `data_criacao` DATETIME NOT NULL,
            `data_ultimo_acesso` DATETIME,
            `tentativas_login` INT DEFAULT 0,
            `bloqueado_ate` DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        
        CREATE TABLE IF NOT EXISTS `log_acesso` (
            `id_log` INT PRIMARY KEY AUTO_INCREMENT,
            `fk_id_usuario` INT,
            `acao` VARCHAR(50) NOT NULL,
            `sucesso` TINYINT NOT NULL,
            `detalhes` TEXT,
            `ip_address` VARCHAR(45),
            `user_agent` TEXT,
            `data_hora` DATETIME NOT NULL,
            FOREIGN KEY (`fk_id_usuario`) REFERENCES `usuario`(`id_usuario`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";

		$pdo->exec($sql);
	}

	$message .= "✓ Database tables created successfully\n";

	// Create default admin user
	$auth = Auth::getInstance();

	// Check if admin already exists
	$stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE login = 'admin'");
	$stmt->execute();

	if ($stmt->fetchColumn() == 0) {
		// Create admin user with default password
		$defaultPassword = 'admin123';
		$hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

		$stmt = $pdo->prepare("
            INSERT INTO usuario (login, senha, nome, email, nivel_acesso, ativo, data_criacao) 
            VALUES ('admin', :password, 'Administrator', 'admin@example.com', 3, 1, NOW())
        ");

		if ($stmt->execute(['password' => $hashedPassword])) {
			$message .= "✓ Admin user created successfully\n";
			$message .= "  Username: admin\n";
			$message .= "  Password: $defaultPassword\n";
			$message .= "  ⚠️  CHANGE THIS PASSWORD IMMEDIATELY!\n";
		} else {
			$error .= "✗ Failed to create admin user\n";
		}
	} else {
		$message .= "ℹ Admin user already exists\n";
	}

	// Create logs directory
	$logsDir = dirname(__DIR__) . '/logs';
	if (!is_dir($logsDir)) {
		if (mkdir($logsDir, 0755, true)) {
			$message .= "✓ Logs directory created\n";
		} else {
			$error .= "✗ Failed to create logs directory\n";
		}
	}
} catch (Exception $e) {
	$error = 'Setup failed: ' . $e->getMessage();
}

// Display results
if ($isCliMode) {
	echo "\n=== RDO System Setup ===\n\n";
	if ($message) {
		echo $message;
	}
	if ($error) {
		echo "\nErrors:\n$error";
	}
	echo "\n=== Setup Complete ===\n";
	echo "\n⚠️  IMPORTANT: Delete this setup file after completion!\n\n";
} else {
	?>
    <!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <title>Setup - Sistema RDO</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="../css/bootstrap4.5.2.min.css">
    </head>
    <body>
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3>RDO System Setup</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($message): ?>
                                <div class="alert alert-success">
                                    <pre><?php echo htmlspecialchars($message); ?></pre>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <pre><?php echo htmlspecialchars($error); ?></pre>
                                </div>
                            <?php endif; ?>
                            
                            <div class="alert alert-warning">
                                <strong>⚠️ IMPORTANT:</strong> Delete this setup file after completion!
                            </div>
                            
                            <a href="../login.php" class="btn btn-primary">Go to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>