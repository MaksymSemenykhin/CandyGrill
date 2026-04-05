CandyGrill
==========

Game API (PHP + MySQL). **Spec:** `docs/assignment-original-spec.md`. **Implementation map:** `docs/technical-spec.md`.

**Repo:** https://github.com/MaksymSemenykhin/CandyGrill

Requirements
------------
* PHP **8.1+** for the app; **dev** tools from `composer.lock` often need **8.2+** (CI uses 8.2 / 8.3).
* `composer install` · PSR-4: `Game\` → `src/`, tests → `tests/`.

API (short)
-----------
* **GET** `/` or `/index.php` — JSON: `ok`, `message`, `lang`.
* **POST** `/` with JSON or form body; field **`command`** (`a-z`, `0-9`, `_`). Session: `Authorization: Bearer <session_id>` from `login`, or `session_id` / `X-Session-Token` in body or headers.
* **OpenAPI:** `public/openapi.yaml` · UI: `public/api-docs/index.html`

Docker / Compose
----------------
PHP + MySQL + optional Memcached; wrappers **`sail`** / **`sail.bat`** (not Laravel Sail).

1. `cp .env.example .env`
2. `./sail build && ./sail up -d` (or `sail.bat` on Windows CMD)
3. `./sail composer install` · `./sail composer migrate`
4. App: `http://127.0.0.1:8080/` (port from `APP_PORT` in `.env`)

WSL on Windows drives: see Compose notes in repo; prefer Docker Desktop WSL2 integration.

Database
--------
Phinx: `phinx.php`, migrations under `database/migrations/`. **`./sail composer migrate`** or `php bin/migrate.php`.

Quality & CI
------------
* `composer run stan` — PHPStan  
* `composer run cs-check` / `cs-fix` — PHP-CS-Fixer  
* `composer run test` — PHPUnit  
* `composer run check` — style + stan + test  
GitHub Actions: `.github/workflows/ci.yml`

Optional: `GAME_INTEGRATION_DB=1` for MySQL integration tests (needs reachable DB matching `DB_*` in `.env`). HTTP tests use a local `php -S` server unless `TEST_BASE_URL` points at a running app.
