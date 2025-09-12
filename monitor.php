<?php
/**
 * Container Monitoring Script
 * Provides detailed diagnostics and real-time monitoring
 */

// CLI colors for better visibility
class Colors {
    const RED = "\033[0;31m";
    const GREEN = "\033[0;32m";
    const YELLOW = "\033[1;33m";
    const BLUE = "\033[0;34m";
    const MAGENTA = "\033[0;35m";
    const CYAN = "\033[0;36m";
    const NC = "\033[0m"; // No Color
}

// Helper function for colored output
function log_message($message, $color = Colors::NC) {
    $timestamp = date('Y-m-d H:i:s');
    echo "{$color}[{$timestamp}] {$message}" . Colors::NC . PHP_EOL;
}

// Header
echo str_repeat("=", 50) . PHP_EOL;
log_message("PROJETO VETEL - CONTAINER MONITOR", Colors::CYAN);
log_message("Real-time System Diagnostics", Colors::CYAN);
echo str_repeat("=", 50) . PHP_EOL . PHP_EOL;

// 1. System Information
log_message("SYSTEM INFORMATION:", Colors::YELLOW);
log_message("  PHP Version: " . PHP_VERSION);
log_message("  SAPI: " . PHP_SAPI);
log_message("  OS: " . PHP_OS);
log_message("  Server: " . $_SERVER['SERVER_SOFTWARE'] ?? 'CLI');
log_message("  Hostname: " . gethostname());
echo PHP_EOL;

// 2. Memory Usage
log_message("MEMORY USAGE:", Colors::YELLOW);
$memory_usage = memory_get_usage(true);
$memory_peak = memory_get_peak_usage(true);
$memory_limit = ini_get('memory_limit');

log_message("  Current: " . formatBytes($memory_usage));
log_message("  Peak: " . formatBytes($memory_peak));
log_message("  Limit: " . $memory_limit);

// Memory percentage
if (preg_match('/^(\d+)(.)/', $memory_limit, $matches)) {
    $limit_bytes = $matches[1];
    switch ($matches[2]) {
        case 'G': $limit_bytes *= 1024 * 1024 * 1024; break;
        case 'M': $limit_bytes *= 1024 * 1024; break;
        case 'K': $limit_bytes *= 1024; break;
    }
    $percent = ($memory_usage / $limit_bytes) * 100;
    $color = $percent < 50 ? Colors::GREEN : ($percent < 80 ? Colors::YELLOW : Colors::RED);
    log_message("  Usage: " . sprintf("%.2f%%", $percent), $color);
}
echo PHP_EOL;

// 3. Disk Usage
log_message("DISK USAGE:", Colors::YELLOW);
$paths = [
    '/var/www/html' => 'Application',
    '/var/www/html/img/album' => 'Uploads',
    '/var/www/html/relatorios' => 'Reports',
    '/var/www/sessions' => 'Sessions'
];

foreach ($paths as $path => $label) {
    if (is_dir($path)) {
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        $used = $total - $free;
        $percent = ($used / $total) * 100;
        
        $color = $percent < 60 ? Colors::GREEN : ($percent < 80 ? Colors::YELLOW : Colors::RED);
        log_message(sprintf("  %s: %.2f%% (Free: %s)", 
            $label, 
            $percent, 
            formatBytes($free)
        ), $color);
    }
}
echo PHP_EOL;

// 4. Directory Permissions
log_message("DIRECTORY PERMISSIONS:", Colors::YELLOW);
$directories = [
    '/var/www/html/img/album' => 'Photo Uploads',
    '/var/www/html/relatorios' => 'Reports',
    '/var/www/sessions' => 'Sessions',
    '/var/log/apache2' => 'Logs'
];

foreach ($directories as $dir => $desc) {
    if (file_exists($dir)) {
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        $owner = posix_getpwuid(fileowner($dir))['name'] ?? 'unknown';
        $group = posix_getgrgid(filegroup($dir))['name'] ?? 'unknown';
        $writable = is_writable($dir);
        
        $status = $writable ? '✓' : '✗';
        $color = $writable ? Colors::GREEN : Colors::RED;
        
        log_message("  {$status} {$desc}: {$perms} ({$owner}:{$group})", $color);
    } else {
        log_message("  ✗ {$desc}: NOT FOUND", Colors::RED);
    }
}
echo PHP_EOL;

