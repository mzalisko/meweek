---
tags: [dbmanager, api, документація, специфікація]
---

# Поточна специфікація API DBManager

**Дата аудиту:** 2026-06-19
**Модель аудиту:** Gemini (Advanced Agentic Coding)

Цей документ описує всі наявні API-ендпоінти у системі DBManager (Core, DataBridge та WP-Plugin), включаючи параметри запитів, схеми відповідей, методи авторизації та ліміти.

---

## 1. API Панелі керування (Core)

Ендпоінт призначений для інтеграції із зовнішніми системами моніторингу працездатності телефонних ліній (наприклад, Ringostat, Binotel, Keitaro).

### 1.1. Зміна статусу телефону через Webhook
- **Роут**: `POST /api/monitoring/numbers`
- **Заголовки**:
  - `Content-Type: application/json`
  - `X-Signature: <HMAC-SHA256>` (обов'язковий)
- **Схема запиту**:
  ```json
  {
    "e164": "+380501234567",
    "status": "down"
  }
  ```
  *Параметри:*
  - `e164` (string, max 20, обов'язковий): номер телефону у міжнародному форматі E.164.
  - `status` (string, обов'язковий): поточний стан лінії. Дозволені значення: `down` (несправний) або `active` (справний).
- **Авторизація**:
  HMAC-SHA256 від сирого тіла запиту (`request->getContent()`), підписаний ключем `services.monitoring.secret`. Порівняння підписів виконується через `hash_equals()`.
- **Схеми відповідей**:
  - **`200 OK`**: Успішно оновлено статус. Payload автоматично перекомпільовано для всіх пов'язаних сайтів та надіслано на DataBridge.
    ```json
    {
      "affected_sites": 2
    }
    ```
  - **`401 Unauthorized`**: Некоректний підпис (`Invalid signature`).
  - **`422 Unprocessable Entity`**: Номер не знайдено в базі даних Core.
    ```json
    {
      "message": "Unknown number"
    }
    ```
  - **`500 Internal Server Error`**: Ключ `services.monitoring.secret` не налаштований у файлі `.env`.
- **Обмеження**: Rate Limit `60` запитів на хвилину.

---

## 2. API Проміжного сервера (DataBridge)

DataBridge служить буфером доставки та роздачі даних, приховуючи основну адмін-панель Core від прямого доступу з інтернету.

### 2.1. Публікація payload сайту (Core → DataBridge)
- **Роут**: `POST /api/internal/publish`
- **Заголовки**:
  - `Content-Type: application/json`
  - `X-Signature: <HMAC-SHA256>` (обов'язковий)
- **Схема запиту**:
  ```json
  {
    "domain": "mysite.com",
    "token_hash": "c20ad411624c4cfb8084b5d3ebcbf852...",
    "ping_url": "https://mysite.com/wp-json/dbm/v1/ping",
    "version": 15,
    "payload": {
      "site": "mysite.com",
      "version": 15,
      "generated_at": "2026-06-19T00:30:13Z",
      "values": [
        {
          "key": "phone_main",
          "type": "phone",
          "geo": ["UA"],
          "value": "+380501234567",
          "state": "ok"
        },
        {
          "key": "ROMANIA",
          "type": "price",
          "geo": ["WORLD", "RU", "BY"],
          "value": "2000",
          "label": "Румунія",
          "state": "ok"
        }
      ]
    }
  }
  ```
- **Авторизація**:
  HMAC-SHA256 від сирого тіла запиту, підписаний спільним між Core та Bridge ключем `services.publish.secret`.
- **Схеми відповідей**:
  - **`200 OK`**: Успішно збережено.
    ```json
    {
      "stored_version": 15
    }
    ```
  - **`401 Unauthorized`**: Некоректний підпис.
  - **`409 Conflict`**: Спроба надіслати старішу або таку ж версію payload, ніж уже збережена в базі (`Stale version`).
  - **`500 Internal Server Error`**: Не налаштовано `services.publish.secret`.

### 2.2. Завантаження GeoIP БД (Core → DataBridge)
- **Роут**: `POST /api/internal/geodb`
- **Заголовки**:
  - `Content-Type: application/octet-stream`
  - `X-Signature: <HMAC-SHA256>` (обов'язковий)
- **Схема запиту**: Сирий бінарний потік файлу бази MaxMind `.mmdb`.
- **Авторизація**: HMAC-SHA256 від бінарного тіла, підписаний `services.publish.secret`.
- **Схеми відповідей**:
  - **`200 OK`**: Успішно завантажено.
    ```json
    {
      "stored_sha": "d4f5667a423a94c..."
    }
    ```
  - **`401 Unauthorized`**: Некоректний підпис.

### 2.3. Отримання даних сайту (WP-Plugin → DataBridge)
- **Роут**: `GET /api/v1/data`
- **Заголовки**:
  - `X-Site-Token: <raw-token>` (обов'язковий)
  - `If-None-Match: "<version>"` (обов'язковий для добової звірки)
- **Авторизація**: 
  Надсилається відкритий API-токен сайту. DataBridge обчислює `hash('sha256', $rawToken)` та зіставляє з базою.
- **Схеми відповідей**:
  - **`200 OK`**: Повертає згенерований payload. Заголовок `ETag` містить версію, а `X-Signature` містить підпис тіла, зроблений за допомогою `services.data.signing_secret`.
  - **`304 Not Modified`**: Якщо версія у `If-None-Match` збігається з версією у DataBridge. Економить трафік.
  - **`401 Unauthorized`**: Токен порожній або не знайдений у базі.
- **Обмеження**: Rate Limit `120` запитів на хвилину.

### 2.4. Завантаження GeoIP БД (WP-Plugin → DataBridge)
- **Роут**: `GET /api/v1/geodb`
- **Заголовки**:
  - `X-Site-Token: <raw-token>` (обов'язковий)
  - `If-None-Match: "<sha256-hash>"` (опціональний)
- **Схеми відповідей**:
  - **`200 OK`**: Повертає бінарний файл `.mmdb`. Заголовок `ETag` містить SHA256 хеш бази. Заголовок `X-Signature` містить HMAC підпис бінарного вмісту.
  - **`304 Not Modified`**: Якщо хеш бази збігається з `If-None-Match`.
  - **`404 Not Found`**: База даних GeoIP ще не завантажена.
- **Обмеження**: Rate Limit `30` запитів на хвилину.

---

## 3. API Клієнтського сайту (WP-Plugin)

### 3.1. Отримання пінгу про нову версію
- **Роут**: `POST /wp-json/dbm/v1/ping` (REST API WordPress)
- **Заголовки**:
  - `Content-Type: application/json`
  - `X-Signature: <HMAC-SHA256>` (обов'язковий)
- **Схема запиту**:
  ```json
  {
    "domain": "mysite.com",
    "version": 15
  }
  ```
- **Авторизація**:
  HMAC-SHA256 від тіла запиту, підписаний ключем `ping_secret`, що налаштований для цього сайту.
- **Схеми відповідей**:
  - **`202 Accepted`**: Сигналізує, що підпис валідний і плагін розпочав асинхронний Pull запит до DataBridge.
  - **`401 Unauthorized`**: Некоректний підпис.

---

## 4. Чого не вистачає для повної інтеграції

1. **Одночасний відклик токенів (Revocation Propagation)**:
   - Зараз при видаленні сайту або токена в Core, DataBridge не дізнається про це моментально. Оскільки DataBridge працює без синхронного зв'язку назад із Core, він валідуватиме токен, доки не відбудеться наступна успішна публікація на цей домен (яка б перезаписала або видалила запис).
   - **Вирішення**: Необхідно створити окремий роут `POST /api/internal/revoke` на DataBridge для негайного видалення сайту з кешу Bridge при видаленні сайту з адмінки Core.
2. **Динамічний статус зв'язку (Health Check)**:
   - В адмінці Core показується колонка `last_seen_at`. Проте вона оновлюється лише коли сателіт робить pull-запит. Якщо сателіт лежить, ми дізнаємося про це лише якщо пінг-запит DeliverPingJob завершиться помилкою. Немає активного фонового моніторингу (ping) з боку Core.
