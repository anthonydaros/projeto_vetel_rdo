<?php
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/startup.php';
    
    use Models\Empresa;
    use Models\Funcionario;
    use Models\FuncionarioDiarioObra;
    use Models\DiarioObra;
    use Models\Obra;
    use Models\Servico;
    use Models\Imagem;
    use Dompdf\Dompdf;
    // $pathAlbum = __DIR__ . '/img';

    if (isset($_FILES['file']) && isset($_FILES['file']['tmp_name']) && !empty($_FILES['file']['name'])) 
    {
        // cleanDir($pathAlbum);
        $numArquivos = count($_FILES['file']['name']);

        $id_diario_obra = isset($_POST['id_diario_obra']) ? $_POST['id_diario_obra'] : 0;
        $dao->deleteAlbum($id_diario_obra);

        /*limpa diretorio do album*/
        cleanAlbumDiario($id_diario_obra);
        
        for ($i = 0; $i < $numArquivos; $i++)
        {
            $arr = explode('.', $_FILES["file"]["name"][$i]);
            $extension = $arr[count($arr)-1];
            $extension = strtolower($extension);
            
            $filePath = "$pathAlbum/diario-$id_diario_obra-foto-$i.$extension"; 

            $imagem = new Imagem();
            $imagem->fk_id_diario_obra = $id_diario_obra;
            $imagem->url = $filePath;

            $ret = $dao->insereImagem($imagem);

            $fileData = file_get_contents($_FILES['file']['tmp_name'][$i]);
            file_put_contents($filePath, $fileData);
        }
    }
    else if (isset($_POST['submit']))
    {
        $time_start = microtime(true);

        extract($_POST);

        $diarioObra = $dao->buscaDiarioObraPorId($id_diario_obra);
        $contratante = $dao->buscaEmpresaPorId($diarioObra->fk_id_contratante);
        $contratada = $dao->buscaEmpresaPorId($diarioObra->fk_id_contratada);
        $cargaHorasDia = 0;

        if (isset($descricaoServico))
        {
            $descricaoServico = array_values(array_filter($descricaoServico, function($servico) {
                return trim($servico) != '';
            }));

            $ret = $dao->deleteTodosServicosDiarioObra($id_diario_obra);
            
            foreach ($descricaoServico as $itemServico)
            {
                $servico = new Servico();
                $servico->descricao = $itemServico;
                $servico->fk_id_diario_obra = $id_diario_obra;
                $servico = $dao->insereServico($servico);
            }
        }
        if (isset($horaEntrada))
        {
            $horaEntrada = array_values(array_filter($horaEntrada, function($hora) {
                return $hora != '';
            }));
        }
        if (isset($horaSaida))
        {
            $horaSaida = array_values(array_filter($horaSaida, function($hora) {
                return $hora != '';
            }));
        }
        if (isset($totalHoras))
        {
            $totalHoras = array_values(array_filter($totalHoras, function($hora) {
                return $hora != '';
            }));
        }
        
        $diarioObra->numero_diario = $numeroRelatorio;
        $diarioObra->data = (new DateTime($data))->format('Y-m-d');
        $diarioObra->obs_gerais = $obsGeral;
        $diarioObra->horario_trabalho = $horarioTrabalho;
        $dao->updateDiarioObra($diarioObra);

        if (isset($nomeFuncionario))
        {
            $funcionarios = array_values(array_filter($nomeFuncionario, function($nome) {
                return trim($nome) != '';
            }));

            $funcionarios = array_map(function($nome) use ($dao) {
                $funcionario = $dao->buscaFuncionarioPorNome($nome);
                return $funcionario;
            }, $funcionarios);

            $ret = $dao->deleteTodosFuncionariosDiarioObra($id_diario_obra);
            
            foreach ($funcionarios as $funcionario)
            {
                $funcionarioDiarioObra = new FuncionarioDiarioObra();
                $funcionarioDiarioObra->fk_id_funcionario = $funcionario->id_funcionario;
                $funcionarioDiarioObra->fk_id_diario_obra = $id_diario_obra;
                $funcionarioDiarioObra->data = (new DateTime($data))->format('Y-m-d');
                $funcionarioDiarioObra->horario_trabalho = current($horaEntrada) . ' às ' . current($horaSaida);

                $arr = explode(':', current($totalHoras));
                $totalHorasFuncionarioObra = $arr[0] + round(($arr[1]/60), 2);
                
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
        $dompdf = new Dompdf();

        $options = $dompdf->getOptions();
        // $options->setDefaultFont('Helvetica');
        $options->setDefaultFont('DejaVu Sans');
        $options->set(
        [
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'isPhpEnabled' => true,
            'chroot' => __DIR__
        ]);

        $dompdf->setOptions($options);
        
        if (($time_diff = microtime(true)-$time_start) < 6.0)
        {
            $time_sleep = (int)(6.0-$time_diff) * 1000000;
            usleep($time_sleep);
        }
        ob_start();

        require_once __DIR__ . '/rdo.php';
        
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
        $text       = "Página {PAGE_NUM} de {PAGE_COUNT}";     
        $font       = $dompdf->getFontMetrics()->get_font('Helvetica', 'normal');   
        $size       = 10;    
        $color      = array(0,0,0);
        $word_space = 0.0;
        $char_space = 0.0;
        $angle      = 0.0;

        $dompdf->getCanvas()->page_text(
            $x, $y, $text, $font, $size, $color, $word_space, $char_space, $angle
        );

        // Output the generated PDF to Browser
        // $dompdf->stream();
        $dompdf->stream("meu_dom.pdf", array("Attachment" => false));
    }

    function cleanAlbumDiario($idDiarioObra)
    {
        $pathAlbum = $GLOBALS['pathAlbum'];

        $handle = opendir($pathAlbum);
        while (false !== ($entry = readdir($handle))) 
        {
            $filename_arr = explode('-', $entry);
            if (!is_dir("$pathAlbum/$entry") && $filename_arr[1] == $idDiarioObra)
            {
                unlink("$pathAlbum/$entry");
            }
        }
        closedir($handle);
    }
?>