// 5. Database Connection
log_message("DATABASE CONNECTION:", Colors::YELLOW);
$envFile = '/var/www/html/.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
    
    if (isset($env['DB_HOST'])) {
        try {
            $dsn = sprintf("mysql:host=%s;port=%s;dbname=%s",
                $env['DB_HOST'],
                $env['DB_PORT'] ?? 3306,
                $env['DB_NAME'] ?? ''
            );
            
            $start = microtime(true);
            $pdo = new PDO($dsn, $env['DB_USERNAME'], $env['DB_PASSWORD'], [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $latency = (microtime(true) - $start) * 1000;
            
            // Test query
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '{$env['DB_NAME']}'");
            $tables = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            log_message("  ✓ Connected to {$env['DB_HOST']}", Colors::GREEN);
            log_message("  Latency: " . sprintf("%.2fms", $latency));
            log_message("  Database: {$env['DB_NAME']}");
            log_message("  Tables: {$tables}");
            
            // Check specific tables
            $required_tables = ['empresa', 'obra', 'diario_obra', 'funcionario', 'imagem', 'servico'];
            $missing = [];
            foreach ($required_tables as $table) {
                $result = $pdo->query("SHOW TABLES LIKE '{$table}'")->rowCount();
                if ($result == 0) {
                    $missing[] = $table;
                }
            }
            
            if (!empty($missing)) {
                log_message("  ⚠ Missing tables: " . implode(', ', $missing), Colors::YELLOW);
            } else {
                log_message("  ✓ All required tables present", Colors::GREEN);
            }
            
        } catch (PDOException $e) {
            log_message("  ✗ Connection failed: " . $e->getMessage(), Colors::RED);
        }
    } else {
        log_message("  ⚠ Database not configured in .env", Colors::YELLOW);
    }
} else {
    log_message("  ✗ .env file not found", Colors::RED);
}
echo PHP_EOL;

// 6. PHP Configuration
log_message("PHP CONFIGURATION:", Colors::YELLOW);
$important_settings = [
    'max_execution_time' => 'Max Execution Time',
    'max_input_time' => 'Max Input Time',
    'memory_limit' => 'Memory Limit',
    'post_max_size' => 'POST Max Size',
    'upload_max_filesize' => 'Upload Max Filesize',
    'max_file_uploads' => 'Max File Uploads',
    'session.gc_maxlifetime' => 'Session Lifetime',
    'date.timezone' => 'Timezone'
];

foreach ($important_settings as $key => $label) {
    $value = ini_get($key);
    log_message("  {$label}: {$value}");
}
echo PHP_EOL;

// 7. Recent Uploads
log_message("RECENT UPLOADS (Last 5):", Colors::YELLOW);
$upload_dir = '/var/www/html/img/album';
if (is_dir($upload_dir)) {
    $files = glob($upload_dir . '/*');
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    $recent = array_slice($files, 0, 5);
    if (empty($recent)) {
        log_message("  No uploads found");
    } else {
        foreach ($recent as $file) {
            $size = formatBytes(filesize($file));
            $time = date('Y-m-d H:i:s', filemtime($file));
            $name = basename($file);
            log_message("  • {$name} ({$size}) - {$time}");
        }
    }
} else {
    log_message("  ✗ Upload directory not found", Colors::RED);
}
echo PHP_EOL;

// 8. Process Information
log_message("PROCESS INFORMATION:", Colors::YELLOW);
if (function_exists('posix_getpid')) {
    log_message("  PID: " . posix_getpid());
    log_message("  UID: " . posix_getuid());
    log_message("  User: " . posix_getpwuid(posix_getuid())['name']);
}

// Apache processes
$apache_procs = shell_exec("ps aux | grep apache2 | grep -v grep | wc -l");
log_message("  Apache Processes: " . trim($apache_procs));
echo PHP_EOL;

// 9. Network Status
log_message("NETWORK STATUS:", Colors::YELLOW);
$hostname = gethostname();
$ip = gethostbyname($hostname);
log_message("  Hostname: {$hostname}");
log_message("  Internal IP: {$ip}");

// Test external connectivity
$external_test = @file_get_contents('http://ipinfo.io/ip', false, stream_context_create([
    'http' => ['timeout' => 2]
]));
if ($external_test) {
    log_message("  External IP: " . trim($external_test));
    log_message("  ✓ Internet connectivity OK", Colors::GREEN);
} else {
    log_message("  ⚠ No internet connectivity", Colors::YELLOW);
}
echo PHP_EOL;

// 10. Error Log Analysis
log_message("RECENT ERRORS (Last 10):", Colors::YELLOW);
$error_log = '/var/log/apache2/error.log';
if (file_exists($error_log)) {
    $errors = shell_exec("tail -10 {$error_log} 2>/dev/null");
    if ($errors) {
        $lines = explode("\n", trim($errors));
        foreach ($lines as $line) {
            if (!empty($line)) {
                // Color code by error level
                if (strpos($line, 'error') !== false || strpos($line, 'Error') !== false) {
                    log_message("  " . substr($line, 0, 100) . "...", Colors::RED);
                } elseif (strpos($line, 'warning') !== false || strpos($line, 'Warning') !== false) {
                    log_message("  " . substr($line, 0, 100) . "...", Colors::YELLOW);
                } else {
                    log_message("  " . substr($line, 0, 100) . "...");
                }
            }
        }
    } else {
        log_message("  No recent errors", Colors::GREEN);
    }
} else {
    log_message("  Error log not found");
}
echo PHP_EOL;

// Summary
echo str_repeat("=", 50) . PHP_EOL;
log_message("MONITORING COMPLETE", Colors::CYAN);
log_message("Timestamp: " . date('Y-m-d H:i:s'), Colors::CYAN);
echo str_repeat("=", 50) . PHP_EOL;

// Helper function
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}