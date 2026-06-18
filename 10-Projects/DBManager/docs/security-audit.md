---
tags: [dbmanager, безпека, аудит, ризики]
---

# Аудит безпеки системи DBManager

**Дата аудиту:** 2026-06-19
**Модель аудиту:** Gemini (Advanced Agentic Coding)

Цей документ представляє детальний аналіз архітектурної безпеки та поточних механізмів захисту у системі DBManager (Core, DataBridge та WP-Plugin).

---

## 1. Поточний стан безпеки: Що вже зроблено

### 1.1. Захист передачі даних (HMAC-SHA256)
- **Core → DataBridge**:
  - Публікація payload та передача MaxMind GeoDB підписуються HMAC-SHA256 за допомогою секретного ключа `services.publish.secret`.
  - DataBridge у [IngestController.php](file:///c:/Dev/Meweek/10-Projects/DBManager/code/bridge/app/Http/Controllers/Api/IngestController.php) та [GeoIngestController.php](file:///c:/Dev/Meweek/10-Projects/DBManager/code/bridge/app/Http/Controllers/Api/GeoIngestController.php) перевіряє підпис, обчислюючи HMAC від сирого контенту (`$request->getContent()`) та звіряє його з заголовком `X-Signature` за допомогою безпечної функції `hash_equals()`, що запобігає атакам по часу (Timing Attacks).
- **DataBridge → WP-Plugin**:
  - Дані, які віддає DataBridge у [DataController.php](file:///c:/Dev/Meweek/10-Projects/DBManager/code/bridge/app/Http/Controllers/Api/DataController.php) та [GeoServeController.php](file:///c:/Dev/Meweek/10-Projects/DBManager/code/bridge/app/Http/Controllers/Api/GeoServeController.php), підписуються ключем `signing_secret`.
  - WP-Plugin у [Synchronizer.php](file:///c:/Dev/Meweek/10-Projects/DBManager/code/plugin/dbmanager/src/Sync/Synchronizer.php) та [PayloadVerifier.php](file:///c:/Dev/Meweek/10-Projects/DBManager/code/plugin/dbmanager/src/Sync/PayloadVerifier.php) валідує підпис тіла відповіді, використовуючи `hash_equals` та локальний секрет.
- **Fail-Closed принцип**:
  - Якщо секрет підпису не налаштований (порожній), система повністю блокує синхронізацію або віддачу даних замість ігнорування підпису (повертає 500 / false).

### 1.2. Авторизація API та Токени
- **Авторизація сателітів (WP-Plugin)**:
  - Кожен сайт надсилає токен у заголовку `X-Site-Token`.
  - На рівні DataBridge токени **ніколи не зберігаються у відкритому вигляді**. База даних зберігає лише SHA256-хеш токена (`token_hash`). Авторизація виконується пошуком за хешем: `PublishedSite::where('token_hash', hash('sha256', $rawToken))->first()`.
  - Це надійно захищає систему: навіть у разі повної компрометації БД DataBridge, зловмисники не зможуть дізнатися дійсні API-токени сателітів для зчитування даних чи підробки пінгу.

### 1.3. Автентифікація користувачів (Core)
- **Панель адміністратора**:
  - Захищено стандартним сесійним механізмом автентифікації Laravel (Breeze).
  - Всі адмін-роути в [web.php](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/routes/web.php) закриті middleware `auth` та кастомним `admin.access`, який перевіряє наявність ролі або прав на перегляд конкретного сайту.
  - Реалізовано захист від міжсайтової підробки запитів (CSRF) на всіх Livewire-формах.

### 1.4. Захист секретів та ключів
- Усі паролі до БД, Redis, секрети HMAC та API-токени винесено у файли `.env`.
- Папки `.env` додано до `.gitignore`, у репозиторії зберігаються лише безпечні шаблони `.env.example`.

### 1.5. Захист JS/CSS та рендеру на сателітах
- Дані (телефони, месенджери, ціни) рендеряться **виключно на серверній стороні (PHP)** за допомогою [render-core.php](file:///c:/Dev/Meweek/10-Projects/DBManager/code/plugin/shared/render-core.php).
- Усі виведення значень екрануються через `htmlspecialchars(..., ENT_QUOTES)` для повного виключення XSS-вразливостей.
- Не використовується жоден клієнтський JS/CSS для підміни контенту. Це унеможливлює виявлення підміни через інструменти розробника, блокування скриптів трекерами та блокувальниками реклами (AdBlock).

### 1.6. Логування та Аудит
- **Core**: Записує будь-які зміни контенту через модель [AuditLog](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Models/AuditLog.php) (хто, коли, що змінено, знімок `old` та `new` значень).
- **DataBridge**: Middleware `bridge.log` логує всі запити до `/v1/data` та `/v1/geodb` із фіксацією статусів та IP-адрес.
- **WP-Plugin**: Пише логи у `wp_options` (помилки підпису, недоступність API, успішні оновлення).

---

## 2. Що НЕ зроблено та Потенційні Ризики

### 2.1. Відсутність захисту від атак повторення (Replay Attacks) на HMAC
- **Ризик**: HMAC-підпис запитів Core → DataBridge та Bridge → WP-Plugin обчислюється суто від тіла запиту. Запит не містить мітки часу (timestamp) або одноразового ідентифікатора (nonce).
- **Вплив**: Зловмисник, що перехопив мережевий трафік публікації, може повторно надіслати той самий запит, оскільки його підпис залишається валідним назавжди. Це дозволить відкотити контент сателіту до застарілої версії (якщо на Bridge версія ще не оновлювалась) або заспамити WP-сервер запитами на синхронізацію.
- **Критичність**: Середня (внутрішня мережа).

### 2.2. Відсутність шифрування токенів при виведенні на екрані (Core)
- **Ризик**: При створенні або ротації токена в [SitesManager.php](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Livewire/SitesManager.php) сирий токен показується користувачу один раз. Проте під час генерації він передається через HTTP-відповідь Livewire. Також немає обмежень на перегляд екрану токенів іншими адміністраторами під час сесії.
- **Критичність**: Низька.

### 2.3. Відсутність валідації HTTPS для Bridge URL
- **Ризик**: Адміністратор може вказати `bridge_url` з протоколом `http://` в налаштуваннях WP-Plugin.
- **Вплив**: Усі дані, включаючи `X-Site-Token` у відкритому вигляді, передаватимуться через мережу незашифрованими. Зловмисник на рівні провайдера чи локальної мережі (MITM) зможе перехопити токен сайту та отримати повний доступ до його payload.
- **Критичність**: Висока (для Production).

### 2.4. Потенційне вичерпання ресурсів при логуванні (DataBridge)
- **Ризик**: Middleware логування DataBridge записує кожне звернення сателітів у БД. При великій кількості сателітів (50+) та частому оновленні сторінок, таблиця логів швидко переповниться.
- **Критичність**: Середня.

---

## 3. Рекомендації та Пріоритети

### Пріоритет 1 (Критичний): Strict HTTPS та валідація URL
1. Додати валідацію `bridge_url` у налаштуваннях WP-Plugin на рівні [Settings.php](file:///c:/Dev/Meweek/10-Projects/DBManager/code/plugin/dbmanager/src/Config/Settings.php): заборонити збереження URL, що починаються з `http://` (дозволити тільки `https://`), за винятком локальних хостів (`localhost`, `127.0.0.1`, `*.local`).
2. Впровадити HSTS заголовки на рівні DataBridge nginx конфігурації.

### Пріоритет 2 (Високий): Захист від Replay Attacks
1. Додати заголовок `X-Timestamp` (мілісекунди Unix epoch) до запитів публікації Core → DataBridge та пінгу DataBridge → WP-Plugin.
2. Включити timestamp в рядок, від якого обчислюється HMAC: `body + timestamp`.
3. На стороні приймача перевіряти, чи не застаріла мітка часу більше ніж на 60 секунд: `abs(time() - timestamp) < 60`.

### Пріоритет 3 (Середній): Обмеження логів та ротація
1. Реалізувати механізм автоматичного видалення логів DataBridge (`request_logs`), старших за 14 днів, через Laravel Console Schedule.
2. Додати Rate Limiting на REST endpoint пінгу у WP-Plugin для запобігання DoS-атакам на базу даних WordPress.
