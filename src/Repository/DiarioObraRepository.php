<?php

declare(strict_types=1);

namespace Src\Repository;

/**
 * Repository for DiarioObra (Work Diary) entity
 */
class DiarioObraRepository extends BaseRepository
{
	protected string $table = 'diario_obra';
	protected string $primaryKey = 'id_diario_obra';
	protected array $fillable = [
		'fk_id_obra',
		'data',
		'horario_trabalho',
		'obs_geral',
		'descricao_resumo'
	];
	protected bool $timestamps = false;

	/**
	 * Find diaries by project
	 */
	public function findByProject(int $projectId): array
	{
		$sql = "SELECT do.*, 
                       o.descricao_resumo as obra_descricao,
                       COUNT(DISTINCT fdo.fk_id_funcionario) as total_funcionarios,
                       SUM(fdo.horas_trabalhadas) as total_horas
                FROM {$this->table} do
                LEFT JOIN obra o ON do.fk_id_obra = o.id_obra
                LEFT JOIN funcionario_diario_obra fdo ON do.id_diario_obra = fdo.fk_id_diario_obra
                WHERE do.fk_id_obra = :project_id
                GROUP BY do.id_diario_obra
                ORDER BY do.data DESC";

		return $this->db->select($sql, ['project_id' => $projectId]);
	}

	/**
	 * Find diary by date and project
	 */
	public function findByDateAndProject(string $date, int $projectId): ?array
	{
		$sql = "SELECT * FROM {$this->table} 
                WHERE data = :date AND fk_id_obra = :project_id
                LIMIT 1";

		return $this->db->selectOne($sql, [
			'date' => $date,
			'project_id' => $projectId
		]);
	}

	/**
	 * Get recent diaries by project
	 */
	public function getRecentByProject(int $projectId, int $limit = 10): array
	{
		$sql = "SELECT do.*, 
                       COUNT(DISTINCT fdo.fk_id_funcionario) as total_funcionarios,
                       SUM(fdo.horas_trabalhadas) as total_horas
                FROM {$this->table} do
                LEFT JOIN funcionario_diario_obra fdo ON do.id_diario_obra = fdo.fk_id_diario_obra
                WHERE do.fk_id_obra = :project_id
                GROUP BY do.id_diario_obra
                ORDER BY do.data DESC
                LIMIT :limit";

		return $this->db->select($sql, [
			'project_id' => $projectId,
			'limit' => $limit
		]);
	}

	/**
	 * Get diary with all related data
	 */
	public function getWithFullDetails(int $diaryId): ?array
	{
		$diary = $this->find($diaryId);

		if (!$diary) {
			return null;
		}

		// Get employees
		$diary['funcionarios'] = $this->getEmployees($diaryId);

		// Get services
		$diary['servicos'] = $this->getServices($diaryId);

		// Get photos
		$diary['fotos'] = $this->getPhotos($diaryId);

		// Get project info
		$sql = 'SELECT o.*, 
                       ec.nome_fantasia as contratante_nome,
                       ec.url_logo as contratante_logo,
                       ed.nome_fantasia as contratada_nome,
                       ed.url_logo as contratada_logo
                FROM obra o
                LEFT JOIN empresa ec ON o.fk_id_contratante = ec.id_empresa
                LEFT JOIN empresa ed ON o.fk_id_contratada = ed.id_empresa
                WHERE o.id_obra = :project_id';

		$diary['obra'] = $this->db->selectOne($sql, ['project_id' => $diary['fk_id_obra']]);

		return $diary;
	}

	/**
	 * Get employees for a diary
	 */
	public function getEmployees(int $diaryId): array
	{
		$sql = 'SELECT f.*, 
                       fdo.horas_trabalhadas,
                       fdo.hora_entrada,
                       fdo.hora_saida,
                       e.nome_fantasia as empresa_nome
                FROM funcionario_diario_obra fdo
                INNER JOIN funcionario f ON fdo.fk_id_funcionario = f.id_funcionario
                LEFT JOIN empresa e ON f.fk_id_empresa = e.id_empresa
                WHERE fdo.fk_id_diario_obra = :diary_id
                ORDER BY f.nome ASC';

		return $this->db->select($sql, ['diary_id' => $diaryId]);
	}

	/**
	 * Add employee to diary
	 */
	public function addEmployee(int $diaryId, array $employeeData): int
	{
		$data = [
			'fk_id_diario_obra' => $diaryId,
			'fk_id_funcionario' => $employeeData['fk_id_funcionario'],
			'horas_trabalhadas' => $employeeData['horas_trabalhadas'] ?? 8,
			'hora_entrada' => $employeeData['hora_entrada'] ?? '08:00',
			'hora_saida' => $employeeData['hora_saida'] ?? '17:00'
		];

		return $this->db->insert('funcionario_diario_obra', $data);
	}

	/**
	 * Remove employee from diary
	 */
	public function removeEmployee(int $diaryId, int $employeeId): bool
	{
		$affected = $this->db->delete('funcionario_diario_obra', [
			'fk_id_diario_obra' => $diaryId,
			'fk_id_funcionario' => $employeeId
		]);

		return $affected > 0;
	}

