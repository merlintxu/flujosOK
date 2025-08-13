# Installation Guide

1. **Clone the repository** and install the dependencies.
   ```bash
   composer install
   ```
2. **Create the environment configuration**.
   ```bash
cp .env.example .env
```
   Edit `.env` and configure the database variables (`DB_HOST`, `DB_PORT`,
   `DB_NAME`, `DB_USER`, `DB_PASS`) and API credentials
   `RINGOVER_API_KEY`, `PIPEDRIVE_API_TOKEN`, `OPENAI_API_KEY`.
   Also set JWT keys: `JWT_KID`, `JWT_KEYS_CURRENT` and (optionally)
   `JWT_KEYS_PREVIOUS`. The value of `ADMIN_PASS` should be generated
   with `password_hash`.
3. **Import the database schema** located in `database/flujodimen_db.sql` into your MySQL/MariaDB server and
   then run the SQL scripts in `database/migrations/` to create the rate limiting and webhook deduplication
   tables.
   ```bash
   mysql -u root -p < database/flujodimen_db.sql
   for f in database/migrations/*.sql; do
       mysql -u root -p your_database < "$f"
   done
   ```
   The `api_tokens` table now stores a `token_hash` column instead of the raw token.
4. **Launch the built-in PHP web server** for local testing.
   ```bash
   php -S localhost:8000 -t public
   ```
5. Visit `http://localhost:8000` to access the API. The admin panel is available in the `admin/` directory.
6. **Review log files** stored in `storage/logs/`.
   Log files rotate automatically once they reach 10&nbsp;MB (older files may be compressed if gzip is available).
   Inspect them in real time with:
   ```bash
   tail -f storage/logs/application.log
   tail -f storage/logs/error.log
   ```
   You can periodically clean old rotated logs using `Logger::cleanOldLogs()`.

7. **Enable database cleanup events** for rate limit logs and webhook deduplication. Run the migration
   `database/migrations/006_cleanup_events.sql` and ensure the MySQL event scheduler is active:
   ```sql
   SET GLOBAL event_scheduler = ON;
   ```
   The cleanup TTLs (default 7 and 30 days) can be overridden by setting `@dedup_ttl_days` and
   `@rate_limit_log_ttl_days` before executing the migration.


## Docker

The repository provides a Dockerfile and `docker-compose.yml` for local development.
Start the services with:
```bash
docker-compose up --build
```
This launches a PHP 8.2 FPM container and a MySQL service preloaded with `database/flujodimen_db.sql`.
Install dependencies using:
```bash
docker-compose exec app composer install
```
The application listens on port 9000 via FPM and MySQL is exposed on port 3306.
