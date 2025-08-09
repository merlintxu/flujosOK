-- Add voicemail_url column for storing Ringover voicemail links
ALTER TABLE calls
    ADD COLUMN IF NOT EXISTS voicemail_url TEXT AFTER recording_url;
