# Cabinet Production Tracker

A small REST API for tracking cabinet orders from order to installation.
Each order has several jobs, and each job moves through the stages
cutting, edge banding, assembly and installation.

> **Status:** work in progress. This README is a skeleton and will be
> completed before submission.

## Tech stack

- PHP / Laravel
- PostgreSQL
- Docker Compose
- Laravel Sanctum (API tokens)

## Quick start

```bash
docker compose up --build
```

> Not available yet. Docker setup is added in a later PR.

## Scope

In scope:

- Data model: orders, production jobs, users, status history
- REST API for orders and jobs (filtering, pagination)
- Separate endpoint for job status transitions with allowed transitions only
- Roles: `installer`, `planner`, `admin`
- Feature tests for transition rules and access control
- Docker Compose, migrations and seeders with fake data

Out of scope (by design): frontend, login page, file uploads.

## Roadmap

- [x] Repository skeleton
- [ ] Design document (states, role matrix, schema)
- [ ] Docker setup
- [ ] Database schema and models
- [ ] Seeders
- [ ] Authentication and roles
- [ ] Orders and jobs API
- [ ] Job status transition
- [ ] Feature tests and CI
- [ ] Optional: ready-for-install notification
- [ ] Optional: jobs-by-status report

## Documentation

- Design decisions: `docs/design.md` (added in a later PR)

## Not done and why

To be filled in before submission.
