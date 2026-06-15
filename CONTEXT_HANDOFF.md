# CONTEXT_HANDOFF — DBManager

Знімок стану роботи для продовження в новій сесії. Канонічний контекст проєкту — `10-Projects/DBManager/DBManager — Контекст.md`; огляд для людини — `10-Projects/DBManager/documentation/`.

**Оновлено:** 2026-06-15 · **Гілка:** main, **ahead 4** від origin (origin на `fd31d7d`; дерево чисте) · **Останній коміт:** `759ac93` · **Core 151 тест зелений.**

## Стан роботи

Проєкт **DBManager** (`10-Projects/DBManager/code/`): три компоненти — **Core** (CRM/адмінка, Laravel), **DataBridge** (публічний ізоляційний шар, Laravel), **WP-плагін** (на сайтах). Деталі «чому 3 URL» — `documentation/Архітектура та чому три URL.md`.

- **Етап 1 — ВИКОНАНО й запушено:** план 1.1 (Core: модель, failover, компілятор payload, webhook), 1.2 (DataBridge: ingest/serve/пінги/звірка, HMAC, geodb), 1.3 (WP-плагін: кеш, шорткод, mu-фолбек, гео на PHP без JS — рішення в `10-Projects/DBManager/adr/2026-06-14 — Гео-рендер плагіна на PHP без JS.md`).
- **Етап 2 — у роботі (адмінка Core, Livewire 3 + Blade + Tailwind):**
  - **2.1 ✅** — вхід (Breeze, сидований `admin@dbmanager.local`/`admin`), оболонка `/admin` (повний екран, хедер/крихти/контекст/сайдбар за макетом v5), грід «Значення» (failover-статуси, область дії, фільтри тип/гео/статус/пошук, перемикач сайта). Read-model `App\Admin\SiteGridReader`.
  - **2.2 ✅** — панель слота (права, 420px, завжди зарезервована): ланцюг номерів, pin/unpin, режим повернення, вичерпання, перемикання месенджерів, «Зберегти»→публікація.
  - **2.3 ✅** — редактор значень: CRUD, область дії група/сайт, перекриття групового, публікація уражених; хелпер `App\Admin\AffectedSites`.
  - **2.4 ✅** — телефонний слот: створення (DataValue+PhoneSlot), у панелі — додати/видалити/редагувати(e164)/↑↓-пріоритет/повернути-деактивувати номер.
  - **2.5 — НА ПАУЗІ (Task 1 з 4 зроблено):** виділення рядків чекбоксами. Лишилось: прев'ю «торкнеться N сайтів», bulk-видалення з публікацією. План: `plans/2026-06-15 — План 2.5 — Bulk-дії.md`.
  - **2.6 — ЗАПРОПОНОВАНО, не почато:** єдина панель редагування (див. «Наступні кроки»).
- Демо-дані: `php artisan db:seed --class=DemoDataSeeder` (група Brand A + domen.ua/domen.org).

## Ключові змінені файли (Етап 2; повний перелік — `git log`)

- `core/app/Livewire/` — `ValuesGrid.php` (грід, фільтри, перемикач сайта, виділення), `SlotPanel.php` (панель слота: pin/режим/вичерпання/месенджери/номери CRUD + статус), `ValueEditor.php` (модалка CRUD значень).
- `core/app/Admin/` — `SiteGridReader.php`, `AffectedSites.php`.
- `core/resources/views/livewire/` — `values-grid`, `slot-panel`, `value-editor` `.blade.php`.
- `core/resources/views/components/layouts/admin.blade.php` (оболонка), `core/resources/views/admin/icons.blade.php` (Lucide-спрайт), `core/app/Helpers/SvgIcon.php` (директива `@svg`).
- `core/routes/web.php` (/ і /dashboard → /admin), `core/database/seeders/{AdminUserSeeder,DemoDataSeeder}.php`.
- `core/.env` + `core/.env.example`, `bridge/.env` + `.env.example` — `DB_HOST`=ім'я сервісу (Laravel читає лише .env, не OS-env compose).
- `docker-compose.yml` — сервіси `wordpress`/`wpcli`, всі порти прив'язані до `127.0.0.1` (безпека; застосується при наступному `docker compose up -d`).
- Тести: `core/tests/Feature/Admin/*` (грід, панель, редактор, номери, виділення).

