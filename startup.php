<?php
    require_once __DIR__ . '/models/Connection.php';
    require_once __DIR__ . '/models/DAO.php';
    require_once __DIR__ . '/models/Empresa.php';
    require_once __DIR__ . '/models/Imagem.php';
    require_once __DIR__ . '/models/Funcionario.php';
    require_once __DIR__ . '/models/DiarioObra.php';
    require_once __DIR__ . '/models/FuncionarioDiarioObra.php';
    require_once __DIR__ . '/models/Obra.php';
    require_once __DIR__ . '/models/Servico.php';
    
    date_default_timezone_set('America/Sao_Paulo');
    set_time_limit(0);
    ini_set('display_errors', '1');

    use Models\Connection;
    use Models\DAO;
    
    $pathAlbum = __DIR__ . '/img/album';
    
    $pdo = Connection::getPDO();

    if (!$pdo)
	{
        die("Problemas com a conexão do banco de dados... Tente mais tarde.");
	}
    
    $dao = new DAO($pdo);
?>
