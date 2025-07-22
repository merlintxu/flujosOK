-- Flujos Dimension v4.2 - Esquema de Base de Datos Completo
-- Migrado y mejorado desde v3

-- Tabla principal de llamadas (mejorada)
CREATE TABLE IF NOT EXISTS calls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ringover_id VARCHAR(100) UNIQUE,
    phone_number VARCHAR(20) NOT NULL,
    direction ENUM('inbound', 'outbound') NOT NULL,
    status ENUM('answered', 'missed', 'busy', 'failed') NOT NULL,
    duration INT DEFAULT 0,
    recording_url TEXT,
    ai_transcription TEXT,
    ai_summary TEXT,
    ai_sentiment ENUM('positive', 'negative', 'neutral') DEFAULT 'neutral',
    pipedrive_contact_id INT,
    batch_id VARCHAR(50),
    sync_status ENUM('pending', 'synced', 'failed') DEFAULT 'pending',
    error_count INT DEFAULT 0,
    last_sync_at TIMESTAMP NULL,
    processing_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    quality_score DECIMAL(3,2),
    tags JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_phone_number (phone_number),
    INDEX idx_direction (direction),
    INDEX idx_status (status),
    INDEX idx_ai_sentiment (ai_sentiment),
    INDEX idx_created_at (created_at),
    INDEX idx_ringover_id (ringover_id),
    INDEX idx_sync_status (sync_status),
    INDEX idx_processing_status (processing_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de tokens JWT
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

-- Tabla de logs del sistema
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level ENUM('info', 'warning', 'error', 'debug') DEFAULT 'info',
    message TEXT,
    context JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_level (level),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de trabajos batch
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

-- Tabla de logs de sincronización
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

-- Tabla de límites de API
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_name VARCHAR(50) NOT NULL,
    endpoint VARCHAR(100),
    requests_count INT DEFAULT 0,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    window_duration INT DEFAULT 3600,
    max_requests INT DEFAULT 1000,
    
    UNIQUE KEY unique_api_endpoint (api_name, endpoint),
    INDEX idx_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de sesiones de usuario
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_data TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    
    INDEX idx_last_activity (last_activity),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de configuraciones del sistema
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

-- Tabla de métricas de rendimiento
CREATE TABLE IF NOT EXISTS performance_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(100) NOT NULL,
    metric_value DECIMAL(10,4),
    metric_unit VARCHAR(20),
    endpoint VARCHAR(100),
    execution_time DECIMAL(8,3),
    memory_usage INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_metric_name (metric_name),
    INDEX idx_endpoint (endpoint),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuraciones por defecto
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('app_version', '4.2', 'string', 'Versión de la aplicación', TRUE),
('sync_interval_hours', '1', 'integer', 'Intervalo de sincronización en horas', FALSE),
('max_batch_size', '100', 'integer', 'Tamaño máximo de lote para procesamiento', FALSE),
('enable_ai_processing', 'true', 'boolean', 'Habilitar procesamiento de IA', FALSE),
('default_sentiment', 'neutral', 'string', 'Sentimiento por defecto para llamadas', FALSE),
('cache_ttl_minutes', '60', 'integer', 'Tiempo de vida del caché en minutos', FALSE);

-- Insertar datos de ejemplo si no existen
INSERT IGNORE INTO calls (ringover_id, phone_number, direction, status, duration, ai_sentiment, created_at) VALUES
('example_001', '+34600123456', 'inbound', 'answered', 180, 'positive', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
('example_002', '+34600789012', 'outbound', 'answered', 240, 'neutral', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
('example_003', '+34600345678', 'inbound', 'missed', 0, 'neutral', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
('example_004', '+34600567890', 'inbound', 'answered', 320, 'positive', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
('example_005', '+34600234567', 'outbound', 'answered', 150, 'negative', DATE_SUB(NOW(), INTERVAL 5 HOUR));
