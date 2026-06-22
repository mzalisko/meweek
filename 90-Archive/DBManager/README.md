# DBManager — інструкція з розгортання та передачі проєкту

Повний пакет проєкту: центральна CRM (**core**), міст доставки (**bridge**) і **WordPress-плагін**.
Vendor/node_modules навмисно НЕ включені — нижче описано, як встановити всі залежності.

---

## 1. Що це за система

Три компоненти, що працюють разом:

| Компонент | Папка | Що робить | Технологія |
|---|---|---|---|
| **Core (CRM)** | `core/` | Адмінка: сайти, телефони, месенджери, ціни, адреси, соцмережі, масові операції, аудит, дашборд. Тут редагуються всі дані. | Laravel 12, PHP 8.4, MySQL |
| **Bridge (міст)** | `bridge/` | Приймає опубліковані дані з Core, зберігає та **доставляє** їх на сайти (плагіни). Підписує дані. | Laravel 12, PHP 8.4, MySQL, черга |
| **Plugin** | `plugin/dbmanager/` | WordPress-плагін: отримує підписані дані з Bridge і показує на сайті. Редагування лише в Core. | PHP 8.4, WordPress |

### Потік даних
```
Адміністратор → Core (редагує) → push → Bridge (зберігає + підписує) → ping → Plugin (на сайті)
```
- Core підписує push до Bridge секретом `BRIDGE_PUBLISH_SECRET` (спільний).
- Bridge доставляє дані плагіну на `ping_url`, підписані **per-site `push_secret`**.
- Плагін перевіряє підпис своїм `signing_secret` (отримує його в «ключі підключення»).

---

## 2. Вимоги

- **PHP 8.4** (з розширеннями: pdo_mysql, mbstring, openssl, curl, bcmath, fileinfo, zip)
- **Composer 2**
- **Node.js 18+** і **npm** (для збірки фронтенду Core)
- **MySQL 8** (дві окремі БД: `dbmanager_core`, `dbmanager_bridge`)
- **WordPress 6+** на PHP 8.4 (для плагіна)
- (Опційно) **Docker + Docker Compose** — найшвидший спосіб підняти все локально

---

## 3. Швидкий старт через Docker (для демо / локальної перевірки)

У корені проєкту вже є `docker-compose.yml` (core, bridge, bridge-worker, два MySQL, redis, wordpress, wpcli).

```bash
# 1. Створити кореневий .env з паролем MySQL
echo "MYSQL_ROOT_PASSWORD=secret" > .env

# 2. Підняти все
docker compose up -d

# 3. Core: залежності, ключ, БД, перший користувач
docker compose exec core composer install
docker compose exec core npm install && docker compose exec core npm run build
docker compose exec core cp .env.example .env   # потім заповнити (див. розділ 5)
docker compose exec core php artisan key:generate
docker compose exec core php artisan migrate --seed

# 4. Bridge: залежності, ключ, БД
docker compose exec bridge composer install
docker compose exec bridge cp .env.example .env # потім заповнити
docker compose exec bridge php artisan key:generate
docker compose exec bridge php artisan migrate
```

- Core: http://127.0.0.1:8001  ·  Bridge: http://127.0.0.1:8002  ·  WordPress: http://127.0.0.1:8080
- **`bridge-worker`** (черга) піднімається автоматично — він **обов'язковий** для доставки даних плагіну.

> Локальні значення секретів/URL для Docker — у розділі 5 (приклад «локальне середовище»).

---

## 4. Ручне встановлення (для продакшну, без Docker)

Виконати для **кожного** з трьох компонентів.

### 4.1 Core (CRM)
```bash
cd core
composer install --no-dev --optimize-autoloader   # PHP-залежності
npm install && npm run build                       # фронтенд (Vite/Tailwind)
cp .env.example .env                                # конфіг (заповнити — розділ 5)
php artisan key:generate                            # APP_KEY
php artisan migrate                                 # схема БД
php artisan db:seed --class=Database\\Seeders\\ValueTypeSeeder   # типи даних (обов'язково!)
php artisan db:seed --class=Database\\Seeders\\GeoTagSeeder      # гео-теги
# перший користувач — див. розділ 6
```
- Веб-сервер (nginx/apache) направити на `core/public`.
- Права на запис: `core/storage`, `core/bootstrap/cache`.

### 4.2 Bridge (міст)
```bash
cd bridge
composer install --no-dev --optimize-autoloader
cp .env.example .env                                # заповнити — розділ 5
php artisan key:generate
php artisan migrate
```
- Веб-сервер → `bridge/public`.
- **Черга обов'язкова** (доставка ping плагіну йде через job):
  ```bash
  php artisan queue:work --sleep=1 --tries=8 --timeout=30
  ```
  У проді — під **supervisor/systemd** (щоб worker завжди працював). Без нього дані не доходять до плагінів.

### 4.3 Plugin (WordPress)
```bash
cd plugin
composer install            # генерує автозавантажувач (vendor/)
```
- Папку `plugin/dbmanager/` (разом із `vendor/`, якщо плагін його використовує) скопіювати у `wp-content/plugins/dbmanager/` на WordPress-сайті.
- Активувати плагін у WP-адмінці.
- Підключити до сайту — розділ 7.

