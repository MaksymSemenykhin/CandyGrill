# Traceability vs official specification

Full assignment text: **`docs/assignment-original-spec.md`**.

---

## Registration (spec request #1) — implemented

| Requirement | Implementation |
|-------------|----------------|
| Client sends **character name** | JSON `name` (string, non-empty after trim, ≤ 64 UTF-8 code points). |
| Server creates **character** with skills **0–50** at random | Table `characters`: `skill_1`, `skill_2`, `skill_3`; `level=1`, `fights`/`fights_won`/`coins`=0. |
| Response — **player identifier** | Field **`player_id`** — **UUID v4** string (`users.public_id`); numeric `users.id` only internally / FK. |
| Persist character fields from spec | Columns: `name`, `level`, `fights`, `fights_won`, `coins`, `skill_1..3` (+ `version`, `updated_at` for future combat). |

Files: migrations `20260401120001_tz_registration_schema.php`, `20260404180000_add_users_public_id.php`, `RegisterHandler`, **`PlayerService::register`**, `UserRepository`, `CharacterRepository::createForPlayer`.

**`session_issue`:** body field **`user_id`** — positive **int** (internal id) or the same **`player_id`** (UUID) if the string exists in `users`.

---

## Login (spec request #2) — implemented

| Requirement | Implementation |
|-------------|----------------|
| Client sends **player identifier** | Field **`player_id`** — **UUID v4** string (same as **`player_id`** from `register`). |
| Server logs in and returns **session identifier** | **`session_id`** — session token (64 hex); **`expires_in`** from `SESSION_TTL_SECONDS`. Send as **`Authorization: Bearer …`** or body **`session_id`** (also accepts **`access_token`** for compatibility). |
| Inactive user | Row in **`users`** with **`status` ≠ `active`** gets no session → **401** `unknown_player`. |

Files: **`LoginHandler`**, **`PlayerService::login`**, **`LoginPlayerIdInput`**, **`ActivePlayerLookup`**, **`UserRepository`**, **`SessionService`**.

---

## Current player profile (`me`) — implemented

| Requirement | Implementation |
|-------------|----------------|
| Authenticated client reads character | JSON `command` = **`me`**, session via **`Authorization: Bearer`** (or **`session_id`** / **`X-Session-Token`** like other POSTs). |
| Response | **`player_id`** (UUID) plus TZ character fields: **`name`**, **`level`**, **`fights`**, **`fights_won`**, **`coins`**, **`skill_1`**, **`skill_2`**, **`skill_3`**. |
| No session | **401** `unauthorized`. |
| No character row | **404** `character_not_found`. |

Files: **`MeHandler`**, **`CharacterRepository::findGameProfileByUserId`**.

---

## Choosing an opponent (spec request #3) — implemented

| Requirement | Implementation |
|-------------|----------------|
| Client requests opponent matchmaking | JSON `command` = **`find_opponents`**, session: **`Authorization: Bearer`** + token from **`login`** (or **`session_id`** in body like other POSTs). |
| Server picks up to **two** random opponents at the **same level** as the client character | **`MatchPool`** index: on **`login`** (and **`find_opponents`** — TTL refresh) the player is stored with `level`, `player_id`, `name`, `until` ≈ session TTL; pick from pool by level (up to 2), backfill from DB — `CharacterRepository::findRandomOpponentSummaries` (active **`users`**, same `level`, exclude `user_id` and already chosen `player_id`s). Pool storage: JSON + flock (path from `SESSION_MEMORY_SYNC_FILE` → `match-pool.json`, or **`MATCH_POOL_SYNC_FILE`**) or **`MATCH_POOL_DRIVER=memcached`**. Disable: **`MATCH_POOL_ENABLED=false`** — SQL only. |
| Response — **ids and names** | Each item: **`player_id`** (UUID `users.public_id`), **`name`**. Field **`data.opponents`** — array of **1 or 2** entries. |
| No suitable players | **404** `no_opponents_available`. |
| No session | **401** `unauthorized`. |
| No **`characters`** row for session user | **404** `character_not_found`. |

Files: **`FindOpponentsHandler`**, **`CharacterRepository`** (level lookup and opponents).

---

## Starting combat (spec request #4) — implemented

| Requirement | Implementation |
|-------------|----------------|
| Client sends **id of the opponent** | JSON **`opponent_player_id`** — UUID v4 (`player_id` of the opponent). |
| Session | **`Authorization: Bearer`** (or body **`session_id`**) like other game commands. |
| Response — **opponent skill values** | nested **`opponent`**: **`player_id`**, **`skill_1`**, **`skill_2`**, **`skill_3`**. |
| **First attacker random** | Field **`first_striker**: `you` \| `opponent` (`you` = initiator / session holder). |
| Opponent acts first | **`opponent_first_move`**: `{ skill: 1\|2\|3, points }` (AI); otherwise `null`. |
| Persist session | **`combat_id`** (UUID) in **`combats`**; optional row in **`combat_moves`** for AI opening; **`state`** JSON for the combat engine. Stats not applied until prize claim (§6). |
| Same **level** | **400** `opponent_level_mismatch`. |
| Self-opponent | **400** `cannot_fight_self`. |
| Unknown / inactive opponent | **404** `opponent_not_found`. |

Files: **`StartCombatHandler`**, **`CombatOpening`**, **`CombatMath`**, **`CombatRepository`**, **`OpponentPlayerIdInput`**, **`CharacterRepository::findInternalIdByUserId`**.

---

## Combat attack (spec §5) — implemented

| Requirement | Implementation |
|-------------|----------------|
| Command | **`combat_attack`**: **`combat_id`** (UUID from `start_combat`), **`skill`** `1`–`3`. |
| Who may play | Only **`state.initiator_user_id`**. Otherwise **403** `not_your_combat`. |
| Turn order | **`CombatTurnOrder`** + **`completed_strikes`**; **409** `not_your_turn` if initiator strikes out of order. |
| Rules | **`CombatStrikeRules`** (no repeat own; no copy opponent’s last). **400** `illegal_skill`. |
| Response | **`your_move`**, optional **`opponent_move`** (AI when combat continues), scores, **`combat_finished`**, **`coins_won`** when finished (actual balance on **claim**, §6). |
| Persistence | Transaction + **`findByPublicIdForUpdate`**; **`appendMove`** per strike; finish updates **`status`**, **`winner_character_id`**, **`finished_at`**. |

Files: **`CombatAttackHandler`**, **`CombatAttackService`**, **`CombatAttackInput`**, **`CombatAi`**, **`CombatResolution`**.

---

## Remaining spec (§6–7, prize claim, levelling)

Prize **claim**, levelling — not implemented. See `docs/assignment-original-spec.md`.

---

## Response localization

Errors, **bootstrap** (**`GET /`**), and other API responses use **Symfony Translation** (`symfony/translation`), files **`translations/api.en.yaml`** and **`translations/api.ru.yaml`**, domain **`api`**.

**Language priority:** body field **`lang`** (`en` / `ru` and variants like `ru-RU`) → query **`lang`** (including **`GET /?lang=ru`** and **`POST /?lang=ru`**) → **`Accept-Language`** (word `ru`) → **`APP_LANG`** in `.env`.

Every JSON response includes **`lang`**: the applied locale code (`en` | `ru`).

---

## General objectives

| Requirement | Status |
|-------------|--------|
| No PHP frameworks | Yes. |
| Concurrent requests | Depends on deployment (FPM, etc.). |
| Scale, Memcached | Partially (sessions, Compose). |
| API extensibility | Yes (`command` + handlers). |
