-- Migration: 006_cleanup_events.sql
-- Description: schedule cleanup for dedup and rate limit logs
-- Date: 2025-08-13

SET @dedup_ttl_days := IFNULL(@dedup_ttl_days, 7);
SET @rate_limit_log_ttl_days := IFNULL(@rate_limit_log_ttl_days, 30);

SET GLOBAL event_scheduler = ON;

-- Webhook deduplication cleanup
DROP EVENT IF EXISTS cleanup_webhook_deduplication;
SET @sql := CONCAT(
    'CREATE EVENT cleanup_webhook_deduplication ON SCHEDULE EVERY 1 DAY DO ',
    'DELETE FROM webhook_deduplication WHERE expires_at < DATE_SUB(NOW(), INTERVAL ',
    @dedup_ttl_days,
    ' DAY)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Rate limit logs cleanup
DROP EVENT IF EXISTS cleanup_rate_limit_logs;
SET @sql := CONCAT(
    'CREATE EVENT cleanup_rate_limit_logs ON SCHEDULE EVERY 1 DAY DO ',
    'DELETE FROM rate_limit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ',
    @rate_limit_log_ttl_days,
    ' DAY)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
