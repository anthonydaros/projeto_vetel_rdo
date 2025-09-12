#!/usr/bin/env php
<?php
/**
 * Servidor PHP de desenvolvimento com configurações personalizadas
 * Uso: php server.php
 */

// Cria arquivo de configuração temporário
$iniConfig = <<<INI
upload_max_filesize = 10M
post_max_size = 100M
max_file_uploads = 20
memory_limit = 256M
max_execution_time = 300
max_input_time = 300
display_errors = On
error_reporting = E_ALL
INI;

$tempIniFile = sys_get_temp_dir() . '/php_server_config.ini';
file_put_contents($tempIniFile, $iniConfig);

// Inicia servidor com configurações personalizadas
$port = 8000;
$docRoot = __DIR__;

echo "Iniciando servidor PHP com configurações personalizadas...\n";
echo "URL: http://localhost:$port\n";
echo "Limite de upload: 10MB\n";
echo "Pressione Ctrl+C para parar\n\n";

// Executa servidor com arquivo de configuração personalizado
$command = sprintf(
    'php -c %s -S localhost:%d -t %s',
    escapeshellarg($tempIniFile),
    $port,
    escapeshellarg($docRoot)
);

passthru($command);

// Limpa arquivo temporário ao sair
unlink($tempIniFile);