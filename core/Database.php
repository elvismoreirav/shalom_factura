<?php
/**
 * SHALOM FACTURA - Clase Database
 * Conexión PDO con patrón Singleton y Query Builder
 */

namespace Shalom\Core;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;
    private ?PDOStatement $stmt = null;
    private array $log = [];
    private bool $debug;
    
    /**
     * Constructor privado (Singleton)
     */
    private function __construct()
    {
        $this->debug = defined('APP_DEBUG') && APP_DEBUG;
        $this->connect();
    }
    
    /**
     * Obtener instancia única
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Establecer conexión
     */
    private function connect(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
        ];
        
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            $this->logError('Connection failed: ' . $e->getMessage());
            throw new \RuntimeException('Error de conexión a la base de datos');
        }
    }
    
    /**
     * Obtener conexión PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
    
    /**
     * Preparar consulta
     */
    public function query(string $sql): self
    {
        $this->stmt = $this->pdo->prepare($sql);
        return $this;
    }
    
    /**
     * Vincular parámetros
     */
    public function bind(string|int $param, mixed $value, ?int $type = null): self
    {
        if ($type === null) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
        }
        
        $this->stmt->bindValue($param, $value, $type);
        return $this;
    }
    
    /**
     * Ejecutar consulta
     */
    public function execute(array $params = []): bool
    {
        try {
            if ($this->debug) {
                $start = microtime(true);
            }
            
            $result = empty($params) ? $this->stmt->execute() : $this->stmt->execute($params);
            
            if ($this->debug) {
                $this->log[] = [
                    'query' => $this->stmt->queryString,
                    'params' => $params,
                    'time' => round((microtime(true) - $start) * 1000, 2) . 'ms'
                ];
            }
            
            return $result;
        } catch (PDOException $e) {
            $this->logError($e->getMessage(), $this->stmt->queryString, $params);
            throw $e;
        }
    }
    
    /**
     * Obtener todos los registros
     */
    public function fetchAll(array $params = []): array
    {
        $this->execute($params);
        return $this->stmt->fetchAll();
    }
    
    /**
     * Obtener un registro
     */
    public function fetch(array $params = []): array|false
    {
        $this->execute($params);
        return $this->stmt->fetch();
    }
    
    /**
     * Obtener un valor
     */
    public function fetchColumn(array $params = [], int $column = 0): mixed
    {
        $this->execute($params);
        return $this->stmt->fetchColumn($column);
    }
    
    /**
     * Obtener número de filas afectadas
     */
    public function rowCount(): int
    {
        return $this->stmt->rowCount();
    }
    
    /**
     * Obtener último ID insertado
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Iniciar transacción
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Confirmar transacción
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }
    
    /**
     * Revertir transacción
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }
    
    /**
     * Verificar si hay transacción activa
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }
    
    // =====================================================
    // MÉTODOS DE CONVENIENCIA
    // =====================================================
    
    /**
     * Insertar registro
     */
    public function insert(string $table, array $data): int|false
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        $this->query($sql);
        
        foreach ($data as $key => $value) {
            $this->bind(":$key", $value);
        }
        
        if ($this->execute()) {
            return (int) $this->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Actualizar registros
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "$column = :set_$column";
        }
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $sets),
            $where
        );
        
        $this->query($sql);
        
        foreach ($data as $key => $value) {
            $this->bind(":set_$key", $value);
        }
        
        foreach ($whereParams as $key => $value) {
            $this->bind($key, $value);
        }
        
        $this->execute();
        return $this->rowCount();
    }
    
    /**
     * Eliminar registros
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM $table WHERE $where";
        $this->query($sql)->execute($params);
        return $this->rowCount();
    }
    
    /**
     * Seleccionar registros
     */
    public function select(
        string $table,
        array $columns = ['*'],
        string $where = '',
        array $params = [],
        string $orderBy = '',
        int $limit = 0,
        int $offset = 0
    ): array {
        $sql = "SELECT " . implode(', ', $columns) . " FROM $table";
        
        if ($where) {
            $sql .= " WHERE $where";
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy";
        }
        
        if ($limit > 0) {
            $sql .= " LIMIT $limit";
            if ($offset > 0) {
                $sql .= " OFFSET $offset";
            }
        }
        
        return $this->query($sql)->fetchAll($params);
    }
    
    /**
     * Contar registros
     */
    public function count(string $table, string $where = '', array $params = []): int
    {
        $sql = "SELECT COUNT(*) FROM $table";
        if ($where) {
            $sql .= " WHERE $where";
        }
        
        return (int) $this->query($sql)->fetchColumn($params);
    }
    
    /**
     * Verificar si existe registro
     */
    public function exists(string $table, string $where, array $params = []): bool
    {
        return $this->count($table, $where, $params) > 0;
    }
    
    /**
     * Obtener registro por ID
     */
    public function find(string $table, int $id, array $columns = ['*']): array|false
    {
        $sql = "SELECT " . implode(', ', $columns) . " FROM $table WHERE id = :id LIMIT 1";
        return $this->query($sql)->fetch([':id' => $id]);
    }
    
    /**
     * Soft delete (marcar como eliminado)
     */
    public function softDelete(string $table, int $id): bool
    {
        return $this->update($table, ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', [':id' => $id]) > 0;
    }
    
    // =====================================================
    // UTILIDADES
    // =====================================================
    
    /**
     * Escapar valor para LIKE
     */
    public function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
    
    /**
     * Obtener log de consultas (solo en debug)
     */
    public function getQueryLog(): array
    {
        return $this->log;
    }
    
    /**
     * Registrar error
     */
    private function logError(string $message, string $query = '', array $params = []): void
    {
        $logMessage = sprintf(
            "[%s] Database Error: %s\nQuery: %s\nParams: %s\n",
            date('Y-m-d H:i:s'),
            $message,
            $query,
            json_encode($params)
        );
        
        error_log($logMessage, 3, LOGS_PATH . '/database.log');
    }
    
    /**
     * Prevenir clonación
     */
    private function __clone() {}
    
    /**
     * Prevenir deserialización
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
