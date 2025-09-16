<?php
/**
 * Gets first file from album directory
 */
function getFirstAlbumFile(string $albumPath): ?string
{
	if (!is_dir($albumPath)) {
		return null;
	}

	$handle = opendir($albumPath);
	if (!$handle) {
		return null;
	}

	$fileData = null;
	while (($entry = readdir($handle)) !== false) {
		$fullPath = "$albumPath/$entry";
		if (is_file($fullPath)) {
			$fileData = file_get_contents($fullPath);
			break;
		}
	}
	closedir($handle);

	return $fileData;
}

/**
 * Formats hour display with appropriate decimal places
 */
function formatHours(float $hours): string
{
	if ($hours === floor($hours)) {
		return number_format($hours, 0, ',', '.') . 'h';
	}

	if (($hours * 10) === floor($hours * 10)) {
		return number_format($hours, 1, ',', '.') . 'h';
	}

	return number_format($hours, 2, ',', '.') . 'h';
}

/**
 * Validates and resolves logo path for PDF generation
 * Works with both filename-only URLs (new) and full-path URLs (legacy)
 * Returns relative paths for DOMPDF compatibility
 */
function getValidLogoSrc(string $logoUrl): string
{
	// Handle empty logo URL
	if (empty($logoUrl)) {
		return '';
	}
	
	// Handle both cases: filename-only and full-path URLs
	if (strpos($logoUrl, '/') === false) {
		// New format: just filename
		$fileName = $logoUrl;
	} else {
		// Legacy format: extract filename from full path
		$fileName = basename($logoUrl);
	}
	
	// Build paths for logo directory (Docker-aware)
	$logoPath = 'img/logo';
	if (file_exists('/.dockerenv')) {
		// Running in Docker - use volume path
		$absolutePath = '/var/www/html/' . $logoPath . '/' . $fileName;
	} else {
		// Local development
		$absolutePath = __DIR__ . '/' . $logoPath . '/' . $fileName;
	}
	$relativePath = $logoPath . '/' . $fileName;
	
	// Reduced logging - only log errors
	if (!file_exists($absolutePath)) {
		error_log("PDF Logo Processing - Missing file: $absolutePath (URL: $logoUrl)");
	}
	
	// Check if file exists locally
	if (!file_exists($absolutePath)) {
		// Return empty string for missing logos
		return '';
	}
	
	// Always use base64 encoding for logos in PDF generation
	// This ensures compatibility across different environments (Docker, local, etc.)
	try {
		$imageData = file_get_contents($absolutePath);
		$imageType = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
		
		// Handle special extensions
		if ($imageType === '05') {
			// This appears to be a special format, likely PNG
			$imageType = 'png';
		}
		
		// Determine correct MIME type
		$mimeTypes = [
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'gif' => 'image/gif',
			'bmp' => 'image/bmp',
			'webp' => 'image/webp'
		];
		
		$mimeType = isset($mimeTypes[$imageType]) ? $mimeTypes[$imageType] : 'image/' . $imageType;
		$base64 = base64_encode($imageData);
		
		return 'data:' . $mimeType . ';base64,' . $base64;
		
	} catch (Exception $e) {
		error_log("Logo Processing - Error encoding image: " . $e->getMessage());
		
		// Return empty string on error
		return '';
	}
}

/**
 * Validates and resolves image path for PDF generation
 * Works with both filename-only URLs (new) and full-path URLs (legacy)
 * First tries HTTP URL for public access, then falls back to base64 encoding
 */
