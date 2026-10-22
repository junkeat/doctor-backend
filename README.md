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

   ## API routes

All endpoints return JSON resources under a `data` key. Validation errors return HTTP `422` with a `message` and field-specific `errors`.

### `GET /api/doctors`

Returns active doctors and their current availability.

Example response:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Dr. Sarah Lim",
      "specialty": "General Practitioner",
      "photo_url": null,
      "available_from": "09:00:00",
      "available_to": "22:00:00",
      "available_now": true
    },
    {
      "id": 2,
      "name": "Dr. Aiman Rashid",
      "specialty": "Family Medicine",
      "photo_url": null,
      "available_from": "09:00:00",
      "available_to": "22:00:00",
      "available_now": true
    },
    {
      "id": 3,
      "name": "Dr. Priya Nair",
      "specialty": "Internal Medicine",
      "photo_url": null,
      "available_from": "09:00:00",
      "available_to": "22:00:00",
      "available_now": true
    }
  ]
}
```

### `POST /api/consultations`

Creates a consultation for patient ID `1`. `scheduled_at` must be in the future and within the doctor's available hours (Asia/Kuala_Lumpur).

Example request:

```json
{
  "doctor_id": 1,
  "type": "chat",
  "scheduled_at": "2026-10-23T10:00:00+08:00",
  "reason": "Discuss recurring headaches."
}
```

Example response (`201 Created`):

```json
{
  "data": {
    "id": 4,
    "type": "chat",
    "scheduled_at": "2026-10-23T02:00:00.000000Z",
    "reason": "Discuss recurring headaches.",
    "status": "scheduled",
    "doctor": {
      "id": 1,
      "name": "Dr. Sarah Lim",
      "specialty": "General Practitioner"
    }
  }
}
```

### `GET /api/consultations`

Returns the current patient's consultations, newest scheduled time first, including each doctor's name and specialty.

Example request:

```http
GET /api/consultations?status=completed
```

Example response:

```json
{
  "data": [
    {
      "id": 2,
      "type": "video",
      "scheduled_at": "2026-09-08T02:00:00.000000Z",
      "reason": "General consultation",
      "status": "completed",
      "doctor": {
        "id": 2,
        "name": "Dr. Aiman Rashid",
        "specialty": "Family Medicine"
      }
    },
    {
      "id": 3,
      "type": "chat",
      "scheduled_at": "2026-09-02T06:00:00.000000Z",
      "reason": "General consultation",
      "status": "completed",
      "doctor": {
        "id": 3,
        "name": "Dr. Priya Nair",
        "specialty": "Internal Medicine"
      }
    }
  ]
}
```
