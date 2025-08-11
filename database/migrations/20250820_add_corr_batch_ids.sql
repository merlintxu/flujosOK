ALTER TABLE calls
    ADD COLUMN correlation_id VARCHAR(255) UNIQUE,
    ADD COLUMN batch_id VARCHAR(255);

CREATE INDEX calls_batch_id_idx ON calls(batch_id);
CREATE INDEX calls_correlation_id_idx ON calls(correlation_id);

ALTER TABLE crm_sync_logs
    ADD COLUMN correlation_id VARCHAR(255),
    ADD COLUMN batch_id VARCHAR(255);

CREATE INDEX crm_sync_logs_batch_id_idx ON crm_sync_logs(batch_id);
CREATE INDEX crm_sync_logs_correlation_id_idx ON crm_sync_logs(correlation_id);
