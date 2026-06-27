# Claude Code — MCP CLI Auth та /rewind

## Що це

Claude Code Week 26 (червень 2026) додав дві команди, що змінюють управління сесіями та MCP-підключеннями:

- **`claude mcp login <server>`** / **`claude mcp logout <server>`** — автентифікація MCP серверів прямо з shell без інтерактивного `/mcp` меню
- **`/rewind`** — відновлення стану розмови до точки перед останнім `/clear`

## Чому важливо

До Week 26 підключення MCP серверів потребувало ручного `/mcp` меню — це блокувало автоматизацію в скриптах і рутинах. Тепер `claude mcp login` можна викликати з будь-якого shell-скрипту або hook.

`/rewind` усуває типову проблему: після `/clear` для звільнення контексту весь стан розмови губиться. Тепер є аварійний відкат.

## Приклад

```bash
# Автентифікація Obsidian MCP перед запуском рутини
claude mcp login obsidian-local

# Перевірка статусу
claude mcp list
```

У сесії:
```
/rewind    # Відновити до стану перед /clear
```

## Як використовувати в Meweek

1. **git-sync skill** — додати `claude mcp login` у startup hook щоб Obsidian MCP завжди активний
2. **daily-digest рутина** — `claude mcp login obsidian-local` перед читанням нотаток замість ручного налаштування
3. **`/rewind` замість `/save-context`** — якщо контекст загубився, відновити через /rewind, а не перечитувати файли

## Пов'язані зміни Week 26

- Background subagents: permission prompts тепер з'являються в головній сесії (не автовідхиляються)
- Shell auto-explain: `! npm test` автоматично пояснює вивід
- Voice dictation: виправлено для японської, китайської, тайської мов

## Джерела

- [Claude Code Changelog](https://code.claude.com/docs/en/changelog)
- [[Claude Code — Managed Settings]]
- [[Claude Code — Рекурсивні субагенти]]
