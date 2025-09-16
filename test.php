<?php
// Test file for Coolify deployment
header('Content-Type: text/plain');
echo "RDO System - Server Test\n";
echo "========================\n\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Server Time: " . date('Y-m-d H:i:s') . "\n";
echo "Server Name: " . $_SERVER['SERVER_NAME'] . "\n";
echo "Server Port: " . $_SERVER['SERVER_PORT'] . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n\n";

// Test database connection
echo "Database Test:\n";
try {
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: '3306';
    $db = getenv('DB_DATABASE');
    $user = getenv('DB_USERNAME');
    $pass = getenv('DB_PASSWORD');

    if ($host && $db && $user) {
        echo "- Host: $host:$port\n";
        echo "- Database: $db\n";
        echo "- Attempting connection...\n";

        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        echo "- Connection: SUCCESS\n";

        // Test query
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = '$db'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "- Tables found: " . $result['count'] . "\n";
    } else {
        echo "- Database configuration not found in environment\n";
    }
} catch (Exception $e) {
    echo "- Database Error: " . $e->getMessage() . "\n";
}

echo "\nDirectory Permissions:\n";
$dirs = [
    '/var/www/html' => 'App Root',
    '/var/www/html/img/album' => 'Photos',
    '/var/www/html/relatorios' => 'Reports'
];

foreach ($dirs as $dir => $name) {
    if (file_exists($dir)) {
        $perms = is_writable($dir) ? 'WRITABLE' : 'READ-ONLY';
        echo "- $name ($dir): $perms\n";
    } else {
        echo "- $name ($dir): NOT FOUND\n";
    }
}

echo "\nStatus: ONLINE\n";
?>