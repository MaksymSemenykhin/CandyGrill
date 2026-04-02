# Сверка с официальным ТЗ

Полный текст задания: **`docs/assignment-original-spec.md`**.

---

## Registration (TZ request #1) — реализовано

| Требование | Реализация |
|------------|------------|
| Клиент передаёт **имя персонажа** | JSON `name` (строка, после trim не пусто, ≤ 64 символов UTF-8). |
| Сервер создаёт **персонажа** со скиллами **0–50** случайно | Таблица `characters`: `skill_1`, `skill_2`, `skill_3`; `level=1`, `fights`/`fights_won`/`coins`=0. |
| Ответ — **player identifier** | Поле **`player_id`** — строка **UUID v4** (`users.public_id`); числовой `users.id` только внутри БД / FK. |
| Хранение атрибутов персонажа из ТЗ | Колонки: `name`, `level`, `fights`, `fights_won`, `coins`, `skill_1..3` (+ `version`, `updated_at` для будущего боя). |

Файлы: миграции `20260401120001_tz_registration_schema.php`, `20260404180000_add_users_public_id.php`, `RegisterHandler`, **`PlayerService::register`**, `UserRepository`, `CharacterRepository::createForPlayer`.

**`session_issue`:** в теле **`user_id`** — положительный **int** (внутренний id) или тот же **`player_id`** (UUID), если строка есть в `users`.

---

## Login (TZ request #2) — реализовано

| Требование | Реализация |
|------------|------------|
| Клиент передаёт **player identifier** | Поле **`player_id`** — строка **UUID v4** (то же значение, что **`player_id`** из `register`). |
| Сервер «логинит» и возвращает **session identifier** | **`session_id`** — токен сессии (64 hex); **`expires_in`** из `SESSION_TTL_SECONDS`. Передавать в заголовке **`Authorization: Bearer …`** или в теле как **`session_id`** (для совместимости ещё принимается **`access_token`**). |
| Неактивный пользователь | Строка в **`users`** с **`status` ≠ `active`** не получает сессию → **401** `unknown_player`. |

Файлы: **`LoginHandler`**, **`PlayerService::login`**, **`LoginPlayerIdInput`**, **`ActivePlayerLookup`**, **`UserRepository`**, **`SessionService`**.

---

## Остальное ТЗ (п.3–6, бой, уровни)

Не реализовано — `docs/assignment-original-spec.md`.

---

## Локализация ответов

Тексты ошибок, **bootstrap** (**`GET /`**) и остальных ответов API переводятся через **Symfony Translation** (`symfony/translation`), каталог **`translations/api.{locale}.yaml`**, домен **`api`**.

**Приоритет языка:** поля тела **`locale`** или **`lang`** (строка `en` / `ru` и региональные варианты вроде `ru-RU`) → те же имена в **query** (в т.ч. **`GET /?locale=ru`** для bootstrap и **`POST /?locale=ru`** для команд) → **`Accept-Language`** (слово `ru`) → **`APP_LOCALE`** в `.env**.

В каждом JSON-ответе Kernel добавляет поле **`locale`**: фактически применённый код языка (`en` | `ru`).

---

## Общие требования из Objective

| Требование | Статус |
|------------|--------|
| Без PHP-фреймворков | Да. |
| Параллельные запросы | Зависит от деплоя (FPM и т.д.). |
| Масштаб, Memcached | Частично (сессии, Compose). |
| Расширяемость API | Да (`command` + хендлеры). |