	/**
	 * Delete all employee records for a diary
	 */
	public function deleteEmployeeRecords(int $diaryId): int
	{
		return $this->db->delete('funcionario_diario_obra', [
			'fk_id_diario_obra' => $diaryId
		]);
	}

	/**
	 * Get services for a diary
	 */
	public function getServices(int $diaryId): array
	{
		$sql = 'SELECT * FROM servico 
                WHERE fk_id_diario_obra = :diary_id
                ORDER BY id_servico ASC';

		return $this->db->select($sql, ['diary_id' => $diaryId]);
	}

	/**
	 * Add service to diary
	 */
	public function addService(int $diaryId, string $description): int
	{
		return $this->db->insert('servico', [
			'fk_id_diario_obra' => $diaryId,
			'descricao' => $description
		]);
	}

	/**
	 * Delete service from diary
	 */
	public function deleteService(int $serviceId): bool
	{
		$affected = $this->db->delete('servico', [
			'id_servico' => $serviceId
		]);

		return $affected > 0;
	}

	/**
	 * Get photos for a diary
	 */
	public function getPhotos(int $diaryId): array
	{
		$sql = 'SELECT * FROM imagem 
                WHERE fk_id_diario_obra = :diary_id
                ORDER BY id_imagem ASC';

		return $this->db->select($sql, ['diary_id' => $diaryId]);
	}

	/**
	 * Add photo to diary
	 */
	public function addPhoto(int $diaryId, string $url): int
	{
		return $this->db->insert('imagem', [
			'fk_id_diario_obra' => $diaryId,
			'url' => $url
		]);
	}

	/**
	 * Delete photo from diary
	 */
	public function deletePhoto(int $photoId): bool
	{
		$affected = $this->db->delete('imagem', [
			'id_imagem' => $photoId
		]);

		return $affected > 0;
	}

	/**
	 * Delete all photos for a diary
	 */
	public function deletePhotos(int $diaryId): int
	{
		return $this->db->delete('imagem', [
			'fk_id_diario_obra' => $diaryId
		]);
	}

	/**
	 * Get diary statistics for a date range
	 */
	public function getStatisticsByDateRange(int $projectId, string $startDate, string $endDate): array
	{
		$sql = "SELECT 
                    COUNT(DISTINCT do.id_diario_obra) as total_days,
                    COUNT(DISTINCT fdo.fk_id_funcionario) as total_unique_employees,
                    SUM(fdo.horas_trabalhadas) as total_hours,
                    AVG(fdo.horas_trabalhadas) as avg_hours_per_employee,
                    MIN(do.data) as first_day,
                    MAX(do.data) as last_day
                FROM {$this->table} do
                LEFT JOIN funcionario_diario_obra fdo ON do.id_diario_obra = fdo.fk_id_diario_obra
                WHERE do.fk_id_obra = :project_id 
                    AND do.data >= :start_date 
                    AND do.data <= :end_date";

		$result = $this->db->selectOne($sql, [
			'project_id' => $projectId,
			'start_date' => $startDate,
			'end_date' => $endDate
		]);

		return $result ?: [];
	}

	/**
	 * Get monthly summary for a project
	 */
	public function getMonthlySummary(int $projectId, int $year, int $month): array
	{
		$sql = "SELECT 
                    DAY(do.data) as day,
                    do.data,
                    COUNT(DISTINCT fdo.fk_id_funcionario) as employees,
                    SUM(fdo.horas_trabalhadas) as hours
                FROM {$this->table} do
                LEFT JOIN funcionario_diario_obra fdo ON do.id_diario_obra = fdo.fk_id_diario_obra
                WHERE do.fk_id_obra = :project_id 
                    AND YEAR(do.data) = :year 
                    AND MONTH(do.data) = :month
                GROUP BY do.data
                ORDER BY do.data ASC";

		return $this->db->select($sql, [
			'project_id' => $projectId,
			'year' => $year,
			'month' => $month
		]);
	}

	/**
	 * Check if diary has dependencies
	 */
	public function hasDependencies(int $diaryId): bool
	{
		// Check for employees
		$sql = 'SELECT COUNT(*) as total FROM funcionario_diario_obra WHERE fk_id_diario_obra = :diary_id';
		$result = $this->db->selectOne($sql, ['diary_id' => $diaryId]);
		if (($result['total'] ?? 0) > 0) {
			return true;
		}

		// Check for services
		$sql = 'SELECT COUNT(*) as total FROM servico WHERE fk_id_diario_obra = :diary_id';
		$result = $this->db->selectOne($sql, ['diary_id' => $diaryId]);
		if (($result['total'] ?? 0) > 0) {
			return true;
		}

		// Check for photos
		$sql = 'SELECT COUNT(*) as total FROM imagem WHERE fk_id_diario_obra = :diary_id';
		$result = $this->db->selectOne($sql, ['diary_id' => $diaryId]);

		return ($result['total'] ?? 0) > 0;
	}
}
