<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/startup.php';

use Dompdf\Dompdf;

if (isset($_GET['id_funcionario'])) {
	extract($_GET);

	$funcionario = $dao->buscaFuncionarioPorId($id_funcionario);

	$listaDiariosFuncionario = $dao->buscaTodosDiariosObraDoFuncionario($id_funcionario);

	$listaObrasFuncionario = array_unique(array_map(function ($diario) {
		return $diario['id_obra'];
	}, $listaDiariosFuncionario));

	if (isset($_GET['id_obra'])) {
		$obra = $dao->buscaObraPorId($id_obra);
		$contratante = $dao->buscaEmpresaPorId($obra->fk_id_contratante);
		$contratada = $dao->buscaEmpresaPorId($obra->fk_id_contratada);

		$periodoObra = $dao->buscaPeriodoObraDoFuncionario($id_obra, $id_funcionario);
		$obra = [
			'funcionario' => $funcionario->nome,
			'min_data' => $periodoObra->min_data,
			'max_data' => $periodoObra->max_data,
			'descricao_resumo' => $obra->descricao_resumo,
			'id_contratante' => $contratante->id_empresa,
			'nome_contratante' => $contratante->nome_fantasia,
			'id_contratada' => $contratada->id_empresa,
			'nome_contratada' => $contratada->nome_fantasia
		];

		echo json_encode($obra);
		die();
	}
} elseif (isset($_POST['submit'])) {
	extract($_POST);

	$mes_ano = explode('-', $mes_ano);

	$listaDiariosObra = $dao->buscaTodosDiariosObraDoFuncionario($id_funcionario, $id_obra);

	$listaDiariosObra = array_filter($listaDiariosObra, function ($diario) use ($mes_ano) {
		$dataDiario = explode('-', $diario['data']);

		return $dataDiario[0] == $mes_ano[0] && $dataDiario[1] == $mes_ano[1];
	});

	$obra = $dao->buscaObraPorId($id_obra);
	$funcionario = $dao->buscaFuncionarioPorId($id_funcionario);
	$contratada = $dao->buscaEmpresaPorId($obra->fk_id_contratada);

	/******************** CRIAÇÃO PDF ********************/

	$dompdf = new Dompdf();
	$options = $dompdf->getOptions();
	$options->setDefaultFont('Helvetica');
	// $options->setDefaultFont('DejaVu Sans');
	$options->set(
		[
			'isHtml5ParserEnabled' => true,
			'isRemoteEnabled' => true,
			'isPhpEnabled' => true,
			'chroot' => __DIR__
		]
	);

	$dompdf->setOptions($options);

	ob_start();

	require_once __DIR__ . '/rdp.php';

	// $html = file_get_contents('rdo.php');
	$html = ob_get_contents();

	ob_end_clean();
	$html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
	$dompdf->loadHtml($html);

	// (Optional) Setup the paper size and orientation
	$dompdf->setPaper('A4', 'portrait');

	// Render the HTML as PDF
	$dompdf->render();

	// Parameters
	$x          = 505;
	$y          = 790;
	$text       = 'Página {PAGE_NUM} de {PAGE_COUNT}';
	$font       = $dompdf->getFontMetrics()->get_font('Helvetica', 'normal');
	$size       = 10;
	$color      = [0, 0, 0];
	$word_space = 0.0;
	$char_space = 0.0;
	$angle      = 0.0;

	$dompdf->getCanvas()->page_text(
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

	// Output the generated PDF to Browser
	// $dompdf->stream();
	$dompdf->stream('meu_dom.pdf', ['Attachment' => false]);
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>R.D.P</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/bootstrap4.5.2.min.css">
    <script src="js/jquery3.5.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap4.5.2.min.js"></script>
    <style>
        html {
            min-height: 120% !important;
        }
    </style>
    <script>
        var range = (length) => new Array(length).fill().map((_,i) => i)
        // console.log(range(12))
        
        const mesesDoAno = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun',
                            'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        function getCalendarioObra(data_inicio, data_fim)
        {
            data_inicio = data_inicio.split('-');
            data_fim = data_fim.split('-');

            let ano_inicio = parseInt(data_inicio[0]);
            let mes_inicio = parseInt(data_inicio[1])-1;
            
            let ano_fim = parseInt(data_fim[0]);
            let mes_fim = parseInt(data_fim[1])-1;

            let arr = [];
            
            for (let ano = ano_inicio; ano <= ano_fim; ano++)
            {
                arr[ano.toString()] = [];
            }

            if (ano_inicio == ano_fim)
            {
                for (let mes = mes_inicio; mes <= mes_fim; mes++)
                {
                    arr[ano_inicio.toString()].push(mes)
                }
            }
            else
            {
                //ano_inicio
                for (let mes = mes_inicio; mes <= 11; mes++)
                {
                    arr[ano_inicio.toString()].push(mes)
                }

                for (let ano = ano_inicio + 1; ano < ano_fim; ano++)
                {
                    arr[ano.toString()] = range(12)
                }

                //ano_fim
                for (let mes = 0; mes <= mes_fim; mes++)
                {
                    arr[ano_fim.toString()].push(mes)
                }
            }
            return arr;
        }

        $(() => {
            $('select#obra').on('change', function() {
                let idOption = $(this).children(':selected').val()
                // console.log(idOption);

                $(`#info-obra`).empty();
                $(`#marcador-mes-ano select#mes_ano`).empty();
                        
                $(`#marcador-mes-ano select#mes_ano`).append(`
                    <option value="" selected class="text-secondary">Selecione o mês/ano</option>
                `);

                if (idOption != '')
                {
                    $.get(`<?php echo htmlspecialchars($_SERVER['PHP_SELF']) ?>?id_obra=${idOption}&id_funcionario=<?php echo htmlspecialchars(isset($_GET['id_funcionario']) ? $_GET['id_funcionario'] : 0) ?>`, function(data, status) {
                        data = JSON.parse(data)
                        // console.log(data);
                        
                        $(`#info-obra`).append(`
                            <div><b>Nome do Funcionário</b>: ${data['funcionario']}</div>
                            <div><b>Obra</b>: ${data['descricao_resumo']}</div>
                            <div><b>Contratante</b>: ${data['nome_contratante']}</div>
                            <div><b>Contratada</b>: ${data['nome_contratada']}</div>
                        `);
                        
                        // let data_inicio = '2021-12-10';
                        // let data_fim = '2022-11-08';

                        let data_inicio = data['min_data'];
                        let data_fim = data['max_data'];

                        let arr = getCalendarioObra(data_inicio, data_fim) 
                        console.log(arr);

                        $(`#marcador-mes-ano`).on('click', function() {
                            console.log(window.innerHeight);
                            window.scrollTo(0, window.innerHeight)
                            // window.scrollBy(0, 200)
                        });

                        for (let ano in arr)
                        {
                            for (let mes of arr[ano])
                            {
                                console.log(`${mesesDoAno[mes]}/${ano}`);
                                $(`#marcador-mes-ano select#mes_ano`).append(`
                                    <option value="${ano}-${mes+1}" class="text-dark">${mesesDoAno[mes]}/${ano}</option>
                                `)
                            }
                        }

                    });
                }
            });
        })
    </script>
    
</head>
<body>
    <div class="container w-50">
        <ul class="nav nav-tabs my-3 mb-5 w-100 mx-auto">
            <li class="nav-item">
                <a class="nav-link" href="cadastroFuncionario.php">Voltar</a>
            </li>
        </ul>
        <h1 class="h3 text-black-50 text-center my-3">Formulário para geração automática de R.D.P</h1>
        <form class="w-75 mx-auto my-4" 
            action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) ?>"
            method="POST"
            id="form"
            enctype="multipart/form-data">

            <div class="form-group my-3 w-100" style="height: 100%">
                <label for="obra">Selecione a obra em que o funcionário trabalha ou já trabalhou:</label>
                <select class="custom-select" name="id_obra" id="obra">
                    <option value="" selected class="text-secondary">Selecione a obra</option>
                    <?php foreach ($listaObrasFuncionario as $idObra) { ?>
                        <option value="<?php echo htmlspecialchars($idObra) ?>"><?php echo htmlspecialchars($dao->buscaObraPorId($idObra)->descricao_resumo) ?></option>
                    <?php } ?>
                </select>
                
                <div id="info-obra" class="d-flex flex-column my-4"></div>
            </div>
            
            <div class="my-4" id="marcador-mes-ano">
                <div class="form-group my-3 mb-5 w-50 h-auto">
                    <label for="mes_ano">Selecione o período de trabalho:</label>
                    <select class="custom-select" name="mes_ano" id="mes_ano">
                        <option value="" selected class="text-secondary">Selecione o mês/ano</option>
                    </select>
                </div>
            </div>

            <input 
                type="hidden" 
                name="id_funcionario" 
                value="<?php echo htmlspecialchars(isset($_GET['id_funcionario']) ? $_GET['id_funcionario'] : '') ?>">
            
            <div class="form-group clearfix">
                <input type="submit"
                    id="submit"
                    name="submit"
                    disabled
                    class="btn btn-primary float-right" 
                    value="Gerar Relatório">
            </div>
        </form>
        <script>
            $(`select`).on('change', function() {
                let obraOptionValue = $('#obra').children(':selected').val()
                let periodoOptionValue = $('#mes_ano').children(':selected').val()

                if (obraOptionValue != '' && periodoOptionValue != '')
                {
                    $(`#submit`).prop('disabled', false)
                }
                else
                {
                    $(`#submit`).prop('disabled', true)
                }
            })
        </script>
    </div>
</body>
</html>