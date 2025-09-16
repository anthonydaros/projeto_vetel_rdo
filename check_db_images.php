<?php
// Check what's stored in database for images

require_once __DIR__ . '/startup.php';

echo "=== Checking Image URLs in Database ===\n\n";

// Check diary 525 images
$id_diario_obra = 525;

$album = $dao->buscaAlbumDiario($id_diario_obra);

echo "Found " . count($album) . " images for diary $id_diario_obra\n\n";

foreach ($album as $index => $image) {
    echo "Image $index:\n";
    echo "  ID: " . $image->id_imagem . "\n";
    echo "  URL: '" . $image->url . "'\n";
    
    // Check URL format
    if (strpos($image->url, '/var/www') !== false) {
        echo "  ⚠️ WARNING: Contains absolute Docker path!\n";
    } elseif (strpos($image->url, '/') === false) {
        echo "  ✓ Correct format (filename only)\n";
    } else {
        echo "  ? Contains path separator\n";
    }
    
    echo "\n";
}