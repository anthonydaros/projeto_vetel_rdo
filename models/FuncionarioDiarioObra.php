<?php
namespace Models;

class FuncionarioDiarioObra
{
	private $id_funcionario_diario_obra;
	private $fk_id_funcionario;
	private $fk_id_diario_obra;
	private $data;
	private $horario_trabalho;
	private $horas_trabalhadas;

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