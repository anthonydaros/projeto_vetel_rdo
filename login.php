<?php
require_once __DIR__ . '/config/Config.php';
require_once __DIR__ . '/auth/Auth.php';

use Auth\Auth;
use Config\Config;

Config::load();
$auth = Auth::getInstance();

// If already logged in, redirect to index
if ($auth->isLoggedIn()) {
	header('Location: index.php');
	exit;
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$username = $_POST['username'] ?? '';
	$password = $_POST['password'] ?? '';

	if (empty($username) || empty($password)) {
		$error = 'Por favor, preencha todos os campos.';
	} else {
		if ($auth->login($username, $password)) {
			// Redirect to original page or index
			$redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
			unset($_SESSION['redirect_after_login']);
			header("Location: $redirect");
			exit;
		} else {
			$error = 'Usuário ou senha inválidos.';
		}
	}
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <title>Login - Sistema RDO</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="css/bootstrap4.5.2.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h2 {
            color: #333;
            font-weight: 600;
        }
        .login-header p {
            color: #666;
            margin-top: 10px;
        }
        .form-control {
            height: 45px;
            font-size: 14px;
            border-radius: 5px;
        }
        .btn-login {
            height: 45px;
            font-size: 16px;
            font-weight: 600;
            background: #667eea;
            border: none;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: #5a67d8;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .alert {
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group label {
            color: #555;
            font-weight: 500;
            margin-bottom: 8px;
        }
        .forgot-password {
            text-align: center;
            margin-top: 20px;
        }
        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        .forgot-password a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2>Sistema RDO</h2>
            <p>Faça login para continuar</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Usuário</label>
                <input type="text" 
                       class="form-control" 
                       id="username" 
                       name="username" 
                       placeholder="Digite seu usuário"
                       required 
                       autofocus
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" 
                       class="form-control" 
                       id="password" 
                       name="password" 
                       placeholder="Digite sua senha"
                       required>
            </div>
            
            <div class="form-group form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                <label class="form-check-label" for="remember">
                    Lembrar-me
                </label>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block btn-login">
                Entrar
            </button>
        </form>
        
        <div class="forgot-password">
            <a href="#">Esqueceu sua senha?</a>
        </div>
    </div>
    
    <script src="js/jquery3.5.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap4.5.2.min.js"></script>
</body>
</html>