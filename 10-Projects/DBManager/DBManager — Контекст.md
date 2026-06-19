---
tags: [dbmanager, контекст]
---

# DBManager — Контекст

**Оновлено:** 2026-06-19
**Стан:** Core, Bridge і WordPress-плагін працюють у push-only моделі; почато сторінку масових операцій.

## Актуально
- Код проєкту: `10-Projects/DBManager/code/`.
- Backup-точка перед bulk-розробкою: `8a5d11f dbmanager: точка бекапу перед масовими змінами`.
- Додано Livewire-сторінку **Масові операції**: `/admin/bulk`, пункт навігації веде саме туди.
- Вибірка цілей: усі доступні сайти, група, вручну вибрані сайти, сайт із дочірніми; додатково фільтри типу, geo, стану, пошуку і номера.
- Підтримані дії першої версії: `replace_text`, `set_value`, `set_geo`, `set_status`, `replace_phone`, `set_phone_status`.
- Для цін geo змінюється не лише через pivot `geoTags`, а й у вкладених `content.prices[*].geo`.
- Заміна номера заблокована без фільтра номера, щоб не переписати всі номери випадково.
- Сторінка показує лічильники цілей/сайтів/номерів, прев'ю до 80 рядків, batch-id аудиту після застосування, адаптивну верстку і окремий горизонтальний скрол таблиці на вузьких екранах.
- Після застосування зміни пишуться в аудит і, якщо прапорець увімкнений, публікують змінені сайти в DataBridge.
- Людський опис оновлено в [[Гайд — Адмінка DBManager (CRM)]].

## Перевірено локально
- `docker compose exec -T core php -l app/Livewire/BulkOperations.php`.
- `docker compose exec -T core php artisan test tests/Feature/Admin/BulkOperationsTest.php` — 4 тести, 17 assertions.
- `docker compose exec -T core php artisan test tests/Feature/Admin/ValuesGridTest.php tests/Feature/Admin/ValuesGridFilterTest.php tests/Feature/Admin/GridSelectionTest.php` — 13 тестів, 38 assertions.
- `cmd /c npm run build`.
- Browser-перевірка `/admin/bulk`: desktop 1280px і mobile 390px без body horizontal overflow; сторінка рендерить лічильники й дію застосування.
