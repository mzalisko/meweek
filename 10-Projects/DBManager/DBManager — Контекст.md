---
tags: [dbmanager, контекст]
---

# DBManager — Контекст

**Оновлено:** 2026-06-19
**Стан:** Реалізовано панель геосимуляції в адмінці WP плагіна, автоматичне виконання шорткодів у ACF полях, the_title, the_excerpt та аварійному плагіні. Тести плагіна проходять на 100%.

## Ключові зміни
- **Панель геосимуляції:** Вкладка в адмінці WP-плагіна для вибору країни симуляції. Підміняє автовизначення країни за IP на рівні ядра плагіна та аварійного му-плагіна.
- **Шорткоди в ACF та WP:** Додано фільтри для виконання шорткодів у полях ACF (`acf/format_value`), `the_title`, `the_excerpt`, `widget_text`.
- **Тип ЦІНА:** Повна реалізація — ValueEditor, відображення в гріді, аудит-лог змін, SitePayloadCompiler, render-core (пріоритет точної країни → WORLD fallback).
- **Дозвіл `can_view_prices`:** Міграція на `user_site_access`/`user_site_group_access`, AccessControl, AccessManager, UI колонка «Ціни», фільтрація в ValuesGrid.
- **Локальні дані:** Кожен сайт зберігає та читає власні значення (`scope_type = 'site'`).
- **Тести:** Всі 236 тестів Core (706 assertions) та 37 тестів плагіна проходять успішно.

## Шлях коду та запуск
- Код: `10-Projects/DBManager/code/`
- Тести Core: `docker exec -i code-core-1 php artisan test`
- Тести плагіна: `docker compose run --rm plugin vendor/bin/phpunit`
