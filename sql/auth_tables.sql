-- Authentication System Tables
-- Run this SQL to add authentication to your database

-- User table
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

-- Access log table
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

-- Create indexes for better performance
CREATE INDEX idx_usuario_login ON usuario(login);
CREATE INDEX idx_usuario_ativo ON usuario(ativo);
CREATE INDEX idx_log_acesso_usuario ON log_acesso(fk_id_usuario);
CREATE INDEX idx_log_acesso_data ON log_acesso(data_hora);

-- Insert default admin user (password: admin123)
-- IMPORTANT: Change this password immediately after first login!
INSERT INTO `usuario` (`login`, `senha`, `nome`, `email`, `nivel_acesso`, `ativo`, `data_criacao`) 
VALUES (
    'admin', 
    '$2y$10$YourHashedPasswordHere', -- Replace with actual hashed password
    'Administrator',
    'admin@example.com',
    3,
    1,
    NOW()
);

-- Note: To generate a password hash, use PHP:
-- echo password_hash('your_password', PASSWORD_DEFAULT);