## What

<!-- 1-2 sentences: what does this PR change? -->

## Why (design decisions)

<!-- Why this approach? Which alternatives did you reject and why? -->

## Scope

- [ ] Backend/API only: no frontend, login page, file upload or other out-of-scope features
- [ ] Only data is fake/seeded data (no real customer data, keys or secrets)

## Area touched

- [ ] Data model / migrations (PostgreSQL, FKs, CHECK constraints, indexes)
- [ ] REST API (orders, jobs, filtering, pagination)
- [ ] Job status transition (allowed transitions, history record)
- [ ] Roles and access (installer / planner / admin)
- [ ] Docker / seeders / one-command setup
- [ ] Tests / CI
- [ ] Optional extras (ready-for-install notification, jobs-by-status report)
- [ ] Docs / README

## Behavior and rules checklist

- [ ] Status changes go through the single transition action (no logic in controllers)
- [ ] Invalid transitions are rejected (422) and nothing is written
- [ ] Every status change writes a history row (who, when, from, to) in the same transaction
- [ ] "Who" comes from the authenticated user, never from the request body
- [ ] An installer sees and changes only their own jobs (other jobs return 404)
- [ ] An installer cannot create orders/jobs or move jobs through production stages
- [ ] List endpoints are validated, paginated and free of N+1 queries

## How to verify

```bash
docker compose up --build
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan test
```

<!-- Add request examples (curl) or steps specific to this PR. -->

## Tests

- [ ] Feature tests added/updated for the rules above
- [ ] Tests run against real PostgreSQL (not SQLite)
- [ ] `php artisan test` is green locally

## Quality checklist

- [ ] `vendor/bin/pint --test` passes
- [ ] `vendor/bin/phpstan analyse` passes
- [ ] `migrate:fresh --seed` runs cleanly on a fresh database
- [ ] No `dd()`, `dump()`, `TODO` or leftover debug code
- [ ] No secrets committed (only `.env.example`)
- [ ] README / docs updated (including the "not done and why" section if scope was cut)

## Notes for the reviewer

<!-- Trade-offs, known limitations, or what you would do next in a real project. -->
