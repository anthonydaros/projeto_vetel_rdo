<?php
declare(strict_types=1);

namespace Src\Repository;

/**
 * Repository for Funcionario (Employee) entity
 */
class FuncionarioRepository extends BaseRepository
{
    protected string $table = 'funcionario';
    protected string $primaryKey = 'id_funcionario';
    protected array $fillable = [
        'fk_id_empresa',
        'nome',
        'cargo'
    ];
    protected bool $timestamps = false;
    
    /**
     * Find employees by company
     */
    public function findByCompany(int $companyId): array
    {
        $sql = "SELECT f.*, e.nome_fantasia as empresa_nome
                FROM {$this->table} f
                LEFT JOIN empresa e ON f.fk_id_empresa = e.id_empresa
                WHERE f.fk_id_empresa = :company_id
                ORDER BY f.nome ASC";
        
        return $this->db->select($sql, ['company_id' => $companyId]);
    }
    
    /**
     * Find employee with company data
     */
    public function findWithCompany(int $id): ?array
    {
        $sql = "SELECT f.*, e.nome_fantasia as empresa_nome, e.cnpj as empresa_cnpj
                FROM {$this->table} f
                LEFT JOIN empresa e ON f.fk_id_empresa = e.id_empresa
                WHERE f.id_funcionario = :id";
        
        return $this->db->selectOne($sql, ['id' => $id]);
    }
    
    /**
     * Search employees by name
     */
    public function searchByName(string $search): array
    {
        $sql = "SELECT f.*, e.nome_fantasia as empresa_nome
                FROM {$this->table} f
                LEFT JOIN empresa e ON f.fk_id_empresa = e.id_empresa
                WHERE f.nome LIKE :search
                ORDER BY f.nome ASC";
        
        return $this->db->select($sql, ['search' => "%$search%"]);
    }
    
    /**
     * Find employees by position/role
     */
    public function findByPosition(string $position): array
    {
        $sql = "SELECT f.*, e.nome_fantasia as empresa_nome
                FROM {$this->table} f
                LEFT JOIN empresa e ON f.fk_id_empresa = e.id_empresa
                WHERE f.cargo LIKE :position
                ORDER BY f.nome ASC";
        
        return $this->db->select($sql, ['position' => "%$position%"]);
    }
    
    /**
     * Get employee work history
     */
    public function getWorkHistory(int $employeeId, int $limit = 30): array
    {
        $sql = "SELECT 
                    fdo.*,
                    do.data,
                    do.horario_trabalho,
                    do.fk_id_obra,
                    o.descricao_resumo as obra_descricao
                FROM funcionario_diario_obra fdo
                INNER JOIN diario_obra do ON fdo.fk_id_diario_obra = do.id_diario_obra
                INNER JOIN obra o ON do.fk_id_obra = o.id_obra
                WHERE fdo.fk_id_funcionario = :employee_id
                ORDER BY do.data DESC
                LIMIT :limit";
        
        return $this->db->select($sql, [
            'employee_id' => $employeeId,
            'limit' => $limit
        ]);
    }
    
    /**
     * Get employee statistics
     */
    public function getStatistics(int $employeeId): array
    {
        $sql = "SELECT 
                    f.id_funcionario,
                    f.nome,
                    f.cargo,
                    COUNT(DISTINCT fdo.fk_id_diario_obra) as total_days_worked,
                    COUNT(DISTINCT do.fk_id_obra) as total_projects,
                    SUM(fdo.horas_trabalhadas) as total_hours,
                    AVG(fdo.horas_trabalhadas) as avg_hours_per_day,
                    MIN(do.data) as first_work_day,
                    MAX(do.data) as last_work_day
                FROM {$this->table} f
                LEFT JOIN funcionario_diario_obra fdo ON f.id_funcionario = fdo.fk_id_funcionario
                LEFT JOIN diario_obra do ON fdo.fk_id_diario_obra = do.id_diario_obra
                WHERE f.id_funcionario = :employee_id
                GROUP BY f.id_funcionario";
        
        $result = $this->db->selectOne($sql, ['employee_id' => $employeeId]);
        
        if (!$result) {
            return [];
        }
        
        // Calculate additional metrics
        if ($result['first_work_day'] && $result['last_work_day']) {
            $firstDate = new \DateTime($result['first_work_day']);
            $lastDate = new \DateTime($result['last_work_day']);
            $interval = $firstDate->diff($lastDate);
            $result['total_period_days'] = $interval->days + 1;
            $result['attendance_rate'] = round(
                ($result['total_days_worked'] / $result['total_period_days']) * 100,
                2
            );
        }
        
        return $result;
    }
    
    /**
     * Get employees by project
     */
    public function getByProject(int $projectId): array
    {
        $sql = "SELECT DISTINCT f.*, e.nome_fantasia as empresa_nome
                FROM {$this->table} f
                INNER JOIN funcionario_diario_obra fdo ON f.id_funcionario = fdo.fk_id_funcionario
                INNER JOIN diario_obra do ON fdo.fk_id_diario_obra = do.id_diario_obra
                LEFT JOIN empresa e ON f.fk_id_empresa = e.id_empresa
                WHERE do.fk_id_obra = :project_id
                ORDER BY f.nome ASC";
        
        return $this->db->select($sql, ['project_id' => $projectId]);
    }
    
    /**
     * Check if employee has work records
     */
    public function hasWorkRecords(int $employeeId): bool
    {
        $sql = "SELECT COUNT(*) as total 
                FROM funcionario_diario_obra 
                WHERE fk_id_funcionario = :employee_id";
        
        $result = $this->db->selectOne($sql, ['employee_id' => $employeeId]);
        
        return ($result['total'] ?? 0) > 0;
    }
    
    /**
     * Get all unique positions/roles
     */
    public function getAllPositions(): array
    {
        $sql = "SELECT DISTINCT cargo 
                FROM {$this->table} 
                WHERE cargo IS NOT NULL AND cargo != ''
                ORDER BY cargo ASC";
        
        $results = $this->db->select($sql);
        
        return array_column($results, 'cargo');
    }
    
    /**
     * Get employees paginated with filters
     */
    public function getPaginated(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        $conditions = [];
        
        if (!empty($filters['company_id'])) {
            $conditions[] = "f.fk_id_empresa = :company_id";
            $params['company_id'] = $filters['company_id'];
        }
        
        if (!empty($filters['position'])) {
            $conditions[] = "f.cargo LIKE :position";
            $params['position'] = "%{$filters['position']}%";
        }
        
        if (!empty($filters['search'])) {
            $conditions[] = "f.nome LIKE :search";
            $params['search'] = "%{$filters['search']}%";
        }
        
        $whereClause = !empty($conditions) 
            ? "WHERE " . implode(' AND ', $conditions)
            : "";
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} f $whereClause";
        $countResult = $this->db->selectOne($countSql, $params);
        $total = (int) ($countResult['total'] ?? 0);
        
        // Get paginated results
        $sql = "SELECT f.*, e.nome_fantasia as empresa_nome
                FROM {$this->table} f
                LEFT JOIN empresa e ON f.fk_id_empresa = e.id_empresa
                $whereClause
                ORDER BY f.nome ASC
                LIMIT :limit OFFSET :offset";
        
        $params['limit'] = $perPage;
        $params['offset'] = $offset;
        
        $items = $this->db->select($sql, $params);
        
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
}