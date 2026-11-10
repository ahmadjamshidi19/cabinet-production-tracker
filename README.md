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

## Design

The full design, written before the implementation, is in
[docs/design.md](docs/design.md). The essentials are below.

### Job status machine

```mermaid
stateDiagram-v2
    [*] --> pending
    pending --> cutting
    cutting --> edge_banding
    edge_banding --> assembly
    assembly --> ready_for_install
    ready_for_install --> installed
    installed --> [*]
```

The flow is strictly linear and forward-only. Skipping a step, moving backwards
and moving to the current status are all rejected with `422`.

### Roles and access

| Transition                         | admin | planner | installer               |
|------------------------------------|:-----:|:-------:|:-----------------------:|
| `pending` → `cutting`              | yes   | yes     | no (403)                |
| `cutting` → `edge_banding`         | yes   | yes     | no (403)                |
| `edge_banding` → `assembly`        | yes   | yes     | no (403)                |
| `assembly` → `ready_for_install`   | yes   | yes     | no (403)                |
| `ready_for_install` → `installed`  | yes   | yes     | own jobs only           |

| Action               | admin | planner | installer                        |
|----------------------|:-----:|:-------:|:--------------------------------:|
| Create order or job  | yes   | yes     | no (403)                         |
| List or view orders  | yes   | yes     | no (403)                         |
| List jobs            | all   | all     | only their own                   |
| Another installer's job | visible | visible | 404                          |

A status change is checked in this order: token (401), job lookup through the
visibility scope (404), request body (422), role and ownership policy (403),
transition validity inside the transaction (422). Authorization comes before
transition validity on purpose: a caller who may not perform a step learns
nothing about the state machine, and forbidden requests never wait for a lock.

### Data model

| Table                  | Notes                                                                  |
|------------------------|------------------------------------------------------------------------|
| `users`                | `role` (`admin`, `planner`, `installer`) with a CHECK constraint       |
| `orders`               | customer, address, `created_by`; no status column (see decisions)      |
| `production_jobs`      | `order_id`, nullable `installer_id`, `title`, `status` with a CHECK    |
| `job_status_histories` | append-only: `job_id`, `changed_by`, `from_status`, `to_status`, `created_at` |

The ER diagram is in [docs/design.md](docs/design.md). Foreign keys declare
their `ON DELETE` behaviour explicitly, and PostgreSQL does not index foreign
key columns by itself, so the indexes are created by hand:

| Index                                       | Reason                                               |
|---------------------------------------------|------------------------------------------------------|
| `production_jobs (order_id)`                | Load the jobs of an order                            |
| `production_jobs (installer_id, status)`    | An installer's job list, optionally filtered         |
| `production_jobs (status)`                  | Status filter for planners and admins                |
| `job_status_histories (job_id, created_at)` | Read the history of one job in order                 |
| `orders (created_by)`                       | Foreign key lookup                                   |

### Key decisions

| Decision | Why |
|----------|-----|
| Order status is derived from its jobs and never stored. No jobs or all `pending` gives `pending`, all `installed` gives `installed`, anything else gives `in_progress`. | A stored value can drift from the jobs. Deriving it keeps one source of truth. Lists eager load only the three job columns needed, so there is no N+1. |
| Transition rules live in the `JobStatus` enum (`allowedTransitions()`, `canTransitionTo()`). | One place to read and change them, no scattered `if` statements, and the enum is a plain class that is easy to unit test. |
| `TransitionJobStatus` runs in a transaction and locks the job row with `lockForUpdate()`. | Two concurrent requests could both validate against the same status and write two history rows that do not form a chain. With the lock the second request waits and validates again against the new status. The status change and its history row commit together. |
| A job of another installer returns 404, not 403. Order endpoints return 403 for installers. | A 403 confirms that the job exists. Jobs are hidden resources, orders are not. Hiding is done by one `visibleTo` query scope plus a scoped route binding, so it cannot be forgotten in a controller. |
| Invalid transitions and invalid filters return 422. | The request is well formed but cannot be applied. The error body has the same shape as a validation error, so clients handle one format. |
| The table is called `production_jobs`. | Laravel's database queue already owns a table called `jobs`. |
| The database guards its own rules: CHECK constraints on status and role, foreign keys, and no `updated_at` on the history table. | Invalid data is rejected even if it bypasses PHP validation. |
| The actor in the history comes from the authenticated user. `role`, `status` and `created_by` are not mass assignable, and a `status` sent when creating a job is ignored. | A request body can never claim who made a change, set a role or skip the state machine. `installer_id` must reference a user whose role is `installer`. |
| Sanctum bearer tokens, with every `/api` error rendered as JSON. | Simple and enough for an API without a login page. Without the JSON rule, an unauthenticated request lacking an `Accept` header would be redirected to a login route that does not exist. |
| Tests run against real PostgreSQL, in a separate `app_testing` database. | CHECK constraints and row locks behave differently on SQLite. |


