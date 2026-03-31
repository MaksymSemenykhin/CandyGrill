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
3. Open `http://127.0.0.1:8080/` — JSON от командного API: `ok`, `stage`, `message` и объект `profile` (время и память).
4. Inside the app container, `composer install` runs on first start if `vendor/` is missing.

### Verify phase 0.1 (automated)
After `composer install` on the host (or inside the app container):

    composer test

Runs PHPUnit: `ProjectLayoutTest` (layout, Phinx, Composer scripts), `DatabaseConfigTest`, `DatabaseMigrationFileTest`, `PhpdotenvLoadTest`, `PublicIndexHttpRequestTest` (HTTP GET `/` and `/index.php`, `Content-Type`), optional `PdoMysqlIntegrationTest` with `GAME_INTEGRATION_DB=1`.

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

Phase 1 — configuration + MySQL schema
---------------------------------------
* **Dotenv:** `vlucas/phpdotenv` — loaded from project root in `phinx.php`, `bin/migrate.php`, and anywhere else via `Dotenv::createImmutable($root)->safeLoad()`.
* **PDO:** `Game\Config\DatabaseConfig::fromEnvironment()` + `Game\Database\PdoFactory` (vars `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`). Reads `$_ENV` with `getenv()` fallback.
* **Migrations:** `robmorgan/phinx` — config `phinx.php`, one class per table under `database/migrations/`; DDL is inline in each file (`$this->execute(<<<'SQL' … SQL)`). Phinx tracks applied versions in table **`phinxlog`**. If you used the old in-house migrator (`schema_migrations`), reset the MySQL volume or drop those tables/rows before switching.

With stack up (`./sail up -d`), from the project root **inside the app container**:

```bash
./sail composer migrate
# same as: ./sail php bin/migrate.php  →  vendor/bin/phinx migrate
vendor/bin/phinx status   # optional
```

Second migrate run is a no-op when already up to date.

**Integration test (optional):** `PdoMysqlIntegrationTest` runs when `GAME_INTEGRATION_DB=1`. Use the same `DB_HOST` as the PHP process: e.g. `./sail exec app env GAME_INTEGRATION_DB=1 composer test` (host `DB_HOST=mysql` in `.env`). From the host OS you would need `DB_HOST=127.0.0.1` and a forwarded MySQL port. Default `composer test` in CI skips this test.

**Schema (agreed design):** `docs/database-schema.md` — integer PKs, append-only `combat_moves`, Memcached for sessions later.

Phase 1.1+ — JSON command API (incremental)
--------------------------------------------
Built in small steps so you can review each slice.

**Текущий маркер:** `Bootstrap::PHASE` = **1.2**.

### Part 1 (база API)

* **`Game\Api\Kernel`** + **`Game\Http\IncomingRequest`**.
* **GET** `/` или `/index.php` — JSON: `ok`, `stage`, `message`, **`profile`** (`time_ms`, `memory_bytes`, `memory_peak_bytes`).
* **POST** `/`, `application/json`, поле **`command`** (`a-z`, `0-9`, `_`).
* Команда проверяется **по файлу** в `src/Api/Handler/{Studly}Handler.php` (`ping` → `PingHandler`), класс реализует `CommandHandler`.
* Неизвестная команда → `400`, `unknown_command`; у ответов из `Kernel` есть **`profile`**.

### Part 2 (done в **1.2**)

* **`DatabaseConfig::isComplete()`** — есть ли `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` (не пустые); пароль может быть пустым. Без исключений.
* **`HealthHandler`**, команда **`health`:** `data.database.configured`, `data.database.reachable` (`SELECT 1` через PDO только если конфиг полный; при ошибке подключения `reachable` = `false`).

**curl** (порт из `.env`, чаще **8080**):

```bash
curl -sS "http://127.0.0.1:8080/"
curl -sS -X POST "http://127.0.0.1:8080/" \
  -H "Content-Type: application/json" \
  -d '{"command":"ping"}'
curl -sS -X POST "http://127.0.0.1:8080/" \
  -H "Content-Type: application/json" \
  -d '{"command":"health"}'
```

### Дальше по плану

* **Part 3:** sessions — `SESSION_DRIVER` memory / Memcached, выдача токена и разбор запроса.
* **Part 4:** `register`, `login`, `me` (+ репозитории).

Later phases
------------
Domain (combat), repositories, sessions, full API, validation/OpenAPI, health checks, etc.