## Поточні проблеми / незавершене

1. **Редагування НЕ уніфіковано в панель** (фідбек користувача): не-телефонні значення відкривають **модалку**, а не бічну панель; **гео-мітки** й **прив'язку месенджера до слота** ще не можна редагувати. → план 2.6.
2. **Bulk (2.5) на паузі** — лише виділення; прев'ю + bulk-видалення не зроблено.
3. **Рев'ю перед продом не проведено** — Етап 2 — багато коду від субагентів; за AGENTS.md §6 потрібне крос-модельне (Codex) + security-рев'ю перед продом.
4. **Loopback-порти** в compose закомічено, але до запущених контейнерів НЕ застосовано (застосуються при `docker compose up -d`).
5. **Технічний борг Етапу 3** (з попередніх рев'ю): anti-replay (nonce/час) на HMAC ingest+webhook; ретенція `request_logs`; моніторинг `failed_jobs`; пропагація відкликання токена Core→Bridge; наскрізний E2E-тест контракту; нити 1.3 (per-request MaxMind Reader, strict base64 на geodb serve, HTTPS-валідація `bridge_url`, тест `MaxMindCountryLookup` на реальній mmdb). Зафіксовано в `DBManager — План.md` Етап 3.
6. **Гетча оточення:** часто застрягає `.git/index.lock` (IDE fsmonitor) — якщо git падає на lock і він старший за ~60с без активного git-процесу, видалити `rm -f .git/index.lock`. Не вбивати `git fsmonitor--daemon`. Git Bash манглить контейнерні шляхи — для них `MSYS_NO_PATHCONV=1` або лапки.

## Наступні кроки (за пріоритетом)

1. **План 2.6 — Єдина панель редагування** (узгоджено з користувачем): клік на будь-який рядок → права панель (не модалка); у панелі редагувати значення (інлайн), **гео-мітки** (додати/прибрати), **прив'язку месенджера** до слота, область дії, Зберегти/Видалити; модалку лишити хіба для «+ Додати» або теж перенести в панель. Стиль — синій акцент `#54708c`, Lucide-іконки.
2. **План 2.5 — добити bulk:** прев'ю «торкнеться N сайтів» + bulk-видалення з публікацією (Tasks 2–4).
3. Далі Етап 2: дашборд + дзвіночок інцидентів + аудит-стрічка з відкатом; ролі/права; імпорт CSV/XLSX.
4. **Перед продом:** крос-модельне рев'ю (Codex) + security; борг Етапу 3.
5. Запушити накопичене (`git push`; ahead 4).

## Запуск і доступи (локально, з `10-Projects/DBManager/code`)

- Підняти: `docker compose up -d`.
- **CRM (Core):** http://localhost:8001 → `admin@dbmanager.local` / `admin` (`/` веде на /login → /admin).
- **DataBridge:** http://localhost:8002/up (API, не UI).
- **WordPress-стенд (плагін):** http://localhost:8080/wp-admin → `admin` / `admin`.
- Тести: `docker compose run --rm core php artisan test` (151), `… bridge …`, `docker compose run --rm plugin ./vendor/bin/phpunit`.
- Збірка фронту адмінки: `MSYS_NO_PATHCONV=1 docker run --rm -v "C:/Dev/Meweek/10-Projects/DBManager/code/core:/app" -w /app node:22 npm run build`.

## Процес

Плани — `10-Projects/DBManager/plans/`; виконання — субагентами по тасках (TDD: тест→red→impl→green→коміт). Коміти українською `dbmanager(...)`, лише шляхи проєкту, push лише на прохання користувача.
