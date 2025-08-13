<?php

namespace FlujosDimension\Core;

use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Thin PSR-3 logger wrapper around Monolog.
 * Adds correlation and batch identifiers to each record.
 */
class Logger implements LoggerInterface
{
    private MonologLogger $logger;

    public function __construct(?string $logDir = null, string $level = LogLevel::INFO, ?Request $request = null)
    {
        $logDir = $logDir ?: dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $this->logger = new MonologLogger('app');
        $this->logger->pushHandler(new StreamHandler($logDir . '/app.log'));
        $this->logger->pushProcessor(function (array $record) use ($request) {
            $record['extra']['batch_id'] = $record['context']['batch_id'] ?? null;
            $record['extra']['correlation_id'] = $record['context']['correlation_id']
                ?? ($request ? $request->getCorrelationId() : null);
            return $record;
        });
    }

    public function emergency($message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }
    public function alert($message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }
    public function critical($message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }
    public function error($message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }
    public function warning($message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }
    public function notice($message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }
    public function info($message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }
    public function debug($message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }
    public function log($level, $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
