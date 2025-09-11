<?php
    // $listaDiariosObra = $dao->buscaTodosDiariosDaObra(isset($_GET['id_obra']) ? $_GET['id_obra'] : 0);
    
?>
<!DOCTYPE html>
    <html lang="pt-br">

    <head>
        <title>Listagem de Relatórios Diários de Obra</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
        <link rel="stylesheet" href="css/bootstrap4.5.2.min.css">
        <script src="js/jquery3.5.1.min.js"></script>
        <script src="js/popper.min.js"></script>
        <script src="js/bootstrap4.5.2.min.js"></script>
    </head>

    <body>
        <div class="container clearfix w-100 my-5">
            <?php if (!empty($listaDiariosObra)) { ?>
                <span class="text-capitalize mr-1">Obra:</span> <b><?php echo htmlspecialchars($listaDiariosObra[0]['descricao_resumo']) ?></b> </br>
                <!-- <span class="text-capitalize mr-1">Contratante:</span><b><?php echo htmlspecialchars($dao->buscaEmpresaPorId($listaDiariosObra[0]['fk_id_contratante'])->nome_fantasia) ?></b> </br>
                <span class="text-capitalize mr-1">Contratada:</span><b><?php echo htmlspecialchars($dao->buscaEmpresaPorId($listaDiariosObra[0]['fk_id_contratada'])->nome_fantasia) ?></b> </br> -->
                <!-- <h1 class="h4 text-center mx-auto pb-3 mb-4 w-50">Listagem de Relatórios Diários de Obra</h1> -->
                <table id="diariosObra" class="table table-striped border mt-3 mb-5 mx-auto">
                    <thead>
                        <tr>
                            <th class="text-center">Nº</th>
                            <th class="text-center">Data</th>
                            <th class="text-center">Contratante</th>
                            <th class="text-center">Contratada</th>
                            <th class="text-center">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($listaDiariosObra as $diarioObra) { ?>
                            <tr>
                                <td class="text-center"><?php echo htmlspecialchars($diarioObra['numero_diario']) ?></td>
                                <td class="text-center"><?php echo htmlspecialchars((new DateTime($diarioObra['data']))->format('d/m/Y')) ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($dao->buscaEmpresaPorId($diarioObra['fk_id_contratante'])->nome_fantasia) ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($dao->buscaEmpresaPorId($diarioObra['fk_id_contratada'])->nome_fantasia) ?></td>
                                <td class="text-center">
                                    <a href="coletorDados.php?id_diario_obra=<?php echo htmlspecialchars($diarioObra['id_diario_obra']) ?>"
                                        style="text-decoration: none"
                                        title="Preencher RDO" 
                                        class="mx-1" 
                                        style="cursor: pointer">
                                        <!-- <i class="fa fa-file-text-o text-dark" aria-hidden="true"></i> -->
                                        <i class="fa fa-pencil-square-o" aria-hidden="true"></i>
                                    </a>
                                    <a id="remover-<?php echo htmlspecialchars($diarioObra['id_diario_obra']) ?>" style="cursor: pointer" title="remover RDO">
                                        <!-- Excluir -->
                                        <i class="text-danger fa fa-times-circle" aria-hidden="true"></i>
                                    </a>
                                    <script>
                                        $(() => {
                                            $(`#remover-<?php echo htmlspecialchars($diarioObra['id_diario_obra']) ?>`).on('click', function() {
                                                if (confirm('Deseja realmente excluir o RDO Nº <?php echo htmlspecialchars($diarioObra['numero_diario']) ?>?'))
                                                {
                                                    $.get(`<?php echo htmlspecialchars($_SERVER['PHP_SELF']) ?>?remover=<?php echo htmlspecialchars($diarioObra['id_diario_obra']) ?>`, function(data, status) {
                                                        // var newURL = location.href.split("?")[0];
                                                        // window.history.pushState('object', document.title, newURL);
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
                <h1 class="h6 font-italic text-secondary text-center mx-auto py-5 my-5 w-50">Nenhum relatório cadastrado ainda para a obra</h1>
            <?php } ?>
            </br></br></br>
        </div>

    </body>
</html>


