<?php
// Teste do contexto do rdo.php

require_once __DIR__ . '/startup.php';

// Definir variáveis que o rdo.php espera
$diarioObra = $dao->buscaDiarioObraPorId(528);
$contratante = $dao->buscaEmpresaPorId($diarioObra->fk_id_contratante);
$contratada = $dao->buscaEmpresaPorId($diarioObra->fk_id_contratada);

echo "Contexto preparado:\n";
echo "- Diário: {$diarioObra->numero_diario}\n";
echo "- Contratante: {$contratante->nome_fantasia}\n";
echo "- Contratada: {$contratada->nome_fantasia}\n";
echo "- Data: {$diarioObra->data}\n";

$album = $dao->buscaAlbumDiario(528);
echo "- Fotos no álbum: " . count($album) . "\n";

if (!empty($album)) {
    echo "- Primeira foto: " . $album[0]['url'] . "\n";
    
    // Testar função getValidImageSrc
    $fileName = basename($album[0]['url']);
    $localPath = __DIR__ . '/img/album/' . $fileName;
    echo "- Path local: $localPath\n";
    echo "- Arquivo existe: " . (file_exists($localPath) ? 'SIM' : 'NÃO') . "\n";
}

echo "\nIniciando captura do RDO...\n";

// Capturar HTML
ob_start();
require_once __DIR__ . '/rdo.php';
$html = ob_get_contents();
ob_end_clean();

// Analisar resultado
$length = strlen($html);
echo "HTML gerado: $length caracteres\n";

if ($length < 1000) {
    echo "HTML muito pequeno - pode ter erro:\n";
    echo substr($html, 0, 500) . "\n";
} else {
    $imageCount = substr_count($html, '<img');
    echo "Imagens encontradas: $imageCount\n";
    
    // Verificar se há paths absolutos
    $absoluteCount = substr_count($html, '/Users/anthonymax/Documents/GIT/anthonydaros/projeto_vetel/img/album/');
    echo "Paths absolutos: $absoluteCount\n";
    
    if ($absoluteCount > 0) {
        echo "✓ Correção funcionando!\n";
    } else {
        echo "✗ Paths não corrigidos\n";
    }
}

echo "Teste concluído!\n";