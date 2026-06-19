---
tags: [dbmanager, інструкція, розгортання]
---

# DBManager — Інструкція з розгортання проєкту

У цій інструкції описано процес розгортання всіх трьох компонентів системи **DBManager**: центральної CRM (Core), сервісу черг (Bridge) та WordPress-плагіна (Plugin) — як у локальному Docker-середовищі, так і на живому сервері (native).

---

## 🐋 Варіант 1. Швидке розгортання через Docker Compose (Локально)

Усі компоненти системи упаковані в єдиний `docker-compose.yml` файл, розташований у директорії `10-Projects/DBManager/code/`.

### 1. Підготовка оточення
1. Перейдіть у робочу директорію коду:
   ```bash
   cd 10-Projects/DBManager/code/
   ```
2. Створіть файл конфігурації `.env` з `.env.example` у папках `core` та `bridge`:
   ```bash
   cp core/.env.example core/.env
   cp bridge/.env.example bridge/.env
   ```

### 2. Запуск контейнерів
Запустіть збірку та старт усіх сервісів у фоновому режимі:
```bash
docker compose up -d --build
```
Це підніме наступні сервіси:
- `mysql` — база даних MySQL 8.4 (порт `33061`)
- `bridge-mysql` — база даних для мосту (порт `33062`)
- `redis` — сервер Redis для черг
- `core` — Laravel CRM (доступний за адресою `http://localhost:8001`)
- `bridge` — Laravel Bridge API (доступний за адресою `http://localhost:8002`)
- `bridge-worker` — воркер Laravel Queue для обробки черг доставки
- `wordpress` — WordPress з PHP 8.4 (доступний за адресою `http://localhost:8080`)
- `wpcli` — допоміжний контейнер CLI для WordPress

### 3. Ініціалізація та міграції
1. **Zапустіть міграції та сідери для CRM (Core)**:
   ```bash
   docker compose exec core php artisan migrate --seed
   ```
2. **Запустіть міграції для Bridge**:
   ```bash
   docker compose exec bridge php artisan migrate
   ```
3. **Ініціалізуйте WordPress та плагін**:
   Запустіть інтеграційний скрипт, який створить базу даних WordPress, встановить ядро та активує плагін:
   ```bash
   docker compose run --rm wpcli bash /plugin/bin/install-wp.sh
   ```
   *Параметри доступу до WordPress:*
   - URL: `http://localhost:8080`
   - Адмін-панель: `http://localhost:8080/wp-admin`
   - Логін: `admin`
   - Пароль: `admin`

---

## 💻 Варіант 2. Розгортання на сервері (Native)

Вимоги до сервера: PHP 8.4 (з розширеннями `mysqli`, `curl`, `mbstring`, `openssl`, `xml`), MySQL 8.0+, Redis.

### 1. Розгортання CRM (Core)
Код знаходиться в `code/core/`.
1. Створіть базу даних MySQL (наприклад, `dbmanager_core`).
2. Перейдіть у папку `core/`, скопіюйте `.env.example` у `.env` та налаштуйте параметри підключення до БД, Redis та доменів.
3. Встановіть PHP-залежності через Composer:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
4. Згенеруйте унікальний ключ програми:
   ```bash
   php artisan key:generate
   ```
5. Виконайте міграції та початкове наповнення БД:
   ```bash
   php artisan migrate --seed
   ```
6. Налаштуйте веб-сервер (Nginx/Apache), спрямувавши його root-директорію на `core/public/`.

### 2. Розгортання Bridge (Міст)
Код знаходиться в `code/bridge/`.
1. Створіть окрему базу даних MySQL (наприклад, `dbmanager_bridge`).
2. Скопіюйте `.env.example` у `.env` у папці `bridge/` та вкажіть налаштування БД і Redis.
3. Встановіть залежності:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
4. Згенеруйте ключ:
   ```bash
   php artisan key:generate
   ```
5. Виконайте міграції:
   ```bash
   php artisan migrate
   ```
6. Налаштуйте веб-сервер, спрямувавши root на `bridge/public/`.
7. **Критично важливо**: Запустіть постійний процес воркера черги (наприклад, через Supervisor):
   ```bash
   php artisan queue:work --sleep=1 --tries=3
   ```

### 3. Установка плагіна на WordPress
Код знаходиться в `code/plugin/`.
1. Скопіюйте папку `dbmanager/` в директорію плагінів WordPress: `wp-content/plugins/dbmanager/`.
2. Скопіюйте папку `shared/` в директорію плагінів WordPress: `wp-content/plugins/shared/` (вона містить загальну логіку рендеру, на яку посилається основний плагін).
3. **Аварійний фолбек (Must-use)**:
   - Скопіюйте файл `mu/dbmanager-fallback.php` у папку `wp-content/mu-plugins/dbmanager-fallback.php`.
   - Скопіюйте файл `shared/render-core.php` у папку `wp-content/mu-plugins/render-core.php`.
4. Активуйте плагін **DBManager** через панель керування WordPress.
5. У розділі **DBManager -> Настройки** введіть секретний ключ підключення, згенерований у кабінеті CRM Core.
