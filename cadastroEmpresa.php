<?php
require_once __DIR__ . '/startup.php';
require_once __DIR__ . '/ftpFunctions.php';
require_once __DIR__ . '/auth/CSRF.php';

use Models\Empresa;
use Auth\CSRF;

/**
 * Checks if a logo file already exists
 */
function isLogoAlreadyRegistered(string $logoName): bool
{
	$logoDirectory = __DIR__ . '/img/logo';

	if (!is_dir($logoDirectory)) {
		return false;
	}

	$files = scandir($logoDirectory);

	foreach ($files as $file) {
		if (is_file("$logoDirectory/$file")) {
			$fileNameWithoutExtension = pathinfo($file, PATHINFO_FILENAME);
			if (strcasecmp($fileNameWithoutExtension, $logoName) === 0) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Handles logo file upload
 */
function handleLogoUpload(): void
{
	if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
		return;
	}

	$fileData = file_get_contents($_FILES['file']['tmp_name']);
	$fileName = $_FILES['file']['name'];
	$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

	$logoPath = strtolower($_POST['pathLogo'] ?? '');

	if (!empty($logoPath) && !isLogoAlreadyRegistered($logoPath)) {
		$logoFilePath = __DIR__ . "/img/logo/{$logoPath}.{$fileExtension}";
		file_put_contents($logoFilePath, $fileData);
	}
}

/**
 * Validates company form data
 */
function validateCompanyData(): ?string
{
	$companyName = trim($_POST['nomeFantasia'] ?? '');

	if (empty($companyName)) {
		return 'nomeRequired=1';
	}

	return null;
}

/**
 * Creates and saves a new company
 */
function createCompany($dao, array $formData): bool
{
	$companyName = trim($formData['nomeFantasia']);
	$isContractor = isset($formData['contratanteSn']) ? (int) $formData['contratanteSn'] : 0;

	$company = new Empresa();
	$company->nome_fantasia = $companyName;
	$company->contratante_sn = $isContractor;

	// Handle logo URL if provided
	if (!empty($formData['pathLogo'])) {
		$fileExtension = strtolower(pathinfo($formData['pathLogo'], PATHINFO_EXTENSION));
		$logoName = strtolower($companyName);
		$company->url_logo = __DIR__ . "/img/logo/{$logoName}.{$fileExtension}";
	}

	return $dao->insereEmpresa($company) !== null;
}

/**
 * Handles company registration form submission
 */
function handleCompanyRegistration($dao): void
{
	if (!CSRF::verifyPost()) {
		redirectWithError('invalid_token');
		return;
	}

	$validationError = validateCompanyData();
	if ($validationError !== null) {
		redirectWithError($validationError);
		return;
	}

	$companyName = trim($_POST['nomeFantasia']);
	$existingCompany = $dao->buscaEmpresaPorNome($companyName);

	if ($existingCompany) {
		redirectWithMessage('sucesso=0');
		return;
	}

	if (createCompany($dao, $_POST)) {
		redirectWithMessage('sucesso=1');
	} else {
		redirectWithError('cadastroFalha=1');
	}
}

/**
 * Handles company deletion
 */
function handleCompanyDeletion($dao): void
{
	$companyId = filter_var($_GET['remover'], FILTER_VALIDATE_INT);

	if ($companyId === false || $companyId <= 0) {
		redirectWithError('invalid_id');
		return;
	}

	$company = $dao->buscaEmpresaPorId($companyId);
	if ($company) {
		// Remove logo file if exists
		if (!empty($company->url_logo) && file_exists($company->url_logo)) {
			unlink($company->url_logo);
		}

		$dao->deleteEmpresa($company);
	}

	header('Location: cadastroEmpresa.php');
	exit;
}

/**
 * Redirects with error parameter
 */
function redirectWithError(string $error): void
{
	header("Location: cadastroEmpresa.php?error={$error}");
	exit;
}

/**
 * Redirects with message parameter
 */
function redirectWithMessage(string $message): void
{
	header("Location: cadastroEmpresa.php?{$message}");
	exit;
}

// Main logic
if (isset($_FILES['file']) && !empty($_FILES['file'])) {
	handleLogoUpload();
} elseif (isset($_POST['submit'])) {
	handleCompanyRegistration($dao);
} elseif (isset($_GET['remover'])) {
	handleCompanyDeletion($dao);
}
?>
<!DOCTYPE html>
    <html lang="pt-br">
    <head>
        <title>Formulário Cadastro de Empresas</title>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="css/bootstrap4.5.2.min.css">
        <script src="js/jquery3.5.1.min.js"></script>
        <script src="js/popper.min.js"></script>
        <script src="js/bootstrap4.5.2.min.js"></script>
        <link rel="stylesheet" href="./dropzone-5.7.0/dist/min/dropzone.min.css">
        <script src="./dropzone-5.7.0/dist/min/dropzone.min.js"></script>
        <style>
            .dropzone {
                border: 2px dashed #888;
                border-radius: 5px;
            }
            .dropzone .dz-preview .dz-image {
                width: 100px;
                height: 100px;
                border-radius: 5px;
            }
            .dropzone .dz-message {
                margin: 10px;
            }
            .dropzone .dz-preview {
                margin: 0;
                min-height: 100px;
            }
            .dropzone .dz-preview.dz-image-preview {
                background: none;
            }
            .dropzone .dz-preview .dz-remove {
                margin-top: -5px;
            }
        </style>
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
                    <a class="nav-link" href="cadastroFuncionario.php">Funcionários</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="#">Empresas</a>
                </li>
            </ul>

            <h1 class="h3 text-center my-3">Cadastro de Empresa</h1>

            <form class="w-75 mx-auto my-4" 
                action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>"
                method="POST"
                id="form"
                enctype="multipart/form-data">

                <div class="form-group my-3 w-75 mx-auto">
                    <label for="nomeFantasia">Nome Fantasia:</label>
                    <input type="text" name="nomeFantasia" id="nomeFantasia" class="form-control">
                
                    <?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == 1) { ?>
                        <script>
                            alert('Empresa cadastrada com sucesso!')
                            var newURL = location.href.split("?")[0];
                            window.history.pushState('object', document.title, newURL);
                            location.reload(true)       
                        </script>
                    <?php } ?>
                    <?php if (isset($_GET['sucesso']) && $_GET['sucesso'] == 0) { ?>
                        <p class="small text-danger">Empresa com esse nome já consta na base de dados!</p>
                    <?php } ?>
                    <?php if (isset($_GET['error']) && $_GET['error'] == 'invalid_id') { ?>
                        <p class="small text-danger">ID inválido fornecido.</p>
                    <?php } ?>
                    <?php if (isset($_GET['error']) && $_GET['error'] == 'invalid_token') { ?>
                        <p class="small text-danger">Token de segurança inválido.</p>
                    <?php } ?>
                
                </div>

                <div class="form-group my-3 w-75 mx-auto">
                    <p 
                        class="dropzone mb-4"
                        style="width: 120px; padding: 3px !important; margin-top: 0 !important; text-align: center; min-height: 100px !important;"
                        id="logo">
                    </p>
                    <input type="hidden" id="pathLogo" name="pathLogo"> 
                </div>
                
                <div class="form-check my-3 w-75 mx-auto px-0" style="line-height: 1.1em">
                    <label class="form-check-label mr-4 pr-2" for="contratante_sn">É contratante:</label>
                    <input type="checkbox" name="contratanteSn" value="1" class="form-check-input" id="contratante_sn">
                    
                    <p class="small text-secondary mt-2">(Marque o checkbox acima apenas caso a empresa for a contratante)</p>
                </div>

                <?php echo CSRF::getTokenField(); ?>

                <button 
                    name="submit"
                    id="submit"
                    style="margin-right: 12%"
                    class="btn btn-primary float-right my-3">
                    Cadastrar
                </button>
            </form>
        </div>
        <script>   
            $(".dropzone#logo").dropzone(
                { 
                    url: "<?php echo $_SERVER['PHP_SELF']; ?>",
                    method: 'POST',
                    init: function()
                    {
                        this.on('addedfile', function(file) {
                            console.log(file);
                            $('#pathLogo').val(file.name)
                        })
                    },
                    success: function(file, response)
                    {
                        $('#form').submit();
                        // alert('Empresa cadastrada com sucesso!')
                    },
                    dictMaxFilesExceeded: 'Somente 1 arquivo permitido',
                    maxFiles: 1, 
                    timeout: 0,
                    dictCancelUpload: '',
                    autoProcessQueue: false,
                    thumbnailWidth: 100,
                    thumbnailHeight: 100,
                    dictRemoveFile: 'Excluir',
                    dictDefaultMessage: '<small class="font-italic">UPLOAD LOGO DA EMPRESA</small>', 
                    addRemoveLinks: true,
                    acceptedFiles: '.jpeg, .jpg, .png',
                    dictInvalidFileType: 'Extensões permitidas: jpg, jpeg e png',
                    sending: function(file, xhr, formData) {
                        formData.append("pathLogo", $('#nomeFantasia').val());
                    }
                    // maxFilesize: 50, // MB
                    // acceptedFiles: '.pdf, .jpeg, .jpg, .png, application/pdf',
                }
            );
            
            let dropzone = Dropzone.forElement("#logo");
            
            $('#submit').on('click', function(e) {
                let filesAccepted = dropzone.getAcceptedFiles().length;
                if (filesAccepted == 1)
                {
                    dropzone.processQueue();
                }
                else
                {
                    $('#form').submit();
                }
            })
        </script>

        <?php require_once __DIR__ . '/listaEmpresas.php'; ?>
    </body>
    
</html>