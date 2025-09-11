<?php
    require_once __DIR__ . '/startup.php';
    require_once __DIR__ . '/ftpFunctions.php';
    
    use Models\Empresa;
    use Models\Funcionario;
    // use Models\FuncionarioDiarioObra;
    use Models\DiarioObra;
    use Models\Obra;

    if (isset($_GET['id_obra']))
    {
        $listaDiariosObra = $dao->buscaTodosDiariosDaObra($_GET['id_obra']);
        // $data = date('Y-m-d');
        $diarioObra = new DiarioObra();
        $diarioObra->fk_id_obra = $_GET['id_obra'];

        if (!empty($listaDiariosObra))
        {
            $diarioObra->numero_diario = ((object)$listaDiariosObra[0])->numero_diario + 1;
            
            $data = ((object)$listaDiariosObra[0])->data;
            $diarioObra->data = (new DateTime($data))->add(new DateInterval('P1D'));
        }
        else
        {
            $diarioObra->numero_diario = 1;
            $diarioObra->data = new DateTime(date('Y-m-d'));
        }
    }
    else if (isset($_POST['id_obra']))
    {
        $diarioObra = new DiarioObra();

        $diarioObra->fk_id_obra = $_POST['id_obra'];
        $diarioObra->numero_diario = isset($_POST['numero_relatorio']) ? $_POST['numero_relatorio'] : 1;
        $diarioObra->data = isset($_POST['data']) ? $_POST['data'] : date('Y-m-d');
        
        if (!$dao->buscaDiarioObraPorIdObraDataNumero($diarioObra))
        {
            $ret = $dao->insereDiarioObra($diarioObra);

            header("Location: cadastroDiarioObras.php?id_obra={$diarioObra->fk_id_obra}");
        }
        else
        {
            header("Location: cadastroDiarioObras.php?id_obra={$diarioObra->fk_id_obra}&sucesso=0");
        }
    }
    else if(isset($_GET['remover']))
    {
        $diarioObra = new DiarioObra();
        
        $diarioObra->id_diario_obra = $_GET['remover'];

        $album = $dao->buscaAlbumDiario($diarioObra->id_diario_obra);
        
        foreach ($album as $foto)
        {
            unlink($foto['url']);
        }
        
        $ret = $dao->deleteAlbum($diarioObra->id_diario_obra);

        $removido = $dao->deleteDiarioObra($diarioObra);
    }
    
?>
<!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <title>Diário de Obras</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="css/bootstrap4.5.2.min.css">
        <script src="js/jquery3.5.1.min.js"></script>
        <script src="js/popper.min.js"></script>
        <script src="js/bootstrap4.5.2.min.js"></script>
    </head>
    <script>
        $(document).ready(function() {
            $('#criarDiario').on('click', function() {
                $(`#form`).submit();
            });
        });
    </script>
    <body>
        <div class="container clearfix w-50">
            <ul class="nav nav-tabs my-3 mb-5 mx-auto w-100">
                <li class="nav-item">
                    <a class="nav-link" href="cadastroObra.php">Voltar</a>
                </li>
            </ul>
            <h1 class="h3 text-secondary text-center my-5 mx-auto">Relatórios Diários de Obra</h1>
            <form class="w-75 mx-auto my-4" 
                action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>"
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
                            value="<?=  $diarioObra->data->format('Y-m-d') ?>" 
                            id="data">
                    </div>
                    <div class="form-group w-25">
                        <label for="numeroRelatorio">Nº Relatório:</label>
                        <input type="text"
                            name="numero_relatorio"
                            style="width: 100px" 
                            class="form-control" 
                            value="<?= isset($diarioObra) ? $diarioObra->numero_diario : '' ?>" 
                            id="numero_relatorio">

                        <input type="hidden"
                            name="id_obra"
                            value="<?= isset($diarioObra) ? $diarioObra->fk_id_obra : 0 ?>">
                    </div>
                </div>

                <div id="criarDiario" class="my-3 d-flex justify-content-end" style="cursor: pointer">
                    <div class="align-self-center mr-2">Criar</div> 
                    <div style="font-size: 26px !important">
                        <i class="text-success fa fa-plus-circle mr-1"></i>
                    </div>
                </div>
            </form>
            

            <? require_once __DIR__ . '/listaDiarioObras.php' ?>
        </div>
    </body>

</html>

<? if (isset($_GET['sucesso']) && $_GET['sucesso'] == 0) { ?>
    <script>
        alert("RDO com a mesmo número já foi cadastrado para esta obra");
    </script>
<? } ?>

