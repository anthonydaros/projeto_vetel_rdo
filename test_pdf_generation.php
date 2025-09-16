<?php
/**
 * Test PDF generation for a specific diary
 */
require_once __DIR__ . '/startup.php';

$diaryId = 524; // Test with diary 524

// Get diary data
$diarioObra = $dao->buscaDiarioObraPorId($diaryId);
if (!$diarioObra) {
    die("Diary $diaryId not found\n");
}

// Get images
$album = $dao->buscaAlbumDiario($diaryId);

echo "=== TESTING PDF GENERATION FOR DIARY $diaryId ===\n\n";
echo "Found " . count($album) . " images\n\n";

// Test the getValidImageSrc function
require_once __DIR__ . '/rdo.php';

echo "Testing image processing:\n";
foreach ($album as $index => $img) {
    echo "Image $index: {$img['url']}\n";

    // Test the function
    $result = getValidImageSrc($img['url']);

    if (strpos($result, 'data:') === 0) {
        // It's base64
        $dataInfo = substr($result, 0, 50);
        echo "  ✅ Converted to base64: $dataInfo...\n";
    } elseif (strpos($result, 'Imagem não encontrada') !== false) {
        echo "  ⚠️  Image not found!\n";
    } else {
        echo "  ❌ Unexpected result: " . substr($result, 0, 100) . "\n";
    }

    // Check if file exists
    $photoPath = __DIR__ . '/img/album/' . basename($img['url']);
    if (file_exists($photoPath)) {
        $size = filesize($photoPath);
        echo "  File exists at: $photoPath (Size: " . number_format($size) . " bytes)\n";
    } else {
        echo "  ⚠️  File NOT found at: $photoPath\n";
    }

    echo "\n";

    if ($index >= 2) break; // Test first 3 images only
}

echo "\n=== CHECKING DOCKER VOLUME ===\n";
// Check if we're in Docker environment
if (file_exists('/.dockerenv')) {
    echo "Running inside Docker container\n";
    $dockerPath = '/var/www/html/img/album/';
    if (is_dir($dockerPath)) {
        $files = scandir($dockerPath);
        $imageFiles = array_filter($files, function($f) {
            return preg_match('/\.(jpg|jpeg|png|webp)$/i', $f);
        });
        echo "Found " . count($imageFiles) . " images in Docker volume\n";

        // Check specific diary 524 images
        $diary524Files = array_filter($imageFiles, function($f) {
            return strpos($f, 'diario-524') === 0;
        });
        echo "Found " . count($diary524Files) . " images for diary 524\n";

        if (!empty($diary524Files)) {
            echo "Sample files:\n";
            foreach (array_slice($diary524Files, 0, 5) as $f) {
                $fullPath = $dockerPath . $f;
                $size = file_exists($fullPath) ? filesize($fullPath) : 0;
                echo "  - $f (" . number_format($size) . " bytes)\n";
            }
        }
    } else {
        echo "Docker volume directory not found: $dockerPath\n";
    }
} else {
    echo "Not running in Docker\n";

    // Check local directory
    $localPath = __DIR__ . '/img/album/';
    if (is_dir($localPath)) {
        $files = scandir($localPath);
        $diary524Files = array_filter($files, function($f) {
            return strpos($f, 'diario-524') === 0;
        });
        echo "Found " . count($diary524Files) . " images for diary 524 in local directory\n";
    }
}

echo "\n=== END TEST ===\n";