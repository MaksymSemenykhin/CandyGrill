CandyGrill — phase 0 (environment and repository)
==================================================

Target stack (locked in for upcoming phases)
--------------------------------------------
* PHP 8.1+
* MySQL 8+ (schema and connectivity — phase 2)
* Optional: Memcached (sessions, phase 5)
* Optional: Docker Compose (phase 8)

Repository
----------
* PSR-4 autoload: `Game\` namespace maps to `src/`.
* Tests: `Game\Tests\` maps to `tests/` (declared in Composer; you can add the folder when needed).
* Do not commit secrets or local overrides: use `.env` (ignored by git).

Install dependencies
--------------------
    composer install

After phase 1 this file will include the HTTP entry point and how to run the server.
