<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mostra todos os erros
echo "PHP Version: " . phpversion() . "\n";
echo "Testing includes...\n";

// Testa cada include individualmente
echo "1. Testing bootstrap.php: ";
if (file_exists(__DIR__ . '/bootstrap.php')) {
    require_once __DIR__ . '/bootstrap.php';
    echo "OK\n";
} else {
    echo "NOT FOUND\n";
}

echo "2. Testing startup.php: ";
if (file_exists(__DIR__ . '/startup.php')) {
    require_once __DIR__ . '/startup.php';
    echo "OK\n";
} else {
    echo "NOT FOUND\n";
}

echo "3. Testing ftpFunctions.php: ";
if (file_exists(__DIR__ . '/ftpFunctions.php')) {
    require_once __DIR__ . '/ftpFunctions.php';
    echo "OK\n";
} else {
    echo "NOT FOUND\n";
}

echo "\n4. Testing DAO: ";
if (class_exists('DAO')) {
    $dao = new DAO();
    echo "OK - DAO created\n";
} else {
    echo "DAO class not found\n";
}

echo "\n5. Testing Imagem class: ";
if (class_exists('Imagem')) {
    $imagem = new Imagem();
    echo "OK - Imagem created\n";
} else {
    echo "Imagem class not found\n";
}

echo "\n6. Testing app() function: ";
if (function_exists('app')) {
    echo "OK - app() exists\n";
    try {
        $service = app('image.upload');
        if ($service) {
            echo "   - image.upload service: OK\n";
        } else {
            echo "   - image.upload service: NOT FOUND\n";
        }
    } catch (Exception $e) {
        echo "   - image.upload service ERROR: " . $e->getMessage() . "\n";
    }
} else {
    echo "app() function not found\n";
}

echo "\n7. Testing Config class: ";
if (class_exists('Config\Config')) {
    echo "OK - Config class exists\n";
} else {
    echo "Config class not found\n";
}

echo "\nAll tests completed.\n";
?>