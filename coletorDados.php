<?php
require_once __DIR__ . '/bootstrap.php'; // Nova arquitetura
require_once __DIR__ . '/ftpFunctions.php';

use Src\Exception\ServiceException;

// Nova lógica de upload de imagens
if (isset($_FILES['file']) && isset($_POST['id_diario_obra'])) {
	try {
		$imageUploadService = app('image.upload');
		$diarioId = (int) $_POST['id_diario_obra'];

		// Processo de upload usando nova arquitetura
		$result = $imageUploadService->uploadImageForDiario($_FILES['file'], $diarioId);

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
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/dropzone/6.0.0-beta.2/dropzone.min.css" integrity="sha512-61eWJhbs/2KkJ2JhEkOdDT8Zu7MwXMhZ1gLNzIx6g7DU+R8VYJy2fQ0JOHHGi3GYKuLsEZo9pV8cWWUjdHpvew==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/6.0.0-beta.2/dropzone-min.js" integrity="sha512-FFyRTpBn3Gly5HfClQk4DiY8DSUJJw0BT2wwxKuOyVyVvRUV1WPL/+oJ97qEAi4MaOl6/F0f4+j2PYmJXL4Jog==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
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
                    <textarea name="obsGeral" class="form-control" id="obsGerais" rows="3">Tempo bom.</textarea>
                </div>

                <!-- ALBUM -->
                <div class="form-group mt-3 mb-5 w-100 mx-auto" id="uploadAlbum">
                    <div class="dropzone mb-4" 
                        id="dropzoneAlbum">
                    </div>
                </div>

                <input type="hidden" id="existeAlbum" name="existeAlbum" value="0">

                <input type="submit"
                    id="submit"
                    style="cursor: pointer"
                    class="btn btn-primary float-right mb-5"
                    name="submit"
                    value="Gerar Relatório">

                <?php if (!empty($album)) { ?>
                    <div class="dropdown clearfix float-right mb-5 py-0">
                        <button class="btn btn-outline-secondary dropdown-toggle float-right mr-5 mb-5" type="button" id="dropdownMenuButton" data-toggle="dropdown">
                            Álbum <small><?php echo htmlspecialchars(count($album) . ' fotos') ?></small>
                        </button>
                        <div class="dropdown-menu py-0" aria-labelledby="dropdownMenuButton">
                            <a class="dropdown-item text-dark" href="?baixar_album=1&id_diario_obra=<?php echo htmlspecialchars(isset($_GET['id_diario_obra']) ? $_GET['id_diario_obra'] : '') ?>">
                                Baixar
                            </a>
                            <a class="dropdown-item text-dark" href="?remover_album=1&id_diario_obra=<?php echo htmlspecialchars(isset($_GET['id_diario_obra']) ? $_GET['id_diario_obra'] : '') ?>" style="cursor: pointer !important">Remover</a>
                        </div>
                    </div>
                <?php } else { ?>
                    <div class="my-0">
                        <button disabled class="btn btn-light float-right mr-5 mb-5">
                            Álbum <small>0 fotos</small>
                        </button>
                    </div>
                <?php } ?>
                
            </form>
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
        autoProcessQueue: false,
        thumbnailWidth: 100,
        thumbnailHeight: 100,
        acceptedFiles: '.jpeg,.jpg,.png,.webp', // Adicionado WebP
        maxFilesize: 5, // 5MB máximo
        
        // Mensagens em português
        dictMaxFilesExceeded: 'Máximo de 20 fotos permitido',
        dictInvalidFileType: 'Extensões permitidas: jpg, jpeg, png, webp',
        dictFileTooBig: 'Arquivo muito grande ({{filesize}}MB). Máximo: {{maxFilesize}}MB',
        dictRemoveFile: 'Excluir',
        dictCancelUpload: 'Cancelar',
        dictDefaultMessage: '<small class="font-italic text-center">UPLOAD DE FOTOS OTIMIZADAS<br/>(MAX. 20 FOTOS - 5MB cada)</small>',
        
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
                }
            });
            
            // Callback de erro
            dz.on("error", function(file, message) {
                console.error("Upload erro:", message);
                if (typeof message === 'object' && message.error) {
                    dz.emit("errormultiple", [file], message.error);
                }
            });
            
            // Remove arquivo do servidor quando removido da interface
            dz.on("removedfile", function(file) {
                if (file.serverId) {
                    // TODO: Implementar endpoint de remoção individual
                    console.log("Removendo arquivo:", file.serverId);
                }
            });
        }
    };

    // Inicializa Dropzone 6.0
    const myDropzone = new Dropzone("#dropzoneAlbum", dropzoneConfig);
    
    // Botão de submit atualizado
    $('#submit').on('click', function(e) {
        const filesAccepted = myDropzone.getAcceptedFiles().length;
        const filesQueued = myDropzone.getQueuedFiles().length;
        
        if (filesQueued > 0) {
            e.preventDefault();
            $('#existeAlbum').val(1);
            
            // Processa fila de upload
            myDropzone.processQueue();
            
            // Aguarda conclusão dos uploads antes de submeter o form
            myDropzone.on("queuecomplete", function() {
                $('#form').submit();
            });
        } else {
            // Sem arquivos para upload, submete normalmente
            $('#form').submit();
        }
    });
</script>
