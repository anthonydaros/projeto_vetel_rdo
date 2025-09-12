<?php

declare(strict_types=1);

namespace Models;

class Empresa
{
	private $id_empresa;
	private $url_logo;
	private $nome_fantasia;
	private $contratante_sn;

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
