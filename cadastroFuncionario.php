<?php
    require_once __DIR__ . '/startup.php';

    use Models\Empresa;
    use Models\Funcionario;
    use Models\FuncionarioObra;
    use Models\Obra;

    $listaEmpresas = $dao->buscaTodasEmpresas();

    if (isset($_POST['submit']))
    {
        if ($_POST['empresa'] == '')
        {
            header('Location: cadastroFuncionario.php?empresaRequired=1');
            die();
        }
        $funcionario = new Funcionario();
        $empresa = $dao->buscaEmpresaPorNome($_POST['empresa']);
        
        if (!$empresa)
        {
            header('Location: cadastroFuncionario.php?empresaExiste=0');
        }
        else if (!$dao->funcionarioJaExiste($_POST['nome'], $empresa->id_empresa))
        {
            $funcionario->fk_id_empresa = $empresa->id_empresa;
            $funcionario->nome = $_POST['nome'];
            $funcionario->cargo = $_POST['cargo'];
            
            $funcionario = $dao->insereFuncionario($funcionario);
            header('Location: cadastroFuncionario.php?cadastroSucesso=1');
        }
        else
        {
            header('Location: cadastroFuncionario.php?funcionarioExiste=1');
        }
    }
    else if (isset($_GET['remover']))
    {
        $funcionario = new Funcionario();
        $funcionario->id_funcionario = $_GET['remover'];
        $removido = $dao->deleteFuncionario($funcionario);   
    }
   
?>
<!DOCTYPE html>
    <html lang="pt-br">

    <head>
        <title>Cadastro de Funcionário</title>
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
                    <a class="nav-link" href="cadastroObra.php">Obras</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="#">Funcionários</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="cadastroEmpresa.php">Empresas</a>
                </li>
            </ul>

            <h1 class="h3 text-center my-3">Cadastro de Funcionário</h1>

            <form class="w-75 mx-auto my-4" 
                action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>"
                method="POST"
                id="form"
                enctype="multipart/form-data">

                <div class="form-group my-3 w-75 mx-auto">
                    <label for="nome">Nome:</label>
                    <input type="text" name="nome" class="form-control" id="nome">
                </div>

                <div class="form-group my-3 w-75 mx-auto">
                    <label for="cargo">Cargo:</label>
                    <input type="text" name="cargo" class="form-control" id="cargo">
                </div>

                <!-- <div class="form-group w-75 mx-auto">
                    <label for="empresa">Empresa:</label>
                    <input type="text" name="empresa" class="form-control" id="empresa">
                </div> -->
                
                <div class="form-group my-3 w-75 mx-auto">
                    <label for="empresa">Empresa:</label>
                    <select class="custom-select" name="empresa" id="empresa">
                        <option value="" selected>Selecionar Empresa</option>
                        <? foreach ($listaEmpresas as $empresa) { ?>
                            <option value="<?= $empresa['nome_fantasia'] ?>"><?= $empresa['nome_fantasia'] ?></option>
                        <? } ?>
                    </select>
                    <? if (isset($_GET['empresaRequired']) && $_GET['empresaRequired'] == 1) { ?>
                        <p class="small text-danger">
                            Informe empresa
                        </p>
                    <? } ?>
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
        
        <? require_once __DIR__ . '/listaFuncionarios.php' ?>
    </body>
    
</html>

<? if (isset($_GET['empresaExiste']) && $_GET['empresaExiste'] == 0) { ?>
    <script>
        alert("Empresa inexistente!");
    </script>
<? } ?>
<? if (isset($_GET['funcionarioExiste']) && $_GET['funcionarioExiste'] == 1) { ?>
    <script>
        alert("Funcionário informado já tem cadastro na empresa");
    </script>
<? } ?>
<? if (isset($_GET['cadastroSucesso']) && $_GET['cadastroSucesso'] == 1) { ?>
    <script>
        alert("Funcionário cadastrado com sucesso!");
    </script>
<? } ?>

