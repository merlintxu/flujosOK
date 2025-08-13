-- Migration: 004_rate_limit_and_dedup_tables.sql
-- Description: create rate limit and webhook deduplication tables
-- Date: 2025-08-13

CREATE TABLE IF NOT EXISTS rate_limit_buckets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket_key VARCHAR(255) NOT NULL,
  tokens DECIMAL(10,2) NOT NULL DEFAULT 0,
  capacity INT UNSIGNED NOT NULL DEFAULT 100,
  last_refill INT UNSIGNED NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY idx_bucket_key (bucket_key),
  KEY idx_last_refill (last_refill)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rate limiting buckets';

CREATE TABLE IF NOT EXISTS rate_limit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket_key VARCHAR(255) NOT NULL,
  action ENUM('allowed','denied','reset') NOT NULL,
  tokens_requested INT UNSIGNED NOT NULL DEFAULT 1,
  tokens_remaining DECIMAL(10,2) NOT NULL DEFAULT 0,
  correlation_id VARCHAR(255) DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bucket_key (bucket_key),
  KEY idx_action (action),
  KEY idx_correlation_id (correlation_id),
  KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rate limiting logs';

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
  KEY idx_correlation_id (correlation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Webhook deduplication';
