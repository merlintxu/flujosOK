-- Anonymize sensitive values in system_config
UPDATE system_config
SET config_value = NULL
WHERE config_key IN (
    'openai.api_key',
    'pipedrive.api_token',
    'ringover.api_key',
    'db.host',
    'db.database',
    'db.username',
    'db.password'
);
