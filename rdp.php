<?php
    $daysOfWeekend = ['Sat' => 'Sábado', 'Sun' => 'Domingo'];
    $meses = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    // Feriados Nacionais - 2021
    $feriados = [
        '01-01-2021' =>	'Confraternização Universal',
        '15-02-2021' => 'Carnaval',
		'16-02-2021' => 'Carnaval',
        '02-04-2021' => 'Sexta-Feira Santa',
        '21-04-2021' => 'Tiradentes',
        '01-05-2021' => 'Dia do Trabalho',
        '03-06-2021' => 'Corpus Christi',
		'07-09-2021' => 'Dia da Independência',
		'12-10-2021' => 'Nossa Sr.a Aparecida',
		'02-11-2021' => 'Finados',
		'15-11-2021' => 'Proclamação da República',
		'20-11-2021' => 'Consciência Negra',
	    '25-12-2021' => 'Natal'
    ];

    function getDayOfWeekend($d, $m, $y)
    {
        $timestamp = mktime(0, 0, 0, $m, $d, $y);
        $dayOfWeek = date('D', $timestamp);
        $daysOfWeekend = $GLOBALS['daysOfWeekend'];

        if (isset($daysOfWeekend[$dayOfWeek]))
        {
            return $daysOfWeekend[$dayOfWeek];
        }
        return '';
    }

    function numberWithTwoDigits($number)
    {
        $formatedNumber = sprintf("%'.02d", $number);

        return $formatedNumber;
    }
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
        * {
            /* font-family: DejaVu Sans !important;  */
            font-size: 14px;
            line-height: 1em;
        }
        .container {
            min-width: 650px;
        }
        #header
        {
            min-width: 650px;
            border: 2px solid #000;
        }
        #header img
        {
            max-width: 140px;
            /* max-height: 80px; */
        }
        .table-bordered {
            min-width: 650px;
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
            border: 2px solid #111;
            line-height: 1.1em;
        }
    </style>
</head>
<body>
    <article class="mt-1">
        <div class="container">

            <!-- TABELA 1 -->
            <table id="header">
                <tr>
                    <td class="my-1 ml-0 pl-0 mr-0 pr-0" style="width: 140px !important; margin-right: 0 !important; padding-right: 0 !important;">
                        <img 
                            class="my-2 ml-4 mr-0 pr-0 py-2"
                            src="<?php echo htmlspecialchars(isset($contratada->url_logo) ? $contratada->url_logo : '') ?>">
                    </td>
                    <td class="text-center ml-0 pl-0" style="margin-left: 0 !important; padding-left: 0 !important; font-size: 16px !important">
                        RELATÓRIO DIÁRIO DE PRESENÇA (R.D.P)
                    </td>
                </tr>
            </table>

            <!-- TABELA 2 -->
            <table class="mt-4 mb-2" id="header" style="border: none">
                <tr class="py-0">
                    <td class="mx-1" style="font-size: 12px !important; line-height: 1.4em">
                        <b>Nome</b>: <?php echo htmlspecialchars($funcionario->nome) ?> </br>
                        <b>Cargo</b>: <?php echo htmlspecialchars($funcionario->cargo) ?> </br>
                        <b>Período</b>: <?php echo htmlspecialchars($meses[$mes_ano[1]-1]) ?> de <?php echo htmlspecialchars($mes_ano[0]) ?></br>
                    </td>
                </tr>
            </table>
            
            <!-- TABELA 3 -->
            <?php if (isset($listaDiariosObra)) { ?>
                <table style="font-size: 13px !important; margin-bottom: 0 !important; padding-bottom: 0 !important;" class="table align-middle table-bordered text-center mt-1 mb-0">
                    <thead>
                        <tr class="py-0">
                            <th style="padding: 0.2em !important; font-weight: 500 !important; width: 120px; font-size: 13px !important" class="py-0">
                                DATA
                            </th>
                            <th style="padding: 0.2em !important; font-weight: 500; width: 140px; font-size: 13px !important" class="py-0">
                                HORÁRIO
                            </th>
                            <th style="padding: 0.2em !important; font-weight: 500; width: 80px; font-size: 13px !important" class="py-0">
                                HORAS
                            </th>
                            <th style="padding: 0.2em !important; font-weight: 500; font-size: 13px !important" class="py-0">
                                OBS
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listaDiariosObra as $diario) { ?>
                            <tr class="py-0 my-0" style="max-height: 0em; height: 0em;">
                                <td style="padding: 0.2em 0.3em !important; max-height: 0em !important;" class="my-0 py-0 text-uppercase"><?php echo htmlspecialchars((new DateTime($diario['data']))->format('d/m/Y')) ?></td>
                                <td style="padding: 0.2em 0.3em !important; max-height: 0em !important;" class="my-0 py-0 text-uppercase"><?php echo htmlspecialchars($diario['horario_trabalho']) ?></td>
                                <td style="padding: 0.2em !important; max-height: 0em !important;" class="my-0 py-0 text-uppercase">
                                    <?php
                                        if ($diario['horas_trabalhadas'] * 10 - floor($diario['horas_trabalhadas'] * 10))
                                        {
                                            echo number_format($diario['horas_trabalhadas'], 2, ',', '.');
                                        }
                                        else
                                        {
                                            echo number_format($diario['horas_trabalhadas'], 1, ',', '.');
                                        }
                                    ?>
                                </td>
                                <td style="padding: 0.2em 0 !important; max-height: 0em !important; max-width: 5px !important" class="my-0 py-0 text-uppercase">
                                    <?php
                                        $data = explode('-', $diario['data']);

                                        $d = numberWithTwoDigits($data[2]);
                                        $m = numberWithTwoDigits($data[1]);
                                        $y = $data[0];

                                        $dayOfWeekend = getDayOfWeekend($d, $m, $y);
                                        
                                        echo $dayOfWeekend;

                                        $key = "$d-$m-$y";
                                        
                                        if (isset($feriados[$key]))
                                        {
                                            if ($dayOfWeekend)
                                                echo ' / ';

                                            echo "FERIADO";
                                        } 
                                    ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
            
            <table style="margin-top: -1px !important; padding-top: 0 !important; border-top:0;" class="border-top-0 mt-0 mb-2 table table-bordered" >
                <tr class="my-0 py-0 border-top" style="">
                    <td class="text-right mx-1 my-0 py-0" style="width: 80%; border-top:0; font-size: 12px !important;">
                        SOMA DE HORAS TRABALHADAS
                    </td>
                    <td class="text-center mx-1 my-0 py-0" style="border-top:0; border-left: 2px solid #222 !important; font-size: 12px !important; ">
                        <?php
                            $sum = array_reduce($listaDiariosObra, function($carry, $item) {
                                return $carry + $item['horas_trabalhadas'];
                            });

                            if ($sum - floor($sum))
                            {
                                if ($sum * 10 - floor($sum * 10))
                                {
                                    echo number_format($sum, 2, ',', '.') . 'h';
                                }
                                else
                                {
                                    echo number_format($sum, 1, ',', '.') . 'h';
                                }
                            }
                            else
                            {
                                echo number_format($sum, 0, ',', '.') . 'h';
                            }
                            
                        ?>
                    </td>
                </tr>
            </table>
            

        </div>
    </article>
</body>
</html>
