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

Файлы: миграции `20260401120001_tz_registration_schema.php`, `20260404180000_add_users_public_id.php`, `RegisterHandler`, `UserRepository`, `CharacterRepository::createForPlayer`.

**`session_issue`:** в теле **`user_id`** — положительный **int** (внутренний id) или тот же **`player_id`** (UUID), если строка есть в `users`.

---

## Остальное ТЗ (п.2–6, бой, уровни)

Не реализовано — см. матрицу в истории репозитория / `docs/assignment-original-spec.md`.

---

## Общие требования из Objective

| Требование | Статус |
|------------|--------|
| Без PHP-фреймворков | Да. |
| Параллельные запросы | Зависит от деплоя (FPM и т.д.). |
| Масштаб, Memcached | Частично (сессии, Compose). |
| Расширяемость API | Да (`command` + хендлеры). |
