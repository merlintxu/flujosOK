-- Expand status to capture call outcomes and remove auxiliary columns.
ALTER TABLE calls
    MODIFY COLUMN status ENUM('pending','completed','answered','missed','busy','failed') DEFAULT 'pending',
    DROP COLUMN IF EXISTS is_answered,
    DROP COLUMN IF EXISTS last_state;
