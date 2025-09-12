<?php

declare(strict_types=1);

namespace Models;

class Imagem
{
	private $id_imagem;
	private $fk_id_diario_obra;
	private $url;

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
