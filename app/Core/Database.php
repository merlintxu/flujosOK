<?php
/**
 * Flujos Dimension v4.1 - Database Core Class
 * Clase de base de datos con conexiones reales y manejo de errores robusto
 * 
 * @version 4.1.2
 *
 */

declare(strict_types=1);

namespace FlujosDimension\Core;

use PDO;            // ← IMPORTACIÓN CLAVE
use PDOException;   // (opcional, pero evita el mismo problema)
class Database
{
    private static $instance = null;
    private $connection = null;
    private $config = [];
    private $lastError = null;
    
    private function __construct()
    {
        $this->loadConfig();
        $this->connect();
    }
    
    /**
     * Singleton pattern para obtener instancia única
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Cargar configuración desde variables de entorno
     */
    private function loadConfig()
    {
        $this->config = [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'dbname' => $_ENV['DB_NAME'] ?? '',
            'username' => $_ENV['DB_USER'] ?? '',
            'password' => $_ENV['DB_PASS'] ?? '',
            'charset' => 'utf8mb4',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]
        ];
    }
    
    /**
     * Establecer conexión a la base de datos
     */
    private function connect()
    {
        try {
            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                $this->config['host'],
                $this->config['port'],
                $this->config['dbname'],
                $this->config['charset']
            );
            
            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );
            
            $this->logInfo("Database connection established successfully");
            
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            $this->logError("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener conexión PDO
     */
    public function getConnection()
    {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }
    
    /**
     * Verificar si la conexión está activa
     */
    public function isConnected()
    {
        try {
            if ($this->connection === null) {
                return false;
            }
            
            $stmt = $this->connection->query('SELECT 1');
            return $stmt !== false;
            
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Ejecutar consulta SELECT
     */
    public function select($query, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            $this->logError("SELECT query failed: " . $e->getMessage() . " | Query: " . $query);
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }
    
    /**
     * Ejecutar consulta SELECT que devuelve un solo registro
     */
    public function selectOne($query, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            $this->logError("SELECT ONE query failed: " . $e->getMessage() . " | Query: " . $query);
            throw new Exception("Query failed: " . $e->getMessage());
        }
    }
    
    /**
     * Ejecutar consulta INSERT
     */
    public function insert($query, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                $lastId = $this->connection->lastInsertId();
                $this->logInfo("INSERT successful. Last ID: " . $lastId);
                return $lastId;
            }
            
            return false;
            
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            $this->logError("INSERT query failed: " . $e->getMessage() . " | Query: " . $query);
            throw new Exception("Insert failed: " . $e->getMessage());
        }
    }
    
    /**
     * Ejecutar consulta UPDATE
     */
    public function update($query, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                $rowCount = $stmt->rowCount();
                $this->logInfo("UPDATE successful. Rows affected: " . $rowCount);
                return $rowCount;
            }
            
            return false;
            
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            $this->logError("UPDATE query failed: " . $e->getMessage() . " | Query: " . $query);
            throw new Exception("Update failed: " . $e->getMessage());
        }
    }
    
    /**
     * Ejecutar consulta DELETE
     */
    public function delete($query, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($query);
            $result = $stmt->execute($params);
            
            if ($result) {
                $rowCount = $stmt->rowCount();
                $this->logInfo("DELETE successful. Rows affected: " . $rowCount);
                return $rowCount;
            }
            
            return false;
            
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            $this->logError("DELETE query failed: " . $e->getMessage() . " | Query: " . $query);
            throw new Exception("Delete failed: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener estadísticas de llamadas para el dashboard
     */
    public function getCallStats($date = null)
    {
        $date = $date ?? date('Y-m-d');
        
        try {
            // Total de llamadas del día
            $totalCalls = $this->selectOne(
                "SELECT COUNT(*) as total FROM calls WHERE DATE(created_at) = ?",
                [$date]
            )['total'] ?? 0;
            
            // Llamadas respondidas
            $answeredCalls = $this->selectOne(
                "SELECT COUNT(*) as answered FROM calls WHERE DATE(created_at) = ? AND status = 'answered'",
                [$date]
            )['answered'] ?? 0;
            
            // Duración promedio
            $avgDuration = $this->selectOne(
                "SELECT AVG(duration) as avg_dur FROM calls WHERE DATE(created_at) = ? AND duration > 0",
                [$date]
            )['avg_dur'] ?? 0;
            
            // Sentiment positivo
            $positiveCalls = $this->selectOne(
                "SELECT COUNT(*) as positive FROM calls WHERE DATE(created_at) = ? AND ai_sentiment = 'positive'",
                [$date]
            )['positive'] ?? 0;
            
            return [
                'total_calls' => (int)$totalCalls,
                'answered_calls' => (int)$answeredCalls,
                'avg_duration' => $this->formatDuration($avgDuration),
                'positive_sentiment' => $totalCalls > 0 ? round(($positiveCalls / $totalCalls) * 100) : 0,
                'answer_rate' => $totalCalls > 0 ? round(($answeredCalls / $totalCalls) * 100) : 0
            ];
            
        } catch (Exception $e) {
            $this->logError("Error getting call stats: " . $e->getMessage());
            return [
                'total_calls' => 0,
                'answered_calls' => 0,
                'avg_duration' => '0:00',
                'positive_sentiment' => 0,
                'answer_rate' => 0
            ];
        }
    }
    
    /**
     * Obtener últimas llamadas
     */
    public function getRecentCalls($limit = 10)
    {
        try {
            return $this->select(
                "SELECT * FROM calls ORDER BY created_at DESC LIMIT ?",
                [$limit]
            );
        } catch (Exception $e) {
            $this->logError("Error getting recent calls: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Crear tablas si no existen
     */
    public function createTablesIfNotExist()
    {
        $tables = [
            'calls' => "
                CREATE TABLE IF NOT EXISTS calls (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ringover_id VARCHAR(255) UNIQUE,
                    phone_number VARCHAR(50),
                    direction ENUM('inbound', 'outbound') DEFAULT 'inbound',
                    status ENUM('answered', 'missed', 'busy', 'failed') DEFAULT 'missed',
                    duration INT DEFAULT 0,
                    recording_url TEXT,
                    ai_transcription TEXT,
                    ai_summary TEXT,
                    ai_keywords TEXT,
                    ai_sentiment ENUM('positive', 'negative', 'neutral') DEFAULT 'neutral',
                    pipedrive_contact_id INT,
                    pipedrive_deal_id INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_created_at (created_at),
                    INDEX idx_phone_number (phone_number),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'api_tokens' => "
                CREATE TABLE IF NOT EXISTS api_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    token_hash VARCHAR(255) UNIQUE,
                    name VARCHAR(100),
                    expires_at TIMESTAMP,
                    last_used_at TIMESTAMP NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_token_hash (token_hash),
                    INDEX idx_expires_at (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ",
            'system_logs' => "
                CREATE TABLE IF NOT EXISTS system_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    level ENUM('info', 'warning', 'error', 'debug') DEFAULT 'info',
                    message TEXT,
                    context JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_level (level),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            "
        ];
        
        foreach ($tables as $tableName => $sql) {
            try {
                $this->connection->exec($sql);
                $this->logInfo("Table '$tableName' created or verified successfully");
            } catch (PDOException $e) {
                $this->logError("Error creating table '$tableName': " . $e->getMessage());
                throw new Exception("Error creating table '$tableName': " . $e->getMessage());
            }
        }
    }
    
    /**
     * Formatear duración en segundos a formato MM:SS
     */
    private function formatDuration($seconds)
    {
        if (!$seconds || $seconds <= 0) {
            return '0:00';
        }
        
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;
        
        return sprintf('%d:%02d', $minutes, $seconds);
    }
    
    /**
     * Obtener último error
     */
    public function getLastError()
    {
        return $this->lastError;
    }
    
    /**
     * Registrar información en logs
     */
    private function logInfo($message)
    {
        $this->writeLog('info', $message);
    }
    
    /**
     * Registrar error en logs
     */
    private function logError($message)
    {
        $this->writeLog('error', $message);
    }
    
    /**
     * Escribir en archivo de log
     */
    private function writeLog($level, $message)
    {
        $logFile = dirname(__DIR__, 2) . '/storage/logs/database.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
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
        throw new Exception("Cannot unserialize singleton");
    }
}
?>

