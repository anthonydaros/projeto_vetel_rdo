<?php

declare(strict_types=1);

namespace Models;

class DAO
{
	private $db;

	public function __construct(\PDO $db)
	{
		$this->db = $db;
	}

	public function __get($attr)
	{
		return $this->$attr;
	}

	public function __set($attr, $value)
	{
		$this->$attr = $value;
	}

	/******************* INSERT *******************/

	public function insereObra(Obra $obra)
	{
		$query = '
				insert into obra(
					fk_id_contratante, 
					fk_id_contratada, 
					descricao_resumo
				) values (
					:fk_id_contratante,
					:fk_id_contratada,
					:descricao_resumo
				)
				';
		$stmt = $this->db->prepare($query);

		// $stmt->bindValue(':product_id', $prod->product_id);

		$data = [
			'fk_id_contratante' => $obra->fk_id_contratante,
			'fk_id_contratada' => $obra->fk_id_contratada,
			'descricao_resumo' => $obra->descricao_resumo
		];

		$stmt->execute($data);

		return $this->buscaUltimaInsercao('obra', 'id_obra');
	}

	public function insereServico(Servico $servico)
	{
		$query = '
				insert into servico(
					fk_id_diario_obra, 
					descricao
				) values (
					:fk_id_diario_obra,
					:descricao
				)
				';
		$stmt = $this->db->prepare($query);

		$data = [
			'fk_id_diario_obra' => $servico->fk_id_diario_obra,
			'descricao' => $servico->descricao
		];

		$stmt->execute($data);

		return $this->buscaUltimaInsercao('servico', 'id_servico');
	}

	public function insereDiarioObra(DiarioObra $diarioObra)
	{
		$query = '
				insert into diario_obra(
					numero_diario,
					fk_id_obra,
					data,
					obs_gerais,
					horario_trabalho,
					carga_horas_dia,
					total_horas
				) values (
					:numero_diario,
					:fk_id_obra,
					:data,
					:obs_gerais,
					:horario_trabalho,
					:carga_horas_dia,
					:total_horas
				)
				';
		$stmt = $this->db->prepare($query);

		$data = [
			'numero_diario' => $diarioObra->numero_diario,
			'fk_id_obra' => $diarioObra->fk_id_obra,
			'data' => $diarioObra->data,
			'obs_gerais' => $diarioObra->obs_gerais,
			'horario_trabalho' => $diarioObra->horario_trabalho,
			'carga_horas_dia' => $diarioObra->carga_horas_dia,
			'total_horas' => $diarioObra->total_horas
		];

		$stmt->execute($data);

		return $this->buscaUltimaInsercao('diario_obra', 'id_diario_obra');
	}

	public function insereEmpresa(Empresa $empresa)
	{
		$query = '
				insert into empresa(
					nome_fantasia,
					contratante_sn,
					url_logo
				) values (
					:nome_fantasia,
					:contratante_sn,
					:url_logo
				)
				';
		$stmt = $this->db->prepare($query);

		$data = [
			'nome_fantasia' => $empresa->nome_fantasia,
			'contratante_sn' => $empresa->contratante_sn,
			'url_logo' => $empresa->url_logo
		];

		$stmt->execute($data);

		return $this->buscaUltimaInsercao('empresa', 'id_empresa');
	}

	public function insereImagem(Imagem $imagem)
	{
		$query = '
				insert into imagem(
					fk_id_diario_obra, 
					url
				) values (
					:fk_id_diario_obra,
					:url
				)
				';
		$stmt = $this->db->prepare($query);

		$data = [
			'fk_id_diario_obra' => $imagem->fk_id_diario_obra,
			'url' => $imagem->url
		];

		$stmt->execute($data);

		return $this->buscaUltimaInsercao('imagem', 'id_imagem');
	}

	public function insereFuncionario(Funcionario $funcionario)
	{
		$query = '
				insert into funcionario(
					fk_id_empresa, 
					nome,
					cargo
				) values (
					:fk_id_empresa,
					:nome,
					:cargo
				)
				';
		$stmt = $this->db->prepare($query);

		$data = [
			'fk_id_empresa' => $funcionario->fk_id_empresa,
			'nome' => $funcionario->nome,
			'cargo' => $funcionario->cargo
		];

		$stmt->execute($data);

		return $this->buscaUltimaInsercao('funcionario', 'id_funcionario');
	}

