<?php
// Configurações de upload - define limites maiores
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '100M');
ini_set('max_file_uploads', '20');
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '300');

// require_once __DIR__ . '/bootstrap.php'; // Nova arquitetura - temporariamente desabilitado
require_once __DIR__ . '/startup.php'; // Includes e DAO
require_once __DIR__ . '/ftpFunctions.php';

use Src\Exception\ServiceException;
use Config\Config;
use Models\Connection;
use Models\DAO;
use Models\Imagem;

// Nova lógica de upload de imagens
if (isset($_FILES['file']) && isset($_POST['id_diario_obra'])) {
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	try {
		$diarioId = (int) $_POST['id_diario_obra'];
		
		// Debug info
		error_log("Upload attempt for diario: " . $diarioId);
		error_log("File info: " . print_r($_FILES['file'], true));
		
		// Debug - verifica limites atuais
		error_log("PHP Upload Limits - Max filesize: " . ini_get('upload_max_filesize') . ", Post max: " . ini_get('post_max_size'));
		error_log("File size received: " . (isset($_FILES['file']['size']) ? $_FILES['file']['size'] : 'N/A'));
		
		// Validate upload
		if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
			$uploadError = isset($_FILES['file']['error']) ? $_FILES['file']['error'] : 'No file';
			
			// Mensagens de erro mais claras com limites atuais
			$currentLimit = ini_get('upload_max_filesize');
			$errorMessages = [
				UPLOAD_ERR_INI_SIZE => 'Arquivo muito grande. Máximo permitido pelo servidor: ' . $currentLimit . '. Reinicie o servidor com: php -c php.ini -S localhost:8000',
				UPLOAD_ERR_FORM_SIZE => 'Arquivo excede o tamanho máximo do formulário',
				UPLOAD_ERR_PARTIAL => 'Upload incompleto. Tente novamente',
				UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
				UPLOAD_ERR_NO_TMP_DIR => 'Erro no servidor: diretório temporário ausente',
				UPLOAD_ERR_CANT_WRITE => 'Erro ao gravar arquivo no servidor',
				UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão PHP'
			];
			
			error_log("Upload error code: " . $uploadError);
			$errorMsg = isset($errorMessages[$uploadError]) ? $errorMessages[$uploadError] : 'Erro desconhecido no upload: ' . $uploadError;
			throw new Exception($errorMsg);
		}
		
		// Usa DAO diretamente por enquanto
		$pdo = Connection::getPDO();
		$dao = new DAO($pdo);
		$album = $dao->buscaAlbumDiario($diarioId);
		
		// Generate unique filename with proper extension
		$originalName = $_FILES['file']['name'];
		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
			$extension = 'jpg'; // Default to jpg
		}
		
		$imageIndex = count($album);
		$maxAttempts = 100;
		for ($i = $imageIndex; $i < $imageIndex + $maxAttempts; $i++) {
			$filename = "diario-{$diarioId}-foto-{$i}.{$extension}";
			$testPath = __DIR__ . '/img/album/' . $filename;
			// Check if file exists in filesystem or database
			$existsInDb = false;
			foreach ($album as $img) {
				if ($img['url'] === $filename) {
					$existsInDb = true;
					break;
				}
			}
			if (!file_exists($testPath) && !$existsInDb) {
				break;
			}
		}
		if ($i >= $imageIndex + $maxAttempts - 1) {
			throw new Exception('Não foi possível gerar nome único para a imagem');
		}
		
		// Ensure directory exists with proper permissions
		$uploadDir = __DIR__ . '/img/album/';
		if (!is_dir($uploadDir)) {
			if (!mkdir($uploadDir, 0777, true)) {
				throw new Exception('Não foi possível criar diretório de upload');
			}
		}
		
		// Make sure directory is writable
		if (!is_writable($uploadDir)) {
			throw new Exception('Diretório de upload sem permissão de escrita');
		}
		
		$uploadPath = $uploadDir . $filename;
		
		// Check if temp file exists and is readable
		if (!is_uploaded_file($_FILES['file']['tmp_name'])) {
			throw new Exception('Arquivo temporário inválido');
		}
		
		// Log file info before move
		error_log("Attempting to move file:");
		error_log("  From: " . $_FILES['file']['tmp_name']);
		error_log("  To: " . $uploadPath);
		error_log("  Temp file exists: " . (file_exists($_FILES['file']['tmp_name']) ? 'yes' : 'no'));
		error_log("  Temp file size: " . (file_exists($_FILES['file']['tmp_name']) ? filesize($_FILES['file']['tmp_name']) : 'N/A'));
		
		// Move arquivo enviado
		if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadPath)) {
			// Insere no banco
			$imagem = new Imagem();
			$imagem->fk_id_diario_obra = $diarioId;
			$imagem->url = $filename;
			$dao->insereImagem($imagem);
			
			// Busca ID da imagem inserida
			$album = $dao->buscaAlbumDiario($diarioId);
			$lastImage = end($album);
			
			$result = [
				'image_id' => $lastImage['id_imagem'],
				'filename' => $filename
			];
		} else {
			// More detailed error message
			$error = error_get_last();
			error_log("move_uploaded_file failed:");
			error_log("  PHP Error: " . print_r($error, true));
			error_log("  Upload error code: " . $_FILES['file']['error']);
			throw new Exception('Falha ao mover arquivo enviado. Erro: ' . ($error ? $error['message'] : 'Desconhecido') . ' - Caminho: ' . $uploadPath);
		}

		// Resposta para Dropzone
		header('Content-Type: application/json');
		echo json_encode([
			'success' => true,
			'message' => 'Imagem enviada com sucesso',
			'image_id' => $result['image_id'],
			'filename' => $result['filename']
		]);
		exit;
	} catch (ServiceException $e) {
		header('HTTP/1.1 400 Bad Request');
		header('Content-Type: application/json');
		echo json_encode([
			'success' => false,
			'error' => $e->getUserMessage()
		]);
		exit;
	}
}

// Endpoint AJAX para carregar fotos
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_photos' && isset($_GET['id_diario_obra'])) {
	header('Content-Type: application/json');
	$photos = $dao->buscaAlbumDiario($_GET['id_diario_obra']);

	// Check for actual file existence and correct extension
	$baseDir = __DIR__ . '/img/album/';
	foreach ($photos as &$photo) {
		$url = $photo['url'];

		// If URL doesn't contain path, check for file with different extensions
		if (strpos($url, '/') === false) {
			$pathWithoutExt = pathinfo($url, PATHINFO_FILENAME);
			$possibleExtensions = ['jpg', 'jpeg', 'png', 'webp'];

			foreach ($possibleExtensions as $ext) {
				$testFile = $baseDir . $pathWithoutExt . '.' . $ext;
				if (file_exists($testFile)) {
					$photo['url'] = $pathWithoutExt . '.' . $ext;
					break;
				}
			}
		}
	}

	echo json_encode($photos);
	exit;
}

