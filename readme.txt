CandyGrill
==========

Simple online game server in PHP and MySQL. The client sends POST requests with a JSON body; the command is in the `command` field; responses are JSON.

The employer assignment wording: `docs/assignment-original-spec.md`.

Author notes
------------
Per the assignment, the submission should include the source code (including tests), a MySQL database dump, and this readme.

Architecture (trade-offs)
-------------------------
- Handlers resolve dependencies via optional constructor defaults and static factories such as SessionService::fromEnvironment() instead of a DI container — intentional for a small codebase; a single bootstrap/factory would be the next step if the project grows.
- MatchPool uses a process-wide singleton for the optional in-memory/memcached opponent pool; tests reset it via MatchPool::resetForTesting().
- Multi-step database work uses DatabaseConnection::transaction() so commit/rollback stay in one place (registration, combat attack, claim).
- Unexpected exceptions are turned into JSON 500 responses with code server_error; enable DEBUG in .env to include error detail in the payload during development.
