# Implementation vs `docs/assignment-original-spec.md`

---

## Registration (#1)

| Requirement | Implementation |
|-------------|----------------|
| Name → character | JSON `name` (trim, ≤ 64 code points). |
| Skills 0–50 random | `characters.skill_1..3`; `level=1`, fights/coins zeroed. |
| Player id | `player_id` UUID v4 (`users.public_id`). |

`session_issue`: body `user_id` = internal int or existing `player_id` UUID.

---

## Login (#2)

| Requirement | Implementation |
|-------------|----------------|
| `player_id` UUID | Returns `session_id` (64 hex), `expires_in`. |
| Bearer / body | `Authorization: Bearer`, or `session_id` / `X-Session-Token`. |
| Inactive user | **401** `unknown_player`. |

---

## `me`

Authenticated `command: me` → `player_id`, `name`, `level`, `fights`, `fights_won`, `coins`, `skill_1..3`.

---

## `find_opponents` (#3)

Up to **2** opponents, same **level**, active users: `MatchPool` + `CharacterRepository::findRandomOpponentSummaries` (optional Memcached). **404** `no_opponents_available`.

---

## `start_combat` (#4)

`opponent_player_id` UUID. Response: opponent skills, `first_striker` (`you`/`opponent`), scores, `opponent_first_move` if they open, `combat_finished`, `coins_won` (null until finished). Persist `combats` + `state`; stats apply on `claim` only.

---

## `combat_attack` (#5)

`combat_id`, `skill` 1–3. Initiator only; turn + repeat/copy rules. Response: moves, scores, `combat_finished`, `coins_won` when done (banked on `claim`).

---

## `claim` (#6)

Initiator, combat `finished`, `results_applied_at` null. Updates `fights` / `fights_won` / `coins` (winner **+10** coins) / `level`, then `markResultsApplied`.

---

## Levelling

`level = max(1, 1 + floor(fights_won / 3))` (`LevelingRules::WINS_PER_LEVEL`), applied in the same `UPDATE` as claim.

---

## Not implemented

Skill upgrades for coins (TZ “in the future”).

---

## i18n

`translations/api.*.yaml`, domain `api`. Order: body `lang` → query `lang` → `Accept-Language` → `APP_LANG`. Every response includes `lang`.

---

## Project constraints

No full PHP framework; PDO; command handlers; optional Memcached for sessions / pool.
