# API Reference

Base URL: `/api`

## Authentication

Most endpoints require a Bearer token generated via `/api/token/generate`.
Include the header:
```
Authorization: Bearer <token>
```

## Endpoints

### `GET /api/status`
Returns basic status information.

### `GET /api/health`
Returns system health checks.

### `POST /api/token/generate`
Generates a JWT token for API access.

### `POST /api/token/validate`
Validates a token.

### `GET /api/token/active`
List active tokens issued by the system.

### `GET /api/calls`
List calls. Parameters: `limit`, `offset`, `date`.

### `POST /api/calls`
Create a new call record. Fields: `phone_number`, `direction`, `status`, `duration`.
`status` accepts `pending`, `completed`, `answered`, `missed`, `busy`, or `failed`.

### `POST /api/sync/hourly`
Trigger hourly synchronization from Ringover.
Parameters can also be supplied via a `GET` request using the query string.

### `GET /api/sync/status`
Get information about the last synchronization.

### `POST /api/webhooks`
Register an external webhook that will be notified on events.
Parameters: `url`, `event`.

Example request:
```bash
curl -X POST /api/webhooks \
  -d "url=https://example.com/hook" \
  -d "event=call.finished"
```

Additional routes exist for testing external APIs and for analytics endpoints as defined in the application.

### Ringover webhook callbacks
Ringover env\u00eda eventos como `recording.available` o `voicemail.available` a
`/api/webhooks/ringover/record-available` y `/api/webhooks/ringover/voicemail-available`.
Cada solicitud incluye el encabezado `X-Ringover-Signature` calculado con HMAC
SHA-256. La aplicación valida esta firma usando `RINGOVER_WEBHOOK_SECRET` antes
de procesar la grabación.
