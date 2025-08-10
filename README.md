# Flujos Dimension Telephony Sync

This project automates the synchronization of Ringover calls with OpenAI processing and Pipedrive CRM integration. It is a PHP 8.2 application that exposes REST endpoints and an optional admin panel for configuration.

## Features

- Import call logs from Ringover.
- Analyze recordings through OpenAI and store results in the database.
- Push processed calls to Pipedrive.
- JWT authenticated API with health and status endpoints.
- Test Ringover connectivity before syncing.
- The admin panel is served through standalone scripts, no built-in controller routes.

## Quick start

```bash
composer install
cp .env.example .env
# Edit `.env` and set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` and `DB_PASS`
# along with your API credentials. The variable
# `RINGOVER_MAX_RECORDING_MB` limits the size of downloaded
# recordings (default 100).
mkdir -p storage/recordings storage/voicemails
php -S localhost:8000 -t public
```

### Console usage

An hourly synchronization task is available as a command line tool. Execute it
via Composer's binary to import the latest calls:

```bash
vendor/bin/console sync:hourly
```

Expired tokens can also be purged with the cleanup command:

```bash
vendor/bin/console token:cleanup
```

See [console commands](docs/modules.md#console-commands) for more details.

Webhook registration is documented in
[docs/API.md#post-apiwebhooks](docs/API.md#post-apiwebhooks).

### Admin login

The admin panel located in the `admin/` directory requires session-based
authentication. Set `ADMIN_USER` and `ADMIN_PASS` in your `.env` file and
visit `admin/login.php` to sign in. After a successful login a CSRF token is
available for API requests within the dashboard.

More detailed steps are available in [docs/INSTALL.md](docs/INSTALL.md).

## Deployment

The deployment workflow uploads the application to the production server via FTP. Configure these secrets in your repository:
- `FTP_SERVER`
- `FTP_USERNAME`
- `FTP_PASSWORD`

Optional: `FTP_PORT` or `FTP_SERVER_DIR` if you need to override defaults.

## Documentation

- [Installation guide](docs/INSTALL.md)
- [Project documentation (Spanish)](docs/README_es.md)
- [API reference](docs/API.md)
- [Module and class reference](docs/modules.md)

## License

This project is licensed under the [MIT License](LICENSE).
