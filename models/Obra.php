<?php
namespace Models;

class Obra
{
	private $id_obra;
	private $fk_id_contratante;
	private $fk_id_contratada;
	private $descricao_resumo;

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