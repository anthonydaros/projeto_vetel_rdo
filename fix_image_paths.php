<?php
/**
 * Script to fix image paths in database
 * Converts absolute paths to filenames only
 * Safe to run multiple times
 */

require_once __DIR__ . '/startup.php';

echo "=== FIXING IMAGE PATHS IN DATABASE ===\n\n";

try {
    $db = Models\Connection::getConnection();

    // Check current state
    echo "1. CHECKING CURRENT STATE...\n";

    $stmt = $db->query("SELECT COUNT(*) as total FROM imagem");
    $totalImages = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $db->query("SELECT COUNT(*) as problematic FROM imagem WHERE url LIKE '/%' OR url LIKE '%/var/www%'");
    $problematicImages = $stmt->fetch(PDO::FETCH_ASSOC)['problematic'];

    echo "   Total images: $totalImages\n";
    echo "   Images with problematic paths: $problematicImages\n\n";

    if ($problematicImages == 0) {
        echo "✅ No problematic paths found. Database is clean!\n";
        exit(0);
    }

    // Fix problematic paths
    echo "2. FIXING PROBLEMATIC PATHS...\n";

    $stmt = $db->query("SELECT id_imagem, url FROM imagem WHERE url LIKE '/%' OR url LIKE '%/var/www%'");
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $fixed = 0;
    foreach ($images as $image) {
        $oldPath = $image['url'];
        $newPath = basename($oldPath);

        // Only update if the new path is different
        if ($oldPath !== $newPath) {
            $updateStmt = $db->prepare("UPDATE imagem SET url = :url WHERE id_imagem = :id");
            $updateStmt->execute(['url' => $newPath, 'id' => $image['id_imagem']]);
            $fixed++;

            if ($fixed <= 10) {
                echo "   Fixed: $oldPath -> $newPath\n";
            } elseif ($fixed == 11) {
                echo "   ... (showing first 10 only)\n";
            }
        }
    }

    echo "\n   Total fixed: $fixed images\n\n";

    // Verify the fix
    echo "3. VERIFYING FIX...\n";

    $stmt = $db->query("SELECT COUNT(*) as remaining FROM imagem WHERE url LIKE '/%' OR url LIKE '%/var/www%'");
    $remaining = $stmt->fetch(PDO::FETCH_ASSOC)['remaining'];

    if ($remaining == 0) {
        echo "✅ SUCCESS! All image paths have been fixed.\n";

        // Show some examples
        echo "\n4. SAMPLE OF FIXED PATHS:\n";
        $stmt = $db->query("SELECT url FROM imagem ORDER BY id_imagem DESC LIMIT 5");
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($samples as $sample) {
            echo "   - " . $sample['url'] . "\n";
        }
    } else {
        echo "⚠️  WARNING: $remaining images still have problematic paths.\n";
        echo "   Please check the database manually.\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== DONE ===\n";