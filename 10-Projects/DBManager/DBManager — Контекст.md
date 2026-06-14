---
tags: [dbmanager, контекст]
---

# DBManager — Контекст

**Оновлено:** 2026-06-14
**Стан:** Плани 1.1 (Core) і 1.2 (DataBridge + доставка) ВИКОНАНО — далі план 1.3 (WP-плагін)

**Зроблено (план 1.1, Core — 49 тестів):**
- Laravel 13 + Docker (core + MySQL 8.4 + Redis); модель даних, значення з гео, слоти з резервами
- Failover (`SlotResolver` + `FailoverEngine`: падіння/відновлення, sticky, pin, спільні номери, дедуп інцидентів)
- `SitePayloadCompiler` (контракт payload: override сайт>група, месенджери, hidden, версіонування)
- Webhook моніторингу (HMAC, throttle, `DB::transaction`); `SiteProvisioner` (токени сайтів, ping_url); `BridgePublisher` (пуш у bridge після failover)

**Зроблено (план 1.2, DataBridge — 24 тести):**
- Окремий Laravel `bridge/` + власна `bridge-mysql` (ізоляція: bridge не торкається БД Core)
- Ingest Core→Bridge (HMAC, монотонність версій); serve по токену (підпис + ETag); звірка `If-None-Match`→304
- Rate limit + журнал запитів; push-пінг сайтам із HMAC і backoff (8 спроб); контрактний round-trip
- Security fail-closed: serve (500) і пінг (виключення) не віддають даних без секрета підпису — раніше підписували порожнім ключем

**Доперевірити (рев'ю плану 1.2 не дійшло до кінця через ліміт сесії 2026-06-14):** атомарність/гонки в ingest, FK на `request_logs`, тести на рівну версію / відкликаний токен / вичерпання пінга / replay, крихкість rate-limit-тесту. Прогнати рев'ю повторно (крос-модельне Claude↔Codex + security) перед мерджем у прод.

**Шлях коду:** `10-Projects/DBManager/code/` (core, bridge = Laravel). Тести з `code/`: `docker compose run --rm core|bridge php artisan test`.

**Наступний крок:** План 1.3 — WP-плагін (кеш, шорткоди, PHP-функції, вкладки «Дані»/«Вставка»/«Налаштування», mu-plugin фолбек). Сценарії: «bridge мертвий → сайт з кешу», «плагін видалили → значення показуються».

**Орієнтири незмінні:** [[DBManager — Дизайн]] — джерело правди; пріоритети №1 — анти-fingerprint + нуль зовнішніх залежностей.
