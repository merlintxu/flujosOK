-- Migration: 001_add_correlation_ids.sql
-- Description: Add correlation_id fields for traceability across all relevant tables
-- Date: 2025-08-12
-- Version: 4.2

-- Add correlation_id to calls table if not exists
ALTER TABLE calls 
ADD COLUMN IF NOT EXISTS correlation_id VARCHAR(255) DEFAULT NULL,
ADD INDEX IF NOT EXISTS idx_calls_correlation_id (correlation_id);

-- Add correlation_id to transcriptions table if not exists  
ALTER TABLE transcriptions 
ADD COLUMN IF NOT EXISTS correlation_id VARCHAR(255) DEFAULT NULL,
ADD INDEX IF NOT EXISTS idx_transcriptions_correlation_id (correlation_id);

-- Add correlation_id to api_audit table if not exists
ALTER TABLE api_audit 
ADD COLUMN IF NOT EXISTS correlation_id VARCHAR(255) DEFAULT NULL,
ADD INDEX IF NOT EXISTS idx_api_audit_correlation_id (correlation_id);

-- Add correlation_id to api_monitoring table if not exists
ALTER TABLE api_monitoring 
ADD COLUMN IF NOT EXISTS correlation_id VARCHAR(255) DEFAULT NULL,
ADD INDEX IF NOT EXISTS idx_api_monitoring_correlation_id (correlation_id);

-- Add correlation_id to async_tasks table if not exists
ALTER TABLE async_tasks 
ADD COLUMN IF NOT EXISTS correlation_id VARCHAR(255) DEFAULT NULL,
ADD INDEX IF NOT EXISTS idx_async_tasks_correlation_id (correlation_id);

-- Add correlation_id to crm_sync_logs table if not exists
ALTER TABLE crm_sync_logs 
ADD COLUMN IF NOT EXISTS correlation_id VARCHAR(255) DEFAULT NULL,
ADD INDEX IF NOT EXISTS idx_crm_sync_logs_correlation_id (correlation_id);

-- Update existing records with generated correlation IDs where NULL
UPDATE calls SET correlation_id = UUID() WHERE correlation_id IS NULL;
UPDATE transcriptions SET correlation_id = UUID() WHERE correlation_id IS NULL;
UPDATE api_audit SET correlation_id = UUID() WHERE correlation_id IS NULL;
UPDATE api_monitoring SET correlation_id = UUID() WHERE correlation_id IS NULL;
UPDATE async_tasks SET correlation_id = UUID() WHERE correlation_id IS NULL;
UPDATE crm_sync_logs SET correlation_id = UUID() WHERE correlation_id IS NULL;

-- Add comment to document the purpose
ALTER TABLE calls COMMENT = 'Calls table with correlation_id for traceability - v4.2';
ALTER TABLE transcriptions COMMENT = 'Transcriptions table with correlation_id for traceability - v4.2';
ALTER TABLE api_audit COMMENT = 'API audit table with correlation_id for traceability - v4.2';
ALTER TABLE api_monitoring COMMENT = 'API monitoring table with correlation_id for traceability - v4.2';
ALTER TABLE async_tasks COMMENT = 'Async tasks table with correlation_id for traceability - v4.2';
ALTER TABLE crm_sync_logs COMMENT = 'CRM sync logs table with correlation_id for traceability - v4.2';

