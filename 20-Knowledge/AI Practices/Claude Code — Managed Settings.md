# Claude Code — Managed Settings

## Що це

Managed Settings — механізм централізованого керування налаштуваннями Claude Code для команд і організацій. Адміністратор задає обмеження, які користувачі або проєктні файли не можуть перевизначити.

Введено у версії 2.1.175 (12 червня 2026).

## Ключові параметри

### `enforceAvailableModels`
Коли увімкнено:
- Список `availableModels` блокує не лише вибір моделі вручну, але й автоматичне розв'язання Default
- Default, що розв'язувався б у заборонену модель, отримує fallback на першу дозволену модель у списку
- Проєктні та користувацькі налаштування не можуть розширити allowlist

### `availableModels`
Allowlist моделей, доступних у цій конфігурації. Формат: масив model ID.

## Tool(param:value) — гранулярний синтаксис дозволів

Введено у версії 2.1.178 (15 червня 2026). Дозволяє блокувати або дозволяти інструмент лише при конкретних значеннях його параметрів.

```json
{
  "permissions": {
    "deny": [
      "Agent(model:claude-fable-5)",
      "Bash(cmd:rm*)"
    ],
    "allow": [
      "Agent(model:claude-haiku-4-5-20251001)"
    ]
  }
}
```

- Підтримує `*` wildcard: `Agent(model:*)` — всі субагенти.
- Прецизійніше за `"deny": ["Agent"]` — блокує модель, а не весь інструмент.
- Критично після billing split: Fable-5 у субагентах коштує $50/M output token.

## Чому важливо

**Контроль витрат**: Fable 5 коштує $50/M output токенів, Sonnet — значно дешевше. Для автоматизованих задач різниця в 5-10× на реальному використанні.

**Передбачуваність бюджету**: після 15 червня 2026 автоматичні агенти (`claude -p`, GitHub Actions) витрачають кредити з окремого пулу ($20–200/міс). Без обмежень одна важка задача може спустошити місячний пул.

**Безпека**: запобігає випадковому використанню потужних моделей у ненадійних агентних пайплайнах.

## Приклад

```json
// .claude/settings.json (managed — не перевизначається проєктом)
{
  "enforceAvailableModels": true,
  "availableModels": ["claude-sonnet-4-6"],
  "permissions": {
    "allow": ["Bash", "Read", "Write", "Edit"]
  }
}
```

Для інтерактивних сесій — окремий файл або override через CLI:
```bash
claude --model claude-fable-5
```

## Як використати в Meweek

| Контекст | Модель | Причина |
|---|---|---|
| Автоматичні рутини (`claude -p`) | `claude-sonnet-4-6` | Економія кредитного пулу |
| Масова обробка (Fitness звіти) | `claude-haiku-4-5` | Швидко + дешево |
| Інтерактивні сесії розробки | Default (Fable 5) | Максимальні можливості |
| Критичні архітектурні рішення | `claude-fable-5` | Явно вказати |

**Рекомендація**: у `CLAUDE.md` навичок-рутин (daily-digest, weekly-review, Fitness) додати `availableModels` обмеження на Sonnet.

## Пов'язано

- [[Harness Engineering — агентні цикли]]
- [[Claude Code — Рекурсивні субагенти]]

## Джерела

- [Claude Code Changelog v2.1.175](https://code.claude.com/docs/en/changelog)
- [Claude Credit Overhaul June 15 (Digital Applied)](https://www.digitalapplied.com/blog/anthropic-claude-credit-overhaul-june-15-2026)
- [Anthropic June 15 Billing Change](https://codersera.com/blog/anthropic-june-2026-billing-change-claude-code/)
