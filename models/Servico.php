<?php
declare(strict_types=1);

namespace Models;

class Servico
{
	private $id_servico;
	private $fk_id_diario_obra;
	private $descricao;

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