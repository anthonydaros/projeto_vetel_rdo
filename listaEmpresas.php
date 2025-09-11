<?php
    $listaEmpresas = $dao->buscaTodasEmpresas();
    
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
    </head>
    <body>
        <div class="container">
            
            <? if (!empty($listaEmpresas)) { ?>
                <h1 class="h4 text-center mx-auto pb-3 mb-4 w-50">Listagem de Empresas Cadastradas</h1>
                <table class="table table-striped border mb-5 mx-auto" style="width: 60%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome Fantasia</th>
                            <th>Papel</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <? foreach ($listaEmpresas as $empresa) { ?>
                            <tr>
                                <td><?= $empresa['id_empresa'] ?></td>
                                <td><?= $empresa['nome_fantasia'] ?></td>
                                <td class="text-capitalize"><?= $empresa['contratante_sn'] == 1 ? 'contratante' : 'contratada' ?></td>
                                <td class="w-25">
                                    <span id="remover-<?= $empresa['id_empresa'] ?>" class="btn btn-link p-0 m-0" title="excluir empresa">
                                        Excluir
                                        <!-- <i class="text-danger fa fa-times-circle" aria-hidden="true"></i> -->
                                    </span>
                                    <script>
                                        $(() => {
                                            $(`#remover-<?= $empresa['id_empresa'] ?>`).on('click', function() {
                                                if (confirm('Deseja realmente excluir a empresa <?= $empresa['nome_fantasia']?>?'))
                                                {
                                                    $.get(`<?= $_SERVER['PHP_SELF'] ?>?remover=<?= $empresa['id_empresa'] ?>`, function(data, status) {
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
                        <? } ?>
                        
                    </tbody>
                </table>
            <? } else { ?>
                <h1 class="h6 font-italic text-secondary text-secondary text-center mx-auto pb-3 mb-4 w-50">Nenhuma empresa foi cadastrada ainda</h1>
            <? } ?>
            </br></br></br>
        </div>
    </body>
</html>


