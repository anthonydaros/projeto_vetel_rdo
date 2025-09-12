<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/startup.php';

// Teste simples de upload
if (isset($_FILES['file'])) {
    echo "Arquivo recebido: " . $_FILES['file']['name'] . "\n";
    echo "Tamanho: " . $_FILES['file']['size'] . "\n";
    echo "Tipo: " . $_FILES['file']['type'] . "\n";
    echo "Temp: " . $_FILES['file']['tmp_name'] . "\n";
    
    $dao = new DAO();
    $diarioId = 1;
    
    // Busca álbum atual
    $album = $dao->buscaAlbumDiario($diarioId);
    echo "Fotos no álbum: " . count($album) . "\n";
    
    // Gera nome do arquivo
    $imageIndex = count($album);
    $filename = "diario-{$diarioId}-foto-{$imageIndex}.jpg";
    $uploadPath = __DIR__ . '/img/album/' . $filename;
    
    echo "Destino: " . $uploadPath . "\n";
    
    // Move arquivo
    if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadPath)) {
        echo "Arquivo movido com sucesso!\n";
        
        // Insere no banco
        $imagem = new Imagem();
        $imagem->setFkIdDiarioObra($diarioId);
        $imagem->setUrl($filename);
        $dao->insereImagem($imagem);
        
        echo "Imagem inserida no banco!\n";
        
        // Busca ID da imagem inserida
        $album = $dao->buscaAlbumDiario($diarioId);
        $lastImage = end($album);
        
        echo json_encode([
            'success' => true,
            'image_id' => $lastImage['id_imagem'],
            'filename' => $filename
        ]);
    } else {
        echo "Erro ao mover arquivo!\n";
        echo "Upload error: " . $_FILES['file']['error'] . "\n";
    }
} else {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Test Upload</title>
    </head>
    <body>
        <h2>Test Upload</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="file" name="file" required>
            <input type="submit" value="Upload">
        </form>
    </body>
    </html>
    <?php
}
?>