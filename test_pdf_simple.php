<?php
// Teste simplificado de PDF com imagens

require_once __DIR__ . '/startup.php';

$diarioObra = $dao->buscaDiarioObraPorId(528);
$album = $dao->buscaAlbumDiario(528);

echo "Testando geração de PDF...\n";
echo "Diário: {$diarioObra->numero_diario}\n";
echo "Fotos: " . count($album) . "\n";

// Testar função getValidImageSrc para uma foto
if (!empty($album)) {
    $photoStoragePath = Config\Config::get('PHOTO_STORAGE_PATH', 'img/album');
    $fileName = basename($album[0]['url']);
    $localPath = __DIR__ . '/' . $photoStoragePath . '/' . $fileName;
    
    echo "Primeira foto:\n";
    echo "- Nome: $fileName\n";
    echo "- Path: $localPath\n";
    echo "- Existe: " . (file_exists($localPath) ? 'SIM' : 'NÃO') . "\n";
}

// Capturar apenas o conteúdo HTML sem renderizar PDF
ob_start();
require_once __DIR__ . '/rdo.php';
$html = ob_get_contents();
ob_end_clean();

echo "HTML gerado: " . strlen($html) . " caracteres\n";

// Contar imagens no HTML
$imageCount = substr_count($html, '<img');
echo "Tags <img> no HTML: $imageCount\n";

// Verificar se há paths absolutos nas imagens
if (strpos($html, '/Users/anthonymax/Documents/GIT/anthonydaros/projeto_vetel/img/album/') !== false) {
    echo "✓ Imagens com paths absolutos detectadas\n";
} else {
    echo "✗ Paths absolutos não encontrados\n";
}

echo "Teste concluído com sucesso!\n";