-- Example idempotent migration for call recordings support.
CREATE TABLE IF NOT EXISTS call_recordings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_id BIGINT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    duration INT,
    format VARCHAR(10) DEFAULT 'mp3',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE calls
    ADD COLUMN IF NOT EXISTS recording_path VARCHAR(500),
    ADD COLUMN IF NOT EXISTS has_recording TINYINT(1) DEFAULT 0;
