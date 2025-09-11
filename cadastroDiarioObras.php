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
        // Input validation for GET parameter id_obra
        $id_obra = filter_var($_GET['id_obra'], FILTER_VALIDATE_INT);
        if ($id_obra === false || $id_obra <= 0) {
            header('Location: cadastroObra.php?error=invalid_id');
            exit;
        }
        
        // Verify if obra exists
        $obraExists = $dao->buscaObraPorId($id_obra);
        if (!$obraExists) {
            header('Location: cadastroObra.php?error=obra_not_found');
            exit;
        }
        
        $listaDiariosObra = $dao->buscaTodosDiariosDaObra($id_obra);
        // $data = date('Y-m-d');
        $diarioObra = new DiarioObra();
        $diarioObra->fk_id_obra = $id_obra;

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
        // Input validation for POST parameter id_obra
        $id_obra = filter_var($_POST['id_obra'], FILTER_VALIDATE_INT);
        if ($id_obra === false || $id_obra <= 0) {
            header('Location: cadastroObra.php?error=invalid_id');
            exit;
        }
        
        // Verify if obra exists
        $obraExists = $dao->buscaObraPorId($id_obra);
        if (!$obraExists) {
            header("Location: cadastroDiarioObras.php?id_obra=$id_obra&error=obra_not_found");
            exit;
        }
        
        $diarioObra = new DiarioObra();

        $diarioObra->fk_id_obra = $id_obra;
        $diarioObra->numero_diario = isset($_POST['numero_relatorio']) ? intval($_POST['numero_relatorio']) : 1;
        $diarioObra->data = isset($_POST['data']) ? $_POST['data'] : date('Y-m-d');
        
        // Additional validation for numero_relatorio
        if ($diarioObra->numero_diario <= 0) {
            header("Location: cadastroDiarioObras.php?id_obra=$id_obra&error=invalid_numero");
            exit;
        }
        
        // Validate date format
        if (!DateTime::createFromFormat('Y-m-d', $diarioObra->data)) {
            header("Location: cadastroDiarioObras.php?id_obra=$id_obra&error=invalid_date");
            exit;
        }
        
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
        // Input validation for GET parameter remover
        $id = filter_var($_GET['remover'], FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            header('Location: cadastroObra.php?error=invalid_id');
            exit;
        }
        
        $diarioObra = new DiarioObra();
        $diarioObra->id_diario_obra = $id;
        
        // Verify if diario exists before deletion
        $diarioExists = $dao->buscaDiarioObraPorId($id);
        if (!$diarioExists) {
            header('Location: cadastroObra.php?error=diario_not_found');
            exit;
        }

        $album = $dao->buscaAlbumDiario($diarioObra->id_diario_obra);
        
        foreach ($album as $foto)
        {
            if (file_exists($foto['url'])) {
                unlink($foto['url']);
            }
        }
        
        $ret = $dao->deleteAlbum($diarioObra->id_diario_obra);

        $removido = $dao->deleteDiarioObra($diarioObra);
        
        // Get the obra ID to redirect properly
        $obraId = $diarioExists->fk_id_obra ?? 0;
        if ($obraId > 0) {
            header("Location: cadastroDiarioObras.php?id_obra=$obraId");
        } else {
            header('Location: cadastroObra.php');
        }
        exit;
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
            
            <?php if (isset($_GET['error'])) { ?>
                <div class="alert alert-danger w-75 mx-auto" role="alert">
                    <?php 
                    switch($_GET['error']) {
                        case 'invalid_id':
                            echo 'ID inválido fornecido.';
                            break;
                        case 'obra_not_found':
                            echo 'Obra não encontrada.';
                            break;
                        case 'diario_not_found':
                            echo 'Diário não encontrado.';
                            break;
                        case 'invalid_numero':
                            echo 'Número do relatório deve ser maior que zero.';
                            break;
                        case 'invalid_date':
                            echo 'Formato de data inválido.';
                            break;
                        default:
                            echo 'Erro desconhecido.';
                    }
                    ?>
                </div>
            <?php } ?>
            
            <?php if (isset($diarioObra)): ?>
            <form class="w-75 mx-auto my-4" 
                action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) ?>"
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
                            value="<?php echo htmlspecialchars($diarioObra->data->format('Y-m-d')) ?>" 
                            id="data">
                    </div>
                    <div class="form-group w-25">
                        <label for="numeroRelatorio">Nº Relatório:</label>
                        <input type="number"
                            name="numero_relatorio"
                            style="width: 100px" 
                            class="form-control" 
                            min="1"
                            value="<?php echo htmlspecialchars(isset($diarioObra) ? $diarioObra->numero_diario : '') ?>" 
                            id="numero_relatorio">

                        <input type="hidden"
                            name="id_obra"
                            value="<?php echo htmlspecialchars(isset($diarioObra) ? $diarioObra->fk_id_obra : 0) ?>">
                    </div>
                </div>

                <div id="criarDiario" class="my-3 d-flex justify-content-end" style="cursor: pointer">
                    <div class="align-self-center mr-2">Criar</div> 
                    <div style="font-size: 26px !important">
                        <i class="text-success fa fa-plus-circle mr-1"></i>
                    </div>
                </div>
            </form>
            <?php endif; ?>
            

            <?php require_once __DIR__ . '/listaDiarioObras.php' ?>
        </div>
    </body>

</html>

<?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == 0) { ?>
    <script>
        alert("RDO com a mesmo número já foi cadastrado para esta obra");
    </script>
<?php } ?>