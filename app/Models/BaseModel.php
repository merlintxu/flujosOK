<?php

namespace FlujosDimension\Models;

use FlujosDimension\Core\Container;
use PDO;
use Exception;

/**
 * Modelo Base - Flujos Dimension v4.2
 * ORM básico para todos los modelos
 */
abstract class BaseModel
{
    protected Container $container;
    protected PDO $database;
    protected $logger;
    
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $guarded = ['id', 'created_at', 'updated_at'];
    protected array $casts = [];
    protected bool $timestamps = true;
    
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->database = $container->resolve('database');
        $this->logger = $container->resolve('logger');
    }
    
    /**
     * Encontrar un registro por ID
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        $stmt = $this->database->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $result = $stmt->fetch();
        return $result ? $this->castAttributes($result) : null;
    }
    
    /**
     * Encontrar un registro o fallar
     */
    public function findOrFail(int $id): array
    {
        $result = $this->find($id);
        
        if (!$result) {
            throw new Exception("Record with ID {$id} not found in table {$this->table}");
        }
        
        return $result;
    }
    
    /**
     * Obtener todos los registros
     */
    public function all(int $limit = 100): array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY {$this->primaryKey} DESC LIMIT :limit";
        $stmt = $this->database->prepare($sql);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll();
        return array_map([$this, 'castAttributes'], $results);
    }
    
    /**
     * Crear un nuevo registro
     */
    public function create(array $data): array
    {
        $data = $this->filterFillable($data);
        $data = $this->validate($data);
        
        if ($this->timestamps) {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":$col", $columns);
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute($data);
        
        $id = $this->database->lastInsertId();
        
        $this->logger->info("Created new record in {$this->table}", [
            'id' => $id,
            'data' => $data
        ]);
        
        return $this->find($id);
    }
    
    /**
     * Actualizar un registro
     */
    public function update(int $id, array $data): array
    {
        $existing = $this->findOrFail($id);
        
        $data = $this->filterFillable($data);
        $data = $this->validate($data);
        
        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $setParts = array_map(fn($col) => "$col = :$col", array_keys($data));
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts) . " WHERE {$this->primaryKey} = :id";
        
        $data['id'] = $id;
        $stmt = $this->database->prepare($sql);
        $stmt->execute($data);
        
        $this->logger->info("Updated record in {$this->table}", [
            'id' => $id,
            'changes' => $data
        ]);
        
        return $this->find($id);
    }
    
    /**
     * Eliminar un registro
     */
    public function delete(int $id): bool
    {
        $existing = $this->findOrFail($id);
        
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->database->prepare($sql);
        $result = $stmt->execute(['id' => $id]);
        
        $this->logger->info("Deleted record from {$this->table}", [
            'id' => $id,
            'record' => $existing
        ]);
        
        return $result;
    }
    
    /**
     * Obtener registros con paginación
     */
    public function paginate(int $page = 1, int $perPage = 20, string $orderBy = null, string $direction = 'DESC'): array
    {
        $orderBy = $orderBy ?: $this->primaryKey;
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        
        // Contar total
        $countSql = "SELECT COUNT(*) as total FROM {$this->table}";
        $countStmt = $this->database->query($countSql);
        $total = $countStmt->fetch()['total'];
        
        // Obtener datos
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT * FROM {$this->table} ORDER BY {$orderBy} {$direction} LIMIT :limit OFFSET :offset";
        
        $stmt = $this->database->prepare($sql);
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $data = $stmt->fetchAll();
        $data = array_map([$this, 'castAttributes'], $data);
        
        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => (int) $total,
                'total_pages' => ceil($total / $perPage),
                'has_next' => $page < ceil($total / $perPage),
                'has_prev' => $page > 1
            ]
        ];
    }
    
    /**
     * Buscar registros con WHERE
     */
    public function where(string $column, $operator, $value = null): array
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE {$column} {$operator} :value";
        $stmt = $this->database->prepare($sql);
        $stmt->execute(['value' => $value]);
        
        $results = $stmt->fetchAll();
        return array_map([$this, 'castAttributes'], $results);
    }
    
    /**
     * Buscar primer registro con WHERE
     */
    public function whereFirst(string $column, $operator, $value = null): ?array
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $sql = "SELECT * FROM {$this->table} WHERE {$column} {$operator} :value LIMIT 1";
        $stmt = $this->database->prepare($sql);
        $stmt->execute(['value' => $value]);
        
        $result = $stmt->fetch();
        return $result ? $this->castAttributes($result) : null;
    }
    
    /**
     * Contar registros
     */
    public function count(array $where = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $params = [];
        
        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $column => $value) {
                $conditions[] = "{$column} = :{$column}";
                $params[$column] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute($params);
        
        return (int) $stmt->fetch()['total'];
    }
    
    /**
     * Verificar si existe un registro
     */
    public function exists(array $where): bool
    {
        return $this->count($where) > 0;
    }
    
    /**
     * Obtener el primer registro
     */
    public function first(): ?array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY {$this->primaryKey} ASC LIMIT 1";
        $stmt = $this->database->query($sql);
        
        $result = $stmt->fetch();
        return $result ? $this->castAttributes($result) : null;
    }
    
    /**
     * Obtener el último registro
     */
    public function latest(): ?array
    {
        $sql = "SELECT * FROM {$this->table} ORDER BY {$this->primaryKey} DESC LIMIT 1";
        $stmt = $this->database->query($sql);
        
        $result = $stmt->fetch();
        return $result ? $this->castAttributes($result) : null;
    }
    
    /**
     * Ejecutar consulta SQL personalizada
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->database->prepare($sql);
        $stmt->execute($params);
        
        $results = $stmt->fetchAll();
        return array_map([$this, 'castAttributes'], $results);
    }
    
    /**
     * Filtrar campos permitidos
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return array_diff_key($data, array_flip($this->guarded));
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    /**
     * Aplicar casting a los atributos
     */
    protected function castAttributes(array $attributes): array
    {
        foreach ($this->casts as $key => $type) {
            if (!isset($attributes[$key])) {
                continue;
            }
            
            $value = $attributes[$key];
            
            switch ($type) {
                case 'int':
                case 'integer':
                    $attributes[$key] = (int) $value;
                    break;
                    
                case 'float':
                case 'double':
                    $attributes[$key] = (float) $value;
                    break;
                    
                case 'bool':
                case 'boolean':
                    $attributes[$key] = (bool) $value;
                    break;
                    
                case 'string':
                    $attributes[$key] = (string) $value;
                    break;
                    
                case 'array':
                case 'json':
                    $attributes[$key] = is_string($value) ? json_decode($value, true) : $value;
                    break;
                    
                case 'datetime':
                    // Mantener como string para compatibilidad
                    break;
            }
        }
        
        return $attributes;
    }
    
    /**
     * Validar datos (debe ser implementado por cada modelo)
     */
    protected function validate(array $data): array
    {
        return $data;
    }
    
    /**
     * Obtener nombre de la tabla
     */
    public function getTable(): string
    {
        return $this->table;
    }
    
    /**
     * Obtener clave primaria
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }
    
    /**
     * Obtener campos fillable
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }
    
    /**
     * Iniciar transacción
     */
    public function beginTransaction(): bool
    {
        return $this->database->beginTransaction();
    }
    
    /**
     * Confirmar transacción
     */
    public function commit(): bool
    {
        return $this->database->commit();
    }
    
    /**
     * Revertir transacción
     */
    public function rollback(): bool
    {
        return $this->database->rollback();
    }
    
    /**
     * Ejecutar en transacción
     */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
}

