<?php
require_once __DIR__ . '/startup.php';

// Get images for diary 524
$diaryId = 524;
$images = $dao->buscaAlbumDiario($diaryId);

echo "=== Debugging Image Paths for Diary $diaryId ===\n\n";

if (empty($images)) {
    echo "No images found for diary $diaryId\n";
} else {
    echo "Found " . count($images) . " images:\n\n";

    foreach ($images as $index => $image) {
        echo "Image $index:\n";
        echo "  ID: " . $image['id_imagem'] . "\n";
        echo "  URL from DB: '" . $image['url'] . "'\n";

        // Check what type of path this is
        if (strpos($image['url'], '/var/www/html') !== false) {
            echo "  ⚠️  PROBLEM: Contains absolute server path!\n";
        } elseif (strpos($image['url'], '/') === false) {
            echo "  ✅ OK: Filename only (correct format)\n";
        } else {
            echo "  ⚠️  WARNING: Contains path separators but not absolute\n";
        }

        echo "\n";
    }

    // Now test getValidImageSrc function
    echo "\n=== Testing getValidImageSrc Function ===\n\n";

    if (function_exists('getValidImageSrc')) {
        // We need to include rdo.php functions
        require_once __DIR__ . '/rdo.php';
    }

    // Test with first image
    if (!empty($images[0])) {
        $testUrl = $images[0]['url'];
        echo "Testing with URL: '$testUrl'\n";

        $result = getValidImageSrc($testUrl);

        if (strpos($result, 'data:') === 0) {
            echo "✅ Function returned base64 data URL (correct)\n";
            echo "First 100 chars: " . substr($result, 0, 100) . "...\n";
        } else {
            echo "⚠️  PROBLEM: Function did not return base64 URL\n";
            echo "Returned: $result\n";
        }
    }
}

echo "\n=== End Debug ===\n";