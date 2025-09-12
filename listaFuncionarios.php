<?php
$listaFuncionarios = $dao->buscaTodosFuncionariosJoinEmpresa();

?>
<!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <title>Listagem de Funcionários</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        <link rel="stylesheet" href="css/bootstrap4.5.2.min.css">
        <script src="js/jquery3.5.1.min.js"></script>
        <script src="js/popper.min.js"></script>
        <script src="js/bootstrap4.5.2.min.js"></script>
    </head>
    <body>
        <div class="container">
            
            <?php if (!empty($listaFuncionarios)) { ?>
                <h1 class="h4 text-center mx-auto pb-3 mb-4 w-50">Listagem de Funcionários</h1>
                <table class="table table-striped border w-75 mb-5 mx-auto">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Cargo</th>
                            <th>Empresa</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                      <tbody>
                        <?php foreach ($listaFuncionarios as $funcionario) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($funcionario['nome']); ?></td>
                                <td><?php echo htmlspecialchars($funcionario['cargo']); ?></td>
                                <td><?php echo htmlspecialchars($funcionario['empresa']); ?></td>
                                <td>
                                    <a href="geradorRdp.php?id_funcionario=<?php echo $funcionario['id_funcionario']; ?>" class="mx-1 text-dark text-decoration-none" title="RDP">
                                        <i class="fa fa-file-text-o text-dark" aria-hidden="true"></i>
                                    </a>
                                    <span id="remover-<?php echo $funcionario['id_funcionario']; ?>" class="mx-1" title="remover funcionário" style="cursor: pointer">
                                        <i class="text-danger fa fa-times-circle" aria-hidden="true"></i>
                                    </span>
                                    <script>
                                        $(() => {
                                            $(`#remover-<?php echo $funcionario['id_funcionario']; ?>`).on('click', function() {
                                                if (confirm('Deseja realmente excluir o funcionário <?php echo $funcionario['nome']; ?>?'))
                                                {
                                                    $.get(`<?php echo $_SERVER['PHP_SELF']; ?>?remover=<?php echo $funcionario['id_funcionario']; ?>`, function(data, status) {
                                                        var newURL = location.href.split("?")[0];
                                                        window.history.pushState('object', document.title, newURL);
                                                        location.reload(true)       
                                                    })
                                                }
                                            });
                                        });
                                    </script>
                                </td>
                            </tr>
                        <?php } ?>
                        
                    </tbody>
                </table>
            <?php } else { ?>
                <h1 class="h6 font-italic text-secondary text-center mx-auto pb-3 mb-4 w-50">Nenhum funcionário cadastrado</h1>
            <?php } ?>
            		
            </br></br></br>
        </div>
    </body>
</html>



