<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/startup.php';
require_once __DIR__ . '/helpers/FileHelper.php';

use Models\FuncionarioDiarioObra;
use Models\Servico;
use Models\Imagem;
use Dompdf\Dompdf;
use Config\Config;
use Helpers\FileHelper;

// Define photo storage path using configuration
$pathAlbum = __DIR__ . '/' . Config::get('PHOTO_STORAGE_PATH', 'img/album');

if (isset($_FILES['file']) && isset($_FILES['file']['tmp_name']) && !empty($_FILES['file']['name'])) {
	// cleanDir($pathAlbum);
	$numArquivos = count($_FILES['file']['name']);

	$id_diario_obra = isset($_POST['id_diario_obra']) ? $_POST['id_diario_obra'] : 0;
	$dao->deleteAlbum($id_diario_obra);

	/*limpa diretorio do album*/
	cleanAlbumDiario($id_diario_obra);

	$uploadErrors = [];
	$successCount = 0;

	for ($i = 0; $i < $numArquivos; $i++) {
		// Create file array for validation
		$currentFile = [
			'name' => $_FILES['file']['name'][$i],
			'type' => $_FILES['file']['type'][$i],
			'tmp_name' => $_FILES['file']['tmp_name'][$i],
			'error' => $_FILES['file']['error'][$i],
			'size' => $_FILES['file']['size'][$i]
		];
		
		// Validate image upload
		$validation = FileHelper::validateImageUpload($currentFile);
		
		if (!$validation['valid']) {
			$uploadErrors[] = "Arquivo {$currentFile['name']}: {$validation['error']}";
			error_log("Upload validation failed for {$currentFile['name']}: {$validation['error']}");
			continue;
		}
		
		// Generate unique sanitized filename
		$fileName = FileHelper::generateUniqueImageName(
			$currentFile['name'],
			$id_diario_obra,
			$i
		);
		
		$filePath = "$pathAlbum/$fileName";
		
		// Ensure filename is unique in filesystem
		$fileName = FileHelper::ensureUniqueFilename($filePath, $fileName);
		$filePath = "$pathAlbum/$fileName";
		
		// Log the sanitized filename for debugging
		error_log("Original filename: {$currentFile['name']} -> Sanitized: $fileName");

		// Save to database
		$imagem = new Imagem();
		$imagem->fk_id_diario_obra = $id_diario_obra;
		$imagem->url = $fileName;  // Salvar apenas nome do arquivo, não path completo

		$ret = $dao->insereImagem($imagem);

		// Save file to disk
		if (move_uploaded_file($currentFile['tmp_name'], $filePath)) {
			$successCount++;
			error_log("Successfully uploaded image: $fileName to $filePath");
		} else {
			$uploadErrors[] = "Falha ao salvar arquivo {$currentFile['name']}";
			error_log("Failed to save file: $fileName to $filePath");
		}
	}
	
	// Log upload summary
	if ($successCount > 0) {
		error_log("Upload completed: $successCount of $numArquivos files uploaded successfully");
	}
	
	if (!empty($uploadErrors)) {
		error_log("Upload errors occurred: " . implode('; ', $uploadErrors));
		// You could store these errors in session to display to user
		// $_SESSION['upload_errors'] = $uploadErrors;
	}
} elseif (isset($_POST['submit'])) {
	$time_start = microtime(true);

	extract($_POST);

	$diarioObra = $dao->buscaDiarioObraPorId($id_diario_obra);
	$contratante = $dao->buscaEmpresaPorId($diarioObra->fk_id_contratante);
	$contratada = $dao->buscaEmpresaPorId($diarioObra->fk_id_contratada);
	$cargaHorasDia = 0;

	if (isset($descricaoServico)) {
		$descricaoServico = array_values(array_filter($descricaoServico, function ($servico) {
			return trim($servico) != '';
		}));

		$ret = $dao->deleteTodosServicosDiarioObra($id_diario_obra);

		foreach ($descricaoServico as $itemServico) {
			$servico = new Servico();
			$servico->descricao = $itemServico;
			$servico->fk_id_diario_obra = $id_diario_obra;
			$servico = $dao->insereServico($servico);
		}
	}
	if (isset($horaEntrada)) {
		$horaEntrada = array_values(array_filter($horaEntrada, function ($hora) {
			return $hora != '';
		}));
	}
	if (isset($horaSaida)) {
		$horaSaida = array_values(array_filter($horaSaida, function ($hora) {
			return $hora != '';
		}));
	}
	if (isset($totalHoras)) {
		$totalHoras = array_values(array_filter($totalHoras, function ($hora) {
			return $hora != '';
		}));
	}

	$diarioObra->numero_diario = $numeroRelatorio;
	$diarioObra->data = (new DateTime($data))->format('Y-m-d');
	$diarioObra->obs_gerais = $obsGeral;
	$diarioObra->horario_trabalho = $horarioTrabalho;
	$dao->updateDiarioObra($diarioObra);

	if (isset($nomeFuncionario)) {
		$funcionarios = array_values(array_filter($nomeFuncionario, function ($nome) {
			return trim($nome) != '';
		}));

		$funcionarios = array_map(function ($nome) use ($dao) {
			$funcionario = $dao->buscaFuncionarioPorNome($nome);
			return $funcionario;
		}, $funcionarios);

		$ret = $dao->deleteTodosFuncionariosDiarioObra($id_diario_obra);

		foreach ($funcionarios as $funcionario) {
			$funcionarioDiarioObra = new FuncionarioDiarioObra();
			$funcionarioDiarioObra->fk_id_funcionario = $funcionario->id_funcionario;
			$funcionarioDiarioObra->fk_id_diario_obra = $id_diario_obra;
			$funcionarioDiarioObra->data = (new DateTime($data))->format('Y-m-d');
			$funcionarioDiarioObra->horario_trabalho = current($horaEntrada) . ' às ' . current($horaSaida);

			$arr = explode(':', current($totalHoras));
			$totalHorasFuncionarioObra = $arr[0] + round(($arr[1] / 60), 2);

			$funcionarioDiarioObra->horas_trabalhadas = $totalHorasFuncionarioObra;

			$ret = $dao->insereFuncionarioDiarioObra($funcionarioDiarioObra);

			$cargaHorasDia += $totalHorasFuncionarioObra;

			next($horaEntrada);
			next($horaSaida);
			next($totalHoras);
		}
	}

	$diarioObra->carga_horas_dia = $cargaHorasDia;
	$diarioObra->total_horas = $cargaHorasDia;
	$totalAcumuladoHorasObra = $dao->calculaTotalAcumuladoHorasObraPorPeriodo($diarioObra)->total_acumulado;
	$totalAcumuladoHorasObra += $cargaHorasDia;

	$dao->updateDiarioObra($diarioObra);

	$data = (new DateTime($data))->format('d/m/Y');

	/******************** INÍCIO CRIAÇÃO PDF ********************/
	
	// Enhanced error logging for PDF generation
	error_log("=== PDF GENERATION STARTED ===");
	error_log("PDF Generation - Diary ID: $id_diario_obra");
	error_log("PDF Generation - Date: $data");
	error_log("PDF Generation - Report Number: $numeroRelatorio");
	
	// Increase memory limit for image processing
	ini_set('memory_limit', '512M');
	error_log("PDF Generation - Memory limit set to: " . ini_get('memory_limit'));
	
	$dompdf = new Dompdf();

	$options = $dompdf->getOptions();
	$options->setDefaultFont('DejaVu Sans');
	
	// Optimized DOMPDF configuration
	$options->set([
		'isHtml5ParserEnabled' => true,
		'isRemoteEnabled' => true,
		'isPhpEnabled' => true,
		'chroot' => realpath(__DIR__), // Restrict file access to project directory
		'logOutputFile' => __DIR__ . '/dompdf_log.txt', // Enable DOMPDF logging
		'enableCssFloat' => true,
		'enableJavascript' => false,
		'isPhpEnabled' => true,
		'tempDir' => sys_get_temp_dir(),
		'fontDir' => __DIR__ . '/vendor/dompdf/dompdf/lib/fonts/',
		'fontCache' => __DIR__ . '/vendor/dompdf/dompdf/lib/fonts/',
		'debugKeepTemp' => true, // Keep temp files for debugging
		'debugCss' => false, // Disable CSS debugging for production
		'debugLayout' => false, // Disable layout debugging for production
		'debugLayoutLines' => false,
		'debugLayoutBlocks' => false,
		'debugLayoutInline' => false,
		'debugLayoutPaddingBox' => false,
		'pdfBackend' => 'CPDF', // Use CPDF backend for better compatibility
		'defaultMediaType' => 'print',
		'defaultPaperSize' => 'a4',
		'defaultPaperOrientation' => 'portrait',
		'isFontSubsettingEnabled' => true,
		'isRemoteEnabled' => true,
		'dpi' => 96, // Standard DPI for images
	]);

	$dompdf->setOptions($options);
	
	error_log("PDF Generation - DOMPDF configured with chroot: " . realpath(__DIR__));
	error_log("PDF Generation - Temp dir: " . sys_get_temp_dir());

	if (($time_diff = microtime(true) - $time_start) < 6.0) {
		$time_sleep = (int) (6.0 - $time_diff) * 1000000;
		usleep($time_sleep);
	}
	
	// Generate HTML with error handling
	error_log("PDF Generation - Starting HTML generation");
	ob_start();

	try {
		require_once __DIR__ . '/rdo.php';
		$html = ob_get_contents();
		ob_end_clean();
		
		error_log("PDF Generation - HTML generated successfully, length: " . strlen($html));
		
		// Log first 500 characters for debugging (but not full HTML to avoid log spam)
		error_log("PDF Generation - HTML preview: " . substr($html, 0, 500) . "...");
		
		// Check for image references in HTML
		$imageCount = substr_count($html, '<img');
		error_log("PDF Generation - Found $imageCount image tags in HTML");
		
		// Log image src paths for debugging
		preg_match_all('/src="([^"]*)"/', $html, $matches);
		foreach ($matches[1] as $i => $src) {
			error_log("PDF Generation - Image $i src: $src");
		}
		
	} catch (Exception $e) {
		ob_end_clean();
		error_log("PDF Generation ERROR - HTML generation failed: " . $e->getMessage());
		throw new Exception("Failed to generate HTML for PDF: " . $e->getMessage());
	}

	// Convert encoding and validate HTML
	$html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
	
	if (empty($html) || strlen($html) < 1000) {
		error_log("PDF Generation ERROR - HTML appears to be empty or too short: " . strlen($html) . " characters");
		throw new Exception("Generated HTML is invalid or empty");
	}
	
	error_log("PDF Generation - Loading HTML into DOMPDF");
	
	try {
		$dompdf->loadHtml($html);
		error_log("PDF Generation - HTML loaded successfully into DOMPDF");
	} catch (Exception $e) {
		error_log("PDF Generation ERROR - Failed to load HTML: " . $e->getMessage());
		throw new Exception("DOMPDF failed to load HTML: " . $e->getMessage());
	}

	// Setup paper size and orientation (maintaining A4 format)
	$dompdf->setPaper('A4', 'portrait');
	error_log("PDF Generation - Paper set to A4 portrait");

	// Render the HTML as PDF with error handling
	error_log("PDF Generation - Starting PDF rendering");
	$render_start = microtime(true);
	
	try {
		$dompdf->render();
		$render_time = microtime(true) - $render_start;
		error_log("PDF Generation - PDF rendered successfully in " . round($render_time, 2) . " seconds");
		
		// Log memory usage
		$memory_usage = memory_get_usage(true);
		$peak_memory = memory_get_peak_usage(true);
		error_log("PDF Generation - Memory usage: " . round($memory_usage/1024/1024, 2) . "MB, Peak: " . round($peak_memory/1024/1024, 2) . "MB");
		
	} catch (Exception $e) {
		error_log("PDF Generation ERROR - Rendering failed: " . $e->getMessage());
		error_log("PDF Generation ERROR - Stack trace: " . $e->getTraceAsString());
		throw new Exception("PDF rendering failed: " . $e->getMessage());
	}

	// Add page numbers with error handling
	try {
		$canvas = $dompdf->getCanvas();
		$pageCount = $canvas->get_page_count();
		error_log("PDF Generation - Generated $pageCount pages");
		
		// Page number parameters
		$x          = 505;
		$y          = 790;
		$text       = 'Página {PAGE_NUM} de {PAGE_COUNT}';
		$font       = $dompdf->getFontMetrics()->get_font('Helvetica', 'normal');
		$size       = 10;
		$color      = [0, 0, 0];
		$word_space = 0.0;
		$char_space = 0.0;
		$angle      = 0.0;

		$canvas->page_text(
			$x,
			$y,
			$text,
			$font,
			$size,
			$color,
			$word_space,
			$char_space,
			$angle
		);
		
		error_log("PDF Generation - Page numbers added successfully");
		
	} catch (Exception $e) {
		error_log("PDF Generation WARNING - Failed to add page numbers: " . $e->getMessage());
		// Don't throw exception here as PDF is already generated
	}

	// Output the generated PDF to Browser
	try {
		error_log("PDF Generation - Streaming PDF to browser");
		$dompdf->stream('RDO_' . $numeroRelatorio . '_' . date('Y-m-d') . '.pdf', ['Attachment' => false]);
		error_log("PDF Generation - PDF streamed successfully");
		error_log("=== PDF GENERATION COMPLETED ===");
	} catch (Exception $e) {
		error_log("PDF Generation ERROR - Failed to stream PDF: " . $e->getMessage());
		throw new Exception("Failed to output PDF: " . $e->getMessage());
	}
}

function cleanAlbumDiario($idDiarioObra)
{
	// Use configuration-based path instead of undefined global
	$pathAlbum = __DIR__ . '/' . Config::get('PHOTO_STORAGE_PATH', 'img/album');

	if (!is_dir($pathAlbum)) {
		error_log("Warning: Album directory does not exist: $pathAlbum");
		return;
	}

	$handle = opendir($pathAlbum);
	if (!$handle) {
		error_log("Error: Cannot open album directory: $pathAlbum");
		return;
	}

	while (false !== ($entry = readdir($handle))) {
		$filename_arr = explode('-', $entry);
		if (!is_dir("$pathAlbum/$entry") && 
		    count($filename_arr) >= 2 && 
		    $filename_arr[1] == $idDiarioObra) {
			$filePath = "$pathAlbum/$entry";
			if (file_exists($filePath)) {
				unlink($filePath);
			}
		}
	}
	closedir($handle);
}
