<?php
declare(strict_types=1);

namespace Models;

class Connection
{
	public static function getPDO()
	{
		try 
		{
			# procura a classe PDO no namespace raiz do php
			// $pdo = new \PDO(
			// 	"mysql:host=localhost;dbname=formulario_bd;charset=utf8",
			// 	"root",
			// 	""
			// );
			$pdo = new \PDO(
				"mysql:host=193.203.175.140:3306;dbname=u447438965_projetovetel;charset=utf8",
				"u447438965_projeto",
				"7BaBS5SvYd"
			);

			return $pdo;
		}
		catch (\PDOException $e)
		{
			return NULL;
		}
	}
}


?>