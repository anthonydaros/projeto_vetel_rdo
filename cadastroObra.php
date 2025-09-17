<?php
require_once __DIR__ . '/startup.php';
require_once __DIR__ . '/ftpFunctions.php';

use Models\Obra;

// Load data for form dropdowns
$allCompanies = $dao->buscaTodasEmpresas();
$contractingCompanies = array_filter($allCompanies, fn ($company) => $company['contratante_sn'] == 1);
$contractedCompanies = array_filter($allCompanies, fn ($company) => $company['contratante_sn'] == 0);

// Form submission handler
if (isset($_POST['submit'])) {
	handleFormSubmission();
}

// Deletion handler
if (isset($_GET['remover'])) {
	handleProjectDeletion();
}

/**
 * Process form submission for new project creation
 */
function handleFormSubmission(): void
{
	global $dao;

	// Collect and validate form data
	$validationErrors = validateFormData();

	if (empty($validationErrors['messages'])) {
		createProject(
			$validationErrors['contractor'],
			$validationErrors['contracted'],
			$validationErrors['description']
		);
		redirect('cadastroObra.php?cadastroSucesso=1');
	} else {
		redirect('cadastroObra.php?' . implode('&', $validationErrors['messages']));
	}
}

/**
 * Validate form submission data
 */
function validateFormData(): array
{
	global $dao;

	$errors = [];
	$result = [
		'messages' => [],
		'description' => '',
		'contractor' => null,
		'contracted' => null
	];

	// Validate project description
	$result['description'] = trim($_POST['obra'] ?? '');
	if (empty($result['description'])) {
		$result['messages'][] = 'obraRequired=1';
	}

	// Validate contractor company
	if (!empty($_POST['contratante'])) {
		$result['contractor'] = $dao->buscaEmpresaPorNome($_POST['contratante']);
		if (!$result['contractor']) {
			$result['messages'][] = 'contratanteExiste=0';
		}
	} else {
		$result['messages'][] = 'contratanteRequired=1';
	}

	// Validate contracted company
	if (!empty($_POST['contratada'])) {
		$result['contracted'] = $dao->buscaEmpresaPorNome($_POST['contratada']);
		if (!$result['contracted']) {
			$result['messages'][] = 'contratadaExiste=0';
		}
	} else {
		$result['messages'][] = 'contratadaRequired=1';
	}

	return $result;
}

/**
 * Create new project in database
 */
function createProject($contractor, $contracted, $description): void
{
	global $dao;

	$project = new Obra();
	$project->fk_id_contratante = $contractor->id_empresa;
	$project->fk_id_contratada = $contracted->id_empresa;
	$project->descricao_resumo = $description;

	$dao->insereObra($project);
}

/**
 * Handle project deletion request
 */
function handleProjectDeletion(): void
{
	global $dao;

	// Validate project ID
	$projectId = filter_var($_GET['remover'], FILTER_VALIDATE_INT);
	if ($projectId === false || $projectId <= 0) {
		jsonResponse(['error' => 'ID inválido fornecido'], 400);
	}

	// Verify project exists
	if (!$dao->buscaObraPorId($projectId)) {
		jsonResponse(['error' => 'Obra não encontrada'], 404);
	}

	// Delete associated records
	deleteProjectRelatedData($projectId);

	// Delete project
	$project = new Obra();
	$project->id_obra = $projectId;
	$result = $dao->deleteObra($project);

	jsonResponse($result);
}

/**
 * Delete all data related to a project
 */
function deleteProjectRelatedData(int $projectId): void
{
	global $dao;

	$projectDiaries = $dao->buscaTodosDiariosDaObra($projectId);

	foreach ($projectDiaries as $diary) {
		deleteAlbumPhotos($diary['id_diario_obra']);
		$dao->deleteAlbum($diary['id_diario_obra']);
		$dao->deleteDiarioObra($diary['id_diario_obra']);
	}
}

/**
 * Delete photo files from an album
 */
function deleteAlbumPhotos(int $diaryId): void
{
	global $dao;

	$photos = $dao->buscaAlbumDiario($diaryId);
	foreach ($photos as $photo) {
		if (file_exists($photo['url'])) {
			unlink($photo['url']);
		}
	}
}

/**
 * Helper function for HTTP redirects
 */
function redirect(string $url): never
{
	header("Location: $url");
	exit;
}

/**
 * Send JSON response and exit
 */
function jsonResponse($data, int $statusCode = 200): never
{
	http_response_code($statusCode);
	echo json_encode($data);
	exit;
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
        

        <form class="w-75 mx-auto my-4" 
            action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>"
            method="POST"
            id="form"
            enctype="multipart/form-data">
            

            <div class="form-group my-3 w-75 mx-auto">
                <label for="contratante">Contratante:</label>
                <select class="custom-select" name="contratante" id="contratante">
                    <option value="" selected class="text-secondary">Selecionar empresa contratante</option>
                    <?php foreach ($contractingCompanies as $company): ?>
                        <option value="<?= htmlspecialchars($company['nome_fantasia']) ?>">
                            <?= htmlspecialchars($company['nome_fantasia']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($_GET['contratanteRequired']) && $_GET['contratanteRequired'] == 1): ?>
                    <p class="small text-danger">Informe empresa contratante</p>
                <?php endif; ?>
            </div>

            <div class="form-group my-3 w-75 mx-auto">
                <label for="contratada">Contratada:</label>
                <select class="custom-select" name="contratada" id="contratada">
                    <option value="" selected class="text-secondary">Selecionar empresa contratada</option>
                    <?php foreach ($contractedCompanies as $company): ?>
                        <option value="<?= htmlspecialchars($company['nome_fantasia']) ?>">
                            <?= htmlspecialchars($company['nome_fantasia']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($_GET['contratadaRequired']) && $_GET['contratadaRequired'] == 1): ?>
                    <p class="small text-danger">Informe empresa contratada</p>
                <?php endif; ?>
            </div>

            <div class="form-group w-75 mx-auto">
                <label for="obra">Obra:</label>
                <input type="text" name="obra" class="form-control" id="obra">
                <?php if (isset($_GET['obraRequired']) && $_GET['obraRequired'] == 1): ?>
                    <p class="small text-danger">Informe a descrição da obra</p>
                <?php endif; ?>
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

    <?php require_once __DIR__ . '/listaObras.php'; ?>
    
    <?php if (isset($_GET['cadastroSucesso']) && $_GET['cadastroSucesso'] == 1): ?>
        <script>alert("Obra cadastrada com sucesso!");</script>
    <?php endif; ?>
</body>
</html>