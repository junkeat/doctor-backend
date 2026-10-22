# HealthLink API

HealthLink is a Laravel API for finding doctors and booking patient consultations. It uses PostgreSQL and currently identifies the patient with database ID `1` until authentication is added.

## Setup

1. Install Composer dependencies:

   ```bash
   composer install
   ```

2. Create a PostgreSQL database:

   ```sql
   CREATE DATABASE healthlink;
   CREATE DATABASE healthlink_test; -- FOR FEATURE TESTS (OPTIONAL)
   ```

3. Copy `.env.example` to `.env`, then set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` to match your PostgreSQL setup.

   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

4. Run migrations and seed the doctors, patient, and sample consultations:

   ```bash
   php artisan migrate --seed
   ```

   PHPUnit uses the separate `healthlink_test` database and runs `migrate:fresh`; configure the same PostgreSQL credentials in `.env` so tests do not alter `healthlink`.

5. Start the API server:

   ```bash
   php artisan serve
   ```

   The API is available at `http://127.0.0.1:8000`. CORS allows requests from `http://localhost:4200`.

   ## API documentation

   Scramble serves the interactive API documentation at [`/docs/api`](http://127.0.0.1:8000/docs/api) and the OpenAPI specification at [`/docs/api.json`](http://127.0.0.1:8000/docs/api.json). Documentation routes are available locally by default.
