<?php

namespace FlujosDimension\Core;

/**
 * Clase Logger - Flujos Dimension v4.2
 * Sistema de logging estructurado
 */
class Logger
{
    private string $logDir;
    private string $defaultLevel;
    
    const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
        'critical' => 4
    ];
    
    public function __construct(?string $logDir = null, string $defaultLevel = 'info')
    {
        $this->logDir = $logDir ?: dirname(__DIR__, 2) . '/storage/logs';
        $this->defaultLevel = $defaultLevel;
        
        // Crear directorio de logs si no existe
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }
    
    /**
     * Log de debug
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }
    
    /**
     * Log de información
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }
    
    /**
     * Log de advertencia
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }
    
    /**
     * Log de error
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }
    
    /**
     * Log crítico
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }
    
    /**
     * Log genérico
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!isset(self::LEVELS[$level])) {
            $level = $this->defaultLevel;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        
        // Formatear contexto
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        // Crear línea de log
        $logLine = "[{$timestamp}] {$levelUpper}: {$message}{$contextStr}" . PHP_EOL;
        
        // Escribir a archivo específico del nivel
        $this->writeToFile($level, $logLine);
        
        // También escribir a archivo general
        $this->writeToFile('application', $logLine);
        
        // Si es error crítico, también escribir a error.log
        if (in_array($level, ['error', 'critical'])) {
            $this->writeToFile('error', $logLine);
        }
    }
    
    /**
     * Log de actividad de API
     */
    public function apiActivity(string $method, string $endpoint, int $statusCode, float $executionTime, array $context = []): void
    {
        $message = "API {$method} {$endpoint} - Status: {$statusCode} - Time: {$executionTime}ms";
        
        $context['method'] = $method;
        $context['endpoint'] = $endpoint;
        $context['status_code'] = $statusCode;
        $context['execution_time'] = $executionTime;
        
        $this->info($message, $context);
        $this->writeToFile('api', "[" . date('Y-m-d H:i:s') . "] {$message} " . json_encode($context) . PHP_EOL);
    }
    
    /**
     * Log de base de datos
     */
    public function database(string $query, float $executionTime, array $params = []): void
    {
        $message = "DB Query executed in {$executionTime}ms";
        
        $context = [
            'query' => $query,
            'execution_time' => $executionTime,
            'params' => $params
        ];
        
        $this->debug($message, $context);
        $this->writeToFile('database', "[" . date('Y-m-d H:i:s') . "] {$message} " . json_encode($context) . PHP_EOL);
    }
    
    /**
     * Log de sincronización
     */
    public function sync(string $source, string $action, bool $success, int $recordsProcessed = 0, ?string $error = null): void
    {
        $status = $success ? 'SUCCESS' : 'FAILED';
        $message = "Sync {$source} - {$action} - {$status}";
        
        if ($recordsProcessed > 0) {
            $message .= " - Records: {$recordsProcessed}";
        }
        
        $context = [
            'source' => $source,
            'action' => $action,
            'success' => $success,
            'records_processed' => $recordsProcessed
        ];
        
        if ($error) {
            $context['error'] = $error;
            $message .= " - Error: {$error}";
        }
        
        if ($success) {
            $this->info($message, $context);
        } else {
            $this->error($message, $context);
        }
        
        $this->writeToFile('sync', "[" . date('Y-m-d H:i:s') . "] {$message} " . json_encode($context) . PHP_EOL);
    }
    
    /**
     * Log de configuración
     */
    public function config(string $action, string $key, $value = null): void
    {
        $message = "Config {$action}: {$key}";
        
        $context = [
            'action' => $action,
            'key' => $key
        ];
        
        if ($value !== null && !in_array($key, ['password', 'token', 'secret', 'key'])) {
            $context['value'] = $value;
        }
        
        $this->info($message, $context);
        $this->writeToFile('config', "[" . date('Y-m-d H:i:s') . "] {$message} " . json_encode($context) . PHP_EOL);
    }
    
    /**
     * Escribir a archivo específico
     */
    private function writeToFile(string $filename, string $content): void
    {
        $filepath = $this->logDir . '/' . $filename . '.log';
        
        // Rotar archivo si es muy grande (>10MB)
        if (file_exists($filepath) && filesize($filepath) > 10 * 1024 * 1024) {
            $this->rotateLogFile($filepath);
        }
        
        file_put_contents($filepath, $content, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Rotar archivo de log
     */
    private function rotateLogFile(string $filepath): void
    {
        $rotatedPath = $filepath . '.' . date('Y-m-d-H-i-s');
        rename($filepath, $rotatedPath);
        
        // Comprimir archivo rotado si gzip está disponible
        if (function_exists('gzencode')) {
            $content = file_get_contents($rotatedPath);
            file_put_contents($rotatedPath . '.gz', gzencode($content));
            unlink($rotatedPath);
        }
    }
    
    /**
     * Limpiar logs antiguos
     */
    public function cleanOldLogs(int $daysToKeep = 30): int
    {
        $deleted = 0;
        $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
        
        $files = glob($this->logDir . '/*.log.*');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
                $deleted++;
            }
        }
        
        $this->info("Cleaned old log files", ['deleted_files' => $deleted, 'days_kept' => $daysToKeep]);
        
        return $deleted;
    }
    
    /**
     * Obtener estadísticas de logs
     */
    public function getStats(): array
    {
        $stats = [];
        $files = glob($this->logDir . '/*.log');
        
        foreach ($files as $file) {
            $filename = basename($file, '.log');
            $stats[$filename] = [
                'size' => filesize($file),
                'lines' => $this->countLines($file),
                'last_modified' => filemtime($file)
            ];
        }
        
        return $stats;
    }
    
    /**
     * Contar líneas en un archivo
     */
    private function countLines(string $file): int
    {
        $lines = 0;
        $handle = fopen($file, 'r');
        
        if ($handle) {
            while (fgets($handle) !== false) {
                $lines++;
            }
            fclose($handle);
        }
        
        return $lines;
    }
    
    /**
     * Buscar en logs
     */
    public function search(string $term, string $logFile = 'application', int $maxResults = 100): array
    {
        $filepath = $this->logDir . '/' . $logFile . '.log';
        $results = [];
        
        if (!file_exists($filepath)) {
            return $results;
        }
        
        $handle = fopen($filepath, 'r');
        $lineNumber = 0;
        
        if ($handle) {
            while (($line = fgets($handle)) !== false && count($results) < $maxResults) {
                $lineNumber++;
                
                if (stripos($line, $term) !== false) {
                    $results[] = [
                        'line_number' => $lineNumber,
                        'content' => trim($line),
                        'file' => $logFile
                    ];
                }
            }
            fclose($handle);
        }
        
        return $results;
    }
}

