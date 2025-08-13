Rol: Codex. Implementa cada tarea de forma aislada, con evidencia y commits separados.

## Tareas
11) Cuotas/backoff unificados por servicio:
   - Crea tabla `rate_limit_config` con políticas RPM/RPH por `service_name`.
   - HttpClient central: respeta `Retry-After`, reintentos exponenciales con jitter y para `429/5xx`.
   - `Core\RateLimiter` consume tokens por endpoint.
   - Validación: logs muestran backoff aplicado y éxito posterior; ausencia de 429 persistentes en pruebas.

12) Cola de trabajos robusta:
   - Ampliar `async_tasks` con: `visible_at`, `reserved_at`, `error_reason`, `dlq` (bool), `max_attempts`, `retry_backoff_sec`.
   - Crear comando `php bin/console queue:work` con ciclo **fetch-reserve-execute-release/bury**.
   - Políticas por `task_type` (`download`, `transcribe`, `analyze`, `crm_sync`).
   - Validación: tareas fallidas pasan a DLQ tras `max_attempts`; muestra logs y conteos.

13) Idempotencia completa en webhooks de Ringover:
   - En `RingoverWebhookController`, generar `dedup_key` (p.ej. `event_id||recording_url||HMAC(payload)`) y `payload_hash`.
   - `UPSERT` en `webhook_deduplication`; si existe y no expirado → `200 OK` no-op.
   - TTL configurable (ver evento o cron del Lote A).
   - Validación: reenvío de un mismo webhook responde `200` sin nuevas tareas.

14) Enriquecimiento CRM en Pipedrive:
   - Mapear `ai_summary`, `ai_keywords`, `ai_sentiment` a campos personalizados (crearlos si no existen).
   - Plantilla de nota con bullets (motivo de llamada, acciones, sentimiento, keywords, enlace a grabación).
   - `CRMSyncJob` actualiza persona/deal y añade nota.
   - Validación: ver nota y campos en un deal de prueba (describe el payload/SDK utilizado y respuestas mock si no hay credenciales).

## Entregables
- Migraciones/ALTER para `rate_limit_config` y `async_tasks`.
- Worker CLI funcional + documentación de ejecución.
- Control de idempotencia en webhooks con TTL y pruebas de reenvío.
- Código de integración Pipedrive + plantilla de nota y mapeos.

## Reglas
- Registrar métricas y logs por tarea (batch/correlation id).
- Mantener seguridad de secretos; mockear si no hay credenciales.