function getValidImageSrc(string $imageUrl): string
{
	// Handle empty URL
	if (empty($imageUrl)) {
		return '';
	}
	
	// Validate input - reject non-image files
	$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
	$fileExtension = strtolower(pathinfo($imageUrl, PATHINFO_EXTENSION));
	
	// Check for invalid extensions
	if (empty($fileExtension) || !in_array($fileExtension, $allowedExtensions)) {
		// Return placeholder for invalid files
		return 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" width="150" height="100">' .
			'<rect width="150" height="100" fill="#f0f0f0" stroke="#ccc"/>' .
			'<text x="75" y="55" text-anchor="middle" fill="#666" font-size="12">Formato inválido</text>' .
			'</svg>'
		);
	}
	
	// Extract filename only (handle both formats)
	$fileName = basename($imageUrl);
	
	// Build paths similar to logo handling - SIMPLIFIED APPROACH!
	// This matches the working logo implementation
	$photoPath = 'img/album';
	if (file_exists('/.dockerenv')) {
		// Running in Docker - use volume path directly
		$absolutePath = '/var/www/html/' . $photoPath . '/' . $fileName;
	} else {
		// Local development
		$absolutePath = __DIR__ . '/' . $photoPath . '/' . $fileName;
	}
	
	// Check if file exists
	error_log("Photo Processing - Checking file exists: $absolutePath");
	if (!file_exists($absolutePath)) {
		error_log("Photo Processing - File does not exist at: $absolutePath");
		// Try alternate extensions (handle jpg/jpeg inconsistency)
		$fileWithoutExt = pathinfo($fileName, PATHINFO_FILENAME);
		$alternateExtensions = ['jpg', 'jpeg', 'png'];
		$found = false;
		
		foreach ($alternateExtensions as $ext) {
			$alternatePath = dirname($absolutePath) . '/' . $fileWithoutExt . '.' . $ext;
			if (file_exists($alternatePath)) {
				$absolutePath = $alternatePath;
				$found = true;
				break;
			}
		}
		
		if (!$found) {
			// Return placeholder for missing images
			return 'data:image/svg+xml;base64,' . base64_encode(
				'<svg xmlns="http://www.w3.org/2000/svg" width="150" height="100">' .
				'<rect width="150" height="100" fill="#f0f0f0" stroke="#ccc"/>' .
				'<text x="75" y="55" text-anchor="middle" fill="#666" font-size="12">Imagem não encontrada</text>' .
				'</svg>'
			);
		}
	}
	
	// Use base64 encoding like logos do - this works in Docker!
	error_log("Photo Processing - Reading file: $absolutePath");
	try {
		$imageData = file_get_contents($absolutePath);
		if ($imageData === false) {
			error_log("Photo Processing - Failed to read file: $absolutePath");
			throw new Exception("Cannot read file");
		}
		
		$fileSize = strlen($imageData);
		error_log("Photo Processing - File size: $fileSize bytes");
		
		$imageType = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
		error_log("Photo Processing - Image type: $imageType");
		
		// Determine correct MIME type
		$mimeTypes = [
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/png',
			'gif' => 'image/gif',
			'bmp' => 'image/bmp',
			'webp' => 'image/webp'
		];
		
		$mimeType = isset($mimeTypes[$imageType]) ? $mimeTypes[$imageType] : 'image/' . $imageType;
		$base64 = base64_encode($imageData);
		$base64Length = strlen($base64);
		error_log("Photo Processing - Base64 length: $base64Length characters");
		
		$result = 'data:' . $mimeType . ';base64,' . $base64;
		error_log("Photo Processing - Successfully encoded: $fileName");
		return $result;
		
	} catch (Exception $e) {
		error_log("Photo Processing - Error encoding image: " . $e->getMessage());
		
		// Return placeholder on error
		return 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" width="150" height="100">' .
			'<rect width="150" height="100" fill="#f0f0f0" stroke="#ccc"/>' .
			'<text x="75" y="55" text-anchor="middle" fill="#666" font-size="12">Erro ao carregar</text>' .
			'</svg>'
		);
	}
}

// Initialize album data - use configuration from startup.php
$firstAlbumFile = getFirstAlbumFile($pathAlbum);

// Check if album is available from GLOBALS, local scope, or prefixed variables
if (!isset($album)) {
    if (isset($rdo_album)) {
        $album = $rdo_album;
        error_log("RDO - Using album from rdo_album prefixed variable");
    } elseif (isset($GLOBALS['album'])) {
        $album = $GLOBALS['album'];
        error_log("RDO - Using album from GLOBALS");
    }
}

// Also get other variables from prefixed names or GLOBALS if not set
if (!isset($id_diario_obra)) {
    if (isset($rdo_id_diario_obra)) {
        $id_diario_obra = $rdo_id_diario_obra;
        error_log("RDO - Using id_diario_obra from rdo_id_diario_obra: $id_diario_obra");
    } elseif (isset($GLOBALS['id_diario_obra'])) {
        $id_diario_obra = $GLOBALS['id_diario_obra'];
        error_log("RDO - Using id_diario_obra from GLOBALS: $id_diario_obra");
    }
}

