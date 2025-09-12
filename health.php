<?php
/**
 * Health Check Endpoint for Coolify Monitoring
 * 
 * This endpoint provides health status information for the application
 * and can be used by Coolify for container health monitoring.
 */

// Set JSON content type
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Initialize response
$response = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => []
];

$hasErrors = false;

// Check 1: PHP Extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'gd', 'zip', 'intl', 'opcache'];
$missingExtensions = [];

foreach ($requiredExtensions as $extension) {
    if (!extension_loaded($extension)) {
        $missingExtensions[] = $extension;
    }
}

if (empty($missingExtensions)) {
    $response['checks']['php_extensions'] = [
        'status' => 'pass',
        'message' => 'All required PHP extensions are loaded'
    ];
} else {
    $response['checks']['php_extensions'] = [
        'status' => 'fail',
        'message' => 'Missing PHP extensions: ' . implode(', ', $missingExtensions)
    ];
    $hasErrors = true;
}

// Check 2: Database Connection
try {
    // Check if environment file exists
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        // Simple .env parser
        $envVars = [];
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $envVars[trim($key)] = trim($value, '"\'');
            }
        }
        
        // Try to connect to database
        $dbHost = $envVars['DB_HOST'] ?? getenv('DB_HOST');
        $dbPort = $envVars['DB_PORT'] ?? getenv('DB_PORT') ?? '3306';
        $dbName = $envVars['DB_NAME'] ?? getenv('DB_NAME');
        $dbUser = $envVars['DB_USERNAME'] ?? getenv('DB_USERNAME');
        $dbPass = $envVars['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
        
        if ($dbHost && $dbName && $dbUser) {
            $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ];
            
            $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
            
            // Test query
            $stmt = $pdo->query('SELECT 1');
            
            $response['checks']['database'] = [
                'status' => 'pass',
                'message' => 'Database connection successful'
            ];
        } else {
            $response['checks']['database'] = [
                'status' => 'warning',
                'message' => 'Database configuration incomplete'
            ];
        }
    } else {
        $response['checks']['database'] = [
            'status' => 'warning',
            'message' => 'Environment configuration not found'
        ];
    }
} catch (PDOException $e) {
    $response['checks']['database'] = [
        'status' => 'fail',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ];
    $hasErrors = true;
} catch (Exception $e) {
    $response['checks']['database'] = [
        'status' => 'fail',
        'message' => 'Database check error: ' . $e->getMessage()
    ];
    $hasErrors = true;
}

// Check 3: Writable Directories
$writableDirectories = [
    '/var/www/html/img/album' => 'Photo uploads directory',
    '/var/www/html/relatorios' => 'Reports directory',
    '/var/www/sessions' => 'Session storage directory'
];

foreach ($writableDirectories as $dir => $description) {
    if (is_dir($dir) && is_writable($dir)) {
        $response['checks']['writable_' . basename($dir)] = [
            'status' => 'pass',
            'message' => $description . ' is writable'
        ];
    } else {
        $response['checks']['writable_' . basename($dir)] = [
            'status' => 'fail',
            'message' => $description . ' is not writable or does not exist'
        ];
        $hasErrors = true;
    }
}

// Check 4: Memory Usage
$memoryLimit = ini_get('memory_limit');
$memoryUsage = memory_get_usage(true);
$memoryUsagePercent = 0;

// Convert memory limit to bytes
if (preg_match('/^(\d+)(.)$/', $memoryLimit, $matches)) {
    $memoryLimitBytes = $matches[1];
    switch ($matches[2]) {
        case 'G':
            $memoryLimitBytes *= 1024 * 1024 * 1024;
            break;
        case 'M':
            $memoryLimitBytes *= 1024 * 1024;
            break;
        case 'K':
            $memoryLimitBytes *= 1024;
            break;
    }
    $memoryUsagePercent = ($memoryUsage / $memoryLimitBytes) * 100;
}

$response['checks']['memory'] = [
    'status' => $memoryUsagePercent < 80 ? 'pass' : 'warning',
    'message' => sprintf('Memory usage: %.2f%% (%.2f MB / %s)', 
        $memoryUsagePercent,
        $memoryUsage / 1024 / 1024,
        $memoryLimit
    )
];

// Check 5: Disk Space
$freeSpace = disk_free_space('/');
$totalSpace = disk_total_space('/');
$usedSpacePercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;

$response['checks']['disk_space'] = [
    'status' => $usedSpacePercent < 90 ? 'pass' : 'warning',
    'message' => sprintf('Disk usage: %.2f%% (%.2f GB free)', 
        $usedSpacePercent,
        $freeSpace / 1024 / 1024 / 1024
    )
];

// Check 6: OPcache Status
if (function_exists('opcache_get_status')) {
    $opcacheStatus = opcache_get_status();
    if ($opcacheStatus && isset($opcacheStatus['opcache_enabled']) && $opcacheStatus['opcache_enabled']) {
        $response['checks']['opcache'] = [
            'status' => 'pass',
            'message' => 'OPcache is enabled and running'
        ];
    } else {
        $response['checks']['opcache'] = [
            'status' => 'warning',
            'message' => 'OPcache is not enabled'
        ];
    }
} else {
    $response['checks']['opcache'] = [
        'status' => 'warning',
        'message' => 'OPcache extension not available'
    ];
}

// Check 7: Application Files
$criticalFiles = [
    '/var/www/html/index.php',
    '/var/www/html/startup.php',
    '/var/www/html/models/DAO.php',
    '/var/www/html/vendor/autoload.php'
];

$missingFiles = [];
foreach ($criticalFiles as $file) {
    if (!file_exists($file)) {
        $missingFiles[] = basename($file);
    }
}

if (empty($missingFiles)) {
    $response['checks']['application_files'] = [
        'status' => 'pass',
        'message' => 'All critical application files present'
    ];
} else {
    $response['checks']['application_files'] = [
        'status' => 'fail',
        'message' => 'Missing critical files: ' . implode(', ', $missingFiles)
    ];
    $hasErrors = true;
}

// Overall Status
if ($hasErrors) {
    $response['status'] = 'unhealthy';
    http_response_code(503); // Service Unavailable
} else {
    $response['status'] = 'healthy';
    http_response_code(200); // OK
}

// Add version information
$response['version'] = [
    'php' => PHP_VERSION,
    'app' => '1.0.0' // You can update this with your actual app version
];

// Add uptime if available
if (function_exists('posix_times')) {
    $times = posix_times();
    if ($times) {
        $response['uptime'] = $times['ticks'] / 100; // Convert to seconds
    }
}

// Output JSON response
echo json_encode($response, JSON_PRETTY_PRINT);
exit;