<?php
// Direct connection to remote database
$host = '103.199.185.165';
$port = '5987';
$dbname = 'default';
$user = 'mariadb';
$pass = 'hr6nhoC6TWfMyAoFZbhB4TPsKoomu00U0gGov1MIsiTihJiG4KTBYXJrpzW2g1n8';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== CHECKING REMOTE DATABASE ===\n\n";

    // Check diary 524 images
    echo "1. Images for Diary 524:\n";
    $stmt = $pdo->query("SELECT id_imagem, url FROM imagem WHERE fk_id_diario_obra = 524 LIMIT 10");
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($images as $img) {
        echo "   ID: {$img['id_imagem']} - URL: {$img['url']}\n";
        if (strpos($img['url'], '/var/www') !== false) {
            echo "   ⚠️  PROBLEM: Contains absolute path!\n";
        }
    }

    // Count problematic paths
    echo "\n2. Statistics:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM imagem");
    $total = $stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as problematic FROM imagem WHERE url LIKE '%/var/www%' OR url LIKE '/%'");
    $problematic = $stmt->fetch()['problematic'];

    echo "   Total images: $total\n";
    echo "   Images with absolute paths: $problematic\n";
    echo "   Percentage with problems: " . round(($problematic/$total)*100, 2) . "%\n";

    // Sample of problematic paths
    if ($problematic > 0) {
        echo "\n3. Sample of problematic paths:\n";
        $stmt = $pdo->query("SELECT id_imagem, fk_id_diario_obra, url FROM imagem WHERE url LIKE '%/var/www%' OR url LIKE '/%' LIMIT 5");
        $problems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($problems as $p) {
            echo "   Diary {$p['fk_id_diario_obra']}: {$p['url']}\n";
        }
    }

    // Check if there are mixed formats (some with path, some without)
    echo "\n4. Path format analysis:\n";
    $stmt = $pdo->query("
        SELECT
            CASE
                WHEN url LIKE '/var/www/html/%' THEN 'Absolute server path'
                WHEN url LIKE '/%' THEN 'Absolute path'
                WHEN url LIKE '%/%' THEN 'Relative path'
                ELSE 'Filename only'
            END as format_type,
            COUNT(*) as count
        FROM imagem
        GROUP BY format_type
    ");
    $formats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($formats as $f) {
        echo "   {$f['format_type']}: {$f['count']} images\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}