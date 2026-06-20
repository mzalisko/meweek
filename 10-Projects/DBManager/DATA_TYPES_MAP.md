---
tags: [dbmanager, дослідження, типи-даних]
---

# DATA_TYPES_MAP — карта «тип даних → механізми»

> Дослідження проведено фан-аутом 8 агентів + критик повноти, перехресно звірено ручним читанням коду. Усі посилання — `file:line` від `10-Projects/DBManager/code/core/`.
> Пов'язано: [[BULK_EDIT_AUDIT]], [[ADDRESS_DECISION]], [[NEW_TYPES_PLAN]], [[DBManager — Контекст]].

## 1. Як влаштована типізація (модель диспетчеризації)

- **Один поліморфний рядок:** усе типізоване значення — це рядок `data_values`. Тип визначає FK `value_type_id → value_types.code`; дані лежать у JSON-колонці `content` (`DataValue.php:17`, cast `array`).
- **`content` — нетипізований blob:** форма ключів задається *ad-hoc* кожним споживачем. Немає PHP-enum, немає стратегії/value-object, немає інтерфейсу, немає реєстру. На рівні БД `value_types.code` — це `string(32) unique` (`2026_06_11_000002_create_dictionaries.php:21`), без CHECK; `content` — nullable json без обмежень. **Сховище нічого не блокує — усі бар'єри в PHP/Blade.**
- **Диспетчеризація = хардкод-розгалуження по рядку коду типу** (`$value->type->code` через `===`/`match`/`in_array`), повторене в кожному споживачі. Немає центрального диспетчера.
- **Phone — унікальний:** має реляційний підграф (`PhoneSlot 1:1`, `NumberEntry`, `PhoneNumber`, `SlotResolver`, `FailoverEngine`). Інші типи живуть лише в `content`. Messenger імітує «резерв/поточний» суто через ключі `content`, не реляціями.
- **address / social / text — вже засіяні** у `value_types` (`ValueTypeSeeder.php:13-18`) і показані в `<select>`, але **не обробляються в жодній гілці диспетчеризації** — провалюються в `default` і поводяться як простий текст.

## 2. Форма `content` за типом (за домовленістю, без валідації схеми)

| Тип | `content` |
|---|---|
| `phone` | `[]` (опц. `phone_format`); номери — у `phone_slots/number_entries` |
| `messenger` | `{value, url, network, messenger_slot, linked_slot, enabled, pinned, exhaustion_policy, return_mode, current_messenger_id, last_active_value/url, emergency_value/url}` |
| `price` | `{prices:[{label, value, geo[]}]}` |
| `text` | `{value}` |
| `address` | **не визначено** → провалюється в загальне `{value}` |
| `social` | **не визначено** → провалюється в загальне `{value}` |

## 3. Карта: механізм → точки дотику

### 3.1 Створення / редагування / видалення (single edit)
- `ValueEditor::save()` `ValueEditor.php:129-290` — будує `content` за типом; **create-валідація `in:phone,messenger,price` (рядок 146) блокує address/social/text**, edit-валідація їх дозволяє. PhoneSlot створюється лише для phone (`276-283`).
- `ValueEditor::edit()/delete()` `103-127 / 292-331` — повністю generic.
- `value-editor.blade.php:18-25` — `<select>` усіх 6 типів; поля лише generic `value` (`37`), messenger `network` (`128`), price-репітер (`50-125`). address/social — без власних полів.
- Сітка: `ValuesGrid.php` (рендер `1229-1275`, інлайн-редактори phone/messenger), `SiteGridReader.php` (рядки `$rows[code]`; messenger `33`, price `93`, інакше generic `buildRow`).
- `values-grid.blade.php` — `$typeLabels:149-154` (лише phone/messenger/price/text), `$typeOrder:170` (phone/messenger/price). **address/social → без назви/іконки, секція в кінці.**

### 3.2 Масові зміни (bulk edit) → детально в [[BULK_EDIT_AUDIT]]
- `BulkOperations.php` — диспетч **за операцією**, не за типом (`isPhoneOperation():815`). `targetType` лише фільтрує набір (`matchedDataValues:449-468`, alias `phone_reserve→phone`). Спец-кейси: phone (`256,605`), price (`295,700`). `displayValue:852-871` — phone/price бес­поко, інакше `content['value']??['url']??'—'`.
- `bulk-operations.blade.php:155-162` — `<select>` уже містить text/address/social; іконки (`248-265`) мають arms для address(pin)/social(link).

### 3.3 Логування / історія / відновлення
- Запис: `value.created/updated/deleted/geo_changed` (generic, `ValueEditor.php:225-307`), `bulk.<operation>` (`BulkOperations.php:331-343`). **Тип НЕ зберігається в рядку аудиту** — відновлюється лише через `subject_id → DataValue`.
- Відображення: `audit-manager.blade.php` — `$dataType match:631-660` розпізнає лише phone/messenger/price, **усе інше → 'Текст'**. `$renderDiff:72-337` знає ключі `value/url/name/prices/phone_slot`.
- Відновлення: `AuditRestorer::restoreSingle:205-393` — по action-рядку, type-agnostic; `serializeValue/restoreValue:24-106` generic, phone_slot — єдиний реляційний спец-кейс. **Content-only тип відновлюється без змін.**

### 3.4 Валідація / нормалізація
- **Немає централізації:** ні FormRequest, ні Rule-об'єкта, ні `rules()`. Усе inline в `ValueEditor::save()` (`144-185`). Дублювання нормалізаторів: `messengerUrlFromValue` ×3 (ValueEditor/MessengerPanel/ValuesGrid), нормалізація телефону ×3 з **різними межами** (`^\+\d{7,15}$` у SlotPanel vs `^\+\d{7,20}$` у BulkOperations).
- address/social/text: лише generic `value required`, **жодної структурної/форматної валідації**.

