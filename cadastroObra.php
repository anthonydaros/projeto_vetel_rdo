<?php
    require_once __DIR__ . '/startup.php';
    require_once __DIR__ . '/ftpFunctions.php';
    require_once __DIR__ . '/auth/CSRF.php';
    
    use Models\Empresa;
    use Models\Funcionario;
    use Models\FuncionarioDiarioObra;
    use Models\Obra;
    use Auth\CSRF;
    
    $listaEmpresas = $dao->buscaTodasEmpresas();
    $listaEmpresasContratantes = array_filter($listaEmpresas, function($empresa) {
        return $empresa['contratante_sn'] == 1;
    });
    $listaEmpresasContratadas = array_filter($listaEmpresas, function($empresa) {
        return $empresa['contratante_sn'] == 0;
    });

    if (isset($_POST['submit']))
    {
        // Verify CSRF token
        if (!CSRF::verifyPost()) {
            header("Location: cadastroObra.php?error=invalid_token");
            exit;
        }
        
        $descricaoResumo = trim(isset($_POST['obra']) ? $_POST['obra'] : '');

        $msg = '';
        $contratante = null;
        $contratada = null;

        if ($descricaoResumo == '')
        {
            $msg .= 'obraRequired=1&';
        }

        if (isset($_POST['contratante']) && $_POST['contratante'] != '')
        {
            $contratante = $dao->buscaEmpresaPorNome($_POST['contratante']);
        
            if (!$contratante)
            {
                $msg .= 'contratanteExiste=0&'; 
            }
        }
        else
        {
            $msg .= 'contratanteRequired=1&';
        }
        
        if (isset($_POST['contratada']) && $_POST['contratada'] != '')
        {
            $contratada = $dao->buscaEmpresaPorNome($_POST['contratada']);
            if (!$contratada)
            {
                $msg .= 'contratadaExiste=0&'; 
            }
        }
        else
        {
            $msg .= 'contratadaRequired=1&';
        }

        if ($contratante && $contratada && $descricaoResumo)
        {
            $obra = new Obra();
            $obra->fk_id_contratante = $contratante->id_empresa;
            $obra->fk_id_contratada = $contratada->id_empresa;
            $obra->descricao_resumo = $descricaoResumo;

            $obra = $dao->insereObra($obra);

            header("Location: cadastroObra.php?cadastroSucesso=1");
        }
        else
        {
            header("Location: cadastroObra.php?$msg");
        }
    }
    else if (isset($_GET['remover']))
    {
        // Input validation for GET parameter
        $id = filter_var($_GET['remover'], FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            echo json_encode(['error' => 'ID inválido fornecido']);
            http_response_code(400);
            exit;
        }
        
        $obra = new Obra();
        $obra->id_obra = $id;
        
        // Verify if the obra exists before deletion
        $obraExists = $dao->buscaObraPorId($id);
        if (!$obraExists) {
            echo json_encode(['error' => 'Obra não encontrada']);
            http_response_code(404);
            exit;
        }
        
        $listaDiariosObra = $dao->buscaTodosDiariosDaObra($obra->id_obra);
        
        foreach ($listaDiariosObra as $diarioObra)
        {
            $album = $dao->buscaAlbumDiario($diarioObra['id_diario_obra']);

            foreach ($album as $foto)
            {
                if (file_exists($foto['url'])) {
                    unlink($foto['url']);
                }
            }

            $ret = $dao->deleteAlbum($diarioObra['id_diario_obra']);
            $ret = $dao->deleteDiarioObra($diarioObra['id_diario_obra']);
        }

        $removido = $dao->deleteObra($obra);
        
        echo json_encode($removido);
        die();
    }
?>
<!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <title>Formulário Cadastro de Obras</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="css/bootstrap4.5.2.min.css">
        <script src="js/jquery3.5.1.min.js"></script>
        <script src="js/popper.min.js"></script>
        <script src="js/bootstrap4.5.2.min.js"></script>
    </head>
    <body>
        <div class="container clearfix">
            <ul class="nav nav-tabs my-3 mb-5 mx-auto w-50">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Index</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="#">Obras</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="cadastroFuncionario.php">Funcionários</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="cadastroEmpresa.php">Empresas</a>
                </li>
            </ul>

            <h1 class="h3 text-center my-3">Cadastro de Obra</h1>
            
            <?php if (isset($_GET['error']) && $_GET['error'] == 'invalid_token') { ?>
                <div class="alert alert-danger w-75 mx-auto" role="alert">
                    Token de segurança inválido.
                </div>
            <?php } ?>

            <form class="w-75 mx-auto my-4" 
                action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>"
                method="POST"
                id="form"
                enctype="multipart/form-data">
                
                <?php echo CSRF::getTokenField(); ?>

                <!-- <div class="form-group my-3 w-75 mx-auto">
                    <label for="contratante">Contratante:</label>
                    <input type="text" name="contratante" class="form-control" id="contratante">
                </div> -->

                <div class="form-group my-3 w-75 mx-auto">
                    <label for="contratante">Contratante:</label>
                    <select class="custom-select" name="contratante" id="contratante">
                        <option value="" selected class="text-secondary">Selecionar empresa contratante</option>
                        <?php foreach ($listaEmpresasContratantes as $empresa) { ?>
                            <option value="<?php echo htmlspecialchars($empresa['nome_fantasia']); ?>"><?php echo htmlspecialchars($empresa['nome_fantasia']); ?></option>
                        <?php } ?>
                    </select>
                    <?php if (isset($_GET['contratanteRequired']) && $_GET['contratanteRequired'] == 1) { ?>
                        <p class="small text-danger">
                            Informe empresa contratante
                        </p>
                    <?php } ?>
                </div>

                <!-- <div class="form-group my-3 w-75 mx-auto">
                    <label for="contratada">Contratada:</label>
                    <input type="text" name="contratada" class="form-control" id="contratada">
                </div> -->

                <div class="form-group my-3 w-75 mx-auto">
                    <label for="contratada">Contratada:</label>
                    <select class="custom-select" name="contratada" id="contratada">
                        <option value="" selected class="text-secondary">Selecionar empresa contratada</option>
                        <?php foreach ($listaEmpresasContratadas as $empresa) { ?>
                            <option value="<?php echo htmlspecialchars($empresa['nome_fantasia']); ?>"><?php echo htmlspecialchars($empresa['nome_fantasia']); ?></option>
                        <?php } ?>
                    </select>
                    <?php if (isset($_GET['contratadaRequired']) && $_GET['contratadaRequired'] == 1) { ?>
                        <p class="small text-danger">
                            Informe empresa contratada
                        </p>
                    <?php } ?>
                </div>

                <div class="form-group w-75 mx-auto">
                    <label for="obra">Obra:</label>
                    <input type="text" name="obra" class="form-control" id="obra">
                    
                    <?php if (isset($_GET['obraRequired']) && $_GET['obraRequired'] == 1) { ?>
                        <p class="small text-danger">
                            Informe a descrição da obra
                        </p>
                    <?php } ?>
                </div>

                <button 
                    name="submit"
                    id="submit"
                    style="margin-right: 12%"
                    class="btn btn-primary float-right my-3">
                    Criar
                </button>
            </form>
        </div>
        <script>   
            
        </script>

        <?php require_once __DIR__ . '/listaObras.php'; ?>
    </body>
    
</html>

<?php if (isset($_GET['cadastroSucesso']) && $_GET['cadastroSucesso'] == 1) { ?>
    <script>
        alert("Obra cadastrada com sucesso!");
    </script>
<?php } ?>