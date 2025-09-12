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
                                                // Preenche os dados no modal
                                                $('#deleteRdoNumber').text('<?php echo htmlspecialchars($diarioObra['numero_diario']) ?>');
                                                $('#deleteRdoDate').text('<?php echo htmlspecialchars((new DateTime($diarioObra['data']))->format('d/m/Y')) ?>');
                                                $('#confirmDeleteBtn').data('diario-id', '<?php echo htmlspecialchars($diarioObra['id_diario_obra']) ?>');
                                                // Mostra o modal
                                                $('#deleteConfirmModal').modal('show');
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

        <!-- Modal de Confirmação de Exclusão -->
        <div class="modal fade" id="deleteConfirmModal" tabindex="-1" role="dialog" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteConfirmModalLabel">
                            <i class="fa fa-exclamation-triangle mr-2"></i>
                            Confirmar Exclusão
                        </h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <i class="fa fa-trash-o text-danger" style="font-size: 48px;"></i>
                        </div>
                        <p class="text-center">
                            Você está prestes a excluir o <strong>RDO Nº <span id="deleteRdoNumber"></span></strong>
                        </p>
                        <p class="text-center text-muted">
                            Data: <span id="deleteRdoDate"></span>
                        </p>
                        <div class="alert alert-warning" role="alert">
                            <i class="fa fa-warning mr-2"></i>
                            <strong>Atenção!</strong> Esta ação irá remover permanentemente:
                            <ul class="mt-2 mb-0">
                                <li>O relatório diário de obra</li>
                                <li>Todas as fotos anexadas</li>
                                <li>Registros de funcionários do dia</li>
                                <li>Serviços executados</li>
                            </ul>
                        </div>
                        <p class="text-center text-danger font-weight-bold">
                            Esta ação não pode ser desfeita!
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">
                            <i class="fa fa-times mr-2"></i>Cancelar
                        </button>
                        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                            <i class="fa fa-trash mr-2"></i>Excluir RDO
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                // Handler para o botão de confirmação de exclusão
                $('#confirmDeleteBtn').on('click', function() {
                    var diarioId = $(this).data('diario-id');
                    
                    // Desabilita o botão e mostra loading
                    $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-2"></i>Excluindo...');
                    
                    // Faz a requisição de exclusão
                    $.get(`<?php echo htmlspecialchars($_SERVER['PHP_SELF']) ?>?remover=${diarioId}`, function(data, status) {
                        // Fecha o modal
                        $('#deleteConfirmModal').modal('hide');
                        // Recarrega a página
                        location.reload(true);
                    }).fail(function() {
                        // Em caso de erro
                        alert('Erro ao excluir o RDO. Por favor, tente novamente.');
                        $('#confirmDeleteBtn').prop('disabled', false).html('<i class="fa fa-trash mr-2"></i>Excluir RDO');
                    });
                });
                
                // Reset do botão quando o modal é fechado
                $('#deleteConfirmModal').on('hidden.bs.modal', function () {
                    $('#confirmDeleteBtn').prop('disabled', false).html('<i class="fa fa-trash mr-2"></i>Excluir RDO');
                });
            });
        </script>

    </body>
</html>


