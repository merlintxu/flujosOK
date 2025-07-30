# Module Overview

## Core
The `app/Core` directory contains the application kernel and utilities. It handles configuration loading, dependency injection, database access, routing, HTTP request and response objects, caching and logging. These classes bootstrap the framework and provide shared infrastructure for the rest of the codebase.

## Controllers
Controllers live in `app/Controllers`. They map incoming HTTP routes to actions using the router. Each controller receives the service container and request object, then orchestrates calls to services and repositories to process data and return JSON or HTML responses.

## Services
Services under `app/Services` encapsulate domain logic and external integrations. Examples include clients for Ringover, OpenAI and Pipedrive as well as analytics helpers. They are registered in the container so controllers and other classes can reuse them.

## Models
Models in `app/Models` represent database tables using a very lightweight ORM style. They define fillable fields and type casts and provide helper methods for querying and persisting records.

## Repositories
Classes in `app/Repositories` implement focused data access layers using PDO. They contain queries for retrieving and updating records, such as pending calls or CRM synchronization status, keeping database logic separate from controllers and services.

## Admin scripts
The `admin` directory provides a small web dashboard implemented with PHP scripts. These pages manage authentication, generate tokens, trigger synchronizations and render HTML views for configuration tasks.

The admin utilities load a lightweight bootstrap file located at `app/bootstrap/container.php`. This script now creates an `Application` instance and exposes its service container so bindings stay identical to the main runtime.
