<?php
/**
 * Database Migration Script
 * Migrates the RDO system to the new external database
 */

require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/models/Connection.php';

use Config\Config;
use Models\Connection;

// Load configuration
Config::load();

echo "=== RDO Database Migration Script ===\n\n";

// Test connection to new database
echo "1. Testing database connection...\n";
try {
    $pdo = Connection::getPDO();
    if (!$pdo) {
        throw new Exception("Failed to connect to database");
    }
    echo "   ✓ Connected successfully to " . Config::get('DB_HOST') . ":" . Config::get('DB_PORT') . "\n";
    echo "   ✓ Database: " . Config::get('DB_DATABASE') . "\n\n";
} catch (Exception $e) {
    die("   ✗ Connection failed: " . $e->getMessage() . "\n");
}

// Create tables for RDO system
echo "2. Creating RDO system tables...\n";

// Drop existing tables if any (be careful!)
$dropTables = [
    'servico',
    'funcionario_diario_obra',
    'imagem',
    'diario_obra',
    'funcionario',
    'obra',
    'empresa',
    'log_acesso',
    'usuario'
];

foreach ($dropTables as $table) {
    try {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
        echo "   - Dropped table if exists: $table\n";
    } catch (PDOException $e) {
        echo "   - Warning dropping $table: " . $e->getMessage() . "\n";
    }
}

echo "\n3. Creating new tables...\n";

