<?php
namespace Models;

class DiarioObra
{
	private $id_diario_obra;
	private $numero_diario;
	private $fk_id_obra;
	private $data;
	private $obs_gerais;
	private $horario_trabalho;
	private $carga_horas_dia;
	private $total_horas;

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