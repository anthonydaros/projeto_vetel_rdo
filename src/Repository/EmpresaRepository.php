<?php
declare(strict_types=1);

namespace Src\Repository;

/**
 * Repository for Empresa (Company) entity
 */
class EmpresaRepository extends BaseRepository
{
    protected string $table = 'empresa';
    protected string $primaryKey = 'id_empresa';
    protected array $fillable = [
        'cnpj',
        'nome_fantasia',
        'contratante_sn',
        'url_logo'
    ];
    protected bool $timestamps = false;
    
    /**
     * Find company by CNPJ
     */
    public function findByCNPJ(string $cnpj): ?array
    {
        // Remove formatting from CNPJ
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        $sql = "SELECT * FROM {$this->table} WHERE REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', '') = :cnpj";
        
        return $this->db->selectOne($sql, ['cnpj' => $cnpj]);
    }
    
    /**
     * Find company by name
     */
    public function findByName(string $name): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE nome_fantasia = :name LIMIT 1";
        
        return $this->db->selectOne($sql, ['name' => $name]);
    }
    
    /**
     * Get all contractor companies
     */
    public function getContractors(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE contratante_sn = 1 
                ORDER BY nome_fantasia ASC";
        
        return $this->db->select($sql);
    }
    
    /**
     * Get all contracted companies
     */
    public function getContracted(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE contratante_sn = 0 
                ORDER BY nome_fantasia ASC";
        
        return $this->db->select($sql);
    }
    
    /**
     * Search companies by name
     */
    public function searchByName(string $search): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE nome_fantasia LIKE :search 
                ORDER BY nome_fantasia ASC";
        
        return $this->db->select($sql, ['search' => "%$search%"]);
    }
    
    /**
     * Check if CNPJ exists (excluding specific ID)
     */
    public function cnpjExists(string $cnpj, ?int $excludeId = null): bool
    {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        $sql = "SELECT COUNT(*) as total FROM {$this->table} 
                WHERE REPLACE(REPLACE(REPLACE(cnpj, '.', ''), '/', ''), '-', '') = :cnpj";
        
        $params = ['cnpj' => $cnpj];
        
        if ($excludeId !== null) {
            $sql .= " AND {$this->primaryKey} != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $result = $this->db->selectOne($sql, $params);
        
        return ($result['total'] ?? 0) > 0;
    }
    
    /**
     * Get companies with project count
     */
    public function getWithProjectCount(): array
    {
        $sql = "SELECT e.*, 
                       COUNT(DISTINCT oc.id_obra) as projects_as_contractor,
                       COUNT(DISTINCT od.id_obra) as projects_as_contracted
                FROM {$this->table} e
                LEFT JOIN obra oc ON e.id_empresa = oc.fk_id_contratante
                LEFT JOIN obra od ON e.id_empresa = od.fk_id_contratada
                GROUP BY e.id_empresa
                ORDER BY e.nome_fantasia ASC";
        
        return $this->db->select($sql);
    }
    
    /**
     * Get company statistics
     */
    public function getStatistics(int $companyId): array
    {
        $sql = "SELECT 
                    e.id_empresa,
                    e.nome_fantasia,
                    e.contratante_sn,
                    COUNT(DISTINCT oc.id_obra) as total_projects_contractor,
                    COUNT(DISTINCT od.id_obra) as total_projects_contracted,
                    COUNT(DISTINCT f.id_funcionario) as total_employees
                FROM {$this->table} e
                LEFT JOIN obra oc ON e.id_empresa = oc.fk_id_contratante
                LEFT JOIN obra od ON e.id_empresa = od.fk_id_contratada
                LEFT JOIN funcionario f ON e.id_empresa = f.fk_id_empresa
                WHERE e.id_empresa = :company_id
                GROUP BY e.id_empresa";
        
        $result = $this->db->selectOne($sql, ['company_id' => $companyId]);
        
        if (!$result) {
            return [];
        }
        
        $result['total_projects'] = $result['total_projects_contractor'] + $result['total_projects_contracted'];
        
        return $result;
    }
    
    /**
     * Update company logo
     */
    public function updateLogo(int $companyId, string $logoUrl): bool
    {
        return $this->update($companyId, ['url_logo' => $logoUrl]);
    }
    
    /**
     * Get companies paginated
     */
    public function getPaginated(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        $conditions = [];
        
        if (isset($filters['type'])) {
            if ($filters['type'] === 'contractor') {
                $conditions[] = "contratante_sn = 1";
            } elseif ($filters['type'] === 'contracted') {
                $conditions[] = "contratante_sn = 0";
            }
        }
        
        if (!empty($filters['search'])) {
            $conditions[] = "(nome_fantasia LIKE :search OR cnpj LIKE :search)";
            $params['search'] = "%{$filters['search']}%";
        }
        
        $whereClause = !empty($conditions) 
            ? "WHERE " . implode(' AND ', $conditions)
            : "";
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} $whereClause";
        $countResult = $this->db->selectOne($countSql, $params);
        $total = (int) ($countResult['total'] ?? 0);
        
        // Get paginated results
        $sql = "SELECT * FROM {$this->table} 
                $whereClause
                ORDER BY nome_fantasia ASC
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