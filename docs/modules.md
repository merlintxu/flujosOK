# Module Overview

## Core
The `app/Core` directory contains the application kernel and utilities. It handles configuration loading, dependency injection, database access, routing, HTTP request and response objects, caching and logging. These classes bootstrap the framework and provide shared infrastructure for the rest of the codebase.

### Application
Bootstraps the application and resolves routes.

**Major methods**

- `run()` dispatch the HTTP request.

**Example**

```php
$app = new Application($config);
$app->run();
```

### Container
Simple dependency injection container used throughout the codebase.

**Major methods**

- `bind(name, resolver)` register a service.
- `resolve(name)` retrieve an instance.

### Config
Singleton that loads environment variables and `.env` values.

**Major methods**

- `getInstance()` access the singleton.
- `all()` return the full array.
- `set(key, value)` update a value.

### Database
PDO wrapper configured from the environment.

**Major methods**

- `query()` run a SQL query.
- `prepare()` create prepared statements.

### Router
Minimal HTTP router used by the API.

**Major methods**

- `get/post/put/delete()` register routes.
- `dispatch()` match the current request.

### Request / Response
Lightweight HTTP objects passed to controllers.

**Major methods**

- `Request::all()` return body parameters.
- `Request::get(header)` fetch query or header values.
- `Response` encapsulates body and status and sends headers on output.

### JWT
Utility for creating and validating tokens.

**Major methods**

- `generateToken()` issue a new token.
- `validateToken(token)` check validity.
- `revokeToken(token)` invalidate.

### Logger
PSR-3 compatible logger used across services.

### CacheManager
Simple filesystem cache helper with `get()`, `set()` and `deletePattern()`.

### ErrorHandler
Registers global exception handling for production environments.

## Controllers
Controllers live in `app/Controllers`. They map incoming HTTP routes to actions using the router. Each controller receives the service container and request object, then orchestrates calls to services and repositories to process data and return JSON or HTML responses.

### BaseController
Shared helper used by all controllers.

**Major methods**

- `jsonResponse()` and `successResponse()` return consistent JSON payloads.
- `validate()` checks incoming data against simple rules.
- `requireAuth()` and `requireAdmin()` enforce JWT authentication.
- `getPaginationParams()` parses common paging options.

**Example**

```php
$base = new class($container, $request) extends BaseController {};
$data = $base->validate($request->all(), ['name' => 'required|string']);
return $base->successResponse($data);
```

### ApiController
Small set of endpoints for monitoring purposes.

**Major methods**

- `status()` returns a short status payload.
- `health()` is a simple heartbeat check.

**Example**

```php
GET /api/status
GET /api/health
```

### CallsController
CRUD layer for the `calls` table.

**Major methods**

- `index()` returns paginated call results.
- `show(id)` displays a specific call.
- `store()` and `update(id)` persist call data.
- `destroy(id)` removes a call record.

**Example**

```php
POST /api/calls
PUT  /api/calls/{id}
```

### ConfigController
Manages runtime configuration.

**Major methods**

- `index()` dumps all current values.
- `update(key)` replaces a specific entry.
- `batch()` applies multiple updates from a JSON body.

**Example**

```bash
curl -X PUT /api/config/APP_ENV -d 'value=local'
```

### TokenController
Handles JSON Web Tokens for the API.

**Major methods**

- `generate()` issues a token.
- `verify()` checks token validity.
- `revoke()` invalidates a token.
- `active()` lists active tokens.

**Example**

```bash
curl /api/token/generate
```

### DashboardController
Builds dashboard metrics from analytics and telephony data.

**Major methods**

- `index()` main dashboard data including system info.
- `quickStats()` quick overview for the current day.
- `realtime()` recent call activity.
- `export()` download metrics in JSON or CSV.

**Example**

```php
GET /api/analytics/dashboard?period=24h
```

### SyncController
Coordinates import of Ringover data.

**Major methods**

- `hourly()` automated sync every hour.
- `manual()` run a custom range import.
- `status()` show last synced timestamp.

**Example**

```bash
php cron/hourly.php
```