---

## 5. Конфігурація `.env` (ключове)

### Core (`core/.env`)
| Змінна | Значення | Нотатка |
|---|---|---|
| `APP_KEY` | (генерується) | `php artisan key:generate` |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | для проду! |
| `DB_DATABASE` | `dbmanager_core` | окрема БД Core |
| `DB_HOST/PORT/USERNAME/PASSWORD` | дані вашого MySQL | |
| `BRIDGE_INGEST_URL` | `https://<bridge-host>/api/internal/publish` | куди Core пушить дані |
| `BRIDGE_PUBLISH_SECRET` | **спільний секрет** | **МАЄ збігатися з bridge!** |
| `BRIDGE_GEODB_URL` | `https://<bridge-host>/api/internal/geodb` | для гео-БД |
| `BRIDGE_LOCAL_PING_URL` | **порожньо в проді** | лише локально: примусовий ping-URL (напр. `http://wordpress/?rest_route=/dbm/v1/ping`) |
| `MONITORING_WEBHOOK_SECRET` | секрет моніторингу | |

### Bridge (`bridge/.env`)
| Змінна | Значення | Нотатка |
|---|---|---|
| `APP_KEY` | (генерується) | |
| `DB_DATABASE` | `dbmanager_bridge` | окрема БД Bridge |
| `BRIDGE_PUBLISH_SECRET` | **той самий, що в Core** | перевірка підпису push |
| `BRIDGE_DATA_SIGNING_SECRET` | випадковий секрет | (резерв; serve тепер підписує per-site `push_secret`) |
| `BRIDGE_PING_SECRET` | випадковий секрет | |

> **Найважливіше:** `BRIDGE_PUBLISH_SECRET` у Core **і** Bridge — **однаковий** рядок (мін. 32 символи). Інакше Bridge відхилятиме push від Core (401). Згенерувати: `php -r "echo bin2hex(random_bytes(32));"`.

---

## 6. Створення першого користувача (адміністратора)

### Варіант А — демо/тестові акаунти (сідер)
```bash
cd core
php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder
```
Створює:
- **admin@dbmanager.local** / пароль `admin` — супер-адмін
- manager@dbmanager.local / `manager` — менеджер
- viewer@dbmanager.local / `viewer` — переглядач

> ⚠️ У ПРОДІ ці дефолтні паролі **обов'язково змінити** або не використовувати сідер, а створити власного адміна (варіант Б).

### Варіант Б — власний адмін для проду (рекомендовано)
```bash
cd core
php artisan tinker
>>> \App\Models\User::create([
...   'name' => 'Адмін',
...   'email' => 'you@yourdomain.com',
...   'password' => \Illuminate\Support\Facades\Hash::make('СильнийПароль123!'),
...   'role' => 'superadmin',
...   'is_active' => true,
... ]);
```
Логін у Core: `https://<core-host>/` (або `/admin`).

---

## 7. Підключення сайту (плагіна) до Core

1. У Core → розділ сайтів → для потрібного сайту **згенерувати «ключ підключення»** (connection key, формат `DBM1.…`).
2. У WordPress → плагін DBManager → налаштування → **вставити ключ** → зберегти.
3. У Core натиснути **«Синхронізувати»** (або відредагувати значення) → з'явиться плашка успіху, а дані доставляться на сайт.
4. Дашборд Core покаже сайт **онлайн** після першої успішної доставки.

> Зміна токена/перепідключення до іншого сайту працює автоматично — плагін приймає дані нового `site_id` навіть із меншою версією.

---

## 8. Прод-чеклист

- [ ] `APP_ENV=production`, `APP_DEBUG=false` (core і bridge)
- [ ] `BRIDGE_PUBLISH_SECRET` однаковий у core і bridge, ≥32 символи, секретний
- [ ] HTTPS на core і bridge; коректні `BRIDGE_INGEST_URL`/`BRIDGE_GEODB_URL`
- [ ] `BRIDGE_LOCAL_PING_URL` **порожній** (інакше всі сайти пінгуватимуться на один локальний URL)
- [ ] Bridge: `queue:work` під supervisor/systemd (інакше дані не доходять до плагінів)
- [ ] Кеші Laravel: `php artisan config:cache && php artisan route:cache && php artisan view:cache` (core і bridge)
- [ ] Права: `storage/`, `bootstrap/cache/` доступні веб-серверу на запис
- [ ] Перший адмін створений власним паролем (не дефолтним)
- [ ] Бекап обох БД (`dbmanager_core`, `dbmanager_bridge`)

---

## 9. Перевірка (тести)

```bash
cd core   && php artisan test     # тести CRM
cd bridge && php artisan test     # тести моста
cd plugin && vendor/bin/phpunit   # тести плагіна
```

---

## 10. Структура пакета
```
DBManager/
├── core/             # CRM (Laravel) — головна адмінка
├── bridge/           # Міст доставки (Laravel + черга)
├── plugin/           # WordPress-плагін (plugin/dbmanager/) + тести
├── docker/           # Dockerfile для PHP-сервісів
├── docker-compose.yml
└── README.md         # цей файл
```

Питання щодо архітектури/безпеки — у вихідних нотатках проєкту в сховищі (`10-Projects/DBManager/`).