	public function insereFuncionarioDiarioObra(FuncionarioDiarioObra $funcionarioDiarioObra)
	{
		$query = '
				insert into funcionario_diario_obra(
					fk_id_funcionario, 
					fk_id_diario_obra,
					data,
					horario_trabalho,
					horas_trabalhadas
				) values (
					:fk_id_funcionario,
					:fk_id_diario_obra,
					:data,
					:horario_trabalho,
					:horas_trabalhadas
				)
				';
		$stmt = $this->db->prepare($query);

		$data = [
			'fk_id_funcionario' => $funcionarioDiarioObra->fk_id_funcionario,
			'fk_id_diario_obra' => $funcionarioDiarioObra->fk_id_diario_obra,
			'data' => $funcionarioDiarioObra->data,
			'horario_trabalho' => $funcionarioDiarioObra->horario_trabalho,
			'horas_trabalhadas' => $funcionarioDiarioObra->horas_trabalhadas
		];

		$ret = $stmt->execute($data);

		return $this->buscaUltimaInsercao('funcionario_diario_obra', 'id_funcionario_diario_obra');
	}

	/******************* SELECT *******************/

	public function buscaTodasObras()
	{
		$query = '
				select * 
					from 
				obra
					order by 
				id_obra desc
				';

		$stmt = $this->db->prepare($query);
		$stmt->execute();

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function buscaAlbumDiario($idDiarioObra)
	{
		$query = '
				select * 
					from 
				imagem
					where
				fk_id_diario_obra = :fk_id_diario_obra
					order by
				url asc
				';

		$stmt = $this->db->prepare($query);
		$data = [
			'fk_id_diario_obra' => $idDiarioObra
		];
		$stmt->execute($data);

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function buscaTodosDiariosDaObra($idObra)
	{
		$query = '
				select * 
					from 
				diario_obra
					inner join
				obra as o
					on fk_id_obra = o.id_obra
				where
					fk_id_obra = :id_obra
				order by
					data desc,
					numero_diario desc
				';

		$stmt = $this->db->prepare($query);
		$data = [
			'id_obra' => $idObra
		];
		$stmt->execute($data);

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function buscaObraPorId($idObra)
	{
		$query = '
				select 
					*
				from 
					obra
				where
					id_obra = :id_obra
				';

		$stmt = $this->db->prepare($query);
		$data = [
			'id_obra' => $idObra
		];
		$stmt->execute($data);

		$arr = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		if (!empty($arr)) {
			return (object) $arr[0];
		}

		return null;
	}

	public function buscaDiarioObraPorIdObraDataNumero($diarioObra)
	{
		$query = '
				select 
					*
				from 
					diario_obra
				where
					fk_id_obra = :fk_id_obra
				and
					numero_diario = :numero_diario
				';

		$stmt = $this->db->prepare($query);
		$data = [
			'fk_id_obra' => $diarioObra->fk_id_obra,
			'numero_diario' => $diarioObra->numero_diario
		];
		$stmt->execute($data);

		$arr = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		if (!empty($arr)) {
			return (object) $arr[0];
		}

		return null;
	}

	public function buscaDiarioObraPorId($idDiarioObra)
	{
		$query = '
				select 
					*
				from 
					diario_obra
				inner join
					obra as o
				on 
					o.id_obra = fk_id_obra
				where
					id_diario_obra = :id_diario_obra
				';

		$stmt = $this->db->prepare($query);
		$data = [
			'id_diario_obra' => $idDiarioObra
		];
		$stmt->execute($data);

		$arr = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		if (!empty($arr)) {
			return (object) $arr[0];
		}

		return null;
	}

	public function buscaTodasEmpresas()
	{
		$query = '
				select * 
					from 
				empresa
					where 1 
				order by 
					id_empresa desc';

		$stmt = $this->db->prepare($query);
		$stmt->execute();

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function buscaTodosServicosDoDiarioObra($idDiarioObra)
	{
		$query = '
				select * 
					from 
				servico
					where
				fk_id_diario_obra = :fk_id_diario_obra
				';

		$stmt = $this->db->prepare($query);
		$data = [
			'fk_id_diario_obra' => $idDiarioObra
		];
		$stmt->execute($data);

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function buscaTodosFuncionariosJoinEmpresa()
	{
		$query = '
				select 
					f.id_funcionario,
					f.fk_id_empresa,
					f.nome,
					f.cargo,
					e.nome_fantasia as empresa,
					e.contratante_sn
				from 
					funcionario as f
				inner join
					empresa as e
				on f.fk_id_empresa = e.id_empresa
					order by f.nome asc
				limit 150
				';

		$stmt = $this->db->prepare($query);
		$stmt->execute();

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function buscaPeriodoObraDoFuncionario($idObra, $idFuncionario)
	{
		$query = '
				select 
					min(fdo.data) as min_data,
					max(fdo.data) as max_data
				from 
					funcionario_diario_obra as fdo
				inner join
					diario_obra as do
				on
					do.id_diario_obra = fdo.fk_id_diario_obra
				where 
					do.fk_id_obra = :fk_id_obra
				and
					fdo.fk_id_funcionario = :fk_id_funcionario';

		$stmt = $this->db->prepare($query);
		$data = [
			'fk_id_obra' => $idObra,
			'fk_id_funcionario' => $idFuncionario
		];
		$stmt->execute($data);

		$arr = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		if (!empty($arr)) {
			return (object) $arr[0];
		}

		return null;
	}

	public function buscaEmpresaPorNome($nomeFantasia)
	{
		$query = '
				select * 
					from 
				empresa 
					where 
				nome_fantasia = :nome_fantasia';

		$stmt = $this->db->prepare($query);
		$data = [
			'nome_fantasia' => $nomeFantasia
		];
		$stmt->execute($data);

		$arr = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		if (!empty($arr)) {
			return (object) $arr[0];
		}

		return null;
	}

	public function buscaEmpresaPorId($idEmpresa)
	{
		$query = '
				select * 
					from 
				empresa 
					where 
				id_empresa = :id_empresa
				';

		$stmt = $this->db->prepare($query);
		$data = [
			'id_empresa' => $idEmpresa
		];
		$stmt->execute($data);

		$arr = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		if (!empty($arr)) {
			return (object) $arr[0];
		}

		return null;
	}

	public function buscaFuncionarioPorId($idFuncionario)
	{
		$query = '
				select * 
					from 
				funcionario 
					where 
				id_funcionario = :id_funcionario
				';

		$stmt = $this->db->prepare($query);
		$data = [
			'id_funcionario' => $idFuncionario
		];
		$stmt->execute($data);

		$arr = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		if (!empty($arr)) {
			return (object) $arr[0];
		}

		return null;
	}

	public function buscaObraPorNome($descricaoObra)
	{
		$query = '
				select * 
					from 
				obra
					where 
				descricao_resumo = :descricao_resumo';

		$stmt = $this->db->prepare($query);
		$data = [
			'descricao_resumo' => $descricaoObra
		];
		$stmt->execute($data);

		$arr = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		if (!empty($arr)) {
			return (object) $arr[0];
		}

		return null;
	}

	public function calculaTotalAcumuladoHorasObraPorPeriodo($diarioObra)
	{
		$query = '
				select 
					sum(carga_horas_dia) as total_acumulado
				from 
					diario_obra
				where 
					fk_id_obra = :fk_id_obra
				and
					data < :data
				';

		$stmt = $this->db->prepare($query);
		$data = [
			'fk_id_obra' => $diarioObra->fk_id_obra,
			'data' => $diarioObra->data
		];
		$stmt->execute($data);

		$arr = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		if (!empty($arr)) {
			return (object) $arr[0];
		}

		return null;
	}

	public function buscaFuncionariosDoDiarioDeObra($idDiarioObra)
	{
		$query = '
				select 
					*
				from 
					funcionario_diario_obra
				inner join 
					funcionario as f
				on
					f.id_funcionario = fk_id_funcionario
				where 
					fk_id_diario_obra = :id_diario_obra
				';

		$stmt = $this->db->prepare($query);
		$data = [
			'id_diario_obra' => $idDiarioObra
		];
		$stmt->execute($data);

		$arr = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		return $arr;
	}

	public function buscaPorFuncionarioDiarioDeObra(FuncionarioDiarioObra $fdo)
	{
		$query = '
				select 
					*
				from
					funcionario_diario_obra
				where 
					fk_id_funcionario = :fk_id_funcionario
				and
					fk_id_diario_obra = :fk_id_diario_obra';

		$stmt = $this->db->prepare($query);
		$data = [
			'fk_id_funcionario' => $fdo->fk_id_funcionario,
			'fk_id_diario_obra' => $fdo->fk_id_diario_obra
		];
		$stmt->execute($data);

		$arr = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		if (!empty($arr)) {
			return (object) $arr[0];
		}

		return null;
	}

	public function buscaFuncionarioPorNome($funcionarioNome)
	{
		$query = '
				select 
					f.id_funcionario,
					f.nome,
					f.cargo,
					e.id_empresa,
					e.nome_fantasia
				from
					funcionario as f
				inner join 
					empresa as e
				on 
					f.fk_id_empresa = e.id_empresa
				where 
					nome = :nome';

		$stmt = $this->db->prepare($query);
		$data = [
			'nome' => $funcionarioNome
		];
		$stmt->execute($data);

		$arr = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		if (!empty($arr)) {
			return (object) $arr[0];
		}

		return null;
	}

	public function pesquisaListaFuncionariosPorNome($nome)
	{
		$query = "
				select 
					f.id_funcionario,
					f.nome,
					f.cargo,
					e.id_empresa,
					e.nome_fantasia
				from
					funcionario as f
				inner join 
					empresa as e
				on 
					f.fk_id_empresa = e.id_empresa
				where 
					nome like concat('%',:nome, '%')
				order by
					f.nome asc
				limit 150	";

		$stmt = $this->db->prepare($query);
		$data = [
			'nome' => $nome
		];
		$stmt->execute($data);

		$arr = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		return $arr;
	}

	public function buscaTodosDiariosObraDoFuncionario($idFuncionario, $idObra = null)
	{
		if (!$idObra) {
			$query = '
				select 
					do.id_diario_obra,
					do.numero_diario,
					do.fk_id_obra as id_obra,
					do.data,
					obra.fk_id_contratante as id_contratante,
					obra.fk_id_contratada as id_contratada,
					obra.descricao_resumo,
					fdo.data,
					fdo.horario_trabalho,
					fdo.horas_trabalhadas
				from 
					funcionario_diario_obra as fdo
				inner join
					diario_obra as do
				on 
					do.id_diario_obra = fk_id_diario_obra
				inner join 
					obra as obra
				on
					obra.id_obra = do.fk_id_obra
				where 
					fdo.fk_id_funcionario = :fk_id_funcionario
				';

			$data = [
				'fk_id_funcionario' => $idFuncionario
			];
		} else {
			$query = '
				select 
					do.id_diario_obra,
					do.numero_diario,
					do.fk_id_obra as id_obra,
					do.data,
					obra.fk_id_contratante as id_contratante,
					obra.fk_id_contratada as id_contratada,
					obra.descricao_resumo,
					fdo.horario_trabalho,
					fdo.horas_trabalhadas
				from 
					funcionario_diario_obra as fdo
				inner join
					diario_obra as do
				on 
					do.id_diario_obra = fk_id_diario_obra
				inner join 
					obra as obra
				on
					obra.id_obra = do.fk_id_obra
				where 
					fdo.fk_id_funcionario = :fk_id_funcionario
				and
					do.fk_id_obra = :fk_id_obra
				order by
					fdo.data asc
				';

			$data = [
				'fk_id_funcionario' => $idFuncionario,
				'fk_id_obra' => $idObra
			];
		}

		$stmt = $this->db->prepare($query);

		$stmt->execute($data);

		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function funcionarioJaExiste($nomeFuncionario, $idEmpresa)
	{
		$query = '
				select * 
					from 
				funcionario 
					where 
				nome = :nome
					and
				fk_id_empresa = :fk_id_empresa
				';

		$stmt = $this->db->prepare($query);
		$data = [
			'nome' => $nomeFuncionario,
			'fk_id_empresa' => $idEmpresa
		];
		$stmt->execute($data);
		$arr = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		return count($arr) > 0;
	}

	public function buscaUltimaInsercao($nomeTabela, $campoId)
	{
		$sql = "select 
                    *
                from 
                    `$nomeTabela`
                where
                    `$campoId` = (SELECT MAX(`$campoId`) FROM `$nomeTabela`)";

		$stmt = $this->db->query($sql);

		$arr = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		if (count($arr) > 0) {
			return (object) $arr[0];
		}

		return null;
	}

	/******************* UPDATE *******************/

	public function updateDiarioObra($diarioObra)
	{
		$sql = 'UPDATE 
                    diario_obra
                SET
					`numero_diario` = :numero_diario,
					`data` = :data,
					`obs_gerais` = :obs_gerais,
					`horario_trabalho` = :horario_trabalho,
					`carga_horas_dia` = :carga_horas_dia,
					`total_horas` = :total_horas
                WHERE
                    `id_diario_obra` = :id_diario_obra';

		$data = [
			'numero_diario' => $diarioObra->numero_diario,
			'data' => $diarioObra->data,
			'obs_gerais' => $diarioObra->obs_gerais,
			'horario_trabalho' => $diarioObra->horario_trabalho,
			'carga_horas_dia' => $diarioObra->carga_horas_dia,
			'total_horas' => $diarioObra->total_horas,
			'id_diario_obra' => $diarioObra->id_diario_obra
		];

		$stmt = $this->db->prepare($sql);
		$ret = $stmt->execute($data);

		return $ret;
	}

	public function updateFuncionarioDiarioObra($fdo)
	{
		$sql = 'UPDATE 
                    funcionario_diario_obra
                SET
					`data` = :data,
					`horario_trabalho` = :horario_trabalho,
					`horas_trabalhadas` = :horas_trabalhadas
                WHERE
                    `id_funcionario_diario_obra` = :id_funcionario_diario_obra';

		$data = [
			'data' => $fdo->data,
			'horario_trabalho' => $fdo->horario_trabalho,
			'horas_trabalhadas' => $fdo->horas_trabalhadas,
			'id_funcionario_diario_obra' => $fdo->id_funcionario_diario_obra
		];

		$stmt = $this->db->prepare($sql);
		$ret = $stmt->execute($data);

		return $ret;
	}

	/******************* DELETE *******************/

	public function deleteEmpresa($empresa)
	{
		$sql = '
				delete
                    from 
                `empresa`
                    where
				`id_empresa` = :id_empresa;
				';

		$stmt = $this->db->prepare($sql);

		$stmt->bindValue(':id_empresa', $empresa->id_empresa);
		$ret = $stmt->execute();

		return $ret;
	}

	public function deleteObra($obra)
	{
		$sql = '
				delete
                    from 
                `obra`
                    where
				`id_obra` = :id_obra
				';

		$stmt = $this->db->prepare($sql);

		$stmt->bindValue(':id_obra', $obra->id_obra);
		$ret = $stmt->execute();

		return $ret;
	}

	public function deleteAlbum($idDiarioObra)
	{
		$sql = '
				delete
                    from 
                `imagem`
                    where
				`fk_id_diario_obra` = :fk_id_diario_obra
				';

		$stmt = $this->db->prepare($sql);

		$stmt->bindValue(':fk_id_diario_obra', $idDiarioObra);
		$ret = $stmt->execute();

		return $ret;
	}

	public function deleteDiarioObra($diarioObra)
	{
		$sql = '
				delete
                    from 
                `diario_obra`
                    where
				`id_diario_obra` = :id_diario_obra;
				';

		$stmt = $this->db->prepare($sql);

		$stmt->bindValue(':id_diario_obra', $diarioObra->id_diario_obra);
		$ret = $stmt->execute();

		return $ret;
	}

	public function deleteFuncionario($funcionario)
	{
		$sql = '
				delete
                    from 
                `funcionario`
                    where
				`id_funcionario` = :id_funcionario;
				';

		$stmt = $this->db->prepare($sql);

		$stmt->bindValue(':id_funcionario', $funcionario->id_funcionario);
		$ret = $stmt->execute();

		return $ret;
	}

	public function deleteFuncionarioDiarioObra($fdo)
	{
		$sql = '
				delete
                    from 
                `funcionario_diario_obra`
                    where
				`id_funcionario_diario_obra` = :id_funcionario_diario_obra;
				';

		$stmt = $this->db->prepare($sql);

		$stmt->bindValue(':id_funcionario_diario_obra', $fdo->id_funcionario_diario_obra);
		$ret = $stmt->execute();

		return $ret;
	}

	public function deleteTodosServicosDiarioObra($idDiarioObra)
	{
		$sql = '
				delete
                    from 
                `servico`
                    where
				`fk_id_diario_obra` = :fk_id_diario_obra;
				';

		$stmt = $this->db->prepare($sql);

		$stmt->bindValue(':fk_id_diario_obra', $idDiarioObra);
		$ret = $stmt->execute();

		return $ret;
	}

	public function deleteTodosFuncionariosDiarioObra($idDiarioObra)
	{
		$sql = '
				delete
                    from 
                `funcionario_diario_obra`
                    where
				`fk_id_diario_obra` = :fk_id_diario_obra;
				';

		$stmt = $this->db->prepare($sql);

		$stmt->bindValue(':fk_id_diario_obra', $idDiarioObra);
		$ret = $stmt->execute();

		return $ret;
	}
}
