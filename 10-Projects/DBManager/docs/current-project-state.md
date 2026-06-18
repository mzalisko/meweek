---
tags: [dbmanager, аудит, стан, документація]
---

# Поточний стан проєкту DBManager

**Дата аудиту:** 2026-06-19
**Модель аудиту:** Gemini (Advanced Agentic Coding)

Цей документ фіксує фактичний стан реалізації системи управління динамічними даними **DBManager** станом на червень 2026 року, спираючись на аналіз реального коду у репозиторії.

---

## 1. Що реально реалізовано

Кодову базу розділено на три головні компоненти у теці [code](file:///c:/Dev/Meweek/10-Projects/DBManager/code):
1. **Core** (адмін-панель на Laravel) — [core](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core)
2. **DataBridge** (проміжний API-сервер доставки) — [bridge](file:///c:/Dev/Meweek/10-Projects/DBManager/code/bridge)
3. **WP-Plugin** (клієнтський плагін WordPress та fallback-компонент) — [plugin](file:///c:/Dev/Meweek/10-Projects/DBManager/code/plugin)

### 1.1. Core (Laravel Адмін-панель)
- **База даних та Моделі**: 
  - Реалізовано моделі для сайтів ([Site](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Models/Site.php)), груп ([SiteGroup](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Models/SiteGroup.php)), значень ([DataValue](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Models/DataValue.php)) та типів значень ([ValueType](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Models/ValueType.php)).
  - Зв'язок DataValue з гео-мітками через pivot-таблицю до моделі [GeoTag](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Models/GeoTag.php).
  - Моделі телефонів ([PhoneSlot](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Models/PhoneSlot.php) та [PhoneNumber](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Models/PhoneNumber.php)) для failover-логіки.
- **Failover-движок**:
  - TDD-перевірена failover-логіка: підтримує ротацію номерів при вичерпанні лімітів або помилках, режими "Auto" та "Sticky" (з привязкою сесії відвідувача), pin/unpin номерів, та фолбек на резервні номери при вичерпанні ланцюжка.
- **Редагування та CRUD значень (Livewire)**:
  - Компонент [ValueEditor](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Livewire/ValueEditor.php) та шаблон [value-editor.blade.php](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/resources/views/livewire/value-editor.blade.php) реалізують створення/редагування/видалення значень типів `text`, `html`, `phone` (з викликом конструктора PhoneSlot) та `price`.
  - Тип даних **ЦІНА** (`price`): повністю інтегровано. Дозволяє створювати слоти (наприклад, `ROMANIA`), які містять набір цін для різних країн (наприклад, Україна -> 1200 UA, Румунія -> 2000 WORLD, RU, BY). При збереженні гео-мітки цін синхронізуються з pivot-таблицею DataValue.
- **Керування дозволами (RBAC)**:
  - Користувачі мають ролі (`superadmin`, `admin`, `manager`).
  - Доступи до сайтів ([UserSiteAccess](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Models/UserSiteAccess.php)) та груп сайтів ([UserSiteGroupAccess](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Models/UserSiteGroupAccess.php)) підтримують прапорці дозволів: `can_view_failover`, `can_view_history`, `can_edit_values`, `can_view_prices` тощо.
  - Компонент [AccessManager](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Livewire/AccessManager.php) керує цими дозволами через Livewire UI з автоматичним збереженням та валідацією.
- **Компіляція та Публікація**:
  - Клас [SitePayloadCompiler](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Services/Publishing/SitePayloadCompiler.php) компілює плоский масив значень для сайту. Для типу `price` він автоматично розгортає один слот у декілька payload values за країнами.
  - Компонент публікації передає ці дані на DataBridge через HTTP клієнт з HMAC-підписом (`X-Signature`).
- **Логування та Аудит**:
  - Реалізовано запис дій у таблицю [AuditLog](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Models/AuditLog.php) з відображенням в [AuditManager](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Livewire/AuditManager.php). Детально форматуються зміни для текстових, телефонних та цінових слотів.
- **Клонування даних**:
  - Можливість клонувати наявні дані при додаванні нового сайту-сателіта через кнопку "Клонувати дані з джерела" в [SitesManager](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Livewire/SitesManager.php).

### 1.2. DataBridge (Laravel API-доставка)
- **Приймання публікацій**: 
  - Клас [IngestController](file:///c:/Dev/Meweek/10-Projects/DBManager/code/bridge/app/Http/Controllers/Api/IngestController.php) перевіряє HMAC-підпис запиту за допомогою `X-Signature` та внутрішнього секретного ключа.
  - Запобігання гонкам (Race condition): через транзакцію та `lockForUpdate()` перевіряється монотонність версії (запит відхиляється статусом 409, якщо версія застаріла).
- **Роздача даних**:
  - Клас [DataController](file:///c:/Dev/Meweek/10-Projects/DBManager/code/bridge/app/Http/Controllers/Api/DataController.php) авторизує WordPress сайти за хешем токена (`X-Site-Token`).
  - Підтримка HTTP-кешування: перевірка `If-None-Match` та повернення статусу `304 Not Modified`.
  - Відповідь 200 містить payload, підписаний за допомогою `signing_secret`.
- **Завантаження MaxMind GeoIP БД**:
  - Класи [GeoIngestController](file:///c:/Dev/Meweek/10-Projects/DBManager/code/bridge/app/Http/Controllers/Api/GeoIngestController.php) та [GeoServeController](file:///c:/Dev/Meweek/10-Projects/DBManager/code/bridge/app/Http/Controllers/Api/GeoServeController.php) для приймання оновленої MaxMind бази від Core та віддачі її на WP сайти з HMAC підписом та `304 Not Modified` перевіркою за SHA256 хешем.
- **Асинхронний пінг сателітів**:
  - Робота [DeliverPingJob](file:///c:/Dev/Meweek/10-Projects/DBManager/code/bridge/app/Jobs/DeliverPingJob.php) шле підписаний запит на `ping_url` сайту з експоненціальним backoff (до 8 спроб).

### 1.3. WP-Plugin та Fallback
- **Основний плагін ([dbmanager](file:///c:/Dev/Meweek/10-Projects/DBManager/code/plugin/dbmanager))**:
  - Збереження конфігурації у `wp_options` через [Settings](file:///c:/Dev/Meweek/10-Projects/DBManager/code/plugin/dbmanager/src/Config/Settings.php).
  - REST API роут `dbm/v1/ping` приймає та валідує пінг від DataBridge, асинхронно запускаючи повну синхронізацію даних.
  - Клієнт [WpHttpDataClient](file:///c:/Dev/Meweek/10-Projects/DBManager/code/plugin/dbmanager/src/Http/WpHttpDataClient.php) робить запити на DataBridge з передачею `If-None-Match` та перевіркою HMAC.
  - Шорткод `[dbm key="..." format="..."]` та глобальна функція `dbm_get()`.
  - Гео-детектор ([GeoDetector](file:///c:/Dev/Meweek/10-Projects/DBManager/code/plugin/dbmanager/src/Geo/GeoDetector.php)) використовує заголовок `CF-IPCountry` (від Cloudflare) або локальну бібліотеку [MaxMindCountryLookup](file:///c:/Dev/Meweek/10-Projects/DBManager/code/plugin/dbmanager/src/Geo/MaxMindCountryLookup.php) для визначення країни відвідувача.
  - Щоденна реконсиляція (звірка версій) через WP Cron (`dbm_daily_reconcile`).
  - Щотижневий синхронізатор гео-бази через WP Cron (`dbm_geodb_sync`).
- **FallBack (MU-Plugin [mu](file:///c:/Dev/Meweek/10-Projects/DBManager/code/plugin/mu))**:
  - Файл [dbmanager-fallback.php](file:///c:/Dev/Meweek/10-Projects/DBManager/code/plugin/mu/dbmanager-fallback.php) завантажується як Must-Use плагін.
  - Якщо основний плагін відсутній/вимкнений, він перехоплює ініціалізацію, оголошує функцію `dbm_get()` та реєструє шорткод. Запобігає білому екрану смерті (WSOD). Працює в режимі спрощеного рендеру (`WORLD` scope) без гео-детекту, оскільки класи основного плагіна недоступні.
- **Ядро рендеру**:
  - Файл [render-core.php](file:///c:/Dev/Meweek/10-Projects/DBManager/code/plugin/shared/render-core.php) є спільним кодом для плагіна та mu-fallback, відповідає за пошук правильного значення за географічним пріоритетом (`країна відвідувача` -> `WORLD`) та його безпечний HTML рендер (підтримка `tel` та `link` форматів).

---

## 2. Що працює частково або вимагає поліпшення

1. **Режим fallback у MU-плагіні**:
   - Робота Fallback перевірена тестами, але в реальному житті mu-плагін має бути скопійований у `wp-content/mu-plugins/` разом із файлом `render-core.php`. Це потребує автоматичного копіювання при активації основного плагіна. Зараз механізм копіювання при активації в `Plugin.php` не прописаний (тільки реєструються крон-події та зачищаються при деактивації).
2. **Перекриття групових значень (Overwrite/✎ цього сайта)**:
   - Створення перекриття працює, проте користувацький досвід Livewire гріда при видаленні перекриття (скидання до значення групи) може бути неочевидним для контент-менеджерів, оскільки в UI немає чіткого маркеру "успадковано / перекрито".
3. **Локальний запуск крон-завдань**:
   - Асинхронність у Laravel Core та Bridge вимагає запущених queue-воркерів (`php artisan queue:work`). В локальному Docker-оточенні черга налаштована на Redis, але в compose-файлі воркери часом потребують ручного перезапуску при оновленні коду.

---

## 3. Що заявлено в планах, але ще не реалізовано

Згідно з загальним планом розвитку ([DBManager — План](file:///c:/Dev/Meweek/10-Projects/DBManager/DBManager — План.md)) не реалізовано такі кроки:
1. **План 2.5 — Bulk-дії**:
   - Масове редагування/видалення виділених значень з візуальним прев'ю "це торкнеться N сайтів".
   - Відкат bulk-операцій.
   - Drag-and-drop зміна пріоритетів номерів у телефонному слоті.
2. **Дашборд та Індикація Інцидентів**:
   - Головний екран моніторингу працездатності доставки на сателіти.
   - Дзвіночок інцидентів в шапці адмінки (наприклад, якщо пінг на якийсь сателіт не проходить понад 3 рази).
   - Візуальна стрічка аудиту з кнопками швидкого відкату (rollback).
3. **Імпорт CSV/XLSX**:
   - Завантаження первинної бази сайтів та значень через файли.
4. **Webhook моніторингу сторонніх сервісів**:
   - Хоча `/api/monitoring/numbers` готовий, немає інтерфейсу для додавання інтеграцій (наприклад, Keitaro / Binotel / Ringostat) та налаштування мапінгу вхідних webhook-параметрів.

---

## 4. Що реалізовано інакше, ніж у початковому дизайні

1. **Групове наслідування даних на DataBridge**:
   - За початковим дизайном передбачалося, що DataBridge знає про групи сайтів і сам розкриває наслідування.
   - **Фактично реалізовано**: DataBridge абсолютно нічого не знає про групи, зв'язки та наслідування. Він є "дурним" сховищем (flat key-value). Клас [SitePayloadCompiler](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Services/Publishing/SitePayloadCompiler.php) в Core повністю розгортає всі групові та індивідуальні значення в плоский список `values` безпосередньо під час компіляції публікації та передає готовий payload на Bridge. Це значно спростило Bridge та зробило доставку ізольованою.
2. **Відсутність збереження історії версій на DataBridge**:
   - Початкові плани згадували журнал версій на DataBridge.
   - **Фактично**: DataBridge зберігає лише ОДНУ (актуальну) версію у таблиці `published_sites`. Будь-яка нова версія просто перезаписує попередню. Вся історія та аудит зберігаються виключно в Core.
3. **Клонування даних**:
   - Замість автоматичного групового наслідування при створенні сателіта додано ручну кнопку клонування даних з сайту-джерела. Це дозволяє уникнути випадкових змін на сателітах, якщо контент-менеджер редагує загальну групу.

---

## 5. Застарілі або суперечливі місця в документації

1. **Групові зв'язки**:
   - У деяких файлах документації (`Компоненти системи.md`) досі згадується "наслідування групових налаштувань на рівні DataBridge". Це суперечить поточному рішенню, де DataBridge є ізольованим сховищем плоских payload.
2. **Вимоги до HTTPS**:
   - У планах зазначено вимогу HTTPS для всіх комунікацій, проте локальне Docker-оточення працює повністю на HTTP (порти 8000, 8001, 8080). Потрібно чітко розмежувати конфігурації Local (HTTP) та Production (Strict HTTPS).
3. **Автоматична реєстрація WP-Cron**:
   - У документації плагіна вказано, що cron реєструється при активації. Але якщо плагін залити через Git без активації (або перенести файли), крон не запуститься. Механізм самовідновлення кронів при ініціалізації (`init`) відсутній.