### 3.5 Пошук / фільтри / експорт / публікація
- Публікація: `SitePayloadCompiler::compile:36-61` → `buildItem:113-125`. `match(code)`: phone/messenger явні, **`default:121-123` розгортає `{value}` + усі решта ключів `content`**. price — окрема fan-out (`buildPriceItems:63`, 1 item на ціну). `TYPE_ORDER:15-19` лише phone/messenger/price (інші → `??99`, в кінці). `BridgePublisher`/`GeoDatabasePublisher` — type-agnostic.
- **Ризик:** `default` arm віддає **всі** внутрішні ключі `content` у публічний payload (без allow-list).
- Таргетинг bulk: `matchedDataValues` generic (новий тип фільтрується коректно). Пошук у сітці (`ValuesGrid::applyFilters:1277`) generic, але **inputs не прив'язані в blade — мертвий UI**; реальний пошук лише в bulk.

### 3.6 Права доступу
- **Per-type гейт існує лише для `price`** через `can_view_prices` (`AccessControl::canViewPrices:83-106`), enforced у 2 місцях: `ValuesGrid.php:1244` (`unset($rows['price'])`) і `ValueEditor.php:140` (блок save). **НЕ enforced** у публікації чи bulk-прев'ю.
- `canForValue:206-229` диспетчить по `scope_type`, **не** по типу — phone/messenger/address/social/text авторизуються однаково.
- **address/social/text — без гейту за замовчуванням:** видимі будь-кому з `can_view` сайту. Якщо тип чутливий (PII-адреса), гейт треба додавати свідомо у ~8 файлах.

### 3.7 Підсвічування змін → деталі в [[BULK_EDIT_AUDIT]]
- Існує **лише в bulk-прев'ю** (`bulk-operations.blade.php`), за прапором `changed` + попарними `new_*`. Токени Tailwind: `ok`(зел.)/`bad`(черв., strike)/`mut`(сір.).
- **Single edit — нуль підсвічування** жодного типу. Інлайн-редактори лише міняють бордюр на `border-acc`.
- `displayValue` для address/social згортає `value` і `new_value` у `'—'` → прев'ю показує `— ▼ —`.

## 4. Реєстр хардкод-списків типів (єдина-точка-істини відсутня)

Один тип треба синхронізувати руками в усіх цих місцях; пропуск = тихий збій тієї гілки:

1. `ValueTypeSeeder.php:13-18` — канонічний список 6 кодів (єдине місце, де всі разом).
2. `ValueEditor.php:146` — **create `in:phone,messenger,price` / edit `in:text,price,messenger,address,social,phone`** (найкритичніший гейт).
3. `SitePayloadCompiler.php:15-19` — `TYPE_ORDER`; `118` — `match`.
4. `value-editor.blade.php:19-24` — `<select>`.
5. `bulk-operations.blade.php:155-162` — `targetType`; `214-218` — `$gridCols`; `248-265` — іконки.
6. `values-grid.blade.php:149-154` — `$typeLabels`; `170` — `$typeOrder`.
7. `audit-manager.blade.php:631-660` — `$dataType` (label 'Тип даних').
8. `BulkOperations.php:86,150,461,815` — псевдотип `phone_reserve`.
9. `AuditManager.php:45-57` — `CHANGE_ACTIONS`; `audit-manager.blade.php:697` — гейт кнопки «Відновити».
10. Права: `UserSiteAccess/UserSiteGroupAccess` casts + `AccessManager.php` (5 масивів) + `access-manager.blade.php` (3 масиви) — лише якщо тип потребує per-type гейту.

## 5. Матриця готовності: новий тип має пройти ВСІ механізми

| Механізм | Чи працює generic? | Що додати для нового типу |
|---|---|---|
| Реєстр (`value_types`) | ✅ address/social засіяні | для нового коду — рядок у seeder |
| Створення (editor) | ❌ create-валідація блокує | розширити `in:` (рядок 146) + поля в blade + гілка `content` |
| Редагування / видалення | ✅ | нічого (якщо content-only) |
| Сітка (відображення) | ⚠️ generic, але без label/іконки | `$typeLabels`, `$typeOrder`; опц. інлайн-редактор |
| Bulk edit | ⚠️ generic ops працюють | `displayValue` гілка; типова операція лише за потреби |
| Логування | ✅ value.* / bulk.* | нічого |
| Аудит — відображення | ❌ показує 'Текст' | arm у `$dataType` (×2 closures) + ключі в `$renderDiff` |
| Відновлення | ✅ content-only | нічого (реляції — лише phone) |
| Валідація | ❌ лише `value required` | per-type правила + нормалізатор |
| Публікація | ⚠️ публікується generic | явний arm у `buildItem` + `TYPE_ORDER` (контроль форми payload) |
| Пошук/фільтри | ⚠️ bulk — так, сітка — мертвий UI | — |
| Права | ✅ ungated | гейт **лише** якщо тип чутливий (PII) |

**Висновок:** реальна обов'язкова поверхня для нового content-типу — `ValueEditor::save()` (+валідація +create-whitelist), `value-editor.blade`, `SitePayloadCompiler::buildItem`, `audit-manager.blade` ($dataType + $renderDiff), `values-grid.blade` ($typeLabels/$typeOrder), `bulk displayValue`. Решта — generic.
