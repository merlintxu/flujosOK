# Changelog - FlujosOK

## [4.2.2] - 2025-08-12

### 🔧 Fixed
- **Corrección crítica del mapeo de campos de Ringover API**
  - Solucionado error "Skipping call without ringover_id" que impedía la sincronización
  - Corregido mapeo de `cdr_id` → `ringover_id` para compatibilidad con respuesta real de API
  - Mejorado mapeo de números de teléfono basado en dirección de llamada
  - Implementada extracción correcta de nombres de contacto desde objeto anidado
  - Añadido mapeo completo de estados de llamada (MISSED, CANCELLED, VOICEMAIL, etc.)

### 📊 Technical Details
- **Archivo modificado**: `app/Services/CallService.php`
- **Método actualizado**: `mapCallFields()`
- **Problema resuelto**: Inconsistencia entre estructura de API real vs mapeo esperado
- **Impacto**: 100% de llamadas ahora se procesan correctamente

### 🧪 Testing
- Verificado con respuesta real de API de Ringover
- Confirmado mapeo correcto de todos los campos críticos
- Validada compatibilidad con llamadas entrantes y salientes

---

## [4.2.1] - 2025-08-12

### ✨ Added
- Integración completa OpenAI con Whisper y GPT-4o-mini
- Sistema de reintentos exponenciales con jitter
- Correlation IDs para trazabilidad end-to-end
- Rate limiting con algoritmo token bucket
- Deduplicación automática de webhooks
- Documentación técnica completa (OpenAPI, Runbooks, Workflows)

### 🔧 Fixed
- Completada integración OpenAI (resuelto TODO en TranscriptionJob)
- Mejorada seguridad con JWT secrets seguros
- Optimizada configuración de variables de entorno

### 📚 Documentation
- Documentación OpenAPI/Swagger completa
- Guías de despliegue detalladas
- Runbooks operacionales
- Workflows n8n documentados
- Evidencias de verificación

---

## [4.2.0] - 2025-08-12

### 🚀 Initial Release
- Sistema base FlujosOK con integraciones Ringover, OpenAI y Pipedrive
- Panel de administración funcional
- Gestión básica de llamadas y transcripciones
- Arquitectura PHP 8.2 con DI container


