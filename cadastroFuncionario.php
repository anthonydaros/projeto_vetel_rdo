<?php
require_once __DIR__ . '/startup.php';

use Models\Funcionario;

/**
 * Validates employee form data
 */
function validateEmployeeData(): ?string
{
	if (empty($_POST['empresa'])) {
		return 'empresaRequired=1';
	}

	if (empty($_POST['nome'])) {
		return 'nomeRequired=1';
	}

	if (empty($_POST['cargo'])) {
		return 'cargoRequired=1';
	}

	return null;
}

/**
 * Creates and saves a new employee
 */
function createEmployee($dao, array $formData, int $companyId): bool
{
	$employee = new Funcionario();
	$employee->fk_id_empresa = $companyId;
	$employee->nome = trim($formData['nome']);
	$employee->cargo = trim($formData['cargo']);

	return $dao->insereFuncionario($employee) !== null;
}

/**
 * Handles employee registration form submission
 */
function handleEmployeeRegistration($dao): void
{
	$validationError = validateEmployeeData();
	if ($validationError !== null) {
		redirectWithError($validationError);
		return;
	}

	$company = $dao->buscaEmpresaPorNome($_POST['empresa']);
	if (!$company) {
		redirectWithError('empresaExiste=0');
		return;
	}

	if ($dao->funcionarioJaExiste($_POST['nome'], $company->id_empresa)) {
		redirectWithError('funcionarioExiste=1');
		return;
	}

	if (createEmployee($dao, $_POST, $company->id_empresa)) {
		redirectWithSuccess('cadastroSucesso=1');
	} else {
		redirectWithError('cadastroFalha=1');
	}
}

/**
 * Handles employee deletion
 */
function handleEmployeeDeletion($dao): void
{
	$employeeId = filter_var($_GET['remover'], FILTER_VALIDATE_INT);

	if ($employeeId === false || $employeeId <= 0) {
		redirectWithError('invalid_id');
		return;
	}

	$employee = $dao->buscaFuncionarioPorId($employeeId);
	if ($employee) {
		$employeeToDelete = new Funcionario();
		$employeeToDelete->id_funcionario = $employeeId;
		$dao->deleteFuncionario($employeeToDelete);
	}

	header('Location: cadastroFuncionario.php');
	exit;
}

/**
 * Redirects with error parameter
 */
function redirectWithError(string $error): void
{
	header("Location: cadastroFuncionario.php?error={$error}");
	exit;
}

/**
 * Redirects with success parameter
 */
function redirectWithSuccess(string $success): void
{
	header("Location: cadastroFuncionario.php?{$success}");
	exit;
}

// Main logic
$listaEmpresas = $dao->buscaTodasEmpresas();

if (isset($_POST['submit'])) {
	handleEmployeeRegistration($dao);
} elseif (isset($_GET['remover'])) {
	handleEmployeeDeletion($dao);
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
            
            <?php if (isset($_GET['error']) && $_GET['error'] == 'invalid_id') { ?>
                <div class="alert alert-danger w-75 mx-auto" role="alert">
                    ID inválido fornecido.
                </div>
            <?php } ?>

            <form class="w-75 mx-auto my-4" 
                action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>"
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
                        <?php foreach ($listaEmpresas as $empresa) { ?>
                            <option value="<?php echo htmlspecialchars($empresa['nome_fantasia']); ?>"><?php echo htmlspecialchars($empresa['nome_fantasia']); ?></option>
                        <?php } ?>
                    </select>
                    <?php if (isset($_GET['empresaRequired']) && $_GET['empresaRequired'] == 1) { ?>
                        <p class="small text-danger">
                            Informe empresa
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
        
        <?php require_once __DIR__ . '/listaFuncionarios.php'; ?>
    </body>
    
</html>

<?php if (isset($_GET['empresaExiste']) && $_GET['empresaExiste'] == 0) { ?>
    <script>
        alert("Empresa inexistente!");
    </script>
<?php } ?>
<?php if (isset($_GET['funcionarioExiste']) && $_GET['funcionarioExiste'] == 1) { ?>
    <script>
        alert("Funcionário informado já tem cadastro na empresa");
    </script>
<?php } ?>
<?php if (isset($_GET['cadastroSucesso']) && $_GET['cadastroSucesso'] == 1) { ?>
    <script>
        alert("Funcionário cadastrado com sucesso!");
    </script>
<?php } ?>
