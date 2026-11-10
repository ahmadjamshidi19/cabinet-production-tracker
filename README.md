# Cabinet Production Tracker

![CI](https://github.com/ahmadjamshidi19/cabinet-production-tracker/actions/workflows/ci.yml/badge.svg)

A small REST API that tracks cabinet orders from the first cut to installation.
An order has several jobs, and every job moves through cutting, edge banding,
assembly and installation. Installers see only their own jobs and may only
change their status.

The focus is database design, access control and code quality rather than the
number of features. Everything runs in containers, so no PHP or PostgreSQL is
needed on the host.

**Stack:** PHP 8.4, Laravel 13, PostgreSQL 17, Laravel Sanctum, PHPUnit, Pint, Larastan.

## Quick start

Requirements: Docker with Compose.

```bash
git clone https://github.com/ahmadjamshidi19/cabinet-production-tracker.git
cd cabinet-production-tracker
docker compose up --build
```

The entrypoint creates `.env` from `.env.example`, installs Composer
dependencies, generates the application key, waits for PostgreSQL, runs the
migrations and seeds the demo data. Once the log shows `Server running`, the
API is available at `http://localhost:8000` (health check: `GET /up`).

Restarting is safe because the seeder does nothing when the demo data already
exists. To reset the data:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

## Demo users

The database is seeded with fake data. Every demo account uses the password
`password`.

| Role      | Email                  |
|-----------|------------------------|
| admin     | admin@example.com      |
| planner   | planner@example.com    |
| installer | installer1@example.com |
| installer | installer2@example.com |

The seed contains 5 orders and 9 jobs that cover every status. Jobs are split
between the two installers, and two jobs are not assigned yet.

## API

All endpoints are under `/api`. Authentication uses Sanctum bearer tokens.
Exchange an email and password for a token, then send it as
`Authorization: Bearer <token>`.

| Method | Path                       | Purpose                                              | Allowed roles                   |
|--------|----------------------------|------------------------------------------------------|---------------------------------|
| POST   | `/api/tokens`              | Exchange email and password for a token              | public                          |
| GET    | `/api/user`                | Current user                                         | any                             |
| POST   | `/api/orders`              | Create an order                                      | admin, planner                  |
| GET    | `/api/orders`              | List orders (paginated)                              | admin, planner                  |
| POST   | `/api/orders/{order}/jobs` | Create a job in an order                             | admin, planner                  |
| GET    | `/api/orders/{order}/jobs` | List the jobs of an order (`status`, `per_page`)     | admin, planner                  |
| GET    | `/api/jobs`                | List jobs (`status`, `per_page`)                     | any; installers see only their own |
| PATCH  | `/api/jobs/{job}/status`   | Change the status of a job                           | see the role matrix below       |

Lists are paginated: `per_page` is between 1 and 100 (default 15) and the
response contains `data`, `links` and `meta`. The `status` filter accepts only
known statuses.

### Errors

Every error under `/api` is JSON, even without an `Accept` header.

| Code | When                                                   | Body                                            |
|------|--------------------------------------------------------|-------------------------------------------------|
| 401  | Missing or invalid token, wrong credentials            | `{"message": "Unauthenticated."}`               |
| 403  | The role may not perform the action                    | `{"message": "This action is unauthorized."}`   |
| 404  | Unknown resource, or a job assigned to someone else    | `{"message": "Resource not found."}`            |
| 422  | Validation failure or an invalid status transition     | `{"message": "...", "errors": {"field": ["..."]}}` |

### Examples

```bash
BASE=http://localhost:8000/api

login() {
  curl -s -X POST "$BASE/tokens" -H 'Content-Type: application/json' \
    -d "{\"email\":\"$1\",\"password\":\"password\"}" | sed -E 's/.*"token":"([^"]+)".*/\1/'
}

PLANNER=$(login planner@example.com)
INSTALLER=$(login installer1@example.com)

# Create an order as a planner: 201, status "pending"
curl -s -X POST "$BASE/orders" -H "Authorization: Bearer $PLANNER" \
  -H 'Content-Type: application/json' \
  -d '{"customer_name":"Test Customer","address":"1 Test Road"}'

# Add a job to order 1 and assign it to installer2 (user id 4): 201, status "pending"
curl -s -X POST "$BASE/orders/1/jobs" -H "Authorization: Bearer $PLANNER" \
  -H 'Content-Type: application/json' \
  -d '{"title":"Extra cabinet","installer_id":4}'

# List jobs with a filter and a page size (an installer sees only their own)
curl -s "$BASE/jobs?status=cutting&per_page=5" -H "Authorization: Bearer $INSTALLER"

# Installer completes their own job at the install step: 200
curl -s -X PATCH "$BASE/jobs/2/status" -H "Authorization: Bearer $INSTALLER" \
  -H 'Content-Type: application/json' -d '{"status":"installed"}'

# Installer tries a production step on their own job: 403
curl -s -o /dev/null -w '%{http_code}\n' -X PATCH "$BASE/jobs/5/status" \
  -H "Authorization: Bearer $INSTALLER" -H 'Content-Type: application/json' \
  -d '{"status":"edge_banding"}'

# Installer asks for another installer's job: 404
curl -s -o /dev/null -w '%{http_code}\n' -X PATCH "$BASE/jobs/3/status" \
  -H "Authorization: Bearer $INSTALLER" -H 'Content-Type: application/json' \
  -d '{"status":"installed"}'

# Planner tries to move a job backwards: 422
curl -s -X PATCH "$BASE/jobs/5/status" -H "Authorization: Bearer $PLANNER" \
  -H 'Content-Type: application/json' -d '{"status":"pending"}'
```
