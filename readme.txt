CandyGrill — phased build
=========================

**Repository:** https://github.com/MaksymSemenykhin/CandyGrill  

Clone (SSH): `git@github.com:MaksymSemenykhin/CandyGrill.git`

Phase 0 — repository
--------------------
* PSR-4: `Game\` → `src/`, `Game\Tests\` → `tests/`.
* `composer install`

Code quality (static analysis + style)
--------------------------------------
* **PHPStan** (`phpstan.neon.dist`, level 6): `composer run stan`
* **PHP-CS-Fixer** (PSR-12 + `declare_strict_types`): `composer run cs-check` (dry run) / `composer run cs-fix`

Run all checks: `composer run check` (style, PHPStan, PHPUnit).

This project targets PHP 8.1+. **Laravel Pint** is not used here (it commonly requires PHP 8.2+); PHP-CS-Fixer is the supported formatter.

**Note:** current **dev** dependencies (e.g. Symfony components used by PHP-CS-Fixer) require **PHP 8.2+** to install from `composer.lock`. CI runs on **8.2 and 8.3** (see `.github/workflows/ci.yml`). Running `composer install` on PHP 8.1 may fail until dev packages are adjusted or you use `--ignore-platform-reqs` (not recommended).

CI / GitHub Actions
---------------------
On push to `master` / `main` and on pull requests, **GitHub Actions** runs:

1. `composer run cs-check` — PHP-CS-Fixer dry run  
2. `composer run stan` — PHPStan  
3. `composer run test` — PHPUnit  

Workflow file: `.github/workflows/ci.yml`.

Phase 0.1 — Composer + Docker (“Sail-like”)
--------------------------------------------
This is **not** the official `laravel/sail` package (that requires Laravel). Same idea: **PHP 8.3 + MySQL 8 + Memcached** in Compose, plus small **`sail` / `sail.bat`** wrappers around `docker compose`.

### Prerequisites
* Docker Desktop (Windows/macOS) or Docker Engine + Compose v2 (Linux).

### Working from WSL (recommended on Windows)
Project lives on a Windows drive (e.g. `H:\CandyGrill` → `/mnt/h/CandyGrill` in WSL).

* Prefer **Docker Desktop with WSL2 integration** enabled for your distro so `docker` / `docker compose` in WSL talk to the same engine.
* In a **WSL bash** shell:
  ```bash
  cd /mnt/h/CandyGrill   # adjust drive letter to match your path
  chmod +x sail
  cp -n .env.example .env
  ./sail build && ./sail up -d
  ./sail composer install    # or: composer install on WSL if PHP/Composer installed there
  ./sail composer test
  ```
* **`./sail composer install`** runs Composer *inside* the `app` container; `composer.lock` must list all packages (run `composer update` on the host/WSL once if you edited `composer.json`).
* If **Git** on the host complains about “dubious ownership” on `/mnt/...`, either clone the repo onto the Linux filesystem (`~/projects/...`) or add a safe directory (see `git help safe.directory`). The **app container** entrypoint already runs `git config --global --add safe.directory /var/www/html` so Composer inside Docker is not blocked by that check.
* Line endings: `sail` and `docker-entrypoint.sh` are set to **LF** via `.gitattributes` so `chmod +x ./sail` keeps a usable shebang script.

### First run
1. `cp .env.example .env` — adjust `APP_PORT` / `DB_PASSWORD` if needed.
2. Build and start:
   * Git Bash / WSL / Linux: `chmod +x sail` then `./sail build` and `./sail up -d`
   * Windows CMD: `sail.bat build` and `sail.bat up -d`
   * Or: `docker compose build` / `docker compose up -d`
3. Open `http://127.0.0.1:8080/` — you should see a short JSON placeholder from phase 0.1.
4. Inside the app container, `composer install` runs on first start if `vendor/` is missing.

### Verify phase 0.1 (automated)
After `composer install` on the host (or inside the app container):

    composer test

Runs PHPUnit: `Phase01DockerStackTest` (layout/autoload), `PublicIndexHttpRequestTest` (real HTTP GET to `/` and `/index.php`, plus `Content-Type`).

HTTP tests start an ephemeral **`php -S`** server unless **`TEST_BASE_URL`** is set (e.g. `./sail up -d` then `TEST_BASE_URL=http://127.0.0.1:8080 composer test`).

### Useful commands
Containers must be running (`./sail up -d`) before `exec`.

* `./sail up -d` / `./sail down`
* `./sail logs -f app`
* **`./sail composer install`** — same as `./sail exec app composer install` (Sail-style shortcut)
* `./sail php -v` — PHP inside the `app` container
* `./sail bash` — shell in `app`
* `./sail phpunit` — runs `vendor/bin/phpunit` in `app` (Bash sail only; on CMD use `sail.bat exec app vendor/bin/phpunit`)
* Any other `docker compose` subcommand passes through, e.g. `./sail exec app ls`

MySQL data persists in the `candygrill-mysql` volume. Memcached is optional for future phases; the PHP image already has the `memcached` extension for parity with production-style stacks.

Phase 1 — next
--------------
Configuration, `.env` loading in PHP, PDO, database schema, migrations (see plan).

**Schema (agreed design):** high-load-oriented layout, UUID v7 as `BINARY(16)` for users/characters/combats, append-only combat moves, Memcached for access tokens (e.g. 24h TTL, renewed on login) — see `docs/database-schema.md`.

Phase 2 — HTTP API shell
-------------------------
Replace `public/index.php` with the real JSON command router.

Later phases
------------
Domain (combat), repositories, sessions, full API, validation/OpenAPI, health checks, etc.