// Endpoint para salvar observações gerais
if (isset($_POST['action']) && $_POST['action'] === 'save_observations' && isset($_POST['id_diario_obra'])) {
	header('Content-Type: application/json');
	try {
		$idDiarioObra = (int) $_POST['id_diario_obra'];
		$obsGerais = $_POST['obs_gerais'] ?? '';
		
		// Inicializa DAO
		$pdo = Connection::getPDO();
		$dao = new DAO($pdo);
		
		// Busca o registro completo
		$diarioObra = $dao->buscaDiarioObraPorId($idDiarioObra);
		
		if (!$diarioObra) {
			throw new Exception('Diário de obra não encontrado');
		}
		
		// Atualiza apenas o campo obs_gerais
		$diarioObra->obs_gerais = $obsGerais;
		
		// Salva no banco
		$result = $dao->updateDiarioObra($diarioObra);
		
		if ($result) {
			echo json_encode([
				'success' => true,
				'message' => 'Observações salvas com sucesso'
			]);
		} else {
			throw new Exception('Erro ao salvar observações');
		}
	} catch (Exception $e) {
		echo json_encode([
			'success' => false,
			'error' => $e->getMessage()
		]);
	}
	exit;
}

// Endpoint para excluir imagem individual
if (isset($_POST['action']) && $_POST['action'] === 'delete_image' && isset($_POST['image_id'])) {
	header('Content-Type: application/json');
	try {
		$imageId = (int) $_POST['image_id'];
		
		// Inicializa DAO
		$pdo = Connection::getPDO();
		$dao = new DAO($pdo);
		
		// Busca informações da imagem antes de deletar
		$imageInfo = $dao->buscaImagemPorId($imageId);
		
		if ($imageInfo) {
			// Remove arquivo físico
			$imagePath = __DIR__ . '/img/album/' . $imageInfo['url'];
			if (file_exists($imagePath)) {
				unlink($imagePath);
			}
			
			// Remove do banco de dados
			$dao->deleteImagemPorId($imageId);
			
			echo json_encode([
				'success' => true,
				'message' => 'Imagem excluída com sucesso'
			]);
		} else {
			echo json_encode([
				'success' => false,
				'error' => 'Imagem não encontrada'
			]);
		}
	} catch (Exception $e) {
		echo json_encode([
			'success' => false,
			'error' => 'Erro ao excluir imagem: ' . $e->getMessage()
		]);
	}
	exit;
}

if (isset($_GET['id_diario_obra'])) {
	$diarioObra = $dao->buscaDiarioObraPorId($_GET['id_diario_obra']);
	$album = $dao->buscaAlbumDiario($_GET['id_diario_obra']);

	if (isset($_GET['baixar_album']) && $_GET['baixar_album'] == 1) {
		$sourcePath = __DIR__ . '/img/album-copy';
		$zipPath = __DIR__ . '/img/album.zip';

		if (!file_exists($sourcePath)) {
			mkdir($sourcePath, 0777, true);
		}

		$i = 0;
		foreach ($album as $foto) {
			$arr = explode('.', $foto['url']);
			$extensao = $arr[count($arr) - 1];

			copyFileFromTo($foto['url'], "$sourcePath/foto-$i.$extensao");
			$i++;
		}

		zipFolder($sourcePath, $zipPath);
		downloadFile($zipPath);

		cleanDir($sourcePath);
		rmdir($sourcePath);
		unlink($zipPath);

		header("Location: coletorDados.php?id_diario_obra={$_GET['id_diario_obra']}");

		die();
	} elseif (isset($_GET['remover_album']) && $_GET['remover_album'] == 1) {
		try {
			$imageUploadService = app('image.upload');
			$diarioId = (int) $_GET['id_diario_obra'];

			// Remove todas as imagens usando nova arquitetura
			$result = $imageUploadService->deleteAllImagesFromDiario($diarioId);

			// Fallback: remove imagens restantes do modo legacy
			foreach ($album as $foto) {
				if (file_exists($foto['url'])) {
					unlink($foto['url']);
				}
			}

			// Remove registros legacy do banco
			$dao->deleteAlbum($diarioId);
		} catch (ServiceException $e) {
			// Em caso de erro, usa método legacy
			foreach ($album as $foto) {
				if (file_exists($foto['url'])) {
					unlink($foto['url']);
				}
			}
			$dao->deleteAlbum($_GET['id_diario_obra']);
		}

		header("Location: coletorDados.php?id_diario_obra={$_GET['id_diario_obra']}");
		exit;
	}

	$contratante = $dao->buscaEmpresaPorId($diarioObra->fk_id_contratante);

	$contratada = $dao->buscaEmpresaPorId($diarioObra->fk_id_contratada);

	$listaFuncionarios = $dao->buscaTodosFuncionariosJoinEmpresa();

	$listaServicos = $dao->buscaTodosServicosDoDiarioObra($_GET['id_diario_obra']);

	$funcionarioDiarioObra = $dao->buscaFuncionariosDoDiarioDeObra($_GET['id_diario_obra']);
} elseif (isset($_GET['funcionario'])) {
	$nomeFuncionario = trim($_GET['funcionario']);
	$result = $dao->pesquisaListaFuncionariosPorNome($nomeFuncionario);

	if ($result) {
		echo json_encode($result);
	}
	die();
} else {
	header('Location: cadastroObra.php');
}

function cleanDir($dirPath)
{
	$handle = opendir($dirPath);
	while (false !== ($entry = readdir($handle))) {
		if (!is_dir("$dirPath/$entry")) {
			unlink("$dirPath/$entry");
		}
	}
	closedir($handle);
}

function copyFileFromTo($sourceFile, $destFile)
{
	$handle = fopen($sourceFile, 'rb');
	$contents = '';
	while (!feof($handle)) {
		$contents .= fread($handle, 8192);
	}
	fclose($handle);

	if (!$handle = fopen($destFile, 'wb')) {
		exit("Não foi possível abrir o arquivo ($destFile)");
	}
	if (fwrite($handle, $contents) === false) {
		exit("Não foi possível escrever no arquivo ($destFile)");
	}
	fclose($handle);
}

function zipFolder($sourcePath, $pathZip)
{
	$rootPath = realpath($sourcePath);
	$zip = new ZipArchive();
	$zip->open($pathZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);

	$handle = opendir($sourcePath);
	while (false !== ($entry = readdir($handle))) {
		if (!is_dir("$sourcePath/$entry")) {
			$filePath = realpath("$sourcePath/$entry");
			$relativePath = substr($filePath, strlen($rootPath) + 1);

			$zip->addFile($filePath, $relativePath);
		}
	}
	closedir($handle);

	$zip->close();
}

function downloadFile($localFile)
{
	if (file_exists($localFile)) {
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . basename($localFile) . '"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate');
		header('Pragma: public');
		header('Content-Length: ' . filesize($localFile));
		readfile($localFile);
	}
}

