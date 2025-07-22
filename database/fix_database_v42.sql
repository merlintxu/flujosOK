-- Flujos Dimension v4.2 - Corrección de Estructura de Base de Datos
-- Script para añadir columnas faltantes y corregir problemas

-- Añadir columnas faltantes a la tabla calls
ALTER TABLE calls 
ADD COLUMN IF NOT EXISTS ai_sentiment ENUM('positive', 'negative', 'neutral') DEFAULT 'neutral' AFTER ai_summary;

ALTER TABLE calls 
ADD COLUMN IF NOT EXISTS ai_transcription TEXT AFTER recording_url;

ALTER TABLE calls 
ADD COLUMN IF NOT EXISTS ai_summary TEXT AFTER ai_transcription;

ALTER TABLE calls 
ADD COLUMN IF NOT EXISTS pipedrive_contact_id INT AFTER ai_sentiment;

ALTER TABLE calls 
ADD COLUMN IF NOT EXISTS batch_id VARCHAR(50) AFTER pipedrive_contact_id;

ALTER TABLE calls 
ADD COLUMN IF NOT EXISTS sync_status ENUM('pending', 'synced', 'failed') DEFAULT 'pending' AFTER batch_id;

ALTER TABLE calls 
ADD COLUMN IF NOT EXISTS error_count INT DEFAULT 0 AFTER sync_status;

ALTER TABLE calls 
ADD COLUMN IF NOT EXISTS last_sync_at TIMESTAMP NULL AFTER error_count;

ALTER TABLE calls 
ADD COLUMN IF NOT EXISTS processing_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending' AFTER last_sync_at;

ALTER TABLE calls 
ADD COLUMN IF NOT EXISTS quality_score DECIMAL(3,2) AFTER processing_status;

ALTER TABLE calls 
ADD COLUMN IF NOT EXISTS tags JSON AFTER quality_score;

-- Añadir índices para optimización
ALTER TABLE calls ADD INDEX IF NOT EXISTS idx_ai_sentiment (ai_sentiment);
ALTER TABLE calls ADD INDEX IF NOT EXISTS idx_sync_status (sync_status);
ALTER TABLE calls ADD INDEX IF NOT EXISTS idx_processing_status (processing_status);
ALTER TABLE calls ADD INDEX IF NOT EXISTS idx_pipedrive_contact_id (pipedrive_contact_id);

-- Crear tabla de tokens JWT si no existe
CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_hash VARCHAR(255) UNIQUE,
    name VARCHAR(100),
    expires_at TIMESTAMP,
    last_used_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_token_hash (token_hash),
    INDEX idx_expires_at (expires_at),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla de logs del sistema si no existe
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level ENUM('info', 'warning', 'error', 'debug') DEFAULT 'info',
    message TEXT,
    context JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_level (level),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla de trabajos batch si no existe
CREATE TABLE IF NOT EXISTS batch_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(50) UNIQUE,
    type ENUM('sync', 'transcription', 'analysis', 'export') NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    total_items INT DEFAULT 0,
    processed_items INT DEFAULT 0,
    failed_items INT DEFAULT 0,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_batch_id (batch_id),
    INDEX idx_status (status),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla de logs de sincronización si no existe
CREATE TABLE IF NOT EXISTS sync_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source ENUM('ringover', 'pipedrive', 'openai') NOT NULL,
    action ENUM('fetch', 'create', 'update', 'delete') NOT NULL,
    status ENUM('success', 'failed') NOT NULL,
    records_processed INT DEFAULT 0,
    error_message TEXT,
    execution_time DECIMAL(8,3),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_source (source),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crear tabla de configuraciones del sistema si no existe
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_setting_key (setting_key),
    INDEX idx_is_public (is_public)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuraciones por defecto si no existen
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('app_version', '4.2', 'string', 'Versión de la aplicación', TRUE),
('sync_interval_hours', '1', 'integer', 'Intervalo de sincronización en horas', FALSE),
('max_batch_size', '100', 'integer', 'Tamaño máximo de lote para procesamiento', FALSE),
('enable_ai_processing', 'true', 'boolean', 'Habilitar procesamiento de IA', FALSE),
('default_sentiment', 'neutral', 'string', 'Sentimiento por defecto para llamadas', FALSE),
('cache_ttl_minutes', '60', 'integer', 'Tiempo de vida del caché en minutos', FALSE);

-- Actualizar llamadas existentes con valores por defecto
UPDATE calls SET ai_sentiment = 'neutral' WHERE ai_sentiment IS NULL;
UPDATE calls SET sync_status = 'pending' WHERE sync_status IS NULL;
UPDATE calls SET processing_status = 'pending' WHERE processing_status IS NULL;
UPDATE calls SET error_count = 0 WHERE error_count IS NULL;

-- Insertar datos de ejemplo si la tabla está vacía
INSERT IGNORE INTO calls (ringover_id, phone_number, direction, status, duration, ai_sentiment, created_at) VALUES
('example_v42_001', '+34600123456', 'inbound', 'answered', 180, 'positive', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
('example_v42_002', '+34600789012', 'outbound', 'answered', 240, 'neutral', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('example_v42_003', '+34600345678', 'inbound', 'missed', 0, 'neutral', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
('example_v42_004', '+34600567890', 'inbound', 'answered', 320, 'positive', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
('example_v42_005', '+34600234567', 'outbound', 'answered', 150, 'negative', DATE_SUB(NOW(), INTERVAL 5 HOUR)),
('example_v42_006', '+34600111222', 'inbound', 'answered', 280, 'positive', DATE_SUB(NOW(), INTERVAL 6 HOUR)),
('example_v42_007', '+34600333444', 'outbound', 'missed', 0, 'neutral', DATE_SUB(NOW(), INTERVAL 7 HOUR)),
('example_v42_008', '+34600555666', 'inbound', 'answered', 195, 'positive', DATE_SUB(NOW(), INTERVAL 8 HOUR)),
('example_v42_009', '+34600777888', 'inbound', 'answered', 410, 'negative', DATE_SUB(NOW(), INTERVAL 9 HOUR)),
('example_v42_010', '+34600999000', 'outbound', 'answered', 125, 'neutral', DATE_SUB(NOW(), INTERVAL 10 HOUR));

-- Verificar estructura final
SELECT 'Estructura de tabla calls verificada' as status;
DESCRIBE calls;

