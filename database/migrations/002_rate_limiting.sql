-- Migration: 002_rate_limiting.sql
-- Description: Create rate limiting buckets table
-- Date: 2025-08-12
-- Version: 4.2

-- Create rate limit buckets table
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rate limiting buckets using token bucket algorithm';

-- Create rate limit logs table for monitoring
CREATE TABLE IF NOT EXISTS rate_limit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket_key VARCHAR(255) NOT NULL,
  action ENUM('allowed', 'denied', 'reset') NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rate limiting activity logs';

-- Create cleanup event for old buckets (runs every hour)
CREATE EVENT IF NOT EXISTS cleanup_rate_limit_buckets
ON SCHEDULE EVERY 1 HOUR
DO DELETE FROM rate_limit_buckets WHERE last_refill < UNIX_TIMESTAMP() - 3600;

-- Create cleanup event for old logs (runs daily, keeps 30 days)
CREATE EVENT IF NOT EXISTS cleanup_rate_limit_logs  
ON SCHEDULE EVERY 1 DAY
DO DELETE FROM rate_limit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Insert default rate limit configurations (corrected for real table structure)
INSERT IGNORE INTO rate_limit_config (service_name, max_requests_per_minute, max_requests_per_hour, backoff_base_delay, backoff_multiplier, max_retries, max_backoff_delay, created_at) VALUES
('openai', 10, 50, 1, 2.00, 3, 60, NOW()),
('pipedrive', 30, 200, 1, 2.00, 3, 60, NOW()),
('ringover', 50, 300, 1, 2.00, 3, 60, NOW()),
('default', 20, 100, 1, 2.00, 3, 60, NOW());

-- Add comment to document the purpose
ALTER TABLE rate_limit_buckets COMMENT = 'Rate limiting buckets using token bucket algorithm - v4.2';
ALTER TABLE rate_limit_logs COMMENT = 'Rate limiting activity logs for monitoring - v4.2';

