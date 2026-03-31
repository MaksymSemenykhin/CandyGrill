# Database and session design (high-load oriented)

This document reflects agreed decisions: UUID primary keys for identity/characters, append-only combat log, **Memcached** for access tokens (no MySQL `MEMORY` engine for sessions).

## Principles

- **MySQL (InnoDB)** is the source of truth for durable game data.
- **Memcached** stores short-lived **opaque access tokens** mapped to a user id (TTL-based expiry).
- **No sliding TTL on every HTTP request** — session lifetime is renewed on **login** (new token + fresh TTL, e.g. **24 hours**). Fewer cache writes, simpler semantics.
- Prefer **time-ordered UUIDs (v7)** over random UUID v4 for primary keys to reduce B-tree fragmentation on insert.

## Identifiers

| Entity | PK type | Storage in MySQL | External / JSON |
|--------|---------|------------------|-----------------|
| `users.id` | UUID v7 | `BINARY(16)` | Canonical string (hex with hyphens) or base32 if you shorten URLs |
| `characters.id` | UUID v7 | `BINARY(16)` | Same |
| `combats.id` | UUID v7 (recommended) | `BINARY(16)` | Same — safe to expose in API |
| `combat_moves.id` | `BIGINT` auto-increment (optional) | `BIGINT` | Usually internal only |

Using `BINARY(16)` avoids `CHAR(36)` overhead. Application layer converts to/from string at API boundaries.

## Tables (logical)

### `users`

- `id` `BINARY(16)` PK  
- `email` (or `login`) `VARCHAR` **UNIQUE**  
- `password_hash` `VARCHAR`  
- `created_at`, `updated_at`  
- Optional: `status` for ban/disable  

### `characters`

- `id` `BINARY(16)` PK  
- `user_id` `BINARY(16)` **UNIQUE** FK → `users.id` (one character per user unless requirements change)  
- Game columns per assignment (name, level, stats, …)  
- `version` `INT NOT NULL DEFAULT 0` — optimistic locking on updates after combat  
- `updated_at`  

### `combats`

- `id` `BINARY(16)` PK  
- `participant_a_id`, `participant_b_id` `BINARY(16)` FK → `characters.id`  
- `status` — e.g. `pending` / `active` / `finished` / `cancelled`  
- `winner_character_id` `BINARY(16)` NULL  
- `started_at`, `finished_at` (nullable)  
- Optional: small `state` JSON if you need unstructured snapshot; keep indexed columns normalized for hot queries  

**Indexes (examples):**  
`(participant_a_id, status)`, `(participant_b_id, status)` for “find my active combat” (or complement with Memcached hot key, see below).

### `combat_moves` (append-only)

- `id` `BIGINT` PK auto-increment  
- `combat_id` `BINARY(16)` NOT NULL FK → `combats.id`  
- `turn_number` `INT` NOT NULL  
- `actor_character_id` `BINARY(16)` NOT NULL  
- `payload` `JSON`  
- `created_at`  

**Unique:** `(combat_id, turn_number)` — avoids duplicate turns and races.  
Reads: `WHERE combat_id = ? ORDER BY turn_number` with pagination if history grows large.

## Sessions / access tokens (Memcached)

- **Not** stored in MySQL `MEMORY` tables — use Memcached (already in Compose) or add Redis later if you need richer TTL/structures.
- On **login**: generate opaque token (random bytes), `KEY =` prefix + hash of token (never store raw token as key if you log keys), `VALUE =` compact blob or JSON with `user_id` (binary UUID or string), **`TTL = 86400`** (24h example).
- **Renewal:** new login issues a new token and **re-`SET`s** with full TTL; old tokens expire naturally unless you explicitly delete keys (e.g. single-session policy).
- **Multi-device:** define policy — allow multiple tokens per user (multiple keys) or maintain `user_id → current token id` and invalidate previous keys on login.

Suggested key shape (example): `cg:sess:{sha256_hex(token)}` — value: `user_id` + optional metadata (scopes, issued_at).

## Optional Memcached for combat read path

To reduce DB load on hot paths:

- `cg:combat:active:{character_id}` → `combat_id`, short TTL; invalidate on combat end.  
- `cg:char:{character_id}` → cached profile snapshot, short TTL; invalidate after stat updates.

DB remains authoritative; cache is safe to drop.

## ER overview

```mermaid
erDiagram
  users ||--o| characters : owns
  characters ||--o{ combats : participates
  combats ||--o{ combat_moves : has
```

## Evolution

- **Redis** — consider if you need frequent `EXPIRE` updates, refresh-token rotation tables in memory, or sorted sets for matchmaking at scale.  
- **Sharding** — UUID v7 keeps IDs globally unique; shard key can be derived later (e.g. hash of `user_id`).  
- **Archival** — old `combat_moves` partitions by month when volume grows.
