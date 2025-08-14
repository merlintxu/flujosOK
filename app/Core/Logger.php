<?php
namespace FlujosDimension\Core;

use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Logger implements LoggerInterface
{
    private MonologLogger $logger;

    public function __construct(
        string $logDir,
        string $minLevel = LogLevel::INFO,
        ?Request $request = null
    ) {
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $this->logger = new MonologLogger('app');
        $this->logger->pushHandler(new StreamHandler(rtrim($logDir,'/').'/app.log', $this->toMonologLevel($minLevel)));

        $corrId = $request?->header('X-Correlation-Id') ?? null;

        // Processor compatible v3 (LogRecord) y v2 (array)
        $this->logger->pushProcessor(function ($record) use ($corrId) {
            // v3
            if (class_exists(LogRecord::class) && $record instanceof LogRecord) {
                $record->extra['correlation_id'] = $record->extra['correlation_id'] ?? ($record->context['correlation_id'] ?? $corrId);
                $record->extra['batch_id']       = $record->extra['batch_id']       ?? ($record->context['batch_id'] ?? null);
                return $record;
            }
            // v2
            if (is_array($record)) {
                $record['extra']['correlation_id'] = $record['extra']['correlation_id'] ?? ($record['context']['correlation_id'] ?? $corrId);
                $record['extra']['batch_id']       = $record['extra']['batch_id']       ?? ($record['context']['batch_id'] ?? null);
                return $record;
            }
            return $record;
        });
    }

    private function toMonologLevel(string $level): int
    {
        return match (strtolower($level)) {
            'debug' => MonologLogger::DEBUG,
            'info' => MonologLogger::INFO,
            'notice' => MonologLogger::NOTICE,
            'warning' => MonologLogger::WARNING,
            'error' => MonologLogger::ERROR,
            'critical' => MonologLogger::CRITICAL,
            'alert' => MonologLogger::ALERT,
            'emergency' => MonologLogger::EMERGENCY,
            default => MonologLogger::INFO,
        };
    }

    // --- PSR-3 passthrough ---
    public function emergency($message, array $context = []): void { $this->logger->emergency($message, $context); }
    public function alert($message, array $context = []): void { $this->logger->alert($message, $context); }
    public function critical($message, array $context = []): void { $this->logger->critical($message, $context); }
    public function error($message, array $context = []): void { $this->logger->error($message, $context); }
    public function warning($message, array $context = []): void { $this->logger->warning($message, $context); }
    public function notice($message, array $context = []): void { $this->logger->notice($message, $context); }
    public function info($message, array $context = []): void { $this->logger->info($message, $context); }
    public function debug($message, array $context = []): void { $this->logger->debug($message, $context); }
    public function log($level, $message, array $context = []): void { $this->logger->log($level, $message, $context); }
}
