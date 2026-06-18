---
tags: [dbmanager, контекст]
---

# DBManager — Контекст

**Оновлено:** 2026-06-18
**Стан:** Реалізовано тип даних ЦІНА з повним циклом: CRUD, грід, аудит, компіляція, render-core гео-пріоритизація, дозвіл `can_view_prices`.

## Ключові зміни
- **Тип ЦІНА:** Повна реалізація — ValueEditor (додавання/видалення цін з гео-мітками), відображення в гріді, аудит-лог змін, SitePayloadCompiler (розгортання слота у кілька payload values за країнами), render-core (пріоритет точної країни → WORLD fallback).
- **Дозвіл `can_view_prices`:** Міграція на `user_site_access` та `user_site_group_access`, моделі, AccessControl (canViewPrices, canViewGroupPrices), AccessManager (blankPermissions, permissionArray, normalizedPermissions, hasAnyPermission, permissionsForLevel), UI колонка «Ціни» в таблиці дозволів, фільтрація price-рядків у ValuesGrid, блокування збереження цін у ValueEditor.
- **Локальні дані:** Кожен сайт зберігає та читає власні значення (`scope_type = 'site'`). Групове наслідування вилучено.
- **Клонування при приєднанні:** Кнопка «Клонувати дані з джерела» у SitesManager.
- **Брендинг:** DataBridge Core — логотип, назва, колір `#69bf81`.
- **Тести:** Всі 236 тестів (706 assertions) проходять успішно.

## Шлях коду та запуск
- Код: `10-Projects/DBManager/code/`
- Запуск тестів: `docker exec -i code-core-1 php artisan test`
