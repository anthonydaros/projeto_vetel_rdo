<?php
declare(strict_types=1);

namespace Src\Repository;

/**
 * Repository for Obra (Project) entity
 */
class ObraRepository extends BaseRepository
{
    protected string $table = 'obra';
    protected string $primaryKey = 'id_obra';
    protected array $fillable = [
        'fk_id_contratante',
        'fk_id_contratada',
        'descricao_resumo'
    ];
    protected bool $timestamps = false;
    
    /**
     * Find projects by contractor
     */
    public function findByContratante(int $contratanteId): array
    {
        $sql = "SELECT o.*, 
                       ec.nome_fantasia as contratante_nome,
                       ed.nome_fantasia as contratada_nome
                FROM {$this->table} o
                LEFT JOIN empresa ec ON o.fk_id_contratante = ec.id_empresa
                LEFT JOIN empresa ed ON o.fk_id_contratada = ed.id_empresa
                WHERE o.fk_id_contratante = :contratante_id
                ORDER BY o.id_obra DESC";
        
        return $this->db->select($sql, ['contratante_id' => $contratanteId]);
    }
    
    /**
     * Find projects by contracted company
     */
    public function findByContratada(int $contratadaId): array
    {
        $sql = "SELECT o.*, 
                       ec.nome_fantasia as contratante_nome,
                       ed.nome_fantasia as contratada_nome
                FROM {$this->table} o
                LEFT JOIN empresa ec ON o.fk_id_contratante = ec.id_empresa
                LEFT JOIN empresa ed ON o.fk_id_contratada = ed.id_empresa
                WHERE o.fk_id_contratada = :contratada_id
                ORDER BY o.id_obra DESC";
        
        return $this->db->select($sql, ['contratada_id' => $contratadaId]);
    }
    
    /**
     * Find project with all related data
     */
    public function findWithRelations(int $id): ?array
    {
        $sql = "SELECT o.*, 
                       ec.nome_fantasia as contratante_nome,
                       ec.cnpj as contratante_cnpj,
                       ec.url_logo as contratante_logo,
                       ed.nome_fantasia as contratada_nome,
                       ed.cnpj as contratada_cnpj,
                       ed.url_logo as contratada_logo
                FROM {$this->table} o
                LEFT JOIN empresa ec ON o.fk_id_contratante = ec.id_empresa
                LEFT JOIN empresa ed ON o.fk_id_contratada = ed.id_empresa
                WHERE o.id_obra = :id";
        
        return $this->db->selectOne($sql, ['id' => $id]);
    }
    
    /**
     * Get active projects (with recent activities)
     */
    public function getActiveProjects(int $daysBack = 30): array
    {
        $sql = "SELECT DISTINCT o.*, 
                       ec.nome_fantasia as contratante_nome,
                       ed.nome_fantasia as contratada_nome,
                       COUNT(DISTINCT do.id_diario_obra) as total_diarios,
                       MAX(do.data) as ultima_atividade
                FROM {$this->table} o
                LEFT JOIN empresa ec ON o.fk_id_contratante = ec.id_empresa
                LEFT JOIN empresa ed ON o.fk_id_contratada = ed.id_empresa
                LEFT JOIN diario_obra do ON o.id_obra = do.fk_id_obra
                WHERE do.data >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                GROUP BY o.id_obra
                ORDER BY ultima_atividade DESC";
        
        return $this->db->select($sql, ['days' => $daysBack]);
    }
    
    /**
     * Get project statistics
     */
    public function getProjectStatistics(int $projectId): array
    {
        $sql = "SELECT 
                    o.id_obra,
                    o.descricao_resumo,
                    COUNT(DISTINCT do.id_diario_obra) as total_diarios,
                    COUNT(DISTINCT fdo.fk_id_funcionario) as total_funcionarios,
                    MIN(do.data) as data_inicio,
                    MAX(do.data) as data_fim,
                    SUM(fdo.horas_trabalhadas) as total_horas
                FROM {$this->table} o
                LEFT JOIN diario_obra do ON o.id_obra = do.fk_id_obra
                LEFT JOIN funcionario_diario_obra fdo ON do.id_diario_obra = fdo.fk_id_diario_obra
                WHERE o.id_obra = :project_id
                GROUP BY o.id_obra";
        
        $result = $this->db->selectOne($sql, ['project_id' => $projectId]);
        
        if (!$result) {
            return [];
        }
        
        // Calculate additional metrics
        $result['dias_trabalhados'] = $result['total_diarios'] ?? 0;
        $result['media_horas_dia'] = $result['total_diarios'] > 0 
            ? round($result['total_horas'] / $result['total_diarios'], 2)
            : 0;
        
        return $result;
    }
    
    /**
     * Search projects by description
     */
    public function searchByDescription(string $search): array
    {
        $sql = "SELECT o.*, 
                       ec.nome_fantasia as contratante_nome,
                       ed.nome_fantasia as contratada_nome
                FROM {$this->table} o
                LEFT JOIN empresa ec ON o.fk_id_contratante = ec.id_empresa
                LEFT JOIN empresa ed ON o.fk_id_contratada = ed.id_empresa
                WHERE o.descricao_resumo LIKE :search
                ORDER BY o.id_obra DESC";
        
        return $this->db->select($sql, ['search' => "%$search%"]);
    }
    
    /**
     * Get projects with pagination and filters
     */
    public function getPaginated(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $params = [];
        $conditions = [];
        
        // Build WHERE conditions
        if (!empty($filters['contratante_id'])) {
            $conditions[] = "o.fk_id_contratante = :contratante_id";
            $params['contratante_id'] = $filters['contratante_id'];
        }
        
        if (!empty($filters['contratada_id'])) {
            $conditions[] = "o.fk_id_contratada = :contratada_id";
            $params['contratada_id'] = $filters['contratada_id'];
        }
        
        if (!empty($filters['search'])) {
            $conditions[] = "o.descricao_resumo LIKE :search";
            $params['search'] = "%{$filters['search']}%";
        }
        
        $whereClause = !empty($conditions) 
            ? "WHERE " . implode(' AND ', $conditions)
            : "";
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} o $whereClause";
        $countResult = $this->db->selectOne($countSql, $params);
        $total = (int) ($countResult['total'] ?? 0);
        
        // Get paginated results
        $sql = "SELECT o.*, 
                       ec.nome_fantasia as contratante_nome,
                       ed.nome_fantasia as contratada_nome
                FROM {$this->table} o
                LEFT JOIN empresa ec ON o.fk_id_contratante = ec.id_empresa
                LEFT JOIN empresa ed ON o.fk_id_contratada = ed.id_empresa
                $whereClause
                ORDER BY o.id_obra DESC
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