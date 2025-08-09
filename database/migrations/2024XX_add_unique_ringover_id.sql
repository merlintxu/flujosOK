-- Ensure unique ringover_id values
ALTER TABLE calls
    ADD UNIQUE INDEX IF NOT EXISTS calls_ringover_id_unique (ringover_id);
