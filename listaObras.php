<?php
    $listaObras = $dao->buscaTodasObras();
    
?>
<!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <title>Listagem de Obras</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        <link rel="stylesheet" href="css/bootstrap4.5.2.min.css">
        <script src="js/jquery3.5.1.min.js"></script>
        <script src="js/popper.min.js"></script>
        <script src="js/bootstrap4.5.2.min.js"></script>
        <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-confirm/3.3.2/jquery-confirm.min.js"></script> -->
    </head>
    <body>
        <div class="container">
            
            <? if (!empty($listaObras)) { ?>
                <h1 class="h4 text-center mx-auto pb-3 mb-4 w-50">Listagem de Obras</h1>
                <table class="table table-striped border w-75 mb-5 mx-auto">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Descrição</th>
                            <th>Contratante</th>
                            <th>Contratada</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <? foreach ($listaObras as $obra) { ?>
                            <tr>
                                <td><?= $obra['id_obra'] ?></td>
                                <td class="w-50"><?= $obra['descricao_resumo'] ?></td>
                                <td><?= $dao->buscaEmpresaPorId($obra['fk_id_contratante'])->nome_fantasia ?></td>
                                <td><?= $dao->buscaEmpresaPorId($obra['fk_id_contratada'])->nome_fantasia ?></td>
                                <td>
                                    <a href="cadastroDiarioObras.php?id_obra=<?= $obra['id_obra'] ?>"
                                        style="text-decoration: none"
                                        title="gerir relatórios" 
                                        class="mx-1" 
                                        style="cursor: pointer">
                                        <i class="fa fa-file-text-o text-dark" aria-hidden="true"></i>
                                    </a>
                                    <span id="remover-<?= $obra['id_obra'] ?>" title="remover obra" class="mx-1" style="cursor: pointer">
                                        <i class="text-danger fa fa-times-circle" aria-hidden="true"></i>
                                    </span>
                                    <script>
                                        $(() => {
                                            $(`#remover-<?= $obra['id_obra'] ?>`).on('click', function() {
                                                if (confirm('Deseja realmente excluir a obra ID '+ <?= $obra['id_obra'] ?>+'?'))
                                                {
                                                    $.get(`<?= $_SERVER['PHP_SELF'] ?>?remover=<?= $obra['id_obra'] ?>`, function(data, status) {
                                                        var newURL = location.href.split("?")[0];
                                                        window.history.pushState('object', document.title, newURL);
                                                        location.reload(true) 
                                                        // console.log(JSON.stringify(data));
                                                    });
                                                }
                                            });
                                        });
                                    </script>
                                </td>
                            </tr>
                        <? } ?>
                        
                    </tbody>
                </table>
            <? } else { ?>
                <h1 class="h6 font-italic text-secondary text-center mx-auto pb-3 mb-4 w-50">Nenhuma obra cadastrada</h1>
            <? } ?>
            </br></br></br>
        </div>
    </body>
</html>