if (!isset($diarioObra)) {
    if (isset($rdo_diarioObra)) {
        $diarioObra = $rdo_diarioObra;
        error_log("RDO - Using diarioObra from rdo_diarioObra");
    } elseif (isset($GLOBALS['diarioObra'])) {
        $diarioObra = $GLOBALS['diarioObra'];
        error_log("RDO - Using diarioObra from GLOBALS");
    }
}

if (!isset($contratante)) {
    if (isset($rdo_contratante)) {
        $contratante = $rdo_contratante;
        error_log("RDO - Using contratante from rdo_contratante");
    } elseif (isset($GLOBALS['contratante'])) {
        $contratante = $GLOBALS['contratante'];
        error_log("RDO - Using contratante from GLOBALS");
    }
}

if (!isset($contratada)) {
    if (isset($rdo_contratada)) {
        $contratada = $rdo_contratada;
        error_log("RDO - Using contratada from rdo_contratada");
    } elseif (isset($GLOBALS['contratada'])) {
        $contratada = $GLOBALS['contratada'];
        error_log("RDO - Using contratada from GLOBALS");
    }
}

if (!isset($dao)) {
    if (isset($rdo_dao)) {
        $dao = $rdo_dao;
        error_log("RDO - Using dao from rdo_dao");
    } elseif (isset($GLOBALS['dao'])) {
        $dao = $GLOBALS['dao'];
        error_log("RDO - Using dao from GLOBALS");
    }
}

// If album is still not loaded, try to load it
if (!isset($album)) {
    // Try to get diary ID from various sources
    $diary_id = null;
    if (isset($diarioObra) && is_object($diarioObra) && isset($diarioObra->id_diario_obra)) {
        $diary_id = $diarioObra->id_diario_obra;
        error_log("RDO - Using diary ID from diarioObra object: $diary_id");
    } elseif (isset($id_diario_obra)) {
        $diary_id = $id_diario_obra;
        error_log("RDO - Using diary ID from id_diario_obra variable: $diary_id");
    } elseif (isset($_GET['id_diario_obra'])) {
        $diary_id = $_GET['id_diario_obra'];
        error_log("RDO - Using diary ID from GET parameter: $diary_id");
    }
    
    if ($diary_id && isset($dao)) {
        $album = $dao->buscaAlbumDiario($diary_id);
        error_log("RDO - Loaded album locally with diary ID: $diary_id");
    } else {
        $album = [];
        error_log("RDO - ERROR: No diary ID or DAO available, album will be empty");
    }
} else {
    error_log("RDO - Album already loaded");
}
error_log("RDO - Album has " . count($album) . " photos");
if (count($album) > 0) {
    error_log("RDO - First photo URL: " . $album[0]['url']);
    error_log("RDO - Processing photos for display...");
}
$albumSize = count($album);
$remainder = $albumSize % 3;
error_log("RDO - Album size: $albumSize, remainder: $remainder");

