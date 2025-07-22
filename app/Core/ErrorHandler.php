<?php

namespace FlujosDimension\Core;

class ErrorHandler
{
    private bool $debug;
    
    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }
    
    public function handleError(int $severity, string $message, string $file, int $line): void
    {
        $this->logError("Error: $message in $file on line $line");
        
        if ($this->debug) {
            echo "Error: $message in $file on line $line\n";
        }
    }
    
    public function handleException(\Throwable $exception): void
    {
        $this->logError("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine());
        
        http_response_code(500);
        header('Content-Type: application/json');
        
        $response = ['success' => false, 'error' => 'Internal server error'];
        
        if ($this->debug) {
            $response['debug'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        echo json_encode($response);
    }
    
    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $this->logError("Fatal error: {$error['message']} in {$error['file']} on line {$error['line']}");
        }
    }
    
    public function logError(string $message): void
    {
        $logFile = dirname(__DIR__, 2) . '/storage/logs/error.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
    }
}
