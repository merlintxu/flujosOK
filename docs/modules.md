# Module Overview

## Core
The `app/Core` directory contains the application kernel and utilities. It handles configuration loading, dependency injection, database access, routing, HTTP request and response objects, caching and logging. These classes bootstrap the framework and provide shared infrastructure for the rest of the codebase.

## Controllers
Controllers live in `app/Controllers`. They map incoming HTTP routes to actions using the router. Each controller receives the service container and request object, then orchestrates calls to services and repositories to process data and return JSON or HTML responses.

### BaseController
Shared helper used by all controllers. It provides JSON response helpers, input validation, authentication checks and utility methods such as `getPaginationParams()` and `handleError()`.

### ApiController
`status()` and `health()` expose minimal endpoints used by monitoring services to verify that the API is online.

### CallsController
CRUD layer for the `calls` table. `index()` paginates results while `show()`, `store()`, `update()` and `destroy()` handle the usual REST operations.

### ConfigController
Reads and updates runtime configuration. `index()` returns all config values, `update()` modifies one key and `batch()` applies multiple changes at once.

### TokenController
Manages JWT tokens. `generate()` creates a new token, `verify()` checks its validity and `revoke()` disables an issued token.

### DashboardController
Uses `AnalyticsService` and `RingoverService` to build dashboard metrics. Endpoints such as `index()`, `quickStats()`, `realtime()` and `export()` return aggregated statistics and CSV exports.

### SyncController
Coordinates import of Ringover data. `hourly()` performs a scheduled sync while `manual()` allows specifying a custom range. `status()` reports the timestamp of the latest call stored locally.

### AnalysisController
Triggers AI processing pipelines. `process()` launches batch analysis using OpenAI, `batchStatus()` reports progress and `sentimentBatch()` runs a quick sentiment job. `keywords()` is currently a placeholder.

### ReportController
Stubs to generate and download reports through `generate()`, `status()`, `download()` and `schedule()`.

### UserController
CRUD operations for application users with extra `permissions()` endpoint to change a user's role.

### WebhookController
Placeholder controller to register incoming webhooks via `create()`.

## Services
Services under `app/Services` encapsulate domain logic and external integrations. Examples include clients for Ringover, OpenAI and Pipedrive as well as analytics helpers. They are registered in the container so controllers and other classes can reuse them.

### AnalyticsService
Works on pending calls from `CallRepository` and invokes `OpenAIService` to generate summaries and sentiment. `processBatch()` handles a group of calls and stores results via `saveBatch()` while `clearCache()` wipes temporary analytics files.

### OpenAIService
Thin wrapper around the OpenAI REST API. The `chat()` method sends a list of messages and returns the JSON response.

### PipedriveService
Interacts with the Pipedrive CRM. `findPersonByPhone()` searches contacts by phone number and `createOrUpdateDeal()` pushes call data into a deal record.

### RingoverService
Communicates with Ringover. `getCalls()` yields calls from the API using pagination and `downloadRecording()` saves call recordings locally.

## Models
Models in `app/Models` represent database tables using a very lightweight ORM style. They define fillable fields and type casts and provide helper methods for querying and persisting records.

### BaseModel
Abstract layer that implements common CRUD helpers (`find()`, `create()`, `update()`, `delete()`), pagination and validation hooks. It is extended by all concrete models.

### Call
Represents the `calls` table. Besides inherited CRUD operations it offers domain-specific queries such as `getStats()`, `search()` and helpers to update AI fields or link a call to Pipedrive.

## Repositories
Classes in `app/Repositories` implement focused data access layers using PDO. They contain queries for retrieving and updating records, such as pending calls or CRM synchronization status, keeping database logic separate from controllers and services.

### CallRepository
Offers optimized queries over the `calls` table. `pending()` returns calls that require analysis, `saveBatch()` persists OpenAI results, `insertOrIgnore()` skips duplicates on import and `markCrmSynced()` flags a call once pushed to Pipedrive.

## Admin scripts
The `admin` directory provides a small web dashboard implemented with PHP scripts. These pages manage authentication, generate tokens, trigger synchronizations and render HTML views for configuration tasks.

The admin utilities load a lightweight bootstrap file located at `app/bootstrap/container.php`. This script now creates an `Application` instance and exposes its service container so bindings stay identical to the main runtime.