?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <title>Coletor de dados para o RDO</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        <link rel="stylesheet" href="css/bootstrap4.5.2.min.css">
        <script src="js/jquery3.5.1.min.js"></script>
        <script src="js/popper.min.js"></script>
        <script src="js/bootstrap4.5.2.min.js"></script>
        <link rel="stylesheet" href="dropzone-5.7.0/dist/dropzone.css" />
        <script src="dropzone-5.7.0/dist/dropzone.js"></script>
        <script>
            // Desabilita o autoDiscover do Dropzone para evitar conflitos
            Dropzone.autoDiscover = false;
        </script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js" integrity="sha512-pHVGpX7F/27yZ0ISY+VVjyULApbDlD0/X0rgGbTqCE7WFW5MezNTWG/dnhtbBuICzsd0WQPgpE4REBLv+UqChw==" crossorigin="anonymous"></script>
        <style>
            .table td {
                padding: 5px 10px;
            }
            .table td.small-col {
                width: 120px !important;
                font-weight: bolder;
            }
            .table td.tiny-col {
                width: 50px !important;
            }
            .table td input {
                border: none;
            }
            ::-webkit-input-placeholder { /* Edge */
                color: #aaa !important;
                /* font-style: italic; */
            }
            :-ms-input-placeholder { /* Internet Explorer 10-11 */
                color: #aaa !important;
                /* font-style: italic; */
            }
            ::placeholder {
                color: #aaa !important;
                /* font-style: italic; */
            }
            .listaFuncionarios .list-group-item-action:hover {
                background: #f8f9fa !important;
                cursor: pointer;
            }
            .listaFuncionarios .list-group-item-action.active {
                background: #f8f9fa !important;
                /* background: #007bff !important; */
                color: #495057;
            }
            /**************** DROPZONE ****************/
            .dropzone {
                border: 2px dashed #888;
                border-radius: 5px;
                padding: 5px !important; 
                margin-top: 0 !important; 
                text-align: center; 
                width: 100%; 
                min-height: 100px !important;
            }
            .dropzone .dz-preview .dz-image {
                width: 100px;
                height: 100px;
                border-radius: 5px;
            }
            .dropzone .dz-message {
                margin: 10px;
            }
            .dropzone .dz-preview {
                margin: 0;
                min-height: 100px;
            }
            .dropzone .dz-preview.dz-image-preview {
                background: none;
            }
            .dropzone .dz-preview .dz-remove {
                margin-top: -5px;
                position: relative;
                z-index: 1000;
                display: inline-block;
            }
            .dropzone .dz-preview .dz-details {
                display: none !important; /* Hide default file details since we have tooltip */
            }
            /* Hover tooltip for image info */
            .dropzone .dz-preview {
                position: relative;
            }
            .dropzone .dz-preview .dz-image-info-tooltip {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(0, 0, 0, 0.9);
                color: white;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 12px;
                white-space: nowrap;
                pointer-events: none;
                opacity: 0;
                transition: opacity 0.3s ease;
                z-index: 2000;
                box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            }
            .dropzone .dz-preview:hover .dz-image-info-tooltip {
                opacity: 1;
            }
            .dropzone .dz-preview .dz-image {
                position: relative;
            }
            .dropzone .dz-preview:hover .dz-image::after {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.3);
                border-radius: 5px;
                pointer-events: none;
            }
        </style>
        <script>

            function filtraFuncionariosInclusos(listaFuncionarios) {
                let funcionariosInclusos = []
                $('.inputFuncionario').not(':focus').each(function(index, element) {
                    let valor = element.value;
                    if (valor != '')
                    {
                        funcionariosInclusos.push(valor)
                    }
                });

                let listaFiltrada = listaFuncionarios.filter(function(item) {
                    let nome = item['nome']
                    return funcionariosInclusos.every(function(value) {
                        return value != nome;
                    });
                });

                console.log(listaFiltrada);
                return listaFiltrada;
            }

            function addDescricaoServico(descricao='') {
                let lineNumber = $('#table-desc-servicos tbody tr:last-child td:first-child').text();
                lineNumber = lineNumber * 1;
                
                $('#table-desc-servicos tbody').append(`
                    <tr>
                        <td class="tiny-col text-center border-right align-middle">${++lineNumber}</td>
                        <td>
                            <input type="text"
                                name="descricaoServico[]"
                                value="${descricao}"
                                placeholder="DESCRIÇÃO DO SERVIÇO AQUI"
                                class="form-control">
                        </td>
                    </tr>
                `)
            }

            function getMinHora()
            {
                let min = 1800;
                $('.horaEntrada').each(function(index, element) {
                    let valor = element.value;
                    if (valor.length == 5)
                    {
                        let hora_minuto = valor.split(':');
                        let time = parseInt(`${hora_minuto[0]}${hora_minuto[1]}`)

                        if (time < min)
                        {
                            min = time;
                        }
                    }
                })

                return min;
            }

            function getMaxHora()
            {
                let max = 700;
                $('.horaSaida').each(function(index, element) {
                    let valor = element.value;
                    if (valor.length == 5)
                    {
                        let hora_minuto = valor.split(':');
                        let time = parseInt(`${hora_minuto[0]}${hora_minuto[1]}`)

                        if (time > max)
                        {
                            max = time;
                        }
                    }
                })

                return max;
            }

            function atualizaCampoHorarioTrabalho()
            {
                let he = getMinHora()
                let hs = getMaxHora()
                he = Math.floor(he / 100).toString().padStart(2, '0') + ':' + (he % 100).toString().padStart(2, '0')
                hs = Math.floor(hs / 100).toString().padStart(2, '0') + ':' + (hs % 100).toString().padStart(2, '0')

                $('#horarioTrabalho').val(`${he}h às ${hs}h`)
            }

            function addFuncionario(nome='', horaEntrada='07:00', horaSaida='17:00', totalHoras='09:00')
            {
                let numFilhos = $('#table-efetivos tbody').children().length;
                $('#table-efetivos tbody').append(`
                    <tr>
                        <td class="border-right align-middle text-center">
                            ${numFilhos+1} 
                        </td>
                        <td class="border-right">
                            <input type="text"
                                autocomplete="off"
                                placeholder="NOME DO FUNCIONÁRIO"
                                name="nomeFuncionario[]"
                                value="${nome}"
                                id="inputFuncionario${numFilhos+1}"
                                class="inputFuncionario form-control">

                            <ul id="listaFuncionarios${numFilhos+1}" class="list-group listaFuncionarios"></ul>
                        </td>
                        <td class="border-right">
                            <input type="text"
                                name="horaEntrada[]"
                                autocomplete="off"
                                value="${horaEntrada}"
                                id="inputHoraEntrada${numFilhos+1}"
                                placeholder="00:00"
                                class="hora horaEntrada form-control">
                        </td>
                        <td class="border-right">
                            <input type="text" 
                                name="horaSaida[]"
                                autocomplete="off"
                                value="${horaSaida}"
                                id="inputHoraSaida${numFilhos+1}"
                                placeholder="00:00"
                                class="hora horaSaida form-control">
                        </td>
                        <td style="width:110px" class="px-0 font-italic text-secondary small bg-light">
                            <!-- <span>TOTAL HORAS</span> -->
                            <input type="text"
                                readonly
                                class="totalHoras bg-light form-control"
                                style="width:110px"
                                placeholder="Total Horas"
                                name="totalHoras[]"
                                value="${totalHoras}">
                        </td>
                    </tr>
                `);

                atualizaCampoHorarioTrabalho();
            }

            function sendUserTypedTextRequest(enteredText, thisValue)
            { 
                let sibling = $(thisValue).next()
                // $('.inputFuncionario').next().empty()
                $.ajax({
                    url: '<?php echo htmlspecialchars($_SERVER['PHP_SELF']) ?>?funcionario='+enteredText,
                    type: 'GET',
                    success: function(result) {
                        if (result)
                        {
                            let lista = JSON.parse(result);
        
                            let listaFiltrada = filtraFuncionariosInclusos(lista)
                            //2
                            $('.inputFuncionario').next().empty()

                            for (item of listaFiltrada)
                            {
                                // console.log(item)
                                $(sibling).append(`
                                    <li class="list-group-item list-group-item-action">
                                        ${item['nome']} (${item['cargo']} /${item['nome_fantasia']}) 
                                    </li>
                                `);
                            }

                            $(sibling).children('li.list-group-item').on('click', function(e) {
                                let campoTexto = $(this).text().split('(')[0].trim()
                                // console.log('*'+campoTexto+'*');

                                $(this).parent().prev().val(campoTexto);
                                $('.inputFuncionario').next().empty()
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log(error);
                    }
                });
            }

            function addListenerFuncionario()
            {
                $('.hora').mask('00:00');

                let numFilhos = $('#table-efetivos tbody').children().length;

                // `#inputHoraSaida${numFilhos}`
                $(`#inputHoraEntrada${numFilhos}, #inputHoraSaida${numFilhos}`).on('keyup', function(e) {
                    atualizaCampoHorarioTrabalho();
                });
           
                $(`input.hora`).on('keyup', function() {
                    let hora = this.value;
                    // console.log(e);

                    if (hora.length == 5)
                    {
                        let arr = hora.split(':');
                        let h = arr[0]
                        let m = arr[1];
                        if (h > 23)
                        {
                            h = '23';
                            m = '59'    
                        }                            
                        if (m > 59)
                            m = '59';
                        
                        this.value = `${h}:${m}`;
                    }
                    
                    let horaEntrada;
                    let horaSaida;
                    
                    if ($(this).hasClass('horaEntrada'))
                    {
                        horaEntrada = $(this).val();
                        horaSaida = $(this).parent().next().children().val();
                        // console.log('entrada: '+horaEntrada)
                        // console.log('saida: '+horaSaida)
                    }
                    else
                    {
                        horaSaida = $(this).val();
                        horaEntrada = $(this).parent().prev().children().val();
                        // console.log('entrada: '+horaEntrada)
                        // console.log('saida: '+horaSaida)
                    }
                    if (horaEntrada.length == 5 && horaSaida.length == 5)
                    {
                        horaEntrada = horaEntrada.split(':')
                        horaSaida = horaSaida.split(':')
                        //create date format          
                        var timeStart = new Date(2021, 0, 1, horaEntrada[0], horaEntrada[1]).getTime();
                        var timeEnd = new Date(2021, 0, 1, horaSaida[0], horaSaida[1]).getTime();

                        // console.log(timeStart);
                        // console.log(timeEnd);
                        var timeDiff = Math.abs(timeEnd - timeStart);
                        // console.log(timeDiff);

                        minutes = Math.ceil(timeDiff / (1000 * 60));
                        // console.log(minutes);
                        
                        hours =  Math.floor(timeDiff / (1000 * 60 * 60));
                        minutes = minutes - hours * 60;
                        
                        if (horaSaida[0] > 14 || (horaSaida[0] == 14 && horaSaida[1] > 0))
                        {
                            hours--;
                        }

                        hours = hours < 10 ? `0${hours}` : hours;
                        minutes = minutes < 10 ? `0${minutes}` : minutes;
                        
                        // console.log(`${hours}:${minutes}`);
                        $(this).parents('tr').find('input.totalHoras').val(`${hours}:${minutes}`)
                    }
                })

                /*******************************************************/
                numFilhos = $('#table-efetivos tbody').children().length;
                $(`#inputFuncionario${numFilhos}`).focusout(function() {
                    let lista = $(this).next();
                    if (!$(lista).is(':hover'))
                    {
                        $(lista).empty();
                    }
                });
                
                $(`#inputFuncionario${numFilhos}`).focusin(function(e) {
                    let sibling = $(this).next();
                    let lista = JSON.parse('<?php echo htmlspecialchars(json_encode($listaFuncionarios)) ?>')
                    
                    //AQUI!!
                    
                    let listaFiltrada = filtraFuncionariosInclusos(lista)

                    // console.log(listaFiltrada);
                    
                    $(sibling).empty();
                    for (item of listaFiltrada)
                    {
                        // console.log(item)
                        $(sibling).append(`
                            <li class="list-group-item list-group-item-action">
                                ${item['nome']} (${item['cargo']} /${item['empresa']}) 
                            </li>
                        `);
                    }

                    $(`#listaFuncionarios${numFilhos} li.list-group-item`).on('click', function(e) {
                        let campoTexto = $(this).text().split('(')[0].trim()
                        console.log('*'+campoTexto+'*');

                        $(this).parent().prev().val(campoTexto);
                        $('.inputFuncionario').next().empty()
                    });
                });

                $(`#inputFuncionario${numFilhos}`).on('keydown', function(e) 
                {                   
                    let tecla = e.key;
                    let sibling = $(this).next()
                    console.log(tecla)
                    
                    if (tecla === 'ArrowDown')
                    {
                        let current = $(sibling).children('.active')
                        let next = $(current).next()    
                        $(current).removeClass('active')
                        $(next).addClass('active')
                        
                        if (current.length && next.length)
                        {
                            $(current).removeClass('active')
                            $(next).addClass('active')
                        }
                        else if (current.length)
                        {
                            $(current).removeClass('active')
                            $(sibling).children('li.list-group-item:first-child').addClass('active')
                        }
                        else
                        {
                            $(sibling).children('li.list-group-item:first-child').addClass('active')
                        }
                    }
                    else if (tecla === 'ArrowUp')
                    {
                        let current = $(sibling).children('.active')
                        let prev = $(current).prev()

                        if (current.length && prev.length)
                        {
                            $(current).removeClass('active')
                            $(prev).addClass('active')
                        }
                        else if (current.length)
                        {
                            $(current).removeClass('active')
                            $(sibling).children('li.list-group-item:last-child').addClass('active')
                        }
                        else
                        {
                            $(sibling).children('li.list-group-item:last-child').addClass('active')
                        }
                    }
                    else if (tecla === 'Enter')
                    {
                        let current = $(sibling).children('.active');
                        if (current.length)
                        {
                            let campoTexto = $(current).text().split('(')[0].trim()
                            $(this).val(campoTexto);

                            $('.inputFuncionario').next().empty()
                        }

                        e.preventDefault();
                    }
                    
                    // return false;
                });
                /*******************************************************/
                
                t1 = (new Date()).getTime();
                $(`#inputFuncionario${numFilhos}`).on('keyup', function(e) {
                    let tecla = e.key;
                    let sibling = $(this).next()
                    // t1 = (new Date()).getTime();

                    // console.log(tecla)
                    
                    if (tecla === 'ArrowDown' ||
                        tecla === 'ArrowUp' ||
                        tecla === 'ArrowLeft' ||
                        tecla === 'ArrowRight' ||
                        tecla === 'Tab' ||
                        tecla === 'Shift' ||
                        tecla === 'Enter')
                    {
                        return false;
                    }

                    $('.inputFuncionario').next().empty()
                    
                    let entrada = this.value.trim();
                    let dt;
                    if (dt = (new Date()).getTime() - t1 > 350)
                    {
                        console.log('dt = '+dt);
                        t1 = (new Date()).getTime();
                        if (entrada != '')
                        {                
                            sendUserTypedTextRequest(entrada, this)        
                        }
                    }
                });
                /*******************************************************/
            }
            /**** FIM function addListenerFuncionario ****/
            /******************************************************************/

            $(() => {
                let existemServicosDiario = '<?php echo isset($listaServicos) && !empty($listaServicos) ?>';
                if (existemServicosDiario)
                {
                    $('#table-desc-servicos').removeClass('d-none')
                    
                    <?php foreach ($listaServicos as $descricaoServico) { ?>
                        addDescricaoServico("<?php echo htmlspecialchars($descricaoServico['descricao']) ?>")
                    <?php } ?>
                }

                let existemFuncionariosDiario = '<?php echo isset($funcionarioDiarioObra) && !empty($funcionarioDiarioObra) ?>';
                if (existemFuncionariosDiario)
                {
                    $('#table-efetivos').removeClass('d-none');
                    
                    let horario_trabalho;
                    let horas_trabalhadas;
                    
                    <?php foreach ($funcionarioDiarioObra as $fdo) { ?>
                        horario_trabalho = '<?php echo htmlspecialchars($fdo['horario_trabalho']) ?>'.split(' às ');
                        horas_trabalhadas = '<?php echo htmlspecialchars($fdo['horas_trabalhadas']) ?>';
                        
                        minutos_trabalhados = Math.floor((horas_trabalhadas - Math.floor(horas_trabalhadas)) * 60)
                        minutos_trabalhados = minutos_trabalhados < 10 ? `0${minutos_trabalhados}` : minutos_trabalhados;

                        horas_trabalhadas = Math.floor(horas_trabalhadas) < 10 ? `0${Math.floor(horas_trabalhadas)}` : Math.floor(horas_trabalhadas);
  
                        addFuncionario('<?php echo htmlspecialchars($fdo['nome']) ?>', horario_trabalho[0], horario_trabalho[1], `${horas_trabalhadas}:${minutos_trabalhados}`)
                        addListenerFuncionario()
                    <?php } ?>
                }

                $('#adicionarDescricao').on('click', function () {
                    // console.log();
                    if ($('#table-desc-servicos').hasClass('d-none'))
                    {
                        $('#table-desc-servicos').removeClass('d-none')
                    }
                    addDescricaoServico();
                });

                $('#adicionarFuncionario').on('click', function() {
                    if ($('#table-efetivos').hasClass('d-none'))
                    {
                        $('#table-efetivos').removeClass('d-none')
                    }
                    addFuncionario();
                    addListenerFuncionario();
                });
            })
        </script>
    </head>
    <body>
        <div class="container w-50">
            <ul class="nav nav-tabs my-3 mb-5 w-100 mx-auto">
                <li class="nav-item">
                    <a class="nav-link" href="cadastroDiarioObras.php?id_obra=<?php echo htmlspecialchars($diarioObra->fk_id_obra) ?>">Voltar</a>
                </li>
            </ul>
            <h1 class="h3 text-black-50 text-center my-3">Formulário para geração automática do R.D.O</h1>

            <!-- CABEÇALHO -->
            <table class="table border my-5 mx-auto">
                <tbody>
                    <tr>
                        <td class="small-col border-right">Contratante</td>
                        <td><?php echo htmlspecialchars($contratante->nome_fantasia) ?></td>
                    </tr>
                    <tr>
                        <td class="small-col border-right">Contratada</td>
                        <td><?php echo htmlspecialchars($contratada->nome_fantasia) ?></td>
                    </tr>
                    <tr>
                        <td class="small-col border-right">Obra</td>
                        <td><?php echo htmlspecialchars($diarioObra->descricao_resumo) ?></td>
                    </tr>
                </tbody>
            </table>

            <form class="mx-auto my-5 clearfix" 
                action="<?php echo htmlspecialchars('exportadorPdf.php') ?>"
                method="POST"
                id="form"
                enctype="multipart/form-data">
                
                <!-- DATA/Nº RELATORIO -->
                <div class="d-flex justify-content-between">
                    <div class="form-group w-25">
                        <label for="data">Data:</label>
                        <input type="date" 
                            name="data"
                            style="width: 160px"
                            class="form-control pl-2" 
                            value="<?php echo htmlspecialchars(isset($diarioObra) ? (new DateTime($diarioObra->data))->format('Y-m-d') : '') ?>" 
                            id="data">
                    </div>
                    <div class="form-group w-25">
                        <label for="numeroRelatorio">Nº Relatório:</label>
                        <input type="text"
                            readonly
                            name="numeroRelatorio"
                            style="width: 100px" 
                            class="form-control" 
                            value="<?php echo htmlspecialchars(isset($diarioObra) ? $diarioObra->numero_diario : '') ?>" 
                            id="numeroRelatorio">

                        <input type="hidden"
                            name="id_diario_obra"
                            value="<?php echo htmlspecialchars(isset($diarioObra) ? $diarioObra->id_diario_obra : 0) ?>">
                    </div>
                </div>

                <!--CAMPO HORÁRIO DE TRABALHO -->
                <div class="form-group my-4 d-none">
                    <label for="horarioTrabalho my-5">Horário de Trabalho:</label>
                    <input type="text"
                        id="horarioTrabalho"
                        name="horarioTrabalho"
                        value="07:00h às 17:00h"
                        placeholder="Informe o horário de trabalho"
                        class="form-control w-50">
                </div>
                
                <!-- DESCRIÇÃO SERVIÇOS -->
                <div class="form-group clearfix">
                    <div 
                        style="cursor: pointer;"
                        class="float-right mb-2 mt-4"
                        title="Adicionar item"
                        id="adicionarDescricao">
                        
                        <span class="text-dark align-top mr-3">Adicionar descrição serviço</span>
                        <i style="font-size: 30px !important" class="fa text-success fa-plus-circle" aria-hidden="true"></i>
                    </div>

                    
                    <table id="table-desc-servicos" class="table border mt-5 mb-3 mx-auto d-none">
                        <thead>
                            <tr class="small">
                                <th class="border-right">ITEM</th>
                                <th>DESCRIÇÃO DOS SERVIÇOS EXECUTADOS</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <!-- FUNCIONÁRIOS -->
                <div class="form-group clearfix">
                    <?php if (!empty($listaFuncionarios)) { ?>
                        <div class="">
                            <div 
                                style="cursor: pointer !important;"
                                class="float-right mb-2 mt-4"
                                title="Adicionar item"
                                id="adicionarFuncionario">
                                
                                <span style="cursor: pointer !important" class="text-dark align-top mr-3">Adicionar funcionário</span>
                                <i style="font-size: 30px !important;" class="fa text-success fa-plus-circle" aria-hidden="true"></i>
                            </div>
                            <div 
                                style="cursor: pointer !important;"
                                class="float-left mb-2 mt-4"
                                title="Adicionar item"
                                id="cadastrarFuncionario">
                                
                                <a href="<?php echo htmlspecialchars('cadastroFuncionario.php') ?>" target="_blank" class="mr-3">Cadastrar novo funcionário</a>
                                <!-- <i style="font-size: 30px !important;" class="fa text-primary fa-floppy-o mr-5" aria-hidden="true"></i> -->
                            </div>
                        </div>
                        
                        <table id="table-efetivos" class="table border mt-5 mb-3 mx-auto d-none">
                            <thead>
                                <tr class="small">
                                    <th class="py-1 border-right align-middle text-center">Nº</th>
                                    <th class="py-1 border-right align-middle text-center">EFETIVO</th>
                                    <th class="py-1 border-right align-middle text-center" style="width: 100px">HORA ENTRADA</th>
                                    <th class="py-1 border-right align-middle text-center" style="width: 100px">HORA SAÍDA</th>
                                    <th class="py-1 border-right align-middle text-center">CÁLCULO</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                        <!-- <p class="float-right">Carga Horária Total: <span id="cargaHorasTotal" class="mr-1">0</span>Hrs</p> -->
                    <?php } else { ?>
                        <h1 class="h6 font-italic text-secondary text-center mx-auto pb-3 my-5 mb-4 w-50">Nenhum funcionário ainda foi cadastrado!
                            <a class="my-3 btn btn-link text-left" href="<?php echo htmlspecialchars('cadastroFuncionario.php') ?>">Cadastrar Funcionários</a>
                        </h1>
                    <?php } ?>
                </div>

                <!-- OBS. GERAIS -->
                <div class="form-group mx-auto d-block mt-3 mb-5">
                    <label for="obsGerais" class="mb-3 font-weight-bolder">Observações Gerais:</label>
                    <textarea name="obsGeral" class="form-control" id="obsGerais" rows="3"><?php echo isset($diarioObra->obs_gerais) ? htmlspecialchars($diarioObra->obs_gerais) : ''; ?></textarea>
                </div>

                <!-- ALBUM -->
                <div class="form-group mt-3 mb-5 w-100 mx-auto" id="uploadAlbum">
                    <div class="dropzone mb-4" 
                        id="dropzoneAlbum">
                    </div>
                </div>

                <input type="hidden" id="existeAlbum" name="existeAlbum" value="0">

                <button type="button"
                    id="submit"
                    style="cursor: pointer; z-index: 1000; position: relative;"
                    class="btn btn-primary float-right mb-5">
                    Gerar Relatório
                </button>
                
                <button type="button" 
                    id="btnSalvarFotos" 
                    class="btn btn-success float-right mb-5 mr-2"
                    style="cursor: pointer; z-index: 1000; position: relative;">
                    <i class="fas fa-save"></i> SALVAR
                </button>

                <?php if (!empty($album)) { ?>
                    <div class="dropdown clearfix float-right mb-5 py-0">
                        <button class="btn btn-outline-secondary dropdown-toggle float-right mr-5 mb-5" type="button" id="dropdownMenuButton" data-toggle="dropdown">
                            Álbum <small><span id="photoCount"><?php echo htmlspecialchars(count($album)) ?></span> fotos</small>
                        </button>
                        <div class="dropdown-menu py-0" aria-labelledby="dropdownMenuButton">
                            <a class="dropdown-item text-dark" href="?baixar_album=1&id_diario_obra=<?php echo htmlspecialchars(isset($_GET['id_diario_obra']) ? $_GET['id_diario_obra'] : '') ?>">
                                Baixar
                            </a>
                            <a class="dropdown-item text-dark" href="?remover_album=1&id_diario_obra=<?php echo htmlspecialchars(isset($_GET['id_diario_obra']) ? $_GET['id_diario_obra'] : '') ?>" style="cursor: pointer !important">Remover</a>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="my-0" id="emptyAlbumDiv">
                        <button disabled class="btn btn-light float-right mr-5 mb-5" id="emptyAlbumButton">
                            Álbum <small><span id="photoCount">0</span> fotos</small>
                        </button>
                    </div>
                <?php } ?>

                <!-- Clear floats to prevent overlap -->
                <div class="clearfix"></div>
                
                <!-- Galeria de Miniaturas (Hidden by default) -->
                <div id="photoGallery" class="row mt-5 pt-3" style="display: none; clear: both; position: relative; z-index: 1;">
                    <div class="col-12">
                        <h6 style="cursor: default; user-select: none; pointer-events: none;">Fotos do Álbum:</h6>
                        <div id="thumbnailContainer" class="d-flex flex-wrap">
                            <!-- Miniaturas serão inseridas aqui dinamicamente -->
                        </div>
                    </div>
                </div>
                
            </form>
        </div>
        
        <!-- Loading Modal for PDF Generation -->
        <div class="modal fade" id="pdfLoadingModal" tabindex="-1" role="dialog" aria-labelledby="pdfLoadingModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-body text-center py-4">
                        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                            <span class="sr-only">Carregando...</span>
                        </div>
                        <h5 class="modal-title mb-2" id="pdfLoadingModalLabel">Preparando PDF</h5>
                        <p class="mb-1" id="loadingStatus">Verificando imagens...</p>
                        <div class="progress mx-auto" style="width: 80%; height: 20px;">
                            <div id="loadingProgress" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <small class="text-muted mt-2 d-block" id="loadingDetails">Aguarde...</small>
                    </div>
                </div>
            </div>
        </div>
        
    </body>
</html>
<script>   
    // Configuração Dropzone 6.0 com nova arquitetura
    const dropzoneConfig = {
        url: 'coletorDados.php', // Upload endpoint atualizado
        method: 'POST',
        maxFiles: 20, // Aumentado para 20 conforme nova configuração
        uploadMultiple: false, // Upload individual para melhor controle
        parallelUploads: 3, // Reduzido para evitar sobrecarga
        timeout: 60000, // 60 segundos timeout
        autoProcessQueue: false, // Desabilitado - upload manual via botão SALVAR
        thumbnailWidth: 100,
        thumbnailHeight: 100,
        acceptedFiles: '.jpeg,.jpg,.png,.webp', // Adicionado WebP
        maxFilesize: 10, // 10MB máximo
        
        // Mensagens em português
        dictMaxFilesExceeded: 'Máximo de 20 fotos permitido',
        dictInvalidFileType: 'Extensões permitidas: jpg, jpeg, png, webp',
        dictFileTooBig: 'Arquivo muito grande ({{filesize}}MB). Máximo: {{maxFilesize}}MB',
        dictRemoveFile: 'Excluir',
        dictCancelUpload: 'Cancelar',
        dictDefaultMessage: '<small class="font-italic text-center">UPLOAD DE FOTOS OTIMIZADAS<br/>(MAX. 20 FOTOS - 10MB cada)</small>',
        
        addRemoveLinks: true,
        
        init: function() {
            const dz = this;
            
            // Adiciona ID do diário a cada upload
            dz.on("sending", function(file, xhr, formData) {
                formData.append("id_diario_obra", <?php echo htmlspecialchars($diarioObra->id_diario_obra) ?>);
            });
            
            // Callback de sucesso
            dz.on("success", function(file, response) {
                console.log("Upload sucesso:", response);
                if (response.success) {
                    file.serverId = response.image_id;
                    file.filename = response.filename;
                    
                    // Remove o arquivo do Dropzone após upload bem-sucedido
                    setTimeout(() => {
                        // Marca como não deletar do servidor
                        file.skipServerDelete = true;
                        dz.removeFile(file);
                    }, 1500); // Aguarda 1.5 segundos para mostrar o sucesso
                }
            });
            
            // Callback de erro
            dz.on("error", function(file, message) {
                console.error("Upload erro:", message);
                if (typeof message === 'object' && message.error) {
                    dz.emit("errormultiple", [file], message.error);
                }
            });
            
            // Add file info tooltip on file added
            dz.on("addedfile", function(file) {
                // Habilita o botão SALVAR quando houver arquivos para upload
                const btnSalvar = document.getElementById('btnSalvarFotos');
                // Verifica arquivos não processados (status !== 'success')
                const needsUpload = dz.files.some(f => f.status !== 'success' && f.status !== 'error');
                if (needsUpload && btnSalvar) {
                    btnSalvar.disabled = false;
                }
                
                // Create tooltip element
                const tooltip = document.createElement('div');
                tooltip.className = 'dz-image-info-tooltip';
                
                // Format file size
                let fileSize = '';
                if (file.size) {
                    const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
                    fileSize = sizeInMB + ' MB';
                } else {
                    fileSize = '~1 MB'; // Default for existing files
                }
                
                // Set tooltip content
                tooltip.innerHTML = `
                    <div><strong>${file.name}</strong></div>
                    <div>Tamanho: ${fileSize}</div>
                `;
                
                // Add tooltip to preview element
                file.previewElement.appendChild(tooltip);
            });
            
            // Remove arquivo do servidor quando removido da interface
            dz.on("removedfile", function(file) {
                // Desabilita o botão SALVAR se não houver mais arquivos para upload
                setTimeout(() => {
                    const btnSalvar = document.getElementById('btnSalvarFotos');
                    const needsUpload = dz.files.some(f => f.status !== 'success' && f.status !== 'error');
                    if (!needsUpload && btnSalvar) {
                        btnSalvar.disabled = true;
                    }
                }, 100);
                
                // Só exclui do servidor se não for apenas limpeza visual
                if (file.serverId && !file.skipServerDelete) {
                    // Faz requisição AJAX para excluir a imagem do servidor
                    $.ajax({
                        url: 'coletorDados.php',
                        type: 'POST',
                        data: {
                            action: 'delete_image',
                            image_id: file.serverId
                        },
                        success: function(response) {
                            console.log('Imagem excluída com sucesso:', file.serverId);
                        },
                        error: function(xhr, status, error) {
                            console.error('Erro ao excluir imagem:', error);
                            alert('Erro ao excluir imagem. Por favor, tente novamente.');
                        }
                    });
                }
            });
        }
    };

    // Inicializa Dropzone 6.0
    const myDropzone = new Dropzone("#dropzoneAlbum", dropzoneConfig);
    
    // Botão SALVAR FOTOS - processa upload manual
    const btnSalvarFotos = document.getElementById('btnSalvarFotos');
    if (btnSalvarFotos) {
        // Inicialmente desabilitado
        btnSalvarFotos.disabled = true;
        
        btnSalvarFotos.addEventListener('click', async function() {
            const btn = this;
            const queuedFiles = myDropzone.getQueuedFiles();
            const obsGerais = document.getElementById('obsGerais').value;
            const idDiarioObra = <?php echo $diarioObra->id_diario_obra; ?>;
            
            // Desabilita o botão durante o salvamento
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SALVANDO...';
            
            // Array de promessas para executar em paralelo
            const promises = [];
            
            // Promessa 1: Salvar observações gerais
            const saveObservations = $.ajax({
                url: 'coletorDados.php',
                type: 'POST',
                data: {
                    action: 'save_observations',
                    id_diario_obra: idDiarioObra,
                    obs_gerais: obsGerais
                }
            }).done(function(response) {
                console.log('Observações salvas:', response);
            }).fail(function(xhr, status, error) {
                console.error('Erro ao salvar observações:', error);
            });
            
            promises.push(saveObservations);
            
            // Promessa 2: Upload de fotos (se houver)
            if (queuedFiles.length > 0) {
                const photoUpload = new Promise((resolve) => {
                    // Remove listener anterior para evitar duplicação
                    myDropzone.off("queuecomplete");
                    
                    myDropzone.on("queuecomplete", function() {
                        resolve();
                    });
                    
                    // Processa a fila de upload
                    myDropzone.processQueue();
                });
                
                promises.push(photoUpload);
            }
            
            // Aguarda todas as operações terminarem
            try {
                await Promise.all(promises);
                
                // Sucesso - restaura o botão
                btn.innerHTML = '<i class="fas fa-save"></i> SALVAR';
                
                // Desabilita se não houver mais mudanças
                const hasQueuedFiles = myDropzone.getQueuedFiles().length > 0;
                const hasTextChanges = obsGerais !== (window.originalObsGerais || '');
                
                btn.disabled = !hasQueuedFiles && !hasTextChanges;
                
                // Atualiza o valor original
                window.originalObsGerais = obsGerais;
                
                // Mostra mensagem de sucesso
                if (typeof showSuccessMessage === 'function') {
                    showSuccessMessage('Dados salvos com sucesso!');
                }
            } catch (error) {
                console.error('Erro ao salvar:', error);
                btn.innerHTML = '<i class="fas fa-save"></i> SALVAR';
                btn.disabled = false;
                alert('Erro ao salvar. Por favor, tente novamente.');
            }
        });
    }
    
    // Não adiciona mais imagens existentes ao Dropzone
    // O Dropzone agora é usado apenas para novas imagens
    // As imagens já salvas aparecem apenas na galeria abaixo
    
    // Armazena o valor original das observações
    window.originalObsGerais = document.getElementById('obsGerais').value;
    
    // Detecta mudanças no textarea de observações
    const obsGeraisTextarea = document.getElementById('obsGerais');
    if (obsGeraisTextarea) {
        obsGeraisTextarea.addEventListener('input', function() {
            const hasTextChanges = this.value !== window.originalObsGerais;
            const hasQueuedFiles = myDropzone.getQueuedFiles().length > 0;
            
            // Habilita o botão se houver mudanças
            if (btnSalvarFotos) {
                btnSalvarFotos.disabled = !hasTextChanges && !hasQueuedFiles;
            }
        });
        
        // Também detecta mudanças ao colar ou cortar texto
        obsGeraisTextarea.addEventListener('paste', function() {
            setTimeout(() => {
                const hasTextChanges = this.value !== window.originalObsGerais;
                const hasQueuedFiles = myDropzone.getQueuedFiles().length > 0;
                if (btnSalvarFotos) {
                    btnSalvarFotos.disabled = !hasTextChanges && !hasQueuedFiles;
                }
            }, 10);
        });
        
        obsGeraisTextarea.addEventListener('cut', function() {
            setTimeout(() => {
                const hasTextChanges = this.value !== window.originalObsGerais;
                const hasQueuedFiles = myDropzone.getQueuedFiles().length > 0;
                if (btnSalvarFotos) {
                    btnSalvarFotos.disabled = !hasTextChanges && !hasQueuedFiles;
                }
            }, 10);
        });
    }
    
    // Sistema de contagem dinâmica e miniaturas
    let photoCount = <?php echo count($album); ?>;
    
    function updatePhotoCount() {
        const countElements = document.querySelectorAll('#photoCount');
        countElements.forEach(el => el.textContent = photoCount);
        
        // Atualizar visibilidade do botão de álbum
        const emptyDiv = document.getElementById('emptyAlbumDiv');
        const dropdownDiv = document.querySelector('.dropdown.clearfix');
        
        if (photoCount > 0) {
            if (emptyDiv) emptyDiv.style.display = 'none';
            
            // Criar dropdown se não existir
            if (!dropdownDiv) {
                createAlbumDropdown();
            }
            
            // Mostrar galeria de miniaturas
            showPhotoGallery();
        } else {
            if (emptyDiv) emptyDiv.style.display = 'block';
            if (dropdownDiv) dropdownDiv.style.display = 'none';
            
            // Esconder galeria
            document.getElementById('photoGallery').style.display = 'none';
        }
    }
    
    function createAlbumDropdown() {
        // Find the submit button more safely
        const submitBtn = document.getElementById('submit');
        if (!submitBtn || !submitBtn.parentNode) {
            console.error('Submit button not found');
            return;
        }
        
        const container = submitBtn.parentNode;
        
        // Check if dropdown already exists to avoid duplicates
        if (document.querySelector('.dropdown.clearfix.float-right')) {
            return;
        }
        
        const dropdownHtml = `
            <div class="dropdown clearfix float-right mb-5 py-0">
                <button class="btn btn-outline-secondary dropdown-toggle float-right mr-5 mb-5" type="button" id="dropdownMenuButton" data-toggle="dropdown">
                    Álbum <small><span id="photoCount">${photoCount}</span> fotos</small>
                </button>
                <div class="dropdown-menu py-0" aria-labelledby="dropdownMenuButton">
                    <a class="dropdown-item text-dark" href="?baixar_album=1&id_diario_obra=<?php echo htmlspecialchars(isset($_GET['id_diario_obra']) ? $_GET['id_diario_obra'] : '') ?>">
                        Baixar
                    </a>
                    <a class="dropdown-item text-dark" href="?remover_album=1&id_diario_obra=<?php echo htmlspecialchars(isset($_GET['id_diario_obra']) ? $_GET['id_diario_obra'] : '') ?>" style="cursor: pointer !important">Remover</a>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('afterend', dropdownHtml);
    }
    
    function showPhotoGallery() {
        const gallery = document.getElementById('photoGallery');
        const container = document.getElementById('thumbnailContainer');
        
        // Limpar container
        container.innerHTML = '';
        
        // Buscar fotos existentes via AJAX
        const diarioId = <?php echo isset($_GET['id_diario_obra']) ? $_GET['id_diario_obra'] : 'null'; ?>;
        if (diarioId) {
            fetch(`?ajax=get_photos&id_diario_obra=${diarioId}`)
                .then(response => response.json())
                .then(photos => {
                    photos.forEach((photo, index) => {
                        const thumbnail = createThumbnail(photo.url, index);
                        container.appendChild(thumbnail);
                    });
                    gallery.style.display = 'block';
                })
                .catch(error => console.error('Erro ao carregar fotos:', error));
        }
    }
    
    function createThumbnail(imageSrc, index) {
        const div = document.createElement('div');
        div.className = 'thumbnail-container m-1';
        
        // Build proper image URL (handle both filename-only and full paths)
        let imageUrl = imageSrc;
        if (imageUrl.indexOf('/') === -1) {
            // Filename only - build full URL
            imageUrl = 'img/album/' + imageSrc;
        } else if (imageUrl.indexOf('/img/album/') === -1) {
            // Legacy full path - extract filename and build local URL
            const filename = imageUrl.split('/').pop();
            imageUrl = 'img/album/' + filename;
        }
        
        div.innerHTML = `
            <div class="position-relative">
                <img src="${imageUrl}" 
                     class="img-thumbnail" 
                     style="width: 80px; height: 60px; object-fit: cover; cursor: pointer;" 
                     onclick="showImageModal('${imageUrl}', ${index})"
                     alt="Foto ${index + 1}">
                <small class="badge badge-secondary position-absolute" style="top: -5px; right: -5px; font-size: 10px;">${index + 1}</small>
            </div>
        `;
        return div;
    }
    
    function showImageModal(imageSrc, index) {
        // Criar modal para visualização da imagem
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Foto ${index + 1}</h5>
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                    </div>
                    <div class="modal-body text-center">
                        <img src="${imageSrc}" class="img-fluid" alt="Foto ${index + 1}">
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        $(modal).modal('show');
        
        // Remover modal quando fechado
        $(modal).on('hidden.bs.modal', function() {
            document.body.removeChild(modal);
        });
    }
    
    // Event listeners do Dropzone para contagem dinâmica
    myDropzone.on("success", function(file) {
        photoCount++;
        updatePhotoCount();
    });
    
    myDropzone.on("removedfile", function(file) {
        if (file.status === 'success') {
            photoCount--;
            updatePhotoCount();
        }
    });
    
    // Inicializar contagem
    updatePhotoCount();
    
    // Image preloading function with timeout
    function preloadImages() {
        return new Promise((resolve, reject) => {
            // Get all images from the album
            const albumImages = [];
            <?php if (!empty($album)) { 
                $relativePath = Config::get('PHOTO_STORAGE_PATH', 'img/album');
                foreach ($album as $img) { ?>
                    albumImages.push('<?php echo htmlspecialchars($relativePath . '/' . $img['url']) ?>');
            <?php }} ?>
            
            console.log('Images to preload:', albumImages);
            
            if (albumImages.length === 0) {
                console.log('No images to preload');
                resolve();
                return;
            }
            
            let loadedCount = 0;
            const totalImages = albumImages.length;
            
            // Set a timeout to prevent infinite loading
            const timeoutId = setTimeout(() => {
                console.warn('Image preloading timeout - continuing anyway');
                resolve();
            }, 10000); // 10 second timeout
            
            // Update modal with progress
            $('#loadingStatus').text('Carregando imagens do álbum...');
            $('#loadingDetails').text(`0 de ${totalImages} imagens carregadas`);
            
            albumImages.forEach((src, index) => {
                const img = new Image();
                img.onload = () => {
                    loadedCount++;
                    const progress = Math.round((loadedCount / totalImages) * 100);
                    $('#loadingProgress').css('width', progress + '%').attr('aria-valuenow', progress);
                    $('#loadingDetails').text(`${loadedCount} de ${totalImages} imagens carregadas`);
                    
                    if (loadedCount === totalImages) {
                        clearTimeout(timeoutId);
                        $('#loadingStatus').text('Imagens carregadas com sucesso!');
                        setTimeout(resolve, 500); // Small delay for user feedback
                    }
                };
                img.onerror = () => {
                    loadedCount++;
                    console.warn(`Failed to preload image: ${src}`);
                    if (loadedCount === totalImages) {
                        clearTimeout(timeoutId);
                        resolve(); // Continue even if some images fail
                    }
                };
                img.src = src;
            });
        });
    }
    
    // Flag to track if we're submitting the form
    let isSubmittingForm = false;
    
    // Botão de submit simplificado - uploads já são feitos imediatamente
    $('#submit').on('click', function(e) {
        // Set flag to indicate we're submitting
        isSubmittingForm = true;
        
        // Como uploads são imediatos, apenas gera o PDF
        e.preventDefault();
        $('#pdfLoadingModal').modal('show');
        
        // Preload existing album images
        preloadImages().then(() => {
            console.log('Images preloaded successfully, submitting form...');
            $('#loadingStatus').text('Gerando PDF...');
            $('#loadingProgress').css('width', '100%');
            
            // Small delay to show completion message
            setTimeout(() => {
                console.log('Submitting form now...');
                // Create a hidden input for PDF generation flag
                if (!$('#form input[name="pdf_submit"]').length) {
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'pdf_submit',
                        value: '1'
                    }).appendTo('#form');
                }
                
                // Submit the form using POST
                const form = document.getElementById('form');
                if (form) {
                    // Remove any jQuery event handlers
                    $(form).off('submit');
                    // Ensure POST method
                    form.method = 'POST';
                    // Use HTMLFormElement.prototype.submit to bypass any override
                    HTMLFormElement.prototype.submit.call(form);
                } else {
                    console.error('Form not found!');
                }
            }, 200);
        }).catch(error => {
            console.error('Error preloading images:', error);
            // Create a hidden input for PDF generation flag
            if (!$('#form input[name="pdf_submit"]').length) {
                $('<input>').attr({
                    type: 'hidden',
                    name: 'pdf_submit',
                    value: '1'
                }).appendTo('#form');
            }
            
            // Submit the form using POST
            const form = document.getElementById('form');
            if (form) {
                // Remove any jQuery event handlers
                $(form).off('submit');
                // Ensure POST method
                form.method = 'POST';
                // Use HTMLFormElement.prototype.submit to bypass any override
                HTMLFormElement.prototype.submit.call(form);
            } else {
                console.error('Form not found!');
            }
        });
    });
</script>
