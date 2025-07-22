# Installation Guide

1. **Clone the repository** and install the dependencies.
   ```bash
   composer install
   ```
2. **Create the environment configuration**.
   ```bash
cp .env.example .env
```
   Edit `.env` and configure the database variables:
   `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.
   Add your API tokens as needed.
   All entry scripts load `bootstrap/env.php` so these values are available.
3. **Import the database schema** located in `database/flujodimen_db.sql` into your MySQL/MariaDB server.
4. **Launch the built-in PHP web server** for local testing.
   ```bash
   php -S localhost:8000 -t public
   ```
5. Visit `http://localhost:8000` to access the API. The admin panel is available in the `admin/` directory.
