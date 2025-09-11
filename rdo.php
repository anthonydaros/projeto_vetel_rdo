<?php
    $pathAlbum = __DIR__ . '/img/album';

    $handle = opendir($pathAlbum);
    while (false !== ($entry = readdir($handle))) 
    {
        if (!is_dir("$pathAlbum/$entry"))
        {
            $fileData = file_get_contents("$pathAlbum/$entry");
            break;
        }
    }
    closedir($handle);
    
    $album = $dao->buscaAlbumDiario($diarioObra->id_diario_obra);
    $tamanhoAlbum = count($album);
    $resto = $tamanhoAlbum % 3;

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
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>R.D.O</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        /* @font-face {
            font-family: 'Open Sans';
            font-style: normal;
            font-weight: normal;
            src: url(http://themes.googleusercontent.com/static/fonts/opensans/v8/cJZKeOuBrn4kERxqtaUH3aCWcynf_cDxXwCLxiixG1c.ttf) format('truetype');
        } */
        * {
            font-family: DejaVu Sans !important; 
            /* font-family: Zapf-Dingbats !important;  */
            /* font-family: 'Open Sans' !important;  */
            font-size: 12px;
            line-height: 1em;
        }

        .container {
            min-width: 650px;
        }
        #header
        {
            border: 2px solid #000;
        }
        #header img
        {
            max-width: 120px;
            max-height: 60px;
        }
        .table-bordered td, .table-bordered thead th 
        {
            padding: 5px;
            color: #111;
            border: 1px solid #111 !important;
            vertical-align: middle;
        }
        .table-bordered thead th {
            font-weight: 600;
        }
        .table-bordered, .table-bordered td, .table-bordered th
        {
            border: 1px solid #111;
            line-height: 1.1em;
        }

        #album
        {
            /* page-break-before: always; */
            /* page-break-after: always; */
        }
        #album th {
            border-top: 2px solid #000 !important;
            border-bottom: 2px solid #000 !important;
            padding-top: 5px !important;
            padding-bottom: 5px !important;
            font-weight: bolder !important;
        }
        #album td {
            border: 1px dotted #111;
            border-top: 0;
            height: 150px;
            width: 150px;
            max-width: 150px;
            max-height: 150px;
            padding: 5px;
        }
        #album td img {
            margin: 2% 2%;
            text-align: center;
            vertical-align: middle;
            /* width: 200px; */
            max-width: 180px;
            max-height: 150px;
        }
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
                        <img 
                            class="m-2"
                            style="min-width: 70px" 
                            src="<?php echo htmlspecialchars(isset($contratada->url_logo) ? $contratada->url_logo : '') ?>">
                    </td>
                    <td class="text-center mx-1" style="width: 300px !important; font-size: 16px !important">
                        RELATÓRIO DIÁRIO DE OBRA (R.D.O)
                    </td>
                    <td class="my-1 mx-1">
                        <img 
                            class="my-2"
                            style="min-width: 70px" 
                            src="<?php echo htmlspecialchars(isset($contratante->url_logo) ? $contratante->url_logo : '') ?>">
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
                            <td class="my-0 py-0"><?php echo htmlspecialchars($descricaoServico[$i-1]) ?></td>
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
                                <td style="padding: 0.2em 0.3em !important; max-height: 0em !important;" class="my-0 py-0 text-uppercase"><?php echo htmlspecialchars(ucwords($funcionarios[$i-1]->nome)) ?></td>
                                <td style="padding: 0.2em 0.3em !important; max-height: 0em !important;" class="my-0 py-0 text-uppercase"><?php echo htmlspecialchars(ucwords($funcionarios[$i-1]->cargo)) ?></td>
                                <td style="padding: 0.2em !important; max-height: 0em !important;" class="my-0 py-0 text-uppercase"><?php echo htmlspecialchars($horaEntrada[$i-1] . ' às ' . $horaSaida[$i-1]) ?></td>
                                <td style="padding: 0.2em 0 !important; max-height: 0em !important; max-width: 5px !important" class="my-0 py-0 text-uppercase"><?php echo htmlspecialchars(ucwords($funcionarios[$i-1]->nome_fantasia)) ?></td>
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
                                    <?php 
                                        if ($cargaHorasDia - floor($cargaHorasDia))
                                        {
                                            if ($cargaHorasDia * 10 - floor($cargaHorasDia * 10))
                                            {
                                                echo number_format($cargaHorasDia, 2, ',', '.') . 'h';
                                            }
                                            else
                                            {
                                                echo number_format($cargaHorasDia, 1, ',', '.') . 'h';
                                            }
                                        }
                                        else
                                        {
                                            echo number_format($cargaHorasDia, 0, ',', '.') . 'h';
                                        }
                                    ?>
                                </span>
                            </div>
                            <div class="py-2">
                                <b class="mr-2">SOMA TOTAL DE HORAS:</b>
                                <span>
                                    <?php 
                                        if ($totalAcumuladoHorasObra - floor($totalAcumuladoHorasObra))
                                        {
                                            if ($totalAcumuladoHorasObra * 10 - floor($totalAcumuladoHorasObra * 10))
                                            {
                                                echo number_format($totalAcumuladoHorasObra, 2, ',', '.') . 'h';
                                            }
                                            else
                                            {
                                                echo number_format($totalAcumuladoHorasObra, 1, ',', '.') . 'h';
                                            }
                                        }
                                        else
                                        {
                                            echo number_format($totalAcumuladoHorasObra, 0, ',', '.') . 'h';
                                        }
                                    ?>
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
                    <?php for ($i = 0; $i < $tamanhoAlbum-$resto; $i += 3) { ?>
                        <tr>
                            <td>
                                <p style="text-align: center; vertical-align: middle;">
                                    <img style="vertical-align: middle;" src="<?php echo htmlspecialchars($album[$i]['url']) ?>">
                                </p>
                            </td>
                            <td>
                                <p style="text-align: center; vertical-align: middle;">
                                    <img style="vertical-align: middle;" src="<?php echo htmlspecialchars($album[$i+1]['url']) ?>">
                                </p>
                            </td>
                            <td>
                                <p style="text-align: center; vertical-align: middle;">
                                    <img style="vertical-align: middle;" src="<?php echo htmlspecialchars($album[$i+2]['url']) ?>">
                                </p>
                            </td>
                        </tr>
                    <?php } ?>
                    
                    <?php if ($resto == 1) { ?>
                        <tr>
                            <td>
                                <p style="text-align: center; vertical-align: middle;">
                                    <img style="vertical-align: middle;" src="<?php echo htmlspecialchars($album[$tamanhoAlbum-1]['url']) ?>">
                                </p>
                            </td>
                            <td></td>
                            <td></td>
                        </tr>
                    <?php } ?>
                    <?php if ($resto == 2) { ?>
                        <tr>
                            <td>
                                <p style="text-align: center; vertical-align: middle;">
                                    <img style="vertical-align: middle;" src="<?php echo htmlspecialchars($album[$tamanhoAlbum-2]['url']) ?>">
                                </p>
                            </td>
                            <td>
                                <p style="text-align: center; vertical-align: middle;">
                                    <img style="vertical-align: middle;" src="<?php echo htmlspecialchars($album[$tamanhoAlbum-1]['url']) ?>">
                                </p>
                            </td>
                            <td></td>
                        </tr>
                    <?php } ?>
                    <?php for ($i = ceil($tamanhoAlbum / 3) * 3; $i < 12; $i += 3) { ?>
                        <tr>
                            <td></td>
                            <td></td>
                            <td></td>
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
