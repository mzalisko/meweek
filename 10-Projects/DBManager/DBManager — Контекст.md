---
tags: [dbmanager, контекст]
---

# DBManager — Контекст

**Оновлено:** 2026-06-22
**Стан:** Реалізовано Дашборд, Обране, Інциденти, повний UI/UX редизайн плагіну, а також приховування резервних месенджерів з окремих ключів у плагіні (з інтеграцією в основний ключ при failover). Всі тести проходять успішно.

## Реалізовано (Дашборд, Обране, Інциденти, Редизайн, Редагування з ValuesGrid, Резервні месенджери)
- **Приховування резервних месенджерів:** з плагіну приховано окремі ключі резервних месенджерів (Viber і т.д.), які натомість failover-інтегровані в основний ключ (Telegram), аналогічно телефонам. Зміни внесено у [SitePayloadCompiler.php](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Services/Publishing/SitePayloadCompiler.php).
- **Редагування сайту з ValuesGrid:** додано кнопку «Редагувати сайт» та висувну бічну панель (slide-over) на сторінку перегляду даних сайту. Зміни внесено у [ValuesGrid.php](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/app/Livewire/ValuesGrid.php) та [values-grid.blade.php](file:///c:/Dev/Meweek/10-Projects/DBManager/code/core/resources/views/livewire/values-grid.blade.php).
- **Повний редизайн плагіну (бізнес-стиль):** оновлено всю стилістику та покращено відступи (whitespace). Зміни внесено в [AdminPages.php](file:///C:/Dev/Meweek/10-Projects/DBManager/code/plugin/dbmanager/src/Admin/AdminPages.php) та [PresentationBlockRenderer.php](file:///C:/Dev/Meweek/10-Projects/DBManager/code/plugin/dbmanager/src/Wp/PresentationBlockRenderer.php).
- **Дашборд (`/admin/dashboard`):** віджети статистики, обрані групи/сайти з робочими статусами, блок непідтверджених інцидентів з кнопкою підтвердження («Acknowledge»), офлайн-сайти.
- **Обране на сторінці сайтів (`/admin/sites`):** додано зірочки (★ / ☆) біля назв неархівованих груп та доменів для додавання до `user_favorites`.
- **Окрема сторінка «Інциденти» (`/admin/incidents`):** сітка квадратних карток сайтів із розподілом по 2 табах («На резервах» та «На основних»).
- **Виправлення відображення резервів (`/admin/site?site=id`):** замінено на стандартні Alpine-атрибути `x-cloak`.

## Тести та перевірка
- `docker compose exec -T core php artisan test --filter=SitePayloadCompilerTest` — **PASS** (13 тестів успішні, перевірено нову поведінку резервів).
- `docker compose exec -T core php artisan test --filter=ValuesGridTest` — **PASS** (новий тест успішного редагування та доступу суперадміна зелений).
- Робочий код: `10-Projects/DBManager/code/`.
