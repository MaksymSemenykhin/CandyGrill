# CandyGrill

Simple online game server in PHP and MySQL. Clients send **POST** requests with a JSON body; the `command` field selects the action; responses are JSON.

Original employer wording: [`docs/assignment-original-spec.md`](docs/assignment-original-spec.md).

## Requirements

- PHP 8.2+ (see `composer.json`)
- MySQL 8 (via Docker or your own instance)
- For the recommended stack: Docker with Docker Compose v2 (`docker compose`)

## Installation and running (Docker, production-style)

The repo ships a **Docker Compose** stack matching the intended production layout: **Nginx** → **PHP 8.3** (`php -S` serving `public/`) → **MySQL 8** → **Memcached**, with optional **phpMyAdmin** for demos. See `compose.yaml`, app image `docker/8.3/Dockerfile`, Nginx config `docker/nginx/docker-compose-proxy.conf`.

### 1. Prerequisites

On the VPS or dev machine: Docker and Docker Compose. Clone the repository and `cd` into the project root.

### 2. Environment

```bash
cp .env.example .env
```

Edit `.env` as needed:

| Variable | Purpose |
|----------|---------|
| `APP_PORT` | Host port mapped to Nginx (default `8080`). API base URL: `http://<host>:APP_PORT/` |
| `DB_*` | Must match the `mysql` service. Keep `DB_HOST=mysql` inside the Compose network unless you rename the service |
| Production | Set `DEBUG=false`, `SESSION_ALLOW_ISSUE=false` |
| CORS | Prefer a comma-separated allowlist in `CORS_ALLOW_ORIGIN` instead of `*` when browsers call known origins |

### 3. Start

```bash
./sail up -d
```

On **Windows** (CMD/PowerShell), from the repo root:

```bat
sail.bat up -d
```

On Git Bash / WSL / Linux, `./sail` is a thin wrapper around `docker compose`.

The `app` container entrypoint (`docker/8.3/docker-entrypoint.sh`) runs `composer install` if needed and **`php bin/migrate.php`** (Phinx), so the MySQL schema is applied when the container starts. Manual runs and local setups are described in **Database migrations** below.

### 4. Using the API

- **POST** JSON to `/` with a `command` field (e.g. `register`, `login`, `start_combat`, …).
- **GET** `/` returns a short JSON hint (no auth) — useful for smoke tests.
- OpenAPI: `public/openapi.yaml`; Swagger UI: `public/api-docs/index.html` when served from the same host.

### 5. Firewall and security

- Expose only `APP_PORT` (or terminate TLS on 443 with an external reverse proxy and forward to the stack).
- **phpMyAdmin** is bound to `PHPMYADMIN_PORT` (default `8081`). Do **not** expose it on public internet — firewall it, remove the service from `compose.yaml`, or restrict by IP/VPN.

### 6. Sessions and scale

For multiple PHP workers behind Nginx, prefer `SESSION_DRIVER=memcached` and optionally `MATCH_POOL_DRIVER=memcached` (the `memcached` hostname is already defined in Compose). Otherwise sessions default to in-memory/file — see comments in `.env.example`.

### 7. Logs and updates

```bash
docker compose logs -f app
# or
./sail logs -f app
```

Deploy updates: `git pull`. If `database/migrations/` contains new files, run migrations again (restart the stack as below, or `composer migrate` if you run PHP outside Docker). Then, if the Dockerfile changed:

```bash
./sail build --no-cache app
./sail up -d
```

## Database migrations

Schema changes for **MySQL** are applied with **[Phinx](https://phinx.org/)**: PHP classes in `database/migrations/`, configuration in `phinx.php` (reads `DB_*` from `.env`). Phinx stores which revisions already ran in the **`phinxlog`** table, so running migrate twice is safe.

**Command:** `composer migrate or bash ./sail composer migrate` (runs `php bin/migrate.php`).

| Scenario | What to do |
|----------|------------|
| **Docker Compose** | Nothing extra on first boot: `docker/8.3/docker-entrypoint.sh` runs `composer migrate` when the `app` container starts. After you `git pull` new migrations, restart so it runs again: `./sail up -d` (or `docker compose up -d app`). |
| **Local PHP + MySQL** | Create an empty database, set `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_PORT` in `.env`, then `composer install` and `composer migrate`. |

If the API returns database errors about missing tables or columns, the usual fix is to run migrations against the same database your `.env` points to.

## Architecture notes

- Handlers use optional constructor defaults and factories such as `SessionService::fromEnvironment()` instead of a full DI container — deliberate for a small codebase.
- `MatchPool` uses a process-wide singleton for the optional opponent pool; tests reset via `MatchPool::resetForTesting()`.
- Multi-step DB work goes through `DatabaseConnection::transaction()` (registration, combat attack, claim).
- Unexpected server errors return JSON `500` with `error.code: server_error`; set `DEBUG=true` in `.env` to include `error.detail` in responses during development.

## Tests and tooling

```bash
composer install
composer test          # phpunit
composer run stan      # phpstan (optional)
```

## Submission (assignment)

The archive should include source (and tests if any), a **MySQL dump**, and **`readme.txt`** (short pointer + deliverables). This **README.md** holds the full setup guide.
