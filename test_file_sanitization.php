<?php

require_once __DIR__ . '/helpers/FileHelper.php';

use Helpers\FileHelper;

echo "=== TESTE DE SANITIZAÇÃO DE NOMES DE ARQUIVOS ===\n\n";

// Test cases with problematic filenames
$testCases = [
    "Foto com Espaços.jpg",
    "Imagem_com_Acentuação_àáâãäèéêëìíîï.PNG",
    "Arquivo!!!Com###Caracteres\$\$Especiais.jpeg",
    "ARQUIVO EM MAIÚSCULAS COM ESPAÇOS.JPG",
    "foto-já-com-hífen.jpg",
    "arquivo.com.múltiplos.pontos.jpg",
    "    espaços no início e fim    .png",
    "arquivo_muito_longo_com_nome_extremamente_grande_que_precisa_ser_truncado_para_evitar_problemas_no_sistema_de_arquivos_teste_teste_teste.jpg",
    "çñ¥€®™.gif",
    ".jpg", // Only extension
    "arquivo_sem_extensao",
    "GRÜN - São Paulo (Filial).png"
];

$diarioObraId = 528;

echo "Testando FileHelper::generateUniqueImageName():\n";
echo str_repeat("-", 80) . "\n";

foreach ($testCases as $index => $originalName) {
    $sanitized = FileHelper::generateUniqueImageName($originalName, $diarioObraId, $index);
    
    echo sprintf(
        "%2d. Original: %-50s\n    Sanitizado: %s\n\n",
        $index + 1,
        $originalName,
        $sanitized
    );
}

echo "\nTestando FileHelper::sanitizeFilename():\n";
echo str_repeat("-", 80) . "\n";

foreach ($testCases as $index => $originalName) {
    $sanitized = FileHelper::sanitizeFilename($originalName);
    
    echo sprintf(
        "%2d. Original: %-50s\n    Sanitizado: %s\n\n",
        $index + 1,
        $originalName,
        $sanitized
    );
}

// Test validation
echo "\nTestando FileHelper::validateImageUpload():\n";
echo str_repeat("-", 80) . "\n";

$mockFiles = [
    [
        'name' => 'valid_image.jpg',
        'type' => 'image/jpeg',
        'tmp_name' => __FILE__, // Using this file as mock
        'error' => UPLOAD_ERR_OK,
        'size' => 1024000 // 1MB
    ],
    [
        'name' => 'too_large.jpg',
        'type' => 'image/jpeg',
        'tmp_name' => __FILE__,
        'error' => UPLOAD_ERR_OK,
        'size' => 6000000 // 6MB - over 5MB limit
    ],
    [
        'name' => 'upload_error.jpg',
        'type' => 'image/jpeg',
        'tmp_name' => '',
        'error' => UPLOAD_ERR_NO_FILE,
        'size' => 0
    ]
];

foreach ($mockFiles as $file) {
    $validation = FileHelper::validateImageUpload($file);
    echo sprintf(
        "Arquivo: %-20s | Válido: %-5s | Erro: %s\n",
        $file['name'],
        $validation['valid'] ? 'SIM' : 'NÃO',
        $validation['error'] ?? 'Nenhum'
    );
}

echo "\n=== TESTE CONCLUÍDO ===\n";