/*
$pathLogo = $pathAlbum . '/logo';
$scan = scandir($pathLogo);
$imgContratada = '';
$imgContratante = '';
foreach($scan as $file)
{
	if (!is_dir("$pathLogo/$file"))
	{
		if (strtolower(explode('.', $file)[0]) == strtolower($contratada->nome_fantasia))
		{
			$imgContratada = "$pathLogo/$file";
		}
		else if (strtolower(explode('.', $file)[0]) == strtolower($contratante->nome_fantasia))
		{
			$imgContratante = "$pathLogo/$file";
		}
	}
}
*/
?>
<!-- DEBUG: RDO.PHP EXECUTION STARTED -->
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>R.D.O</title>
    <!-- DOMPDF-optimized styles - Bootstrap replaced with compatible CSS -->
    <style>
        /* DOMPDF-compatible base styles */
        * {
            font-family: DejaVu Sans; 
            font-size: 12px;
            line-height: 1.2em;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans;
            font-size: 12px;
            line-height: 1.2em;
            color: #000;
        }

        /* Container and layout - optimized for A4 PDF */
        .container {
            width: 100%;
            max-width: 650px;
            margin: 0 auto;
            padding: 10px;
        }

        /* Tables - DOMPDF compatible */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        
        td, th {
            padding: 5px;
            vertical-align: middle;
            border: 1px solid #111;
        }
        
        th {
            font-weight: bold;
            text-align: center;
        }

        /* Header table */
        #header {
            border: 2px solid #000;
        }
        
        #header td {
            border: none;
            padding: 5px;
        }
        
        #header img {
            max-width: 120px;
            max-height: 60px;
            display: block;
        }

        /* Main content tables */
        .table-bordered {
            border: 1px solid #111;
        }
        
        .table-bordered td,
        .table-bordered th {
            padding: 5px;
            color: #111;
            border: 1px solid #111;
            vertical-align: middle;
        }
        
        .table-bordered th {
            font-weight: bold;
            background-color: #f9f9f9;
        }

        /* Album/Photos section - critical for image display */
        #album {
            page-break-inside: avoid;
        }
        
        #album th {
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 8px 5px;
            font-weight: bold;
            font-size: 14px;
        }
        
        #album td {
            border: 1px dotted #111;
            border-top: none;
            height: 150px;
            width: 150px;
            text-align: center;
            vertical-align: middle;
            padding: 5px;
        }
        
        #album td img {
            max-width: 140px;
            max-height: 140px;
            width: auto;
            height: auto;
            vertical-align: middle;
        }

        /* Text alignment utilities */
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        
        /* Font weight utilities */
        .font-weight-bold, .font-weight-bolder { font-weight: bold; }
        
        /* Spacing utilities - simplified for DOMPDF */
        .mt-1 { margin-top: 5px; }
        .mt-4 { margin-top: 20px; }
        .my-4 { margin-top: 20px; margin-bottom: 20px; }
        .my-5 { margin-top: 25px; margin-bottom: 25px; }
        .mb-0 { margin-bottom: 0; }
        .mb-2 { margin-bottom: 10px; }
        .mb-4 { margin-bottom: 20px; }
        .mb-5 { margin-bottom: 25px; }
        .py-0 { padding-top: 0; padding-bottom: 0; }
        .py-2 { padding-top: 10px; padding-bottom: 10px; }
        
        /* Page break controls for PDF */
        .page-break-before { page-break-before: always; }
        .page-break-after { page-break-after: always; }
        .page-break-inside-avoid { page-break-inside: avoid; }
        
        /* Row and column structure */
        .row { width: 100%; }
        .col { display: table-cell; }
        
        /* Border utilities */
        .border-bottom-0 { border-bottom: none; }
        
        /* Paragraph spacing */
        p { margin: 5px 0; }
        
        /* Separator styles */
        hr { 
            border: none; 
            border-top: 1px solid #444; 
            margin: 5px 0; 
        }

        /* Generic spacing */
        .d-block { display: block; }
        .mx-1 { margin-left: 5px; margin-right: 5px; }
        .my-1 { margin-top: 5px; margin-bottom: 5px; }
        .my-0 { margin-top: 0; margin-bottom: 0; }
        .px-2 { padding-left: 10px; padding-right: 10px; }
        .align-top { vertical-align: top; }
        .align-middle { vertical-align: middle; }
        .text-uppercase { text-transform: uppercase; }
        
        /* Float utilities */
        .float-right { float: right; }
        .float-left { float: left; }
    </style>
    <!-- <script src="js/jquery3.5.1.min.js"></script> -->
