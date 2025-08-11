ALTER TABLE calls
    ADD COLUMN call_id VARCHAR(255),
    ADD COLUMN contact_number VARCHAR(50),
    ADD COLUMN caller_name VARCHAR(255),
    ADD COLUMN contact_name VARCHAR(255),
    ADD COLUMN voicemail_url TEXT,
    ADD COLUMN start_time TIMESTAMP NULL,
    ADD COLUMN total_duration INT,
    ADD COLUMN incall_duration INT;

CREATE INDEX calls_call_id_idx ON calls(call_id);
CREATE INDEX calls_start_time_idx ON calls(start_time);

CREATE TABLE crm_sync_logs (
    call_id INT NOT NULL,
    result VARCHAR(20) NOT NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
