# Documentación del Proyecto

Flujos Dimension sincroniza llamadas de Ringover y analiza las grabaciones con OpenAI para integrarlas posteriormente en Pipedrive. La aplicación está escrita en PHP 8.2 y cuenta con un panel de administración opcional.

## Características

- Importación de llamadas de Ringover.
- Procesamiento de audios mediante OpenAI y almacenamiento en base de datos.
- Envío de resultados a Pipedrive como oportunidades.
- API REST autenticada con JWT y endpoints de salud.

## Instalación rápida

1. Ejecuta `composer install` para descargar las dependencias.
2. Copia `.env.example` a `.env` y configura `DB_HOST`, `DB_PORT`, `DB_NAME`,
   `DB_USER` y `DB_PASS`, además de tus tokens de API.
3. Importa `database/flujodimen_db.sql` en tu servidor MySQL/MariaDB. La tabla
   `api_tokens` ahora guarda el `token_hash` en lugar del token en texto claro.
4. Inicia el servidor con `php -S localhost:8000 -t public`.

### Acceso al panel de administración

Configura `ADMIN_USER` y `ADMIN_PASS` en tu archivo `.env` y accede a
`admin/login.php` para iniciar sesión en el panel. Tras autenticarte se genera
un token CSRF para las peticiones de la interfaz.

Consulta [docs/INSTALL.md](INSTALL.md) para más detalles en inglés.

### Comandos de consola

Se incluyen dos tareas programadas a trav\u00e9s de Symfony Console:

- `sync:hourly` importa las llamadas recientes de Ringover.
- `token:cleanup` elimina los tokens expirados.

Ejemplo de uso:

```bash
vendor/bin/console sync:hourly
vendor/bin/console token:cleanup
```

### Endpoint de webhooks

El API permite registrar webhooks externos mediante:

`POST /api/webhooks`

```bash
curl -X POST https://localhost/api/webhooks \
  -d "url=https://ejemplo.com/hook" \
  -d "event=call.finished"
```

## Variables de entorno

### Base de datos
- `DB_HOST`: servidor de la base de datos.
- `DB_PORT`: puerto de conexión.
- `DB_NAME`: nombre de la base de datos.
- `DB_USER`: usuario de conexión.
- `DB_PASS`: contraseña del usuario.

### API Ringover
- `RINGOVER_API_URL`: URL base de la API.
- `RINGOVER_API_KEY`: clave utilizada en el encabezado `Authorization`.
- `RINGOVER_WEBHOOK_SECRET`: secreto para verificar las firmas de los webhooks.
- `RINGOVER_MAX_RECORDING_MB`: tamaño máximo permitido para descargar grabaciones (MB).

### API OpenAI
- `OPENAI_API_URL`: URL base de la API de OpenAI.
- `OPENAI_API_KEY`: clave de autenticación para OpenAI.
- `OPENAI_MODEL`: modelo de transcripción a utilizar.

### API Pipedrive
- `PIPEDRIVE_API_URL`: URL base de la API de Pipedrive.
- `PIPEDRIVE_API_TOKEN`: token de autenticación de Pipedrive.

### Seguridad y administración
- `JWT_KID`: identificador de la clave activa.
- `JWT_KEYS_CURRENT`: clave actual para firmar tokens JWT.
- `JWT_KEYS_PREVIOUS`: claves anteriores aceptadas durante la rotación.
- `ADMIN_USER`: usuario del panel de administración.
- `ADMIN_PASS`: contraseña encriptada para el panel.
