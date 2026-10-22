# HealthLink API

HealthLink is a Laravel API for finding doctors and booking consultations. It uses PostgreSQL and Laravel Sanctum bearer tokens to authenticate users.

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

4. Create the development database schema and seed the doctors, sample user, and consultations:

   ```bash
   php artisan migrate:fresh --seed
   ```

   This command drops all tables in the configured database before recreating them. Use it only for local development or another database whose contents can be discarded.

   PHPUnit uses the separate `healthlink_test` database and runs `migrate:fresh`; configure the same PostgreSQL credentials in `.env` so tests do not alter `healthlink`.

5. Start the API server:

   ```bash
   php artisan serve
   ```

   The API is available at `http://127.0.0.1:8000`. CORS allows requests from `http://localhost:4200`.

## API documentation

Scramble serves the interactive API documentation at [`/docs/api`](http://127.0.0.1:8000/docs/api) and the OpenAPI specification at [`/docs/api.json`](http://127.0.0.1:8000/docs/api.json). Documentation routes are available locally by default.

## API routes

Resource endpoints return JSON under a `data` key. Validation errors return HTTP `422` with a `message` and field-specific `errors`.

### `POST /api/register`

Creates a user and returns a Sanctum token.

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "password": "secure-password",
  "password_confirmation": "secure-password"
}
```

Example response:

```json
{
  "user": {
    "id": 1,
    "name": "Jane Doe",
    "email": "jane@example.com"
  },
  "token": "1|<token>"
}
```

### `POST /api/login`

Use the returned Sanctum token as a bearer token in the `Authorization` header for protected endpoints. The Scramble OpenAPI documentation marks consultation endpoints as requiring bearer authentication; doctors, registration, and login remain public.

Accepts an `email` and `password` and returns the same user-and-token response as registration. Include the returned token on protected routes:

```http
Authorization: Bearer <token>
```

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

Creates a consultation for the authenticated user. `scheduled_at` must be in the future and within the doctor's available hours (Asia/Kuala_Lumpur).

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
    "user": {
      "id": 1,
      "name": "John",
      "email": "john@example.com"
    },
    "doctor": {
      "id": 1,
      "name": "Dr. Sarah Lim",
      "specialty": "General Practitioner"
    }
  }
}
```

### `GET /api/consultations`

Returns the authenticated user's consultations, newest scheduled time first, including each doctor's name and specialty.

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
      "user": {
        "id": 1,
        "name": "John",
        "email": "john@example.com"
      },
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
      "user": {
        "id": 1,
        "name": "John",
        "email": "john@example.com"
      },
      "doctor": {
        "id": 3,
        "name": "Dr. Priya Nair",
        "specialty": "Internal Medicine"
      }
    }
  ]
}
```
