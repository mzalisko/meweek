---
tags: [dbmanager, контекст]
---

# DBManager — Контекст

**Оновлено:** 2026-06-15
**Стан:** Плани 1.1 (Core), 1.2 (DataBridge), 1.3 (WP-плагін з гео на PHP) ВИКОНАНО. Етап 2 розпочато — план 2.1 (адмінка): вхід (Breeze) + оболонка `/admin` готові (Tasks 1–2 з 7), грід «Значення» — наступні таски. Огляд системи для людини — `documentation/`.

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

**Доперевірку рев'ю 1.2 завершено (2026-06-14):** виправлено — атомарна монотонність ingest (`lockForUpdate`), гранична перевірка рівної версії, лог відмов пушу bridge. Не баг — відсутність FK на `request_logs` (навмисно: логуються й невідомі токени). Відкладено в Етап 2/3 (борг безпеки/стабільності, див. План): anti-replay (nonce/час) на HMAC ingest+webhook, ретенція `request_logs`, моніторинг failed_jobs (вичерпання пінга), пропагація відкликання токена Core→Bridge, наскрізний E2E-тест контракту. Перед продом — крос-модельне рев'ю (Codex) + security.

**Зроблено (план 1.3, WP-плагін — 36 юніт-тестів + інтеграція на живому WP):**
- Чиста WP-незалежна логіка (рендер з кешу, HMAC-перевірка, синхронізатор, гео-видимість) — PHPUnit з фейками; рендер суто PHP, **без JS**
- Доставка: приймач пінга `/wp-json/dbm/v1/ping` (HMAC), добова звірка `If-None-Match`, кеш у `wp_options` (переживає видалення плагіна)
- 3 вкладки адмінки («Дані»/«Вставка»/«Налаштування»); шорткод `[dbm key=…]` + `dbm_get()` + `format=tel`; mu-plugin фолбек (самодостатній)
- Гео на PHP: видимість за країною (CF-IPCountry → MaxMind → WORLD); MaxMind-база роздається центр→bridge→плагін; вбудований чистий-PHP `maxmind-db/reader`. Рішення — [[2026-06-14 — Гео-рендер плагіна на PHP без JS|ADR]]
- Інтеграційні сценарії-приймання зелені: «bridge мертвий → кеш», «плагін видалено → mu-фолбек», «UA бачить UA-значення, інші — WORLD». Людський гайд: `50-Guides/AI/Гайд — WP-плагін DBManager.md`

**Шлях коду:** `10-Projects/DBManager/code/` (core, bridge = Laravel; `plugin/` = WP-плагін). Тести: `docker compose run --rm core|bridge php artisan test`; `docker compose run --rm plugin ./vendor/bin/phpunit`; інтеграція — `wpcli` + `bin/install-wp.sh` + `bin/scenario*.php`.

**Фінальне рев'ю 1.3 пройдено (2026-06-14):** безпека/анти-fingerprint/живучість — надійні; знайдено й виправлено 2 функціональні гео-баги (vendor MaxMind не вантажився/не деплоївся → IP-лукап мертвий; `dbm_get('key')` без країни затінювався mu-фолбеком). Нити (per-request reader, strict base64, HTTPS-валідація bridge_url, тест на реальній mmdb) → борг Етапу 3.

**Наступний крок:** Етап 2 — адмінка за UI, ролі й права, імпорт CSV/XLSX, аудит з відкатом (гео-рендер уже зроблено в 1.3). Перед продом — крос-модельне рев'ю (Codex) + борг безпеки з Етапу 3.

**Орієнтири незмінні:** [[DBManager — Дизайн]] — джерело правди; пріоритети №1 — анти-fingerprint + нуль зовнішніх залежностей.
