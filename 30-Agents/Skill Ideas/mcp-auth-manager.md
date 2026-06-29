# Ідея навички: mcp-auth

## Призначення

Автоматизація автентифікації та управління credentials для MCP-серверів через новий CLI-інтерфейс `claude mcp login/logout`.

## Вхідні дані

- Назва MCP-сервера (або "all" для всіх налаштованих)
- Дія: `login` / `logout` / `status`

## Вихідні дані

- Список налаштованих MCP-серверів зі статусом автентифікації
- Підтвердження успішного login/logout
- Інструкції якщо потрібна додаткова дія від користувача

## Приклад workflow

```
/mcp-auth login github      → claude mcp login github
/mcp-auth status            → показати статус всіх серверів
/mcp-auth logout github     → claude mcp logout github
```

## Розширений сценарій

При старті нової сесії перевіряти статус усіх MCP-серверів і автоматично пропонувати login для тих, що потребують оновлення.

## Орієнтовна цінність

- **Висока**: прибирає ручний крок у кожній сесії з MCP
- Спрощує onboarding нових сесій
- Захищає від ситуації "MCP не відповідає — бо credentials застаріли"

## Складність реалізації

**Low** — обгортка навколо вже існуючих CLI-команд `claude mcp login/logout`

## Джерела

- [Claude Code Week 26 — `claude mcp login`](https://code.claude.com/docs/en/whats-new/2026-w26)
