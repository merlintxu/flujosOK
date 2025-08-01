-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 22-07-2025 a las 15:46:21
-- Versión del servidor: 10.11.13-MariaDB-deb11-log
-- Versión de PHP: 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `flujo_dimen_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `access_tokens`
--

CREATE TABLE `access_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `refresh_token` varchar(255) DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `revoked` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `api_audit`
--

CREATE TABLE `api_audit` (
  `id` int(11) NOT NULL,
  `api_name` varchar(50) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `request_data` text DEFAULT NULL,
  `response_data` text DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `response_time_ms` int(11) DEFAULT NULL,
  `success` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `api_configurations`
--

CREATE TABLE `api_configurations` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `api_monitoring`
--

CREATE TABLE `api_monitoring` (
  `id` int(11) NOT NULL,
  `api_name` varchar(100) NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `response_time` int(11) NOT NULL,
  `status_code` int(11) NOT NULL,
  `success` tinyint(1) NOT NULL,
  `error_message` text DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `api_monitoring`
--

INSERT INTO `api_monitoring` (`id`, `api_name`, `endpoint`, `response_time`, `status_code`, `success`, `error_message`, `timestamp`) VALUES
(1, 'Ringover', '/v2/calls', 372, 200, 1, NULL, '2025-07-18 10:37:50'),
(2, 'OpenAI', '/v1/models', 424, 200, 1, NULL, '2025-07-18 10:37:50'),
(3, 'Pipedrive', '/v1/users', 187, 200, 1, NULL, '2025-07-18 10:37:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `api_tokens`
--

CREATE TABLE `api_tokens` (
  `id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `async_tasks`
--

CREATE TABLE `async_tasks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `task_id` varchar(100) NOT NULL,
  `task_type` varchar(50) NOT NULL,
  `task_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`task_data`)),
  `priority` tinyint(4) NOT NULL DEFAULT 5,
  `status` enum('pending','processing','completed','failed','scheduled') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(4) NOT NULL DEFAULT 0,
  `scheduled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `batch_statistics`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `batch_statistics` (
`date` date
,`total_batches` bigint(21)
,`total_calls_processed` decimal(32,0)
,`total_estimated_cost` decimal(32,6)
,`avg_cost_per_batch` decimal(14,10)
,`completed_batches` bigint(21)
,`failed_batches` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cache_storage`
--

CREATE TABLE `cache_storage` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cache_key` varchar(255) NOT NULL,
  `cache_value` longtext NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `calls`
--

CREATE TABLE `calls` (
  `id` int(11) NOT NULL,
  `call_id` varchar(255) NOT NULL,
  `ringover_id` varchar(255) DEFAULT NULL,
  `phone_number` varchar(50) DEFAULT NULL,
  `caller_name` varchar(255) DEFAULT NULL,
  `contact_name` varchar(255) DEFAULT NULL,
  `direction` enum('inbound','outbound') NOT NULL,
  `duration` int(11) DEFAULT 0,
  `recording_url` text DEFAULT NULL,
  `ai_transcription` text DEFAULT NULL,
  `recording_file` varchar(255) DEFAULT NULL,
  `transcription` text DEFAULT NULL,
  `transcription_confidence` decimal(5,4) DEFAULT 0.0000,
  `analysis` text DEFAULT NULL,
  `sentiment` varchar(50) DEFAULT NULL,
  `sentiment_confidence` decimal(5,4) DEFAULT 0.0000,
  `crm_synced` tinyint(1) DEFAULT 0,
  `urgency_level` int(11) DEFAULT 0,
  `keywords` text DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `pipedrive_person_id` int(11) DEFAULT NULL,
  `pipedrive_deal_id` int(11) DEFAULT NULL,
  `status` enum('pending','processing','completed','error') DEFAULT 'pending',
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
  `pipedrive_contact_id` int(11) DEFAULT NULL,
  `action_items` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`action_items`)),
  `call_quality_score` decimal(4,3) DEFAULT NULL,
  `customer_satisfaction_score` decimal(4,3) DEFAULT NULL,
  `business_value_score` decimal(4,3) DEFAULT NULL,
  `opportunity_type` enum('venta','soporte','consulta','queja','seguimiento') DEFAULT NULL,
  `ai_processed_at` timestamp NULL DEFAULT NULL,
  `batch_id` varchar(100) DEFAULT NULL,
  `has_recording` tinyint(1) DEFAULT 0,
  `start_time` timestamp NULL DEFAULT NULL,
  `is_answered` tinyint(1) DEFAULT 0,
  `last_state` varchar(50) DEFAULT NULL,
  `total_duration` int(11) DEFAULT 0,
  `incall_duration` int(11) DEFAULT 0,
  `contact_number` varchar(50) DEFAULT NULL,
  `recording_path` varchar(500) DEFAULT NULL,
  `sentiment_numeric` tinyint(4) GENERATED ALWAYS AS (case `ai_sentiment` when 'positive' then 1 when 'neutral' then 0 when 'negative' then -1 end) STORED
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `calls`
--

INSERT INTO `calls` (`id`, `call_id`, `ringover_id`, `phone_number`, `caller_name`, `contact_name`, `direction`, `duration`, `recording_url`, `ai_transcription`, `recording_file`, `transcription`, `transcription_confidence`, `analysis`, `sentiment`, `sentiment_confidence`, `crm_synced`, `urgency_level`, `keywords`, `summary`, `pipedrive_person_id`, `pipedrive_deal_id`, `status`, `created_at`, `updated_at`, `pending_recordings`, `pending_transcriptions`, `pending_analysis`, `pending_crm_sync`, `agent_id`, `agent_name`, `transcription_text`, `sentiment_label`, `sentiment_score`, `ai_summary`, `ai_keywords`, `ai_sentiment`, `pipedrive_contact_id`, `action_items`, `call_quality_score`, `customer_satisfaction_score`, `business_value_score`, `opportunity_type`, `ai_processed_at`, `batch_id`, `has_recording`, `start_time`, `is_answered`, `last_state`, `total_duration`, `incall_duration`, `contact_number`, `recording_path`) VALUES
(1, 'call_687a168a8004a', NULL, NULL, NULL, NULL, '', 0, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 0.9000, 0, 0, '[\"servicios\",\"consultor\\u00eda\",\"precios\",\"disponibilidad\"]', NULL, NULL, NULL, 'pending', '2025-07-18 09:40:26', '2025-07-18 09:40:38', 0, 0, 0, 0, NULL, 'Juan Pérez', 'Hola, buenos días. Estoy interesado en sus servicios de consultoría. Me gustaría saber más sobre los precios y disponibilidad.', 'positivo', 0.850, 'El cliente mostró interés en los servicios de consultoría y solicitó información sobre precios y disponibilidad.', NULL, 'neutral', NULL, '[\"Proporcionar informaci\\u00f3n sobre precios\",\"Confirmar disponibilidad de servicios\"]', 0.950, 0.900, 0.800, 'consulta', '2025-07-18 09:40:38', NULL, 1, '2025-07-18 07:40:26', 1, 'ANSWERED', 180, 175, '34699450182', NULL),
(2, 'call_687a168a80081', NULL, NULL, NULL, NULL, '', 0, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 0.9000, 0, 0, '[\"propuesta\",\"personalizada\",\"reuni\\u00f3n\",\"contactar\"]', NULL, NULL, NULL, 'pending', '2025-07-18 09:40:26', '2025-07-18 09:40:42', 0, 0, 0, 0, NULL, 'María García', 'Gracias por contactarnos. Hemos revisado su solicitud y podemos ofrecerle una propuesta personalizada. ¿Cuándo sería un buen momento para una reunión?', 'positivo', 0.850, 'El agente ofreció una propuesta personalizada y solicitó un momento para programar una reunión.', NULL, 'neutral', NULL, '[\"Programar reuni\\u00f3n\",\"Enviar propuesta personalizada\"]', 0.950, 0.900, 0.800, 'venta', '2025-07-18 09:40:42', NULL, 1, '2025-07-18 08:40:26', 1, 'ANSWERED', 240, 235, '34612345678', NULL),
(3, 'call_687a168a80092', NULL, NULL, NULL, NULL, '', 0, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 0.0000, 0, 0, NULL, NULL, NULL, NULL, 'pending', '2025-07-18 09:40:26', '2025-07-18 09:40:26', 0, 0, 0, 0, NULL, '', '', NULL, NULL, NULL, NULL, 'neutral', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-07-18 09:10:26', 0, 'NO_ANSWER', 0, 0, '34687654321', NULL),
(4, '', NULL, '+34600123456', NULL, 'Cliente de Prueba', 'inbound', 180, NULL, NULL, NULL, NULL, 0.0000, NULL, NULL, 0.0000, 0, 0, '[\"factura\",\" cargo no reconocido\",\" consulta\"]', NULL, NULL, NULL, 'completed', '2025-07-18 10:30:11', '2025-07-18 10:30:14', 0, 0, 0, 0, 1, 'Agente de Prueba', 'Hola, buenos días. Llamo para consultar sobre mi factura del mes pasado. Parece que hay un cargo que no reconozco.', 'neutral', NULL, NULL, NULL, 'neutral', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 0, NULL, 0, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `call_analysis`
--

CREATE TABLE `call_analysis` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `call_id` bigint(20) UNSIGNED NOT NULL,
  `analysis_type` varchar(50) NOT NULL,
  `analysis_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`analysis_data`)),
  `confidence_score` decimal(5,4) DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `call_keywords`
--

CREATE TABLE `call_keywords` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `call_id` bigint(20) UNSIGNED NOT NULL,
  `keyword` varchar(100) NOT NULL,
  `frequency` int(11) UNSIGNED DEFAULT 1,
  `relevance_score` decimal(5,4) DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `call_quality_metrics`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `call_quality_metrics` (
`date` date
,`total_calls` bigint(21)
,`avg_quality_score` decimal(8,7)
,`avg_satisfaction_score` decimal(8,7)
,`avg_business_value` decimal(8,7)
,`positive_calls` bigint(21)
,`negative_calls` bigint(21)
,`sales_opportunities` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `call_recordings`
--

CREATE TABLE `call_recordings` (
  `id` int(11) NOT NULL,
  `call_id` int(11) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `format` varchar(10) DEFAULT 'mp3',
  `upload_date` timestamp NULL DEFAULT current_timestamp(),
  `processed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `call_stats_view`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `call_stats_view` (
`call_date` date
,`total_calls` bigint(21)
,`avg_duration` decimal(14,4)
,`inbound_calls` decimal(22,0)
,`outbound_calls` decimal(22,0)
,`positive_calls` decimal(22,0)
,`negative_calls` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `crm_contacts`
--

CREATE TABLE `crm_contacts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `pipedrive_id` int(11) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `dashboard_summary`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `dashboard_summary` (
`category` varchar(6)
,`total_metrics` int(1)
,`avg_value` decimal(3,1)
,`last_update` datetime /* mariadb-5.3 */
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `error_logs`
--

CREATE TABLE `error_logs` (
  `id` int(11) NOT NULL,
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
  `resolution_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hourly_metrics`
--

CREATE TABLE `hourly_metrics` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `hourly_metrics`
--

INSERT INTO `hourly_metrics` (`id`, `hour_timestamp`, `total_calls`, `answered_calls`, `missed_calls`, `avg_duration`, `calls_with_recording`, `transcribed_calls`, `answer_rate`, `recording_rate`, `created_at`, `updated_at`) VALUES
(1, '2025-07-18 11:00:00', 1, 0, 1, 0.00, 0, 0, 0.00, 0.00, '2025-07-18 09:21:38', '2025-07-18 09:40:44');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `openai_batches`
--

CREATE TABLE `openai_batches` (
  `id` int(11) NOT NULL,
  `batch_id` varchar(100) NOT NULL,
  `openai_batch_id` varchar(100) NOT NULL,
  `status` enum('validating','failed','in_progress','finalizing','completed','expired','cancelling','cancelled','processed') DEFAULT 'validating',
  `call_count` int(11) DEFAULT 0,
  `estimated_cost` decimal(10,6) DEFAULT 0.000000,
  `actual_cost` decimal(10,6) DEFAULT 0.000000,
  `processed_count` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `performance_stats`
--

CREATE TABLE `performance_stats` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `metric_name` varchar(100) NOT NULL,
  `metric_value` decimal(15,4) NOT NULL,
  `metric_unit` varchar(20) NOT NULL DEFAULT 'count',
  `recorded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rate_limit_config`
--

CREATE TABLE `rate_limit_config` (
  `id` int(11) NOT NULL,
  `service_name` varchar(50) NOT NULL,
  `max_requests_per_minute` int(11) DEFAULT 60,
  `max_requests_per_hour` int(11) DEFAULT 1000,
  `backoff_base_delay` int(11) DEFAULT 1,
  `backoff_multiplier` decimal(3,2) DEFAULT 2.00,
  `max_retries` int(11) DEFAULT 3,
  `max_backoff_delay` int(11) DEFAULT 60,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `rate_limit_config`
--

INSERT INTO `rate_limit_config` (`id`, `service_name`, `max_requests_per_minute`, `max_requests_per_hour`, `backoff_base_delay`, `backoff_multiplier`, `max_retries`, `max_backoff_delay`, `created_at`, `updated_at`) VALUES
(1, 'ringover', 50, 800, 2, 2.00, 5, 120, '2025-07-19 15:38:46', '2025-07-19 15:38:46');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `recent_calls_analysis`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `recent_calls_analysis` (
`id` int(11)
,`created_at` timestamp
,`phone_number` varchar(50)
,`duration` int(11)
,`ai_sentiment` enum('positive','negative','neutral')
,`sentiment_numeric` tinyint(4)
,`keywords` text
,`pipedrive_deal_id` int(11)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `recent_calls_view`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `recent_calls_view` (
`id` int(11)
,`phone_number` varchar(50)
,`contact_name` varchar(255)
,`agent_name` varchar(255)
,`direction` enum('inbound','outbound')
,`duration` int(11)
,`status` enum('pending','processing','completed','error')
,`sentiment_label` varchar(20)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sync_logs`
--

CREATE TABLE `sync_logs` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sync_history`
--

CREATE TABLE `sync_history` (
  `id` int(11) NOT NULL,
  `last_synced_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `sync_history` (`id`, `last_synced_at`) VALUES (1, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `system_alerts`
--

CREATE TABLE `system_alerts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `alert_type` varchar(50) NOT NULL,
  `severity` enum('low','medium','high','critical') DEFAULT 'medium',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `system_config`
--

CREATE TABLE `system_config` (
  `id` int(11) NOT NULL,
  `config_key` varchar(255) NOT NULL,
  `config_value` text DEFAULT NULL,
  `config_type` enum('string','integer','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `system_config`
--

INSERT INTO `system_config` (`id`, `config_key`, `config_value`, `config_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'database.enable_query_log', 'false', 'boolean', 'Habilitar logging de consultas SQL', 1, '2025-07-17 14:02:38', '2025-07-17 14:02:38'),
(2, 'system.environment', 'production', 'string', 'Entorno del sistema', 1, '2025-07-17 14:02:38', '2025-07-17 22:43:01'),
(3, 'system.debug', 'false', 'boolean', 'Modo debug del sistema', 1, '2025-07-17 14:02:38', '2025-07-17 14:02:38'),
(4, 'system.timezone', 'Europe/Madrid', 'string', 'Zona horaria del sistema', 1, '2025-07-17 14:02:38', '2025-07-17 14:02:38'),
(5, 'database_version', '3.0.0', 'string', NULL, 1, '2025-07-17 21:27:44', '2025-07-17 21:27:44'),
(6, 'last_backup', '2025-07-17 23:27:44', 'string', NULL, 1, '2025-07-17 21:27:44', '2025-07-17 21:27:44'),
(7, 'advanced.transcription_enabled', '1', 'string', 'Habilitar transcripciÃ³n automÃ¡tica', 1, '2025-07-17 22:21:22', '2025-07-17 22:21:22'),
(8, 'advanced.sentiment_analysis', '1', 'string', 'Habilitar anÃ¡lisis de sentimientos', 1, '2025-07-17 22:21:22', '2025-07-17 22:21:22'),
(9, 'advanced.crm_sync', '1', 'string', 'Habilitar sincronizaciÃ³n con CRM', 1, '2025-07-17 22:21:22', '2025-07-17 22:21:22'),
(10, 'advanced.auto_alerts', '1', 'string', 'Habilitar alertas automÃ¡ticas', 1, '2025-07-17 22:21:22', '2025-07-17 22:21:22'),
(11, 'db.host', 'localhost', 'string', 'Host de la base de datos', 1, '2025-07-17 22:43:01', '2025-07-17 22:43:01'),
(12, 'db.database', 'flujo_dimen_db', 'string', 'Nombre de la base de datos', 1, '2025-07-17 22:43:01', '2025-07-17 22:43:01'),
(13, 'db.username', 'flujodime_user', 'string', 'Usuario de la base de datos', 1, '2025-07-17 22:43:01', '2025-07-17 22:43:01'),
(14, 'openai.api_key', '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', 'string', 'Clave API de OpenAI', 1, '2025-07-17 22:43:01', '2025-07-17 22:43:01'),
(15, 'pipedrive.api_token', 'cf544ac7d7bcd0cf1cf8f4d32b6b83444caae6f6', 'string', 'Token API de Pipedrive', 1, '2025-07-17 22:43:01', '2025-07-17 22:43:01'),
(16, 'ringover.api_key', 'fae17469b847770c06352a367a12e740bf174595', 'string', 'Clave API de Ringover', 1, '2025-07-17 22:43:01', '2025-07-17 22:43:01'),
(17, 'system.version', '3.0.0', 'string', 'Versión del sistema', 1, '2025-07-17 22:43:01', '2025-07-17 22:43:01'),
(19, 'transcription.enabled', '1', 'string', 'Transcripción habilitada', 1, '2025-07-17 22:43:01', '2025-07-17 22:43:01'),
(20, 'analysis.enabled', '1', 'string', 'Análisis habilitado', 1, '2025-07-17 22:43:01', '2025-07-17 22:43:01'),
(21, 'crm.sync_enabled', '1', 'string', 'Sincronización CRM habilitada', 1, '2025-07-17 22:43:01', '2025-07-17 22:43:01'),
(22, 'ringover.status', 'inactive', 'string', 'Estado de la API de Ringover - No funcional', 1, '2025-07-18 08:15:50', '2025-07-18 08:15:50'),
(23, 'ringover.last_check', '2025-07-18 10:15:50', 'string', 'Última verificación de Ringover', 1, '2025-07-18 08:15:50', '2025-07-18 08:15:50'),
(24, 'alert.email.enabled', 'false', 'string', 'Habilitar alertas por email', 1, '2025-07-18 08:45:42', '2025-07-18 08:45:42'),
(25, 'alert.email.recipients', 'admin@flujos-dimension.com', 'string', 'Destinatarios de alertas por email', 1, '2025-07-18 08:45:42', '2025-07-18 08:45:42'),
(26, 'alert.webhook.enabled', 'false', 'string', 'Habilitar alertas por webhook', 1, '2025-07-18 08:45:42', '2025-07-18 08:45:42'),
(27, 'alert.webhook.url', '', 'string', 'URL del webhook para alertas', 1, '2025-07-18 08:45:42', '2025-07-18 08:45:42'),
(28, 'alert.threshold.critical', '5', 'string', 'Umbral de errores críticos por hora', 1, '2025-07-18 08:45:42', '2025-07-18 08:45:42'),
(29, 'alert.threshold.error', '20', 'string', 'Umbral de errores por hora', 1, '2025-07-18 08:45:42', '2025-07-18 08:45:42'),
(30, 'alert.threshold.warning', '50', 'string', 'Umbral de warnings por hora', 1, '2025-07-18 08:45:42', '2025-07-18 08:45:42'),
(31, 'alert.performance.slow_query', '5000', 'string', 'Umbral de consulta lenta (ms)', 1, '2025-07-18 08:45:42', '2025-07-18 08:45:42'),
(32, 'sync_interval_hours', '1', 'integer', 'Intervalo de sincronización en horas', 1, '2025-07-18 09:20:16', '2025-07-18 09:20:16'),
(33, 'batch_size_limit', '50', 'integer', 'Límite de llamadas por batch', 1, '2025-07-18 09:20:16', '2025-07-18 09:20:16'),
(34, 'openai_model', 'gpt-4o-mini', 'string', 'Modelo de OpenAI para análisis', 1, '2025-07-18 09:20:16', '2025-07-18 09:20:16'),
(35, 'openai_temperature', '0.3', '', 'Temperatura para análisis de OpenAI', 1, '2025-07-18 09:20:16', '2025-07-18 09:20:16'),
(36, 'openai_max_tokens', '1000', 'integer', 'Máximo de tokens por respuesta', 1, '2025-07-18 09:20:16', '2025-07-18 09:20:16'),
(37, 'cost_alert_threshold', '10.00', '', 'Umbral de alerta de costos en USD', 1, '2025-07-18 09:20:16', '2025-07-18 09:20:16'),
(38, 'auto_process_recordings', 'true', 'boolean', 'Procesar grabaciones automáticamente', 1, '2025-07-18 09:20:16', '2025-07-18 09:20:16'),
(39, 'retention_days_logs', '30', 'integer', 'Días de retención para logs', 1, '2025-07-18 09:20:16', '2025-07-18 09:20:16'),
(40, 'retention_days_metrics', '90', 'integer', 'Días de retención para métricas', 1, '2025-07-18 09:20:16', '2025-07-18 09:20:16'),
(41, 'retention_days_recordings', '60', 'integer', 'Días de retención para grabaciones', 1, '2025-07-18 09:20:16', '2025-07-18 09:20:16'),
(42, 'dashboard_enabled', '1', 'string', 'Dashboard de monitoreo habilitado', 1, '2025-07-18 10:37:50', '2025-07-18 10:37:50'),
(43, 'dashboard_refresh_interval', '30', 'string', 'Intervalo de actualización en segundos', 1, '2025-07-18 10:37:50', '2025-07-18 10:37:50'),
(44, 'dashboard_max_errors', '100', 'string', 'Máximo número de errores a mostrar', 1, '2025-07-18 10:37:50', '2025-07-18 10:37:50'),
(45, 'dashboard_retention_days', '30', 'string', 'Días de retención de datos de monitoreo', 1, '2025-07-18 10:37:50', '2025-07-18 10:37:50'),
(46, 'api_monitoring_enabled', '1', 'string', 'Monitoreo de APIs habilitado', 1, '2025-07-18 10:37:50', '2025-07-18 10:37:50'),
(47, 'performance_monitoring_enabled', '1', 'string', 'Monitoreo de rendimiento habilitado', 1, '2025-07-18 10:37:50', '2025-07-18 10:37:50');

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `system_health_view`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `system_health_view` (
`log_date` date
,`level` enum('DEBUG','INFO','WARNING','ERROR','CRITICAL')
,`count` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `level` enum('DEBUG','INFO','WARNING','ERROR','CRITICAL') DEFAULT 'INFO',
  `message` text DEFAULT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context`)),
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `system_logs`
--

INSERT INTO `system_logs` (`id`, `level`, `message`, `context`, `user_id`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 'INFO', 'Prueba del sistema de monitoreo', '{\"test\":true,\"timestamp\":\"2025-07-18 10:45:42\"}', NULL, '52.90.91.25', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36', '2025-07-18 08:45:42');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `system_monitoring`
--

CREATE TABLE `system_monitoring` (
  `id` int(11) NOT NULL,
  `metric_name` varchar(100) NOT NULL,
  `metric_value` decimal(10,2) NOT NULL,
  `metric_unit` varchar(20) DEFAULT '',
  `category` varchar(50) NOT NULL,
  `timestamp` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `system_monitoring`
--

INSERT INTO `system_monitoring` (`id`, `metric_name`, `metric_value`, `metric_unit`, `category`, `timestamp`) VALUES
(1, 'cpu_usage', 54.00, '%', 'system', '2025-07-18 10:37:50'),
(2, 'memory_usage', 62.00, '%', 'system', '2025-07-18 10:37:50'),
(3, 'disk_usage', 40.00, '%', 'system', '2025-07-18 10:37:50'),
(4, 'active_connections', 25.00, 'conn', 'database', '2025-07-18 10:37:50'),
(5, 'query_time_avg', 179.00, 'ms', 'database', '2025-07-18 10:37:50'),
(6, 'api_calls_hour', 197.00, 'calls', 'api', '2025-07-18 10:37:50'),
(7, 'error_rate', 2.00, '%', 'errors', '2025-07-18 10:37:50');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `transcriptions`
--

CREATE TABLE `transcriptions` (
  `id` int(11) NOT NULL,
  `call_id` int(11) DEFAULT NULL,
  `original_text` text DEFAULT NULL,
  `processed_text` text DEFAULT NULL,
  `confidence_score` decimal(3,2) DEFAULT NULL,
  `language` varchar(10) DEFAULT 'es',
  `processing_time` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
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
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `role`, `active`, `last_login`, `two_factor_enabled`, `two_factor_secret`, `remember_token`, `created_at`, `updated_at`, `deleted_at`, `full_name`, `is_active`) VALUES
(1, 'admin', 'admin@flujos-dimension.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', 'admin', 1, NULL, 0, NULL, NULL, '2025-07-17 21:27:44', NULL, NULL, NULL, 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `access_tokens`
--
ALTER TABLE `access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD UNIQUE KEY `refresh_token` (`refresh_token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_refresh_token` (`refresh_token`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `idx_revoked` (`revoked`);

--
-- Indices de la tabla `api_audit`
--
ALTER TABLE `api_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_api_name` (`api_name`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_success` (`success`);

--
-- Indices de la tabla `api_configurations`
--
ALTER TABLE `api_configurations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `api_name` (`api_name`),
  ADD KEY `idx_api_name` (`api_name`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `idx_health_status` (`health_status`);

--
-- Indices de la tabla `api_monitoring`
--
ALTER TABLE `api_monitoring`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_api_timestamp` (`api_name`,`timestamp`),
  ADD KEY `idx_success_timestamp` (`success`,`timestamp`);

--
-- Indices de la tabla `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `idx_token_hash` (`token_hash`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indices de la tabla `async_tasks`
--
ALTER TABLE `async_tasks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_task_id` (`task_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_task_type` (`task_type`);

--
-- Indices de la tabla `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_entity_type` (`entity_type`),
  ADD KEY `idx_entity_id` (`entity_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indices de la tabla `cache_storage`
--
ALTER TABLE `cache_storage`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_cache_key` (`cache_key`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indices de la tabla `calls`
--
ALTER TABLE `calls`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `call_id` (`call_id`),
  ADD UNIQUE KEY `idx_calls_call_id` (`call_id`),
  ADD KEY `idx_call_id` (`call_id`),
  ADD KEY `idx_phone_number` (`phone_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_calls_phone_number` (`phone_number`),
  ADD KEY `idx_calls_created_at` (`created_at`),
  ADD KEY `idx_calls_status` (`status`),
  ADD KEY `idx_agent_id` (`agent_id`),
  ADD KEY `idx_direction` (`direction`),
  ADD KEY `idx_sentiment` (`sentiment_label`),
  ADD KEY `idx_phone_agent` (`phone_number`,`agent_id`),
  ADD KEY `idx_status_created` (`status`,`created_at`),
  ADD KEY `idx_sentiment_direction` (`sentiment_label`,`direction`),
  ADD KEY `idx_duration` (`duration`),
  ADD KEY `idx_sentiment_label` (`sentiment_label`),
  ADD KEY `idx_opportunity_type` (`opportunity_type`),
  ADD KEY `idx_ai_processed_at` (`ai_processed_at`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_call_quality_score` (`call_quality_score`),
  ADD KEY `idx_business_value_score` (`business_value_score`),
  ADD KEY `idx_contact_number` (`contact_number`),
  ADD KEY `idx_start_time` (`start_time`),
  ADD KEY `idx_calls_start_time` (`start_time`),
  ADD KEY `idx_calls_agent_id` (`agent_id`),
  ADD KEY `idx_calls_direction` (`direction`),
  ADD KEY `idx_ai_sentiment` (`ai_sentiment`),
  ADD KEY `idx_calls_created_sentiment` (`created_at`,`sentiment_numeric`);
ALTER TABLE `calls` ADD FULLTEXT KEY `idx_calls_keywords` (`keywords`);

--
-- Indices de la tabla `call_analysis`
--
ALTER TABLE `call_analysis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_call_id` (`call_id`);

--
-- Indices de la tabla `call_keywords`
--
ALTER TABLE `call_keywords`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_call_id` (`call_id`),
  ADD KEY `idx_keyword` (`keyword`);

--
-- Indices de la tabla `call_recordings`
--
ALTER TABLE `call_recordings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_call_id` (`call_id`),
  ADD KEY `idx_processed` (`processed`);

--
-- Indices de la tabla `crm_contacts`
--
ALTER TABLE `crm_contacts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_pipedrive_id` (`pipedrive_id`);

--
-- Indices de la tabla `error_logs`
--
ALTER TABLE `error_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_error_type` (`error_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_resolved` (`resolved`);

--
-- Indices de la tabla `hourly_metrics`
--
ALTER TABLE `hourly_metrics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_hour` (`hour_timestamp`),
  ADD KEY `idx_hour_timestamp` (`hour_timestamp`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indices de la tabla `openai_batches`
--
ALTER TABLE `openai_batches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_batch_id` (`batch_id`),
  ADD UNIQUE KEY `unique_openai_batch_id` (`openai_batch_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indices de la tabla `performance_stats`
--
ALTER TABLE `performance_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_metric_name` (`metric_name`),
  ADD KEY `idx_recorded_at` (`recorded_at`);

--
-- Indices de la tabla `rate_limit_config`
--
ALTER TABLE `rate_limit_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_name` (`service_name`);

--
-- Indices de la tabla `sync_logs`
--
ALTER TABLE `sync_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job_id` (`job_id`),
  ADD KEY `idx_service_operation` (`service_name`,`operation`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indices de la tabla `sync_history`
--
ALTER TABLE `sync_history`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `system_alerts`
--
ALTER TABLE `system_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_alert_type` (`alert_type`),
  ADD KEY `idx_severity` (`severity`);

--
-- Indices de la tabla `system_config`
--
ALTER TABLE `system_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_config_key` (`config_key`);

--
-- Indices de la tabla `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_level` (`level`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_level_created` (`level`,`created_at`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

--
-- Indices de la tabla `system_monitoring`
--
ALTER TABLE `system_monitoring`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_metric_timestamp` (`metric_name`,`timestamp`),
  ADD KEY `idx_category_timestamp` (`category`,`timestamp`);

--
-- Indices de la tabla `transcriptions`
--
ALTER TABLE `transcriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_call_id` (`call_id`),
  ADD KEY `idx_confidence` (`confidence_score`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_active` (`active`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `access_tokens`
--
ALTER TABLE `access_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `api_audit`
--
ALTER TABLE `api_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `api_configurations`
--
ALTER TABLE `api_configurations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `api_monitoring`
--
ALTER TABLE `api_monitoring`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `api_tokens`
--
ALTER TABLE `api_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `async_tasks`
--
ALTER TABLE `async_tasks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cache_storage`
--
ALTER TABLE `cache_storage`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `calls`
--
ALTER TABLE `calls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `call_analysis`
--
ALTER TABLE `call_analysis`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `call_keywords`
--
ALTER TABLE `call_keywords`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `call_recordings`
--
ALTER TABLE `call_recordings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `crm_contacts`
--
ALTER TABLE `crm_contacts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `error_logs`
--
ALTER TABLE `error_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `hourly_metrics`
--
ALTER TABLE `hourly_metrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `openai_batches`
--
ALTER TABLE `openai_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `performance_stats`
--
ALTER TABLE `performance_stats`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rate_limit_config`
--
ALTER TABLE `rate_limit_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `sync_logs`
--
ALTER TABLE `sync_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sync_history`
--
ALTER TABLE `sync_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `system_alerts`
--
ALTER TABLE `system_alerts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `system_config`
--
ALTER TABLE `system_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT de la tabla `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `system_monitoring`
--
ALTER TABLE `system_monitoring`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `transcriptions`
--
ALTER TABLE `transcriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

-- --------------------------------------------------------

--
-- Estructura para la vista `batch_statistics`
--
DROP TABLE IF EXISTS `batch_statistics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`flujodime_user`@`localhost` SQL SECURITY DEFINER VIEW `batch_statistics`  AS SELECT cast(`openai_batches`.`created_at` as date) AS `date`, count(0) AS `total_batches`, sum(`openai_batches`.`call_count`) AS `total_calls_processed`, sum(`openai_batches`.`estimated_cost`) AS `total_estimated_cost`, avg(`openai_batches`.`estimated_cost`) AS `avg_cost_per_batch`, count(case when `openai_batches`.`status` = 'completed' then 1 end) AS `completed_batches`, count(case when `openai_batches`.`status` = 'failed' then 1 end) AS `failed_batches` FROM `openai_batches` GROUP BY cast(`openai_batches`.`created_at` as date) ORDER BY cast(`openai_batches`.`created_at` as date) DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `call_quality_metrics`
--
DROP TABLE IF EXISTS `call_quality_metrics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`flujodime_user`@`localhost` SQL SECURITY DEFINER VIEW `call_quality_metrics`  AS SELECT cast(coalesce(`calls`.`start_time`,`calls`.`created_at`) as date) AS `date`, count(0) AS `total_calls`, avg(`calls`.`call_quality_score`) AS `avg_quality_score`, avg(`calls`.`customer_satisfaction_score`) AS `avg_satisfaction_score`, avg(`calls`.`business_value_score`) AS `avg_business_value`, count(case when `calls`.`sentiment_label` = 'positivo' then 1 end) AS `positive_calls`, count(case when `calls`.`sentiment_label` = 'negativo' then 1 end) AS `negative_calls`, count(case when `calls`.`opportunity_type` = 'venta' then 1 end) AS `sales_opportunities` FROM `calls` WHERE `calls`.`ai_processed_at` is not null GROUP BY cast(coalesce(`calls`.`start_time`,`calls`.`created_at`) as date) ORDER BY cast(coalesce(`calls`.`start_time`,`calls`.`created_at`) as date) DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `call_stats_view`
--
DROP TABLE IF EXISTS `call_stats_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`flujodime_user`@`localhost` SQL SECURITY DEFINER VIEW `call_stats_view`  AS SELECT cast(`calls`.`created_at` as date) AS `call_date`, count(0) AS `total_calls`, avg(`calls`.`duration`) AS `avg_duration`, sum(case when `calls`.`direction` = 'inbound' then 1 else 0 end) AS `inbound_calls`, sum(case when `calls`.`direction` = 'outbound' then 1 else 0 end) AS `outbound_calls`, sum(case when `calls`.`sentiment_label` = 'positive' then 1 else 0 end) AS `positive_calls`, sum(case when `calls`.`sentiment_label` = 'negative' then 1 else 0 end) AS `negative_calls` FROM `calls` WHERE `calls`.`created_at` >= current_timestamp() - interval 90 day GROUP BY cast(`calls`.`created_at` as date) ORDER BY cast(`calls`.`created_at` as date) DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `dashboard_summary`
--
DROP TABLE IF EXISTS `dashboard_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`flujodime_user`@`localhost` SQL SECURITY DEFINER VIEW `dashboard_summary`  AS SELECT 'system' AS `category`, 7 AS `total_metrics`, 50.5 AS `avg_value`, current_timestamp() AS `last_update` ;

-- --------------------------------------------------------

--
-- Estructura para la vista `recent_calls_analysis`
--
DROP TABLE IF EXISTS `recent_calls_analysis`;

CREATE ALGORITHM=UNDEFINED DEFINER=`flujodime_user`@`localhost` SQL SECURITY DEFINER VIEW `recent_calls_analysis`  AS SELECT `c`.`id` AS `id`, `c`.`created_at` AS `created_at`, `c`.`phone_number` AS `phone_number`, `c`.`duration` AS `duration`, `c`.`ai_sentiment` AS `ai_sentiment`, `c`.`sentiment_numeric` AS `sentiment_numeric`, json_extract(`c`.`analysis`,'$.keywords') AS `keywords`, `c`.`pipedrive_deal_id` AS `pipedrive_deal_id` FROM `calls` AS `c` WHERE `c`.`created_at` >= current_timestamp() - interval 30 day ;

-- --------------------------------------------------------

--
-- Estructura para la vista `recent_calls_view`
--
DROP TABLE IF EXISTS `recent_calls_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`flujodime_user`@`localhost` SQL SECURITY DEFINER VIEW `recent_calls_view`  AS SELECT `calls`.`id` AS `id`, `calls`.`phone_number` AS `phone_number`, `calls`.`contact_name` AS `contact_name`, `calls`.`agent_name` AS `agent_name`, `calls`.`direction` AS `direction`, `calls`.`duration` AS `duration`, `calls`.`status` AS `status`, `calls`.`sentiment_label` AS `sentiment_label`, `calls`.`created_at` AS `created_at` FROM `calls` WHERE `calls`.`created_at` >= current_timestamp() - interval 30 day ORDER BY `calls`.`created_at` DESC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `system_health_view`
--
DROP TABLE IF EXISTS `system_health_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`flujodime_user`@`localhost` SQL SECURITY DEFINER VIEW `system_health_view`  AS SELECT cast(`system_logs`.`created_at` as date) AS `log_date`, `system_logs`.`level` AS `level`, count(0) AS `count` FROM `system_logs` WHERE `system_logs`.`created_at` >= current_timestamp() - interval 7 day GROUP BY cast(`system_logs`.`created_at` as date), `system_logs`.`level` ORDER BY cast(`system_logs`.`created_at` as date) DESC, `system_logs`.`level` ASC ;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `access_tokens`
--
ALTER TABLE `access_tokens`
  ADD CONSTRAINT `access_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `call_recordings`
--
ALTER TABLE `call_recordings`
  ADD CONSTRAINT `call_recordings_ibfk_1` FOREIGN KEY (`call_id`) REFERENCES `calls` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `transcriptions`
--
ALTER TABLE `transcriptions`
  ADD CONSTRAINT `transcriptions_ibfk_1` FOREIGN KEY (`call_id`) REFERENCES `calls` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