</head>
<body>
    <article class="mt-1">
        <div class="container">

            <!-- TABELA 1 -->
            <table id="header" style="border: 1px solid #000">
                <tr class="py-0">
                    <td class="my-1 mx-1">
                        <?php if (!empty($contratada->url_logo)): ?>
                        <img 
                            class="m-2"
                            style="min-width: 70px; max-height: 70px; object-fit: contain;" 
                            src="<?php echo htmlspecialchars(getValidLogoSrc($contratada->url_logo)) ?>">
                        <?php endif; ?>
                    </td>
                    <td class="text-center mx-1" style="width: 300px !important; font-size: 16px !important">
                        RELATÓRIO DIÁRIO DE OBRA<br>(R.D.O)
                    </td>
                    <td class="my-1 mx-1">
                        <?php if (!empty($contratante->url_logo)): ?>
                        <img 
                            class="my-2"
                            style="min-width: 70px; max-height: 70px; object-fit: contain;" 
                            src="<?php echo htmlspecialchars(getValidLogoSrc($contratante->url_logo)) ?>">
                        <?php endif; ?>
                    </td>
                    <td style="max-width: 50px; border-left: 1px solid #444; font-size: 12px !important;">
                        <span class="d-block mx-1 my-1">
                            DATA:
                            <?php echo htmlspecialchars($data) ?>                            
                        </span>
                        <hr style="margin: 0 !important; border-top: 1px solid #444">
                        <span class="d-block mx-1 my-1">
                            RELATÓRIO Nº: <?php echo htmlspecialchars($numeroRelatorio) ?>
                        </span>
                    </td>
                </tr>
            </table>

            <!-- TABELA 2 -->
            <table class="table table-bordered my-4">
                <tr class="px-2">
                    <td style="width: 120px">Contratante</td>
                    <td><?php echo htmlspecialchars($contratante->nome_fantasia) ?></td>
                </tr>
                <tr class="px-2">
                    <td>Contratada</td>
                    <td><?php echo htmlspecialchars($contratada->nome_fantasia) ?></td>
                </tr>
                <tr class="px-2">
                    <td>Obra</td>
                    <td><?php echo htmlspecialchars($diarioObra->descricao_resumo) ?></td>
                </tr>
            </table>

            <!-- TABELA 3 -->
            <?php if (isset($descricaoServico)) { ?>
            <table class="table table-bordered my-4">
                <thead>
                    <tr>
                        <td class="text-center font-weight-bolder" style="width: 40px">
                            ITEM
                        </td>
                        <td class="font-weight-bolder">
                            DESCRIÇÃO DOS SERVIÇOS EXECUTADOS
                        </td>
                    </tr>
                </thead>
                <tbody class="align-middle">
                    <?php for ($i = 1; $i <= count($descricaoServico); $i++) { ?>
                        <tr style="font-size:12px !important;">
                            <td class="my-0 py-0 text-center"><?php echo htmlspecialchars($i) ?></td>
                            <td class="my-0 py-0"><?php echo htmlspecialchars($descricaoServico[$i - 1]) ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <?php } ?>

            <!-- TABELA 4 -->
            <?php if (isset($funcionarios)) { ?>
                <table class="table align-middle table-bordered text-center mt-4 mb-0 border-bottom-0">
                    <thead>
                        <tr class="py-0">
                            <td class="font-weight-bolder">
                                N
                            </td>
                            <td class="font-weight-bolder" style="width: 200px">
                                EFETIVO
                            </td>
                            <td class="font-weight-bolder" style="width: 150px">
                                CARGO
                            </td>
                            <td class="font-weight-bolder">
                                HORÁRIO
                            </td>
                            <td class="font-weight-bolder">
                                EMPRESA
                            </td>
                        </tr>
                    </thead>
                    <tbody class="align-middle" style="font-size: 10px !important">
                        <?php for ($i = 1; $i <= count($funcionarios); $i++) { ?>
                            <tr class="py-0 my-0" style="max-height: 0em; height: 0em; padding: 0.2em !important">
                                <td style="padding: 0.2em 0 !important; max-height: 0em !important; max-width: 2px !important" class="my-0 py-0"><?php echo htmlspecialchars($i) ?></td>
                                <td style="padding: 0.2em 0.3em !important; max-height: 0em !important;" class="my-0 py-0 text-uppercase"><?php echo htmlspecialchars(ucwords($funcionarios[$i - 1]->nome)) ?></td>
                                <td style="padding: 0.2em 0.3em !important; max-height: 0em !important;" class="my-0 py-0 text-uppercase"><?php echo htmlspecialchars(ucwords($funcionarios[$i - 1]->cargo)) ?></td>
                                <td style="padding: 0.2em !important; max-height: 0em !important;" class="my-0 py-0 text-uppercase"><?php echo htmlspecialchars($horaEntrada[$i - 1] . ' às ' . $horaSaida[$i - 1]) ?></td>
                                <td style="padding: 0.2em 0 !important; max-height: 0em !important; max-width: 5px !important" class="my-0 py-0 text-uppercase"><?php echo htmlspecialchars(ucwords($funcionarios[$i - 1]->nome_fantasia)) ?></td>
                            </tr>
                        <?php } ?>
                        
                    </tbody>
                </table>
                
                <!-- TABELA 5 -->
                <table class="table table-bordered mt-0 mb-4" style="border-top: none;">
                    <tr style="border-top: 0 !important;">
                        <td class="text-center" style="line-height: 1.2em !important;width: 40%">
                            <p class="my-0 font-weight-bolder">HORÁRIO DE TRABALHO:</p>
                            <?php echo htmlspecialchars($horarioTrabalho) ?>
                        </td>
                        <td class="text-center p-0">
                            <div class="py-2" style="border-bottom: 1px solid #333;">
                                <b class="mr-2">CARGA HORAS DO DIA:</b>
                                <span>
                                    <?php echo formatHours($cargaHorasDia); ?>
                                </span>
                            </div>
                            <div class="py-2">
                                <b class="mr-2">SOMA TOTAL DE HORAS:</b>
                                <span>
                                    <?php echo formatHours($totalAcumuladoHorasObra); ?>
                                </span>
                            </div>
                        </td>
                    </tr>
                </table>
            <?php } ?>

            <!-- TABELA 6 -->
            <table class="table table-bordered my-4 mb-2">
                <thead>
                    <tr>
                        <td class="font-weight-bolder">
                            OBSERVAÇÕES GERAIS
                        </td>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="pt-0 mt-0 pb-4 mb-3"><?php echo htmlspecialchars($obsGeral ? $obsGeral : ' ') ?></td>
                    </tr>
                </tbody>
            </table>

            <!-- FOTOS -->
            <table id="album" class="table my-5">
                <thead>
                    <tr>
                        <th style="border-left: 2px solid #000 !important;"></th>
                        <th class="text-center">
                            FOTOS
                        </th>
                        <th style="border-right: 2px solid #000 !important;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // SIMPLIFIED PHOTO LOADING: Follow the same pattern as logos
                    // Load album directly using diarioObra object, just like logos do
                    $photos = [];
                    if (isset($diarioObra) && is_object($diarioObra) && isset($dao)) {
                        $photos = $dao->buscaAlbumDiario($diarioObra->id_diario_obra);
                        error_log("FOTOS section - Loaded " . count($photos) . " photos");
                    }

                    $photoCount = count($photos);
                    $remainder = $photoCount % 3;

                    // Generate photo rows in groups of 3 (same logic but simplified)
                    for ($i = 0; $i < $photoCount - $remainder; $i += 3) {
                    ?>
                        <tr>
                            <td>
                                <p style="text-align: center; vertical-align: middle;">
                                    <?php if (isset($photos[$i]['url'])): ?>
                                        <img style="vertical-align: middle;" src="<?php echo htmlspecialchars(getValidImageSrc($photos[$i]['url'])) ?>">
                                    <?php endif; ?>
                                </p>
                            </td>
                            <td>
                                <p style="text-align: center; vertical-align: middle;">
                                    <?php if (isset($photos[$i + 1]['url'])): ?>
                                        <img style="vertical-align: middle;" src="<?php echo htmlspecialchars(getValidImageSrc($photos[$i + 1]['url'])) ?>">
                                    <?php endif; ?>
                                </p>
                            </td>
                            <td>
                                <p style="text-align: center; vertical-align: middle;">
                                    <?php if (isset($photos[$i + 2]['url'])): ?>
                                        <img style="vertical-align: middle;" src="<?php echo htmlspecialchars(getValidImageSrc($photos[$i + 2]['url'])) ?>">
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    <?php } ?>

                    <?php
                    // Handle remaining photos (if not divisible by 3)
                    if ($remainder > 0) {
                    ?>
                        <tr>
                            <?php for ($j = $photoCount - $remainder; $j < $photoCount; $j++): ?>
                            <td>
                                <p style="text-align: center; vertical-align: middle;">
                                    <?php if (isset($photos[$j]['url'])): ?>
                                        <img style="vertical-align: middle;" src="<?php echo htmlspecialchars(getValidImageSrc($photos[$j]['url'])) ?>">
                                    <?php endif; ?>
                                </p>
                            </td>
                            <?php endfor; ?>

                            <?php
                            // Fill remaining cells with empty content
                            for ($k = 0; $k < (3 - $remainder); $k++): ?>
                            <td>
                                <p style="text-align: center; vertical-align: middle;">
                                </p>
                            </td>
                            <?php endfor; ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>

            <table class="table table-bordered my-4">
                <tbody>
                    <tr class="text-center">
                        <td style="height: 50px !important" class="align-top">VISTO CONTRATANTE: <b><?php echo htmlspecialchars($contratante->nome_fantasia) ?></b></td>
                        <td style="height: 50px !important" class="align-top">VISTO CONTRATADA: <b><?php echo htmlspecialchars($contratada->nome_fantasia) ?></b></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </article>
</body>
</html>