// Create empresa table
$sql = "
CREATE TABLE `empresa` (
    `id_empresa` INT PRIMARY KEY AUTO_INCREMENT,
    `nome_fantasia` VARCHAR(50) UNIQUE NOT NULL,
    `contratante_sn` TINYINT DEFAULT 1,
    `url_logo` VARCHAR(200) UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";
try {
    $pdo->exec($sql);
    echo "   ✓ Created table: empresa\n";
} catch (PDOException $e) {
    echo "   ✗ Error creating empresa: " . $e->getMessage() . "\n";
}

// Create obra table
$sql = "
CREATE TABLE `obra` (
    `id_obra` INT PRIMARY KEY AUTO_INCREMENT,
    `fk_id_contratante` INT NOT NULL,
    `fk_id_contratada` INT NOT NULL,
    `descricao_resumo` VARCHAR(500),
    FOREIGN KEY (`fk_id_contratante`) REFERENCES `empresa`(`id_empresa`) ON DELETE CASCADE,
    FOREIGN KEY (`fk_id_contratada`) REFERENCES `empresa`(`id_empresa`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";
try {
    $pdo->exec($sql);
    echo "   ✓ Created table: obra\n";
} catch (PDOException $e) {
    echo "   ✗ Error creating obra: " . $e->getMessage() . "\n";
}

// Create funcionario table
$sql = "
CREATE TABLE `funcionario` (
    `id_funcionario` INT PRIMARY KEY AUTO_INCREMENT,
    `fk_id_empresa` INT,
    `nome` VARCHAR(250),
    `cargo` VARCHAR(50) NOT NULL,
    FOREIGN KEY (`fk_id_empresa`) REFERENCES `empresa`(`id_empresa`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";
try {
    $pdo->exec($sql);
    echo "   ✓ Created table: funcionario\n";
} catch (PDOException $e) {
    echo "   ✗ Error creating funcionario: " . $e->getMessage() . "\n";
}

// Create diario_obra table
$sql = "
CREATE TABLE `diario_obra` (
    `id_diario_obra` INT PRIMARY KEY AUTO_INCREMENT,
    `numero_diario` INT NOT NULL,
    `fk_id_obra` INT NOT NULL,
    `data` DATE NOT NULL,
    `obs_gerais` TEXT,
    `horario_trabalho` VARCHAR(50),
    `carga_horas_dia` FLOAT(10, 2),
    `total_horas` FLOAT(10, 2),
    FOREIGN KEY (`fk_id_obra`) REFERENCES `obra`(`id_obra`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";
try {
    $pdo->exec($sql);
    echo "   ✓ Created table: diario_obra\n";
} catch (PDOException $e) {
    echo "   ✗ Error creating diario_obra: " . $e->getMessage() . "\n";
}

// Create imagem table
$sql = "
CREATE TABLE `imagem` (
    `id_imagem` INT PRIMARY KEY AUTO_INCREMENT,
    `fk_id_diario_obra` INT NOT NULL,
    `url` VARCHAR(300) UNIQUE,
    FOREIGN KEY (`fk_id_diario_obra`) REFERENCES `diario_obra`(`id_diario_obra`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";
try {
    $pdo->exec($sql);
    echo "   ✓ Created table: imagem\n";
} catch (PDOException $e) {
    echo "   ✗ Error creating imagem: " . $e->getMessage() . "\n";
}

// Create funcionario_diario_obra table (fixed typo)
$sql = "
CREATE TABLE `funcionario_diario_obra` (
    `id_funcionario_diario_obra` INT PRIMARY KEY AUTO_INCREMENT,
    `fk_id_funcionario` INT NOT NULL,
    `fk_id_diario_obra` INT NOT NULL,
    `data` DATE,
    `horario_trabalho` VARCHAR(50),
    `horas_trabalhadas` FLOAT(10, 2),
    FOREIGN KEY (`fk_id_funcionario`) REFERENCES `funcionario`(`id_funcionario`) ON DELETE CASCADE,
    FOREIGN KEY (`fk_id_diario_obra`) REFERENCES `diario_obra`(`id_diario_obra`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";
try {
    $pdo->exec($sql);
    echo "   ✓ Created table: funcionario_diario_obra\n";
} catch (PDOException $e) {
    echo "   ✗ Error creating funcionario_diario_obra: " . $e->getMessage() . "\n";
}

// Create servico table
$sql = "
CREATE TABLE `servico` (
    `id_servico` INT PRIMARY KEY AUTO_INCREMENT,
    `fk_id_diario_obra` INT NOT NULL,
    `descricao` VARCHAR(500),
    FOREIGN KEY (`fk_id_diario_obra`) REFERENCES `diario_obra`(`id_diario_obra`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";
try {
    $pdo->exec($sql);
    echo "   ✓ Created table: servico\n";
} catch (PDOException $e) {
    echo "   ✗ Error creating servico: " . $e->getMessage() . "\n";
}

// Create authentication tables
echo "\n4. Creating authentication tables...\n";

// Create usuario table
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";
try {
    $pdo->exec($sql);
    echo "   ✓ Created table: usuario\n";
} catch (PDOException $e) {
    echo "   ✗ Error creating usuario: " . $e->getMessage() . "\n";
}

// Create log_acesso table
$sql = "
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";
try {
    $pdo->exec($sql);
    echo "   ✓ Created table: log_acesso\n";
} catch (PDOException $e) {
    echo "   ✗ Error creating log_acesso: " . $e->getMessage() . "\n";
}

// Create indexes
echo "\n5. Creating indexes...\n";
$indexes = [
    "CREATE INDEX idx_usuario_login ON usuario(login)",
    "CREATE INDEX idx_usuario_ativo ON usuario(ativo)",
    "CREATE INDEX idx_log_acesso_usuario ON log_acesso(fk_id_usuario)",
    "CREATE INDEX idx_log_acesso_data ON log_acesso(data_hora)"
];

foreach ($indexes as $index) {
    try {
        $pdo->exec($index);
        echo "   ✓ Created index\n";
    } catch (PDOException $e) {
        // Index might already exist
        echo "   - Index note: " . $e->getMessage() . "\n";
    }
}

// Create default admin user
echo "\n6. Creating default admin user...\n";
$stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario WHERE login = 'admin'");
$stmt->execute();

if ($stmt->fetchColumn() == 0) {
    $defaultPassword = 'admin123';
    $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO usuario (login, senha, nome, email, nivel_acesso, ativo, data_criacao) 
        VALUES ('admin', :password, 'Administrator', 'admin@example.com', 3, 1, NOW())
    ");
    
    if ($stmt->execute(['password' => $hashedPassword])) {
        echo "   ✓ Admin user created\n";
        echo "   Username: admin\n";
        echo "   Password: $defaultPassword\n";
        echo "   ⚠️  CHANGE THIS PASSWORD IMMEDIATELY!\n";
    } else {
        echo "   ✗ Failed to create admin user\n";
    }
} else {
    echo "   ℹ Admin user already exists\n";
}

// Check if there's data to import from the old database
echo "\n7. Checking for existing data to migrate...\n";

// Check if the SQL file has RDO data (not WordPress data)
$sqlFile = __DIR__ . '/u447438965_rEW7E.20250911174218.sql';
if (file_exists($sqlFile)) {
    $sqlContent = file_get_contents($sqlFile);
    
    // Check if it contains RDO tables
    if (strpos($sqlContent, 'CREATE TABLE `empresa`') !== false ||
        strpos($sqlContent, 'INSERT INTO `empresa`') !== false) {
        echo "   ℹ Found RDO data in SQL file\n";
        echo "   To import: mysql -h " . Config::get('DB_HOST') . " -P " . Config::get('DB_PORT') . 
              " -u " . Config::get('DB_USERNAME') . " -p " . Config::get('DB_DATABASE') . " < $sqlFile\n";
    } else {
        echo "   ⚠️  SQL file contains WordPress data, not RDO data\n";
        echo "   ℹ System will start with empty database\n";
    }
} else {
    echo "   ℹ No SQL backup file found\n";
}

// Test the tables
echo "\n8. Verifying tables...\n";
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "   Tables created: " . implode(', ', $tables) . "\n";

echo "\n=== Migration Complete ===\n";
echo "\nNext steps:\n";
echo "1. Test the application by visiting: http://localhost/login.php\n";
echo "2. Login with: admin / admin123\n";
echo "3. Change the admin password immediately\n";
echo "4. If you have RDO data to import, use the appropriate SQL file\n";
?>