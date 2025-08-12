-- Migration: 003_webhook_deduplication.sql
-- Description: Add webhook deduplication table for idempotency
-- Date: 2025-08-12
-- Version: 4.2

-- Create webhook deduplication table
CREATE TABLE IF NOT EXISTS webhook_deduplication (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  deduplication_key VARCHAR(255) NOT NULL,
  webhook_type VARCHAR(50) NOT NULL,
  payload_hash VARCHAR(64) NOT NULL,
  correlation_id VARCHAR(255) DEFAULT NULL,
  processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY idx_deduplication_key (deduplication_key),
  KEY idx_expires_at (expires_at),
  KEY idx_webhook_type (webhook_type),
  KEY idx_correlation_id (correlation_id),
  KEY idx_processed_at (processed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Webhook deduplication for idempotency - v4.2';

-- Create webhook processing logs table
CREATE TABLE IF NOT EXISTS webhook_processing_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  webhook_type VARCHAR(50) NOT NULL,
  deduplication_key VARCHAR(255) NOT NULL,
  correlation_id VARCHAR(255) DEFAULT NULL,
  status ENUM('processed', 'duplicate', 'failed') NOT NULL,
  payload_size INT UNSIGNED DEFAULT 0,
  processing_time_ms INT UNSIGNED DEFAULT 0,
  error_message TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_webhook_type (webhook_type),
  KEY idx_status (status),
  KEY idx_correlation_id (correlation_id),
  KEY idx_created_at (created_at),
  KEY idx_deduplication_key (deduplication_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Webhook processing activity logs - v4.2';

-- Create cleanup event for expired deduplication records (runs every hour)
CREATE EVENT IF NOT EXISTS cleanup_webhook_deduplication
ON SCHEDULE EVERY 1 HOUR
DO DELETE FROM webhook_deduplication WHERE expires_at < NOW();

-- Create cleanup event for old processing logs (runs daily, keeps 30 days)
CREATE EVENT IF NOT EXISTS cleanup_webhook_processing_logs
ON SCHEDULE EVERY 1 DAY  
DO DELETE FROM webhook_processing_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Add indexes for performance
ALTER TABLE webhook_deduplication 
ADD INDEX IF NOT EXISTS idx_webhook_type_expires (webhook_type, expires_at),
ADD INDEX IF NOT EXISTS idx_processed_correlation (processed_at, correlation_id);

ALTER TABLE webhook_processing_logs
ADD INDEX IF NOT EXISTS idx_type_status_created (webhook_type, status, created_at),
ADD INDEX IF NOT EXISTS idx_correlation_created (correlation_id, created_at);

-- Add comment to document the purpose
ALTER TABLE webhook_deduplication COMMENT = 'Webhook deduplication for idempotency using TTL - v4.2';
ALTER TABLE webhook_processing_logs COMMENT = 'Webhook processing activity logs for monitoring - v4.2';

