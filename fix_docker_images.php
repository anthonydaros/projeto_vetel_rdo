<?php
/**
 * Fix for Docker image loading issue
 * The problem: getValidImageSrc works differently for logos vs photos
 * Logos use a simpler path and work, photos have complex logic that fails
 */

// Simplified getValidImageSrc that mirrors the logo approach
function getValidImageSrcFixed(string $imageUrl): string
{
    // Handle empty URL
    if (empty($imageUrl)) {
        return '';
    }
    
    // Validate input - reject non-image files
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    $fileExtension = strtolower(pathinfo($imageUrl, PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        return '';
    }
    
    // Extract filename only (handle both formats)
    $fileName = basename($imageUrl);
    
    // Build paths similar to logo handling - SIMPLIFIED!
    $photoPath = 'img/album';
    if (file_exists('/.dockerenv')) {
        // Running in Docker - use volume path directly
        $absolutePath = '/var/www/html/' . $photoPath . '/' . $fileName;
    } else {
        // Local development
        $absolutePath = __DIR__ . '/' . $photoPath . '/' . $fileName;
    }
    
    // Check if file exists
    if (!file_exists($absolutePath)) {
        // Return placeholder
        return 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="100">' .
            '<rect width="150" height="100" fill="#f0f0f0" stroke="#ccc"/>' .
            '<text x="75" y="55" text-anchor="middle" fill="#666" font-size="12">Imagem não encontrada</text>' .
            '</svg>'
        );
    }
    
    // Always use base64 encoding (like logos do)
    try {
        $imageData = @file_get_contents($absolutePath);
        if ($imageData === false) {
            throw new Exception("Failed to read file");
        }
        
        $imageType = $fileExtension;
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp'
        ];
        
        $mimeType = $mimeTypes[$imageType] ?? 'image/jpeg';
        $base64 = base64_encode($imageData);
        
        return 'data:' . $mimeType . ';base64,' . $base64;
        
    } catch (Exception $e) {
        // Return error placeholder
        return 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="100">' .
            '<rect width="150" height="100" fill="#f0f0f0" stroke="#ccc"/>' .
            '<text x="75" y="55" text-anchor="middle" fill="#666" font-size="12">Erro ao carregar</text>' .
            '</svg>'
        );
    }
}

// Test the fix
echo "Testing simplified image loading (matching logo approach):\n\n";

$testImage = 'diario-525-foto-0.jpg';
echo "Test image: $testImage\n";

$result = getValidImageSrcFixed($testImage);
if (strpos($result, 'data:image/jpeg') === 0) {
    echo "✓ SUCCESS: Image loaded and converted to base64\n";
    echo "  Data URI length: " . strlen($result) . " characters\n";
} else {
    echo "✗ FAILED: Could not load image\n";
    echo "  Result: " . substr($result, 0, 100) . "...\n";
}