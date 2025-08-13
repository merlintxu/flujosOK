-- Migration: 007_create_rate_limit_config.sql
-- Description: Create rate_limit_config table for per-service rate limiting policies
-- Date: 2025-08-20

CREATE TABLE IF NOT EXISTS rate_limit_config (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_name VARCHAR(50) NOT NULL,
  max_requests_per_minute INT NOT NULL DEFAULT 60,
  max_requests_per_hour INT NOT NULL DEFAULT 1000,
  backoff_base_delay INT NOT NULL DEFAULT 1,
  backoff_multiplier DECIMAL(3,2) NOT NULL DEFAULT 2.00,
  max_retries INT NOT NULL DEFAULT 3,
  max_backoff_delay INT NOT NULL DEFAULT 60,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY idx_service_name (service_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rate limit policies per external service';

INSERT IGNORE INTO rate_limit_config (service_name, max_requests_per_minute, max_requests_per_hour, backoff_base_delay, backoff_multiplier, max_retries, max_backoff_delay, created_at)
VALUES
  ('openai', 10, 50, 1, 2.00, 3, 60, NOW()),
  ('pipedrive', 30, 200, 1, 2.00, 3, 60, NOW()),
  ('ringover', 50, 300, 1, 2.00, 3, 60, NOW()),
  ('default', 20, 100, 1, 2.00, 3, 60, NOW());
