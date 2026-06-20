---
tags: [dbmanager, дослідження, план, типи-даних]
---

# NEW_TYPES_PLAN — інтеграція «Соцмережі» та «Адреси» в усі механізми

> План реалізації на основі [[DATA_TYPES_MAP]], [[ADDRESS_DECISION]], [[BULK_EDIT_AUDIT]]. Посилання — `file:line` від `code/core/`.
> Принцип: новий тип має пройти **кожен** механізм або падати тест.

## Канонічні форми `content`

```
social.content  = { network: string(enum), value: string(handle), url: string|null }
address.content = { country, region, city, street, postcode, value(похідний) }
```
- `social` структурно близький до messenger мінус слот/failover; перевикористовує ключі `network/value/url` → generic-механізми вже показують value/url.
- `address` — структурований за [[ADDRESS_DECISION]] (A+), із `value`-дзеркалом.

## Спільна підготовка (фундамент)

1. **Зняти головний бар'єр create** — `ValueEditor.php:146`: розширити create-`in:` до `phone,messenger,price,text,address,social` (або обчислювати з `ValueType::pluck('code')`). Це робить address/social/text створюваними.
2. **(Рекомендовано) Доробити `text`** заразом — він уже засіяний; коштує лише label/іконки + create-whitelist. Закриває потребу «вільний текст» з [[ADDRESS_DECISION]].
3. **Нормалізатори** — не плодити 4-ту копію `messengerUrlFromValue`; винести спільний хелпер у `app/Support/` (URL із handle/platform).

## Соцмережі (`social`)

| Механізм | Зміна |
|---|---|
| Валідація | `ValueEditor::save` (~`152`): `network` required `in:<telegram,instagram,facebook,tiktok,…>`; `value` (handle) required, regex `^@?[A-Za-z0-9._]+$` або url |
| Нормалізація content | гілка `if type==='social'`: `{value:handle, network:platform, url: socialUrl(platform,handle)}` |
| Редактор UI | `value-editor.blade.php`: `@if social` — `<select>` платформи (як messenger `network`, `128`); generic `value` уже показано |
| Публікація | `SitePayloadCompiler::buildItem:118` — `'social' => socialItem()` (форма `{network, value, url}`); `TYPE_ORDER` за бажанням |
| Сітка | `values-grid.blade.php`: `$typeLabels['social']=['Соцмережа','link']`, `$typeOrder`; рендер — generic value/url |
| Аудит | `audit-manager.blade.php:631-660` — arm `social` (×2 closures); `$renderDiff` — ключі `network/url` |
| Bulk | `targetType` уже є; іконка є. `displayValue` — гілка social (показ `@handle (network)`); generic ops `set_status/set_geo` ок |
| Права | без гейту (соц-нік не чутливий) |
| Логування/відновлення | без змін (value.* / generic restore) |

## Адреси (`address`, структуровано A+)

| Механізм | Зміна |
|---|---|
| Властивості компонента | додати `$addrCountry/$addrRegion/$addrCity/$addrStreet/$addrPostcode`; reset у `createFor()`, load у `edit()` |
| Валідація | `city` required; решта nullable string; зібрати `value` = `composeAddress(...)` |
| Нормалізація content | гілка `if type==='address'`: структуровані поля + `value` (sync); **preserve-блок** на update (як messenger `199-217`), щоб не загубити поля |
| Редактор UI | `@if address` — поля country/region/city/street/postcode |
| Публікація | `SitePayloadCompiler::buildItem` — `'address' => addressItem()` (явний allow-list ключів, **без** витоку службових полів); `TYPE_ORDER` |
| Сітка | `$typeLabels['address']=['Адреса','pin']`, `$typeOrder`; рендер — зібраний `value` |
| Аудит | arm `address` (×2); `$renderDiff` — per-field мітки (`street/city/country/postcode`) |
| Bulk | `displayValue` — гілка address (зібраний рядок). **Політика:** дозволити лише `set_status/set_geo`; `set_value/replace_text` для address приховати/заблокувати ([[BULK_EDIT_AUDIT]] B1) |
| Права | за замовч. без гейту; якщо адресу вважати PII — додати `can_view_address` за патерном `can_view_prices` (8 файлів, див. [[DATA_TYPES_MAP]] §4.10). **Винести в окреме рішення** перед релізом |
| Логування/відновлення | без змін (content-only round-trips) |

## Підсвічування (вимога §4 завдання)

- Зробити `displayValue` змістовним для social/address (інакше bulk-прев'ю показує `— ▼ —`).
- Додати підсвічування в **single edit** (зараз нуль): показ оригіналу + dirty-індикатор у `value-editor.blade.php`. Узгодити з єдиним diff-компонентом із [[BULK_EDIT_AUDIT]] §7.

## Порядок реалізації (інкрементально, кожен крок — зелені тести)

1. Фундамент: create-whitelist + (опц.) `text`. Тест: address/social створюються.
2. `social` end-to-end (валідація → content → editor → publish → grid → audit → bulk display). Тести social у всіх механізмах.
3. `address` структуровано end-to-end + bulk-політика. Тести address у всіх механізмах.
4. Підсвічування single-edit + `displayValue` для нових типів.
5. Регресія: phone/price/messenger без змін.

## Контракт тестів (мають падати, якщо тип «випав»)

Для кожного нового типу — Feature/Unit, що перевіряють **усі** механізми:
- Створення через `ValueEditor` (create-whitelist пропускає; content коректний).
- Редагування/видалення (+аудит-рядки).
- **Bulk:** проходження через `matchedDataValues` + дозволені операції + rollback.
- **Логування:** value.created/updated/deleted/geo_changed з повним content.
- **Публікація:** `SitePayloadCompiler` віддає очікувану форму (social `{network,value,url}`; address структуровано, без службових ключів).
- **Аудит-відображення:** 'Тип даних' = «Соцмережа»/«Адреса», не «Текст».
- **Валідація:** правильні значення проходять, неправильні — ні (social platform enum/handle; address city required).
- **Регресія:** phone/price/messenger payload і поведінка незмінні.
