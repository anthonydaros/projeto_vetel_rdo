<?php

declare(strict_types=1);

namespace Src\Repository;

use Src\Database\DatabaseManager;
use Src\Exception\RepositoryException;

/**
 * Base repository with common CRUD operations
 */
abstract class BaseRepository
{
	protected DatabaseManager $db;
	protected string $table;
	protected string $primaryKey = 'id';
	protected array $fillable = [];
	protected bool $timestamps = true;
	protected ?string $softDelete = null;

	public function __construct(DatabaseManager $db)
	{
		$this->db = $db;
	}

	/**
	 * Find record by ID
	 */
	public function find(int $id): ?array
	{
		$sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";

		if ($this->softDelete) {
			$sql .= " AND {$this->softDelete} IS NULL";
		}

		return $this->db->selectOne($sql, ['id' => $id]);
	}

	/**
	 * Find all records with pagination
	 */
	public function findAll(int $limit = 100, int $offset = 0, array $orderBy = []): array
	{
		$sql = "SELECT * FROM {$this->table}";

		if ($this->softDelete) {
			$sql .= " WHERE {$this->softDelete} IS NULL";
		}

		if (!empty($orderBy)) {
			$orderClauses = [];
			foreach ($orderBy as $column => $direction) {
				$direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
				$orderClauses[] = "`$column` $direction";
			}
			$sql .= ' ORDER BY ' . implode(', ', $orderClauses);
		} else {
			$sql .= " ORDER BY {$this->primaryKey} DESC";
		}

		$sql .= ' LIMIT :limit OFFSET :offset';

		return $this->db->select($sql, [
			'limit' => $limit,
			'offset' => $offset
		]);
	}

	/**
	 * Count total records
	 */
	public function count(array $where = []): int
	{
		$sql = "SELECT COUNT(*) as total FROM {$this->table}";
		$params = [];

		if ($this->softDelete) {
			$where[$this->softDelete] = null;
		}

		if (!empty($where)) {
			$conditions = $this->buildWhereConditions($where, $params);
			$sql .= ' WHERE ' . implode(' AND ', $conditions);
		}

		$result = $this->db->selectOne($sql, $params);
		return (int) ($result['total'] ?? 0);
	}

	/**
	 * Find records by conditions
	 */
	public function findWhere(array $where, int $limit = 100, int $offset = 0): array
	{
		$sql = "SELECT * FROM {$this->table}";
		$params = [];

		if ($this->softDelete) {
			$where[$this->softDelete] = null;
		}

		if (!empty($where)) {
			$conditions = $this->buildWhereConditions($where, $params);
			$sql .= ' WHERE ' . implode(' AND ', $conditions);
		}

		$sql .= " ORDER BY {$this->primaryKey} DESC";
		$sql .= ' LIMIT :limit OFFSET :offset';

		$params['limit'] = $limit;
		$params['offset'] = $offset;

		return $this->db->select($sql, $params);
	}

	/**
	 * Find single record by conditions
	 */
	public function findOneWhere(array $where): ?array
	{
		$results = $this->findWhere($where, 1, 0);
		return $results[0] ?? null;
	}

	/**
	 * Create new record
	 */
	public function create(array $data): int
	{
		$data = $this->filterFillable($data);

		if ($this->timestamps) {
			$data['created_at'] = date('Y-m-d H:i:s');
			$data['updated_at'] = date('Y-m-d H:i:s');
		}

		return $this->db->insert($this->table, $data);
	}

	/**
	 * Update record by ID
	 */
	public function update(int $id, array $data): bool
	{
		$data = $this->filterFillable($data);

		if ($this->timestamps) {
			$data['updated_at'] = date('Y-m-d H:i:s');
		}

		$affected = $this->db->update(
			$this->table,
			$data,
			[$this->primaryKey => $id]
		);

		return $affected > 0;
	}

	/**
	 * Delete record by ID
	 */
	public function delete(int $id): bool
	{
		if ($this->softDelete) {
			// Soft delete
			return $this->update($id, [
				$this->softDelete => date('Y-m-d H:i:s')
			]);
		}

		// Hard delete
		$affected = $this->db->delete(
			$this->table,
			[$this->primaryKey => $id]
		);

		return $affected > 0;
	}

	/**
	 * Restore soft deleted record
	 */
	public function restore(int $id): bool
	{
		if (!$this->softDelete) {
			throw new RepositoryException('Soft delete is not enabled for this repository');
		}

		$sql = "UPDATE {$this->table} SET {$this->softDelete} = NULL WHERE {$this->primaryKey} = :id";
		$statement = $this->db->getConnection()->prepare($sql);
		$statement->execute(['id' => $id]);

		return $statement->rowCount() > 0;
	}

	/**
	 * Check if record exists
	 */
	public function exists(int $id): bool
	{
		return $this->find($id) !== null;
	}

	/**
	 * Begin transaction
	 */
	public function beginTransaction(): void
	{
		$this->db->beginTransaction();
	}

	/**
	 * Commit transaction
	 */
	public function commit(): void
	{
		$this->db->commit();
	}

	/**
	 * Rollback transaction
	 */
	public function rollback(): void
	{
		$this->db->rollback();
	}

	/**
	 * Execute in transaction
	 */
	public function transaction(callable $callback)
	{
		return $this->db->transaction($callback);
	}

	/**
	 * Filter data to only fillable fields
	 */
	protected function filterFillable(array $data): array
	{
		if (empty($this->fillable)) {
			return $data;
		}

		return array_intersect_key($data, array_flip($this->fillable));
	}

	/**
	 * Build WHERE conditions for query
	 */
	protected function buildWhereConditions(array $where, array &$params): array
	{
		$conditions = [];

		foreach ($where as $column => $value) {
			if (is_null($value)) {
				$conditions[] = "`$column` IS NULL";
			} elseif (is_array($value)) {
				// Handle IN clause
				$placeholders = [];
				foreach ($value as $i => $val) {
					$placeholder = "{$column}_{$i}";
					$placeholders[] = ":$placeholder";
					$params[$placeholder] = $val;
				}
				$conditions[] = "`$column` IN (" . implode(', ', $placeholders) . ')';
			} elseif (is_string($value) && strpos($value, '%') !== false) {
				// Handle LIKE clause
				$conditions[] = "`$column` LIKE :$column";
				$params[$column] = $value;
			} else {
				$conditions[] = "`$column` = :$column";
				$params[$column] = $value;
			}
		}

		return $conditions;
	}

	/**
	 * Get table name
	 */
	public function getTable(): string
	{
		return $this->table;
	}

	/**
	 * Get primary key name
	 */
	public function getPrimaryKey(): string
	{
		return $this->primaryKey;
	}
}