### AnalysisController
Endpoints to trigger AI processing.

**Major methods**

- `process()` launch a batch for pending calls.
- `batchStatus(id)` query progress.
- `sentimentBatch()` quick sentiment analysis.
- `keywords()` placeholder.

**Example**

```bash
curl /api/analysis/process?max=20
```

### ReportController
Stubs for report generation.

**Major methods**

- `generate()` start a report.
- `status(id)` check progress.
- `download(id)` get file when ready.
- `schedule()` schedule periodic runs.

**Example**

```bash
GET /api/reports/status/123
```

### UserController
Manages application users.

**Major methods**

- `index()` list active users.
- `create()` add a new account.
- `update(id)` modify existing user.
- `permissions(id)` change the role.

**Example**

```bash
POST /api/users
```

### WebhookController
Placeholder for webhook registration.

**Major methods**

- `create()` store an external webhook definition.

**Example**

```bash
POST /api/webhooks
```

## Services
Services under `app/Services` encapsulate domain logic and external integrations. Examples include clients for Ringover, OpenAI and Pipedrive as well as analytics helpers. They are registered in the container so controllers and other classes can reuse them.

### AnalyticsService
Processes call records using OpenAI.

**Major methods**

- `processBatch(max)` handle a set of pending calls.
- `lastProcessed()` number of calls processed in last run.
- `getDashboardData(period)` aggregate metrics for the dashboard.
- `clearCache()` remove cached analytics data.

**Example**

```php
$service->processBatch(50);
```

### OpenAIService
Thin wrapper around the OpenAI REST API.

**Major methods**

- `chat(messages, extra)` perform a chat completion request.

**Example**

```php
$openai->chat([['role' => 'user', 'content' => 'Hi']]);
```

### PipedriveService
Simplified client for the Pipedrive CRM.

**Major methods**

- `findPersonByPhone(phone)` look up contact IDs.
- `createOrUpdateDeal(payload)` create a deal.

**Example**

```php
$service->createOrUpdateDeal(['title' => 'New Call']);
```

### RingoverService
Client for the Ringover API.

**Major methods**

- `getCalls(since)` stream call data with pagination.
- `downloadRecording(url)` save a recording locally.
- `testConnection()` verify API availability and is used by the dashboard health checks.

**Example**

```php
$ringover->testConnection();
```

## Models
Models in `app/Models` represent database tables using a very lightweight ORM style. They define fillable fields and type casts and provide helper methods for querying and persisting records.

### BaseModel
Abstract layer implementing common CRUD helpers.

**Major methods**

- `find(id)` retrieve a row.
- `create(data)` insert a record.
- `update(id, data)` update fields.
- `delete(id)` remove a record.
- `paginate(page, perPage)` helper for listing.

**Example**

```php
$call = Call::create([...]);
```

### Call
Model for the `calls` table.

**Major methods**

- `getStats()` aggregated call metrics.
- `search(term)` filter by phone or text.
- `markCrmSynced(id)` update CRM status.

**Example**

```php
$call->markCrmSynced($id);
```

## Repositories
Classes in `app/Repositories` implement focused data access layers using PDO. They contain queries for retrieving and updating records, such as pending calls or CRM synchronization status, keeping database logic separate from controllers and services.

### CallRepository
Data access layer for call records.

**Major methods**

- `pending(max)` list calls pending AI analysis.
- `saveBatch(calls, choices)` persist OpenAI results.
- `insertOrIgnore(call)` deduplicate on import.
- `markCrmSynced(id)` mark as synced with Pipedrive.

**Example**

```php
$repo->insertOrIgnore($call);
```

## Admin scripts
The `admin` directory provides a small web dashboard implemented with PHP scripts. These pages manage authentication, generate tokens, trigger synchronizations and render HTML views for configuration tasks.

The admin utilities load a lightweight bootstrap file located at `app/bootstrap/container.php`. This script now creates an `Application` instance and exposes its service container so bindings stay identical to the main runtime. Built-in admin routes were removed from the application kernel; all admin functionality lives in these scripts until a dedicated controller is introduced.
