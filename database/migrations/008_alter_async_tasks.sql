-- Migration: 008_alter_async_tasks.sql
-- Description: Add visibility, reservation and DLQ fields to async_tasks table
-- Date: 2025-08-20

ALTER TABLE async_tasks
  ADD COLUMN visible_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER attempts,
  ADD COLUMN reserved_at TIMESTAMP NULL DEFAULT NULL AFTER visible_at,
  ADD COLUMN error_reason TEXT NULL AFTER reserved_at,
  ADD COLUMN dlq TINYINT(1) NOT NULL DEFAULT 0 AFTER error_reason,
  ADD COLUMN max_attempts INT NOT NULL DEFAULT 3 AFTER dlq,
  ADD COLUMN retry_backoff_sec INT NOT NULL DEFAULT 60 AFTER max_attempts;
