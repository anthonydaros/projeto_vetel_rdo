<?php
declare(strict_types=1);

namespace Src\Database;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Database Manager with secure query execution
 * Prevents SQL injection through prepared statements only
 */
class DatabaseManager
{
    private PDO $connection;
    private array $queryLog = [];
    private bool $transactionActive = false;
    
    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }
    
    /**
     * Execute a SELECT query with prepared statement
     * 
     * @param string $sql The SQL query with placeholders
     * @param array $params Parameters to bind
     * @return array Query results
     */
    public function select(string $sql, array $params = []): array
    {
        $statement = $this->prepare($sql);
        $this->bindParameters($statement, $params);
        $statement->execute();
        
        $this->logQuery($sql, $params);
        
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Execute a SELECT query and return single row
     * 
     * @param string $sql The SQL query with placeholders
     * @param array $params Parameters to bind
     * @return array|null Single row or null if not found
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $results = $this->select($sql, $params);
        return $results[0] ?? null;
    }
    
    /**
     * Execute an INSERT query
     * 
     * @param string $table Table name
     * @param array $data Associative array of column => value
     * @return int Last insert ID
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->escapeIdentifier($table),
            implode(', ', array_map([$this, 'escapeIdentifier'], $columns)),
            implode(', ', $placeholders)
        );
        
        $statement = $this->prepare($sql);
        $this->bindParameters($statement, $data);
        $statement->execute();
        
        $this->logQuery($sql, $data);
        
        return (int) $this->connection->lastInsertId();
    }
    
    /**
     * Execute an UPDATE query
     * 
     * @param string $table Table name
     * @param array $data Data to update
     * @param array $where WHERE conditions
     * @return int Number of affected rows
     */
    public function update(string $table, array $data, array $where): int
    {
        $setClause = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $setClause[] = $this->escapeIdentifier($column) . " = :set_$column";
            $params["set_$column"] = $value;
        }
        
        $whereClause = $this->buildWhereClause($where, $params);
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $this->escapeIdentifier($table),
            implode(', ', $setClause),
            $whereClause
        );
        
        $statement = $this->prepare($sql);
        $this->bindParameters($statement, $params);
        $statement->execute();
        
        $this->logQuery($sql, $params);
        
        return $statement->rowCount();
    }
    
    /**
     * Execute a DELETE query
     * 
     * @param string $table Table name
     * @param array $where WHERE conditions
     * @return int Number of deleted rows
     */
    public function delete(string $table, array $where): int
    {
        $params = [];
        $whereClause = $this->buildWhereClause($where, $params);
        
        $sql = sprintf(
            "DELETE FROM %s WHERE %s",
            $this->escapeIdentifier($table),
            $whereClause
        );
        
        $statement = $this->prepare($sql);
        $this->bindParameters($statement, $params);
        $statement->execute();
        
        $this->logQuery($sql, $params);
        
        return $statement->rowCount();
    }
    
    /**
     * Begin a database transaction
     */
    public function beginTransaction(): void
    {
        if (!$this->transactionActive) {
            $this->connection->beginTransaction();
            $this->transactionActive = true;
        }
    }
    
    /**
     * Commit the current transaction
     */
    public function commit(): void
    {
        if ($this->transactionActive) {
            $this->connection->commit();
            $this->transactionActive = false;
        }
    }
    
    /**
     * Rollback the current transaction
     */
    public function rollback(): void
    {
        if ($this->transactionActive) {
            $this->connection->rollBack();
            $this->transactionActive = false;
        }
    }
    
    /**
     * Execute a callable within a transaction
     * 
     * @param callable $callback The function to execute
     * @return mixed The callback return value
     * @throws \Exception If callback throws an exception
     */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Prepare a PDO statement
     */
    private function prepare(string $sql): PDOStatement
    {
        try {
            return $this->connection->prepare($sql);
        } catch (PDOException $e) {
            throw new DatabaseException(
                "Failed to prepare statement: " . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }
    
    /**
     * Bind parameters to a prepared statement
     */
    private function bindParameters(PDOStatement $statement, array $params): void
    {
        foreach ($params as $key => $value) {
            $type = PDO::PARAM_STR;
            
            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_BOOL;
            } elseif (is_null($value)) {
                $type = PDO::PARAM_NULL;
            }
            
            $statement->bindValue(
                is_int($key) ? $key + 1 : ":$key",
                $value,
                $type
            );
        }
    }
    
    /**
     * Build WHERE clause from conditions array
     */
    private function buildWhereClause(array $conditions, array &$params): string
    {
        $clauses = [];
        
        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                // Handle IN clause
                $placeholders = [];
                foreach ($value as $i => $val) {
                    $placeholder = "where_{$column}_{$i}";
                    $placeholders[] = ":$placeholder";
                    $params[$placeholder] = $val;
                }
                $clauses[] = $this->escapeIdentifier($column) . " IN (" . implode(', ', $placeholders) . ")";
            } elseif (is_null($value)) {
                $clauses[] = $this->escapeIdentifier($column) . " IS NULL";
            } else {
                $placeholder = "where_$column";
                $clauses[] = $this->escapeIdentifier($column) . " = :$placeholder";
                $params[$placeholder] = $value;
            }
        }
        
        return implode(' AND ', $clauses) ?: '1=1';
    }
    
    /**
     * Escape identifier (table/column name)
     */
    private function escapeIdentifier(string $identifier): string
    {
        // Remove any existing backticks and re-add them
        $identifier = str_replace('`', '', $identifier);
        return "`$identifier`";
    }
    
    /**
     * Log executed queries for debugging
     */
    private function logQuery(string $sql, array $params): void
    {
        $this->queryLog[] = [
            'sql' => $sql,
            'params' => $params,
            'time' => microtime(true)
        ];
        
        // Keep only last 100 queries in memory
        if (count($this->queryLog) > 100) {
            array_shift($this->queryLog);
        }
    }
    
    /**
     * Get query log for debugging
     */
    public function getQueryLog(): array
    {
        return $this->queryLog;
    }
    
    /**
     * Clear query log
     */
    public function clearQueryLog(): void
    {
        $this->queryLog = [];
    }
    
    /**
     * Get the underlying PDO connection
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }
}

/**
 * Custom database exception
 */
class DatabaseException extends \Exception
{
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}