## Testing and quality

```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
```

The suite has 30 tests (feature tests plus one unit test with data sets). It
covers:

- allowed transitions and the history row (who, from, to)
- invalid transitions (backwards, skipped step, same status) leaving the job and
  its history untouched
- installers seeing only their own jobs and getting 404 for someone else's job
- installers unable to perform production steps or to create orders and jobs
- planners creating orders and jobs, and `status` in a job body being ignored
- status filter validation and pagination, including the `per_page` limit
- missing or invalid tokens returning 401 with a JSON body
- derived order status
- that a transition takes a `FOR UPDATE` lock

Tests use a dedicated database, `app_testing`, created by
`docker/postgres/init.sql`. It is forced through both environment and server
variables in `phpunit.xml`, because the container's own `DB_DATABASE` reaches
Laravel through `$_SERVER`. `tests/TestCase.php` also aborts before touching
any other database, so the suite cannot wipe the development data.

GitHub Actions runs Pint, Larastan (level 6) and the test suite against a
PostgreSQL 17 service on every pull request and on pushes to `main`.

### Where to look

| Path | What it holds |
|------|---------------|
| `app/Enums/JobStatus.php` | Statuses and allowed transitions |
| `app/Actions/TransitionJobStatus.php` | Transition inside a transaction with a row lock |
| `app/Policies/` | Role and ownership rules |
| `app/Models/ProductionJob.php` | `visibleTo` scope and enum casts |
| `app/Http/` | Thin controllers, form requests and API resources |
| `database/migrations/` | Schema with foreign keys, CHECK constraints and indexes |
| `database/seeders/DemoSeeder.php` | Idempotent demo data with consistent history |
| `docs/design.md` | Design document written before the code |

## What is not done, and why

The time was limited, so I kept the required scope small and clean instead of
adding features.

| Not done | Why, and what I would do |
|----------|--------------------------|
| Notification when a job becomes `ready_for_install` (optional) | Skipped for time. I would dispatch an event after commit and handle it in a queued listener on the `database` queue, with a separate worker service in Compose. |
| Jobs-per-status report (optional) | Skipped for time. I would add a `GROUP BY status` endpoint and keep the `(status)` index. On a table this small PostgreSQL will choose a sequential scan, so I would document `EXPLAIN ANALYZE` and say the index pays off only at a larger scale. |
| Real concurrency test | The lock is verified by asserting that the transition issues `FOR UPDATE`, not by running parallel connections. A parallel test would be the next step. |
| Rate limiting on `/api/tokens`, token expiry and revocation | Out of scope for the exercise. In a real project I would add throttling, an expiry and a logout endpoint. |
| User management | The exercise did not ask for it. Users come from the seeder. |
| History row when a job is created | History records status changes only. `from_status` is nullable so a creation event can be added without a migration. |
| Production container | The Compose file is a development setup: `artisan serve`, a bind mount, and demo seeding on every start. For production I would build an image with php-fpm and nginx and no bind mount, and seed nothing. |
| CORS | Left at the framework defaults. I would restrict the allowed origins to the real frontend. |
| API documentation | The curl examples above stand in for an OpenAPI file. |
| Default Laravel welcome page | The skeleton page is still served at `/`. It is not part of the API. |

## Future improvements

The current design is deliberately small: three fixed roles, one linear flow
and one database. The ideas below are where I would go next. For each library
I say what it would solve and why I did not add it now, because a package
should earn its place.

### Libraries worth adding when the need appears

| Library | What it would give | Why not now |
|---------|--------------------|-------------|
| [spatie/laravel-permission](https://github.com/spatie/laravel-permission) | Roles and permissions stored in the database, for example `jobs.transition.install`, so an admin can edit what a role may do | Three fixed roles map cleanly to a backed enum and two small policies. A permission schema, a cache and extra tables would move the rules from reviewable code into data. I would adopt it when roles become configurable or more roles appear. |
| [spatie/laravel-model-states](https://github.com/spatie/laravel-model-states) | A class per state, transition classes, guarded transitions and side effects tied to a transition | A single forward-only flow fits an enum with `allowedTransitions()`. I would adopt it when the flow branches (rework loops, on hold, cancelled) or states carry behaviour. The row lock and the history row would stay in the same transactional action. |
| [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog) | A generic audit trail for any model change, such as a renamed job or a reassigned installer | The history table already gives the required trail of status changes. This would cover everything else. |
| [spatie/laravel-query-builder](https://github.com/spatie/laravel-query-builder) | Filtering, sorting and includes from the query string, with an allow list | One status filter does not need it. It pays off once planners filter by installer, date range and customer. |
| [spatie/laravel-data](https://github.com/spatie/laravel-data) | Typed data objects shared by requests, actions and resources | Form requests and resources are enough at this size. |
| Redis with Laravel Horizon | Fast queues with monitoring and retries | The `database` queue is enough for one notification type, and Redis adds infrastructure. I would switch when queue volume or monitoring needs grow. |

### Architecture and design patterns

- **Domain-oriented structure.** Group code by domain (orders, production,
  identity) instead of by technical layer once the codebase grows. Actions
  stay the unit of business logic.
- **Domain events.** Dispatch `JobStatusChanged` after commit. Notifications,
  metrics and webhooks become independent listeners. If delivery must be
  guaranteed, add a transactional outbox table.
- **Idempotency keys** on `POST` and `PATCH`, so a request retried after a
  network failure is applied once. This matters most for mobile clients.
- **Optimistic concurrency** (a version column or ETags) for editable fields
  such as a job title or an installer assignment. Status changes are already
  protected by the row lock.
- **Read models for reporting.** Use a database view or a counter table when
  report queries get heavy, instead of growing the write model.
- **API versioning** (`/api/v1`) and a generated OpenAPI document (for example
  with Scramble), so clients and docs follow the code.
- **Structured logging with request IDs** and metrics for the transition
  endpoint.
- **What I would not add:** a repository layer over Eloquent or an event-sourced
  history. At this scale they add code without adding safety.

### Product features

- **Ready-for-install notification** through a queued listener, sent by email,
  SMS or push. This is the optional item from the exercise.
- **Offline-first installer app** (for example with Capacitor): status changes
  are queued on the device and synced later with idempotency keys. The server
  state machine always wins on conflict.
- **Live planner board** with a Kanban view by status, updated in real time
  (Laravel Reverb and WebSockets).
- **Site floorplan view.** An interactive SVG of the installation site per
  order, where installers mark each cabinet as installed.
- **QR labels per job**, scanned at each workshop station to change status in
  one tap.
- **Production metrics:** time spent in each stage, bottleneck detection, late
  job alerts and the jobs-per-status report.
- **Controlled rework loop:** a backward transition (for example assembly to
  edge banding) that needs a reason and is recorded in the history.
- **Photos and customer sign-off** at installation (file storage such as S3).
- **A read-only customer link** that shows the progress of an order.
- **AI assistance, with a human deciding:** flag jobs stuck longer than
  usual, suggest an installer from workload and location, and search orders in
  natural language.

### Suggested order

1. Notifications with a worker service (small, and it proves the event design).
2. The jobs-per-status report and stage metrics.
3. The rework loop, together with `laravel-model-states` if the flow starts to branch.
4. The offline-first installer app with idempotency keys.
