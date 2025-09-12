<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug detalhado
echo "=== TESTE DE UPLOAD DETALHADO ===\n\n";

// 1. Verificar se os arquivos existem
echo "1. Verificando arquivos necessários:\n";
$files = ['startup.php', 'models/DAO.php', 'models/Imagem.php'];
foreach ($files as $file) {
    echo "   - $file: " . (file_exists(__DIR__ . '/' . $file) ? "OK" : "NÃO ENCONTRADO") . "\n";
}

// 2. Tentar carregar startup.php
echo "\n2. Carregando startup.php:\n";
try {
    require_once __DIR__ . '/startup.php';
    echo "   - startup.php carregado com sucesso\n";
} catch (Exception $e) {
    echo "   - ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Verificar classes
echo "\n3. Verificando classes:\n";
echo "   - DAO: " . (class_exists('DAO') ? "OK" : "NÃO ENCONTRADA") . "\n";
echo "   - Imagem: " . (class_exists('Imagem') ? "OK" : "NÃO ENCONTRADA") . "\n";

// 4. Testar conexão com banco
echo "\n4. Testando conexão com banco:\n";
try {
    $dao = new DAO();
    echo "   - DAO criado com sucesso\n";
    
    // Buscar álbum
    $album = $dao->buscaAlbumDiario(1);
    echo "   - Álbum do diário 1: " . count($album) . " fotos\n";
} catch (Exception $e) {
    echo "   - ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

// 5. Simular upload
echo "\n5. Simulando upload:\n";
if (isset($_FILES['file'])) {
    echo "   - Arquivo recebido: " . $_FILES['file']['name'] . "\n";
    
    $diarioId = 1;
    $imageIndex = count($album);
    $filename = "diario-{$diarioId}-foto-{$imageIndex}.jpg";
    $uploadPath = __DIR__ . '/img/album/' . $filename;
    
    echo "   - Destino: $uploadPath\n";
    
    // Tentar mover arquivo
    if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadPath)) {
        echo "   - Arquivo movido com sucesso!\n";
        
        // Inserir no banco
        $imagem = new Imagem();
        $imagem->setFkIdDiarioObra($diarioId);
        $imagem->setUrl($filename);
        
        try {
            $dao->insereImagem($imagem);
            echo "   - Imagem inserida no banco!\n";
            
            // Buscar ID
            $album = $dao->buscaAlbumDiario($diarioId);
            $lastImage = end($album);
            
            echo "\n=== SUCESSO ===\n";
            echo json_encode([
                'success' => true,
                'image_id' => $lastImage['id_imagem'],
                'filename' => $filename
            ]);
        } catch (Exception $e) {
            echo "   - ERRO ao inserir no banco: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   - ERRO ao mover arquivo\n";
        echo "   - Error code: " . $_FILES['file']['error'] . "\n";
    }
} else {
    echo "   - Nenhum arquivo enviado\n";
    echo "\n=== FORMULÁRIO DE TESTE ===\n";
    ?>
    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="file" required>
        <input type="submit" value="Testar Upload">
    </form>
    <?php
}

echo "\n";
?>