<?php

declare(strict_types=1);

namespace Src\Repository;

use Src\Database\DatabaseManager;

/**
 * Repository para gerenciamento de imagens do album
 */
class ImageRepository extends BaseRepository
{
	protected string $table = 'album';
	protected string $primaryKey = 'id_album';

	public function __construct(DatabaseManager $db)
	{
		parent::__construct($db);
	}

	/**
	 * Encontra imagens por ID do diário de obra
	 */
	public function findByDiario(int $diarioId): array
	{
		return $this->db->select(
			"SELECT * FROM {$this->table} WHERE fk_id_diario_obra = :diario_id ORDER BY id_album",
			['diario_id' => $diarioId]
		);
	}

	/**
	 * Conta quantas imagens existem para um diário específico
	 */
	public function countImagesByDiario(int $diarioId): int
	{
		$result = $this->db->selectOne(
			"SELECT COUNT(*) as count FROM {$this->table} WHERE fk_id_diario_obra = :diario_id",
			['diario_id' => $diarioId]
		);

		return (int) ($result['count'] ?? 0);
	}

	/**
	 * Cria novo registro de imagem
	 */
	public function create(array $data): array
	{
		$imageData = [
			'fk_id_diario_obra' => $data['fk_id_diario_obra'],
			'url' => $this->generateImagePath($data['filename']),
			'filename' => $data['filename'] ?? null,
			'original_name' => $data['original_name'] ?? null,
			'file_size' => $data['file_size'] ?? null,
			'width' => $data['width'] ?? null,
			'height' => $data['height'] ?? null,
			'mime_type' => $data['mime_type'] ?? null,
			'uploaded_at' => $data['uploaded_at'] ?? date('Y-m-d H:i:s')
		];

		$id = $this->db->insert($this->table, $imageData);

		return array_merge(['id' => $id], $imageData);
	}

	/**
	 * Atualiza registro de imagem
	 */
	public function update(int $id, array $data): bool
	{
		$allowedFields = [
			'filename', 'original_name', 'file_size', 'width',
			'height', 'mime_type', 'updated_at'
		];

		$updateData = array_intersect_key($data, array_flip($allowedFields));

		if (empty($updateData)) {
			return false;
		}

		return $this->db->update($this->table, $updateData, [$this->primaryKey => $id]) > 0;
	}

	/**
	 * Remove imagem por ID
	 */
	public function delete(int $id): bool
	{
		return $this->db->delete($this->table, [$this->primaryKey => $id]) > 0;
	}

	/**
	 * Remove todas as imagens de um diário
	 */
	public function deleteByDiario(int $diarioId): int
	{
		return $this->db->delete($this->table, ['fk_id_diario_obra' => $diarioId]);
	}

	/**
	 * Encontra imagem por ID
	 */
	public function findById(int $id): ?array
	{
		$result = $this->db->selectOne(
			"SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id",
			['id' => $id]
		);

		return $result ?: null;
	}

	/**
	 * Encontra imagem por filename
	 */
	public function findByFilename(string $filename): ?array
	{
		$result = $this->db->selectOne(
			"SELECT * FROM {$this->table} WHERE filename = :filename",
			['filename' => $filename]
		);

		return $result ?: null;
	}

	/**
	 * Obtém todas as imagens com paginação
	 */
	public function getPaginated(array $filters = [], int $page = 1, int $perPage = 20): array
	{
		$where = [];
		$params = [];

		if (!empty($filters['diario_id'])) {
			$where[] = 'fk_id_diario_obra = :diario_id';
			$params['diario_id'] = $filters['diario_id'];
		}

		if (!empty($filters['date_from'])) {
			$where[] = 'uploaded_at >= :date_from';
			$params['date_from'] = $filters['date_from'];
		}

		if (!empty($filters['date_to'])) {
			$where[] = 'uploaded_at <= :date_to';
			$params['date_to'] = $filters['date_to'];
		}

		if (!empty($filters['mime_type'])) {
			$where[] = 'mime_type = :mime_type';
			$params['mime_type'] = $filters['mime_type'];
		}

		$whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);
		$offset = ($page - 1) * $perPage;

		// Query principal
		$sql = "SELECT * FROM {$this->table} 
                {$whereClause} 
                ORDER BY uploaded_at DESC 
                LIMIT :limit OFFSET :offset";

		$params['limit'] = $perPage;
		$params['offset'] = $offset;

		$items = $this->db->select($sql, $params);

		// Count total
		$countSql = "SELECT COUNT(*) as total FROM {$this->table} {$whereClause}";
		$countParams = array_diff_key($params, ['limit' => '', 'offset' => '']);
		$total = $this->db->selectOne($countSql, $countParams)['total'];

		return [
			'items' => $items,
			'total' => (int) $total,
			'page' => $page,
			'per_page' => $perPage,
			'total_pages' => ceil($total / $perPage)
		];
	}

	/**
	 * Obtém estatísticas gerais
	 */
	public function getTotalCount(int $diarioId = null): int
	{
		if ($diarioId) {
			return $this->countImagesByDiario($diarioId);
		}

		$result = $this->db->selectOne("SELECT COUNT(*) as count FROM {$this->table}");
		return (int) ($result['count'] ?? 0);
	}

	/**
	 * Obtém tamanho total dos arquivos
	 */
	public function getTotalSize(int $diarioId = null): int
	{
		$where = $diarioId ? 'WHERE fk_id_diario_obra = :diario_id' : '';
		$params = $diarioId ? ['diario_id' => $diarioId] : [];

		$result = $this->db->selectOne(
			"SELECT SUM(file_size) as total_size FROM {$this->table} {$where}",
			$params
		);

		return (int) ($result['total_size'] ?? 0);
	}

	/**
	 * Obtém contagem de imagens por diário
	 */
	public function getCountByDiario(): array
	{
		return $this->db->select(
			"SELECT fk_id_diario_obra, COUNT(*) as count, SUM(file_size) as total_size 
             FROM {$this->table} 
             GROUP BY fk_id_diario_obra 
             ORDER BY count DESC"
		);
	}

	/**
	 * Obtém uploads recentes
	 */
	public function getRecentUploads(int $limit = 10): array
	{
		return $this->db->select(
			"SELECT a.*, d.data as diario_data, d.numero_diario 
             FROM {$this->table} a 
             LEFT JOIN diario_obra d ON a.fk_id_diario_obra = d.id_diario_obra 
             ORDER BY a.uploaded_at DESC 
             LIMIT :limit",
			['limit' => $limit]
		);
	}

	/**
	 * Encontra imagens órfãs (sem diário correspondente)
	 */
	public function findOrphanImages(): array
	{
		return $this->db->select(
			"SELECT a.* FROM {$this->table} a 
             LEFT JOIN diario_obra d ON a.fk_id_diario_obra = d.id_diario_obra 
             WHERE d.id_diario_obra IS NULL"
		);
	}

	/**
	 * Encontra imagens duplicadas por filename
	 */
	public function findDuplicateImages(): array
	{
		return $this->db->select(
			"SELECT filename, COUNT(*) as count 
             FROM {$this->table} 
             WHERE filename IS NOT NULL 
             GROUP BY filename 
             HAVING count > 1"
		);
	}

	/**
	 * Busca imagens por padrão de nome
	 */
	public function searchByFilename(string $pattern): array
	{
		return $this->db->select(
			"SELECT * FROM {$this->table} 
             WHERE filename LIKE :pattern 
             ORDER BY uploaded_at DESC",
			['pattern' => "%{$pattern}%"]
		);
	}

	/**
	 * Obtém imagens grandes (acima de um tamanho específico)
	 */
	public function getLargeImages(int $sizeThresholdMB = 2): array
	{
		$sizeBytes = $sizeThresholdMB * 1024 * 1024;

		return $this->db->select(
			"SELECT * FROM {$this->table} 
             WHERE file_size > :size_threshold 
             ORDER BY file_size DESC",
			['size_threshold' => $sizeBytes]
		);
	}

	/**
	 * Obtém estatísticas por tipo MIME
	 */
	public function getStatsByMimeType(): array
	{
		return $this->db->select(
			"SELECT mime_type, 
                    COUNT(*) as count, 
                    SUM(file_size) as total_size,
                    AVG(file_size) as avg_size
             FROM {$this->table} 
             WHERE mime_type IS NOT NULL 
             GROUP BY mime_type 
             ORDER BY count DESC"
		);
	}

	/**
	 * Gera caminho da imagem baseado no filename
	 */
	private function generateImagePath(string $filename): string
	{
		return '/img/album/original/' . $filename;
	}

	/**
	 * Backup dos metadados de imagens para migração
	 */
	public function exportMetadata(): array
	{
		return $this->db->select(
			"SELECT 
                fk_id_diario_obra,
                filename,
                original_name,
                file_size,
                width,
                height,
                mime_type,
                uploaded_at,
                url
             FROM {$this->table} 
             ORDER BY fk_id_diario_obra, uploaded_at"
		);
	}
}
