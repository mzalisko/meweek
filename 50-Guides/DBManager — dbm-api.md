# DBManager — dbm-api

Повний технічний контракт: [[DBManager — dbm-api]].

## Для чого

`dbm-api` описує зовнішні webhook-виклики в DBManager Core, коли стороння CRM або моніторинг хоче повідомити, що номер або месенджер упав чи відновився.

## Що вже працює

Падіння або відновлення номера:

```http
POST /api/monitoring/numbers
```

Тіло:

```json
{
  "e164": "+380441112233",
  "status": "down"
}
```

`status` може бути `down` або `active`.

## Безпека

Запит підписується через:

```http
X-Signature: hmac-sha256(raw-json-body, MONITORING_WEBHOOK_SECRET)
```

Секрет зберігається тільки у `.env` Core і не має потрапляти в нотатки, коміти або логи.

## Месенджери

Для месенджерів у документі зафіксовано рекомендований контракт:

```http
POST /api/monitoring/messengers
```

Цей endpoint ще треба реалізувати в Core. Очікувана логіка: `down` вимикає конкретний месенджер або резерв, `active` повертає його, Core перераховує поточний активний месенджер і публікує payload у Bridge.

## Що перевірити після виклику

1. Core повернув `affected_sites`.
2. У Bridge немає завислих `jobs` і `failed_jobs`.
3. `bridge-worker` запущений.
4. У WordPress-плагіні оновилась версія кешу.
