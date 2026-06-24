# Skill Idea: mcp-ssh-auth

## Назва навички
`mcp-ssh-auth`

## Мета
Аутентифікація MCP серверів у headless/SSH середовищах через `claude mcp login --no-browser`, без відкриття браузера. Корисна для рутин на claude.ai або CI/CD де немає GUI.

## Вхідні дані
- Назва MCP сервера (`<n>`) або список серверів
- `--no-browser` прапор (для SSH)
- Опціонально: credentials або token через stdin

## Вихідні дані
- Статус аутентифікації для кожного сервера
- Список успішно підключених MCP серверів
- Інструкція якщо аутентифікація потребує ручного кроку

## Приклад воркфлоу
```
/mcp-ssh-auth github obsidian

→ Запуск: claude mcp login github --no-browser
→ Redirect URL надіслано на stdin
→ Завершення: github MCP authenticated ✓
→ Запуск: claude mcp login obsidian --no-browser
→ Завершення: obsidian MCP authenticated ✓

→ Всі MCP сервери готові до роботи в headless режимі.
```

## Орієнтована цінність
- **Висока для рутин**: scheduled рутини на claude.ai Web запускаються без доступу до браузера. Без цього — MCP сервери не аутентифіковані і не доступні.
- Відкриває використання GitHub MCP, Obsidian MCP та інших у повністю автоматизованих флоу.

## Складність реалізації
**Низька**: обгортка навколо `claude mcp login/logout` CLI команд (доступно з v2.1.186). Потребує обробки stdin redirect та помилок аутентифікації.

## Передумови
- Claude Code v2.1.186+
- MCP сервери сконфігуровані в `.claude/settings.json`
- SSH/headless середовище

## Джерела ідеї
- [Claude Code Changelog v2.1.186](https://code.claude.com/docs/en/changelog)
- [[AI Дайджест 2026-06-24]]
