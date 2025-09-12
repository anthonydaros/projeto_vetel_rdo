<?php
// Teste de geração de PDF com imagens

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/startup.php';

use Models\FuncionarioDiarioObra;
use Models\Servico;
use Models\Imagem;
use Dompdf\Dompdf;
use Config\Config;

// Define photo storage path using configuration
$pathAlbum = __DIR__ . '/' . Config::get('PHOTO_STORAGE_PATH', 'img/album');

echo "Testando geração de PDF para diário 528...\n";

// Simular dados POST
$_POST['submit'] = true;
$_POST['id_diario_obra'] = 528;
$_POST['numeroRelatorio'] = 36;
$_POST['data'] = '2025-09-09';
$_POST['obsGeral'] = 'Teste de geração PDF com imagens';
$_POST['horarioTrabalho'] = '08:00 às 17:00';

$id_diario_obra = 528;
$numeroRelatorio = 36;
$data = '2025-09-09';
$obsGeral = 'Teste de geração PDF com imagens';
$horarioTrabalho = '08:00 às 17:00';

// Buscar dados do diário
$diarioObra = $dao->buscaDiarioObraPorId($id_diario_obra);
$contratante = $dao->buscaEmpresaPorId($diarioObra->fk_id_contratante);
$contratada = $dao->buscaEmpresaPorId($diarioObra->fk_id_contratada);

echo "Diário encontrado: {$diarioObra->numero_diario}\n";
echo "Contratante: {$contratante->nome_fantasia}\n";
echo "Contratada: {$contratada->nome_fantasia}\n";

// Atualizar diário
$diarioObra->numero_diario = $numeroRelatorio;
$diarioObra->data = (new DateTime($data))->format('Y-m-d');
$diarioObra->obs_gerais = $obsGeral;
$diarioObra->horario_trabalho = $horarioTrabalho;

// Criar PDF
$dompdf = new Dompdf();
$options = $dompdf->getOptions();
$options->setDefaultFont('DejaVu Sans');
$options->set([
    'isHtml5ParserEnabled' => true,
    'isRemoteEnabled' => true,
    'isPhpEnabled' => true,
    'chroot' => realpath(__DIR__),
    'enableCssFloat' => true,
    'enableJavascript' => false,
]);
$dompdf->setOptions($options);

echo "Gerando conteúdo HTML...\n";

// Testar função getValidImageSrc antes de gerar HTML
$album = $dao->buscaAlbumDiario($id_diario_obra);
echo "Fotos no álbum: " . count($album) . "\n";

if (!empty($album)) {
    echo "Testando primeira foto...\n";
    
    function getValidImageSrc(string $imageUrl): string
    {
        // Extract filename from URL (handles both local and production paths)
        $fileName = basename($imageUrl);
        
        // Build local path using configured photo storage path
        $photoStoragePath = Config::get('PHOTO_STORAGE_PATH', 'img/album');
        $absolutePath = __DIR__ . '/' . $photoStoragePath . '/' . $fileName;
        
        // Check if file exists locally
        if (!file_exists($absolutePath)) {
            error_log("Missing image file for PDF: $absolutePath (original URL: $imageUrl)");
            // Return a placeholder or empty data URI
            return 'data:image/svg+xml;base64,' . base64_encode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="100" viewBox="0 0 150 100">' .
                '<rect width="150" height="100" fill="#f0f0f0" stroke="#ccc"/>' .
                '<text x="75" y="55" text-anchor="middle" fill="#666" font-size="12">Imagem não encontrada</text>' .
                '</svg>'
            );
        }
        
        // Return the absolute path for DOMPDF
        return $absolutePath;
    }
    
    $testResult = getValidImageSrc($album[0]['url']);
    echo "Resultado do teste: " . substr($testResult, 0, 100) . "...\n";
}

// Capturar conteúdo do RDO
ob_start();
require_once __DIR__ . '/rdo.php';
$html = ob_get_contents();
ob_end_clean();

echo "HTML gerado: " . strlen($html) . " caracteres\n";

// Verificar se há imagens no HTML
$imageCount = substr_count($html, '<img');
echo "Imagens encontradas no HTML: $imageCount\n";

// Processar HTML
$html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');

echo "Renderizando PDF...\n";
$dompdf->render();

$pdfOutput = $dompdf->output();
echo "PDF gerado: " . strlen($pdfOutput) . " bytes\n";

// Salvar PDF
$filename = "relatorios/teste_diario_528_" . date('Y-m-d_H-i-s') . ".pdf";
file_put_contents($filename, $pdfOutput);
echo "PDF salvo em: $filename\n";

// Verificar se o PDF contém referências a imagens
$pdfText = $pdfOutput;
$imageRefs = 0;
if (strpos($pdfText, '/img/album/') !== false) {
    $imageRefs++;
}

echo "Referências a imagens no PDF: " . ($imageRefs > 0 ? "SIM" : "NÃO") . "\n";
echo "Teste concluído!\n";