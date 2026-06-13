---
tags: [dbmanager, контекст]
---

# DBManager — Контекст

**Оновлено:** 2026-06-13
**Стан:** План 1.1 (Core) ВИКОНАНО — далі план 1.2 (DataBridge і доставка)

**Зроблено (план 1.1, код у `code/`):**
- Laravel 13 + Docker (core + MySQL 8.4 + Redis); 42 тести зелені; міграції на MySQL пройшли
- Модель даних: сайти/групи/токени, значення з типами і гео-мітками, слоти з ланцюгами резервів, аудит/інциденти/публікації
- Failover: `SlotResolver` (ok/on_reserve/pinned/exhausted) + `FailoverEngine` (падіння/відновлення, sticky, pin/unpin, спільні номери, дедуплікація інцидентів, точні before-знімки)
- `SitePayloadCompiler` — контракт payload (override сайт>група, телефони з failover, прив'язані месенджери viber/wa, hidden, версіонування)
- Webhook моніторингу: HMAC, throttle, атомарність (DB::transaction), публікація уражених сайтів

**Шлях коду:** `10-Projects/DBManager/code/` (core = Laravel). Запуск тестів: з `code/` → `docker compose run --rm core php artisan test`.

**Відкладено в наступні плани/деплой (з фінального рев'ю):**
- Черга публікацій замість inline (план 1.2 — доставка)
- Throttle webhook по IP може гасити легітимний failover-шторм → переглянути ключ ліміту з дизайном моніторингу
- Шлях webhook (напряму в Core чи приймач на bridge) і VPN vs allowlist — з DevOps при розгортанні

**Наступний крок:** writing-plans → план 1.2 (DataBridge: read-only API по токену з контракту payload, push-пінги з ретраями, добова звірка) і далі 1.3 (WP-плагін DBManager з mu-фолбеком)

**Орієнтири незмінні:** [[DBManager — Дизайн]] — джерело правди; пріоритети №1 — анти-fingerprint + нуль зовнішніх залежностей.
