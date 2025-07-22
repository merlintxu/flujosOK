# Flujos Dimension Telephony Sync

This project automates the synchronization of Ringover calls with OpenAI processing and Pipedrive CRM integration. It is a PHP 8.2 application that exposes REST endpoints and an optional admin panel for configuration.

## Features

- Import call logs from Ringover.
- Analyze recordings through OpenAI and store results in the database.
- Push processed calls to Pipedrive.
- JWT authenticated API with health and status endpoints.

## Quick start

```bash
composer install
cp .env.example .env
# Edit .env with your database and API credentials
php -S localhost:8000 -t public
```

More detailed steps are available in [docs/INSTALL.md](docs/INSTALL.md).

## Documentation

- [Installation guide](docs/INSTALL.md)
- [Project documentation (Spanish)](docs/README_es.md)
- [API reference](docs/API.md)
