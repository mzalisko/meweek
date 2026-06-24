# Claude Code — Тихий режим рутин

## Що це

`CLAUDE_CLIENT_PRESENCE_FILE` — змінна середовища Claude Code (додана червень 2026), що вказує на файл-маркер. Поки цей файл існує, Claude Code не надсилає системні push-сповіщення на мобільний пристрій.

Мета: автоматизовані рутини не спамлять телефон системними сповіщеннями — лише цілеспрямовані через `PushNotification` tool.

## Чому це важливо

Фонові рутини (daily-digest, weekly-review, Fitness, CI) за замовчуванням можуть надсилати нативні push від Claude Code. Це перериває роботу без корисного сигналу. З `CLAUDE_CLIENT_PRESENCE_FILE`:

- Системні push вимкнені під час роботи рутини
- Агент сам вирішує, коли сповіщати — через `PushNotification` tool
- Чіткий розподіл: "шум" vs "сигнал"

## Приклад

```bash
# У скрипті запуску рутини
PRESENCE_FILE="/tmp/claude-presence-$$"
touch "$PRESENCE_FILE"
export CLAUDE_CLIENT_PRESENCE_FILE="$PRESENCE_FILE"

claude -p "виконай daily digest..."

rm -f "$PRESENCE_FILE"
```

Або в `.claude/settings.json` для постійних сесій:
```json
{
  "env": {
    "CLAUDE_CLIENT_PRESENCE_FILE": "/tmp/claude-presence"
  }
}
```

## Як використати в Meweek

| Рутина | Дія |
|---|---|
| `daily-ai-digest` | Додати presence file у SessionStart хук |
| `weekly-review` | Те саме |
| `fitness` автоматизація | Presence file на час звіту |

Сповіщення надсилати лише через `PushNotification` tool з `<routine_summary>` тегами.

## Пов'язано

- [[Claude Code — Managed Settings]]
- [[Harness Engineering — агентні цикли]]
- [[AI Дайджест 2026-06-19]]

## Джерела

- [Claude Code What's New](https://code.claude.com/docs/en/whats-new)
- [Claude Code Updates June 2026](https://releasebot.io/updates/anthropic/claude-code)
