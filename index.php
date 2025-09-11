<?php

require_once __DIR__ . '/startup.php';
require_once __DIR__ . '/auth/Auth.php';

use Auth\Auth;

// Require authentication
Auth::requireAuth();

$auth = Auth::getInstance();
$userName = $auth->getUserName();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
	<title>Página Inicial</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="css/bootstrap4.5.2.min.css">
	<script src="js/jquery3.5.1.min.js"></script>
	<script src="js/popper.min.js"></script>
	<script src="js/bootstrap4.5.2.min.js"></script>
	<style></style>
</head>
<body>
	<div class="container clearfix">
		<!-- User info bar -->
		<div class="d-flex justify-content-between align-items-center py-3 mb-3 border-bottom">
			<div>
				<span class="text-muted">Bem-vindo, </span>
				<strong><?php echo htmlspecialchars($userName); ?></strong>
			</div>
			<div>
				<a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
			</div>
		</div>
		
		<ul class="nav nav-tabs my-3 mb-5 mx-auto w-50">
			<li class="nav-item">
				<a class="nav-link active" href="#">Index</a>
			</li>
			<li class="nav-item">
				<a class="nav-link" href="cadastroObra.php">Obras</a>
			</li>
			<li class="nav-item">
				<a class="nav-link" href="cadastroFuncionario.php">Funcionários</a>
			</li>
			<li class="nav-item">
				<a class="nav-link" href="cadastroEmpresa.php">Empresas</a>
			</li>
		</ul>

		<h1 style="line-height: 1.8em" 
			class="h4 py-5 align-middle text-success text-center my-5 w-50 mx-auto">Bem-vindo ao sistema de geração automática e controle de relatórios RDOs e RDPs :)</h1>
	</div>
</body>
</html>
