-- =========================================================
-- FlujosOK - Esquema canónico unificado (2025-08-11)
-- - Corrige duplicado pipedrive_person_id en `calls`
-- - Pliega migraciones 20250811 y 20250820
-- - Unifica charset/collation a utf8mb4_unicode_ci
-- - Crea índices sin duplicados
-- - Anonimiza system_config (sin secretos)
-- =========================================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- Opcional: borra todo lo existente
DROP VIEW IF EXISTS batch_statistics, call_quality_metrics, call_stats_view,
                    dashboard_summary, recent_calls_analysis, recent_calls_view, system_health_view;

DROP TABLE IF EXISTS
access_tokens, api_audit, api_configurations, api_monitoring, api_tokens,
async_tasks, audit_logs, cache_storage, call_analysis, call_keywords,
call_recordings, calls, crm_contacts, error_logs, hourly_metrics, openai_batches,
performance_stats, rate_limit_config, sync_history, sync_logs, system_alerts,
system_config, system_logs, system_monitoring, transcriptions, users, webhooks;

-- =========================================================
-- Tablas base
-- =========================================================

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `role` enum('admin','manager','user') DEFAULT 'user',
  `active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_active` (`active`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`,`username`,`email`,`password_hash`,`first_name`,`last_name`,`role`,`active`,`created_at`,`is_active`)
VALUES (1,'admin','admin@flujos-dimension.com',
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Admin','User','admin',1,current_timestamp(),1);

CREATE TABLE `access_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `refresh_token` varchar(255) DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `revoked` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  UNIQUE KEY `refresh_token` (`refresh_token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_revoked` (`revoked`),
  CONSTRAINT `access_tokens_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token_hash` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_name` varchar(50) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `request_data` text DEFAULT NULL,
  `response_data` text DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `response_time_ms` int(11) DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_api_name` (`api_name`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_success` (`success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_configurations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_name` varchar(50) NOT NULL,
  `api_url` varchar(255) NOT NULL,
  `api_key_encrypted` text DEFAULT NULL,
  `api_token_encrypted` text DEFAULT NULL,
  `timeout_seconds` int(11) DEFAULT 30,
  `rate_limit_per_minute` int(11) DEFAULT 60,
  `active` tinyint(1) DEFAULT 1,
  `last_health_check` timestamp NULL DEFAULT NULL,
  `health_status` enum('healthy','degraded','down') DEFAULT 'healthy',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_name` (`api_name`),
  KEY `idx_active` (`active`),
  KEY `idx_health_status` (`health_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_monitoring` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service` varchar(100) NOT NULL,
  `request_path` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `response_time` int(11) NOT NULL,
  `status_code` int(11) NOT NULL,
  `success` tinyint(1) NOT NULL,
  `correlation_id` varchar(255) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_service_timestamp` (`service`,`timestamp`),
  KEY `idx_success_timestamp` (`success`,`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `async_tasks` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` varchar(100) NOT NULL,
  `task_type` varchar(50) NOT NULL,
  `task_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`task_data`)),
  `priority` tinyint(4) NOT NULL DEFAULT 5,
  `status` enum('pending','processing','completed','failed','scheduled') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(4) NOT NULL DEFAULT 0,
  `scheduled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_task_id` (`task_id`),
  KEY `idx_status` (`status`),
  KEY `idx_task_type` (`task_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_entity_type` (`entity_type`),
  KEY `idx_entity_id` (`entity_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `audit_logs_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_storage` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cache_key` varchar(255) NOT NULL,
  `cache_value` longtext NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_cache_key` (`cache_key`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================  CALLS  ======================
CREATE TABLE `calls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `call_id` varchar(255) NOT NULL,
  `ringover_id` varchar(255) DEFAULT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `caller_name` varchar(255) DEFAULT NULL,
  `contact_name` varchar(255) DEFAULT NULL,
  `direction` enum('inbound','outbound') NOT NULL,
  `duration` int(11) DEFAULT 0,
  `recording_url` text DEFAULT NULL,
  `voicemail_url` text DEFAULT NULL,
  `ai_transcription` text DEFAULT NULL,
  `recording_file` varchar(255) DEFAULT NULL,
  `transcription` text DEFAULT NULL,
  `transcription_confidence` decimal(5,4) DEFAULT 0.0000,
  `analysis` longtext DEFAULT NULL,
  `sentiment` varchar(50) DEFAULT NULL,
  `sentiment_confidence` decimal(5,4) DEFAULT 0.0000,
  `crm_synced` tinyint(1) DEFAULT 0,
  `urgency_level` int(11) DEFAULT 0,
  `keywords` text DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `pipedrive_person_id` int(11) DEFAULT NULL,                 -- (CORREGIDO: solo una vez)
  `pipedrive_deal_id` int(11) DEFAULT NULL,
  `status` enum('pending','completed','answered','missed','busy','failed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pending_recordings` tinyint(1) DEFAULT 0,
  `pending_transcriptions` tinyint(1) DEFAULT 0,
  `pending_analysis` tinyint(1) DEFAULT 0,
  `pending_crm_sync` tinyint(1) DEFAULT 0,
  `agent_id` int(11) DEFAULT NULL,
  `agent_name` varchar(255) DEFAULT NULL,
  `transcription_text` text DEFAULT NULL,
  `sentiment_label` varchar(20) DEFAULT NULL,
  `sentiment_score` decimal(4,3) DEFAULT NULL,
  `ai_summary` text DEFAULT NULL,
  `ai_keywords` text DEFAULT NULL,
  `ai_sentiment` enum('positive','negative','neutral') DEFAULT 'neutral',
  `action_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`action_items`)),
  `call_quality_score` decimal(4,3) DEFAULT NULL,
  `customer_satisfaction_score` decimal(4,3) DEFAULT NULL,
  `business_value_score` decimal(4,3) DEFAULT NULL,
  `opportunity_type` enum('venta','soporte','consulta','queja','seguimiento') DEFAULT NULL,
  `ai_processed_at` timestamp NULL DEFAULT NULL,
  `batch_id` varchar(100) DEFAULT NULL,                       -- Plegado de 20250820
  `correlation_id` varchar(255) DEFAULT NULL,                 -- Plegado de 20250820
  `has_recording` tinyint(1) DEFAULT 0,
  `start_time` timestamp NULL DEFAULT NULL,                   -- Plegado de 20250811
  `total_duration` int(11) DEFAULT 0,
  `incall_duration` int(11) DEFAULT 0,
  `contact_number` varchar(50) DEFAULT NULL,                   -- Plegado de 20250811
  `recording_path` varchar(500) DEFAULT NULL,
  `answered_time` timestamp NULL DEFAULT NULL,                 -- NUEVO
  `end_time` timestamp NULL DEFAULT NULL,                      -- NUEVO
  `queue_duration` int(11) DEFAULT NULL,                       -- NUEVO
  `ringing_duration` int(11) DEFAULT NULL,                     -- NUEVO
  `channel_id` varchar(50) DEFAULT NULL,                       -- NUEVO
  `sentiment_numeric` tinyint(4) GENERATED ALWAYS AS (
    CASE `ai_sentiment`
      WHEN 'positive' THEN 1
      WHEN 'neutral'  THEN 0
      WHEN 'negative' THEN -1
    END
  ) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_call_id` (`call_id`),
  UNIQUE KEY `uq_ringover_id` (`ringover_id`),
  KEY `idx_phone_number` (`phone_number`),
  KEY `idx_status` (`status`),
  KEY `idx_agent_id` (`agent_id`),
  KEY `idx_direction` (`direction`),
  KEY `idx_sentiment_label` (`sentiment_label`),
  KEY `idx_status_created` (`status`,`created_at`),
  KEY `idx_duration` (`duration`),
  KEY `idx_opportunity_type` (`opportunity_type`),
  KEY `idx_ai_processed_at` (`ai_processed_at`),
  KEY `idx_batch_id` (`batch_id`),
  KEY `idx_correlation_id` (`correlation_id`),
  KEY `idx_contact_number` (`contact_number`),
  KEY `idx_start_time` (`start_time`),
  FULLTEXT KEY `ft_keywords` (`keywords`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `call_recordings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `call_id` int(11) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `format` varchar(10) DEFAULT 'mp3',
  `upload_date` timestamp NULL DEFAULT current_timestamp(),
  `processed` tinyint(1) DEFAULT 0,
  `job_status` enum('queued','in_progress','done','failed') DEFAULT 'queued',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_call_id` (`call_id`),
  KEY `idx_processed` (`processed`),
  CONSTRAINT `call_recordings_call_fk` FOREIGN KEY (`call_id`) REFERENCES `calls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `call_analysis` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `call_id` bigint(20) UNSIGNED NOT NULL,
  `analysis_type` varchar(50) NOT NULL,
  `analysis_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`analysis_data`)),
  `confidence_score` decimal(5,4) DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_call_id` (`call_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `call_keywords` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `call_id` bigint(20) UNSIGNED NOT NULL,
  `keyword` varchar(100) NOT NULL,
  `frequency` int(11) UNSIGNED DEFAULT 1,
  `relevance_score` decimal(5,4) DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_call_id` (`call_id`),
  KEY `idx_keyword` (`keyword`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `crm_contacts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pipedrive_id` int(11) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_pipedrive_id` (`pipedrive_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `crm_sync_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `call_id` int(11) NOT NULL,
  `result` varchar(20) NOT NULL,
  `error_message` text NULL,
  `correlation_id` varchar(255) DEFAULT NULL,
  `batch_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_call_id` (`call_id`),
  KEY `idx_batch_id` (`batch_id`),
  KEY `idx_correlation_id` (`correlation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `error_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `error_type` varchar(50) NOT NULL,
  `error_message` text NOT NULL,
  `error_code` varchar(50) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `line_number` int(11) DEFAULT NULL,
  `stack_trace` text DEFAULT NULL,
  `request_data` text DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `resolved` tinyint(1) DEFAULT 0,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_error_type` (`error_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_resolved` (`resolved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hourly_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hour_timestamp` datetime NOT NULL,
  `total_calls` int(11) DEFAULT 0,
  `answered_calls` int(11) DEFAULT 0,
  `missed_calls` int(11) DEFAULT 0,
  `avg_duration` decimal(8,2) DEFAULT 0.00,
  `calls_with_recording` int(11) DEFAULT 0,
  `transcribed_calls` int(11) DEFAULT 0,
  `answer_rate` decimal(5,2) DEFAULT 0.00,
  `recording_rate` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_hour` (`hour_timestamp`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `openai_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` varchar(100) NOT NULL,
  `openai_batch_id` varchar(100) NOT NULL,
  `status` enum('validating','failed','in_progress','finalizing','completed','expired','cancelling','cancelled','processed') DEFAULT 'validating',
  `call_count` int(11) DEFAULT 0,
  `estimated_cost` decimal(10,6) DEFAULT 0.000000,
  `actual_cost` decimal(10,6) DEFAULT 0.000000,
  `processed_count` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_batch_id` (`batch_id`),
  UNIQUE KEY `unique_openai_batch_id` (`openai_batch_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `performance_stats` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `metric_name` varchar(100) NOT NULL,
  `metric_value` decimal(15,4) NOT NULL,
  `metric_unit` varchar(20) NOT NULL DEFAULT 'count',
  `recorded_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_metric_name` (`metric_name`),
  KEY `idx_recorded_at` (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rate_limit_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(50) NOT NULL,
  `max_requests_per_minute` int(11) DEFAULT 60,
  `max_requests_per_hour` int(11) DEFAULT 1000,
  `backoff_base_delay` int(11) DEFAULT 1,
  `backoff_multiplier` decimal(3,2) DEFAULT 2.00,
  `max_retries` int(11) DEFAULT 3,
  `max_backoff_delay` int(11) DEFAULT 60,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_name` (`service_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sync_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `last_synced_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sync_history` (`id`,`last_synced_at`) VALUES (1,NULL);

CREATE TABLE `sync_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` varchar(100) NOT NULL,
  `service_name` varchar(50) NOT NULL,
  `operation` varchar(50) NOT NULL,
  `status` enum('started','in_progress','completed','failed') DEFAULT 'started',
  `total_records` int(11) DEFAULT 0,
  `processed_records` int(11) DEFAULT 0,
  `error_count` int(11) DEFAULT 0,
  `execution_time` decimal(8,2) DEFAULT 0.00,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_job_id` (`job_id`),
  KEY `idx_service_operation` (`service_name`,`operation`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_alerts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `alert_type` varchar(50) NOT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_alert_type` (`alert_type`),
  KEY `idx_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(255) NOT NULL,
  `config_value` text DEFAULT NULL,
  `config_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sanitización (plegado de 20240906120000)
UPDATE system_config
SET config_value = NULL
WHERE config_key IN (
  'openai.api_key','pipedrive.api_token','ringover.api_key',
  'db.host','db.database','db.username','db.password'
);

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level` enum('DEBUG','INFO','WARNING','ERROR','CRITICAL') DEFAULT 'INFO',
  `message` text DEFAULT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context`)),
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_level` (`level`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_level_created` (`level`,`created_at`),
  KEY `idx_user_created` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_monitoring` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `metric_name` varchar(100) NOT NULL,
  `metric_value` decimal(10,2) NOT NULL,
  `metric_unit` varchar(20) DEFAULT '',
  `category` varchar(50) NOT NULL,
  `timestamp` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_metric_timestamp` (`metric_name`,`timestamp`),
  KEY `idx_category_timestamp` (`category`,`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `transcriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `call_id` int(11) DEFAULT NULL,
  `original_text` text DEFAULT NULL,
  `processed_text` text DEFAULT NULL,
  `confidence_score` decimal(3,2) DEFAULT NULL,
  `language` varchar(10) DEFAULT 'es',
  `processing_time` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_call_id` (`call_id`),
  KEY `idx_confidence` (`confidence_score`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `transcriptions_call_fk` FOREIGN KEY (`call_id`) REFERENCES `calls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(255) NOT NULL,
  `event` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- Vistas
-- =========================================================

CREATE VIEW `batch_statistics` AS
SELECT CAST(`openai_batches`.`created_at` AS DATE) AS `date`,
       COUNT(*) AS `total_batches`,
       SUM(`openai_batches`.`call_count`) AS `total_calls_processed`,
       SUM(`openai_batches`.`estimated_cost`) AS `total_estimated_cost`,
       AVG(`openai_batches`.`estimated_cost`) AS `avg_cost_per_batch`,
       COUNT(CASE WHEN `openai_batches`.`status`='completed' THEN 1 END) AS `completed_batches`,
       COUNT(CASE WHEN `openai_batches`.`status`='failed' THEN 1 END) AS `failed_batches`
FROM `openai_batches`
GROUP BY CAST(`openai_batches`.`created_at` AS DATE)
ORDER BY `date` DESC;

CREATE VIEW `call_quality_metrics` AS
SELECT CAST(COALESCE(c.`start_time`, c.`created_at`) AS DATE) AS `date`,
       COUNT(*) AS `total_calls`,
       AVG(c.`call_quality_score`) AS `avg_quality_score`,
       AVG(c.`customer_satisfaction_score`) AS `avg_satisfaction_score`,
       AVG(c.`business_value_score`) AS `avg_business_value`,
       COUNT(CASE WHEN c.`sentiment_label`='positivo' THEN 1 END) AS `positive_calls`,
       COUNT(CASE WHEN c.`sentiment_label`='negativo' THEN 1 END) AS `negative_calls`,
       COUNT(CASE WHEN c.`opportunity_type`='venta' THEN 1 END) AS `sales_opportunities`
FROM `calls` c
WHERE c.`ai_processed_at` IS NOT NULL
GROUP BY `date`
ORDER BY `date` DESC;

CREATE VIEW `call_stats_view` AS
SELECT CAST(c.`created_at` AS DATE) AS `call_date`,
       COUNT(*) AS `total_calls`,
       AVG(c.`duration`) AS `avg_duration`,
       SUM(CASE WHEN c.`direction`='inbound'  THEN 1 ELSE 0 END) AS `inbound_calls`,
       SUM(CASE WHEN c.`direction`='outbound' THEN 1 ELSE 0 END) AS `outbound_calls`,
       SUM(CASE WHEN c.`sentiment_label`='positive' THEN 1 ELSE 0 END) AS `positive_calls`,
       SUM(CASE WHEN c.`sentiment_label`='negative' THEN 1 ELSE 0 END) AS `negative_calls`
FROM `calls` c
WHERE c.`created_at` >= CURRENT_TIMESTAMP() - INTERVAL 90 DAY
GROUP BY `call_date`
ORDER BY `call_date` DESC;

CREATE VIEW `dashboard_summary` AS
SELECT 'system' AS `category`, 7 AS `total_metrics`, 50.5 AS `avg_value`, CURRENT_TIMESTAMP() AS `last_update`;

CREATE VIEW `recent_calls_analysis` AS
SELECT c.`id`, c.`created_at`, c.`phone_number`, c.`duration`, c.`ai_sentiment`,
       c.`sentiment_numeric`, JSON_EXTRACT(c.`analysis`, '$.keywords') AS `keywords`,
       c.`pipedrive_deal_id`
FROM `calls` c
WHERE c.`created_at` >= CURRENT_TIMESTAMP() - INTERVAL 30 DAY;

CREATE VIEW `recent_calls_view` AS
SELECT c.`id`, c.`phone_number`, c.`contact_name`, c.`agent_name`,
       c.`direction`, c.`duration`, c.`status`, c.`sentiment_label`, c.`created_at`
FROM `calls` c
WHERE c.`created_at` >= CURRENT_TIMESTAMP() - INTERVAL 30 DAY
ORDER BY c.`created_at` DESC;

CREATE VIEW `system_health_view` AS
SELECT CAST(s.`created_at` AS DATE) AS `log_date`,
       s.`level`, COUNT(*) AS `count`
FROM `system_logs` s
WHERE s.`created_at` >= CURRENT_TIMESTAMP() - INTERVAL 7 DAY
GROUP BY `log_date`, s.`level`
ORDER BY `log_date` DESC, s.`level` ASC;

COMMIT;
SET FOREIGN_KEY_CHECKS=1;
