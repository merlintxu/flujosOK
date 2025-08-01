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

### `POST /api/sync/hourly`
Trigger hourly synchronization from Ringover.

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
