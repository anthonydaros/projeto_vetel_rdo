<?php
namespace Models;

class Funcionario
{
	private $id_funcionario;
	private $fk_id_empresa;
	private $nome;
	private $cargo;

	public function __construct()
	{
	}

	public function __get($attr)
	{
		return $this->$attr;
	}

	public function __set($attr, $value)
	{
		$this->$attr = $value;
	}
}


?>