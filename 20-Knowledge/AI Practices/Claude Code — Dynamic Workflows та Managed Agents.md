# Claude Code — Dynamic Workflows та Managed Agents

## Що це

Дві нові системи оркестрації агентів у Claude Code (GA — червень 2026):

**Dynamic Workflows** — мульти-агентний ранінг, що запускається keyword `ultracode` у промпті. Один оркестратор → кілька паралельних субагентів → синтез результатів. Підтримує ланцюжок до 5 рівнів субагентів.

**Managed Agents** (public beta) — хмарне середовище для запуску агентів за розкладом. Функціонал:
- Scheduled deployments (cron-like без локального cron)
- Environment variables у vaults (безпечне сховище секретів)
- `ant` CLI для керування агентами з терміналу

## Чому важливо

Раніше паралельна робота агентів потребувала ручного harness і cron. Тепер:
- `ultracode` → Claude Code сам будує флот агентів під задачу
- Managed Agents → рутини живуть у хмарі, не на локальній машині
- Субагенти можуть породжувати власних субагентів (5 рівнів = 5^5 = потенційно тисячі агентів)

## Приклад

```
# Dynamic Workflows — запуск у Claude Code
ultracode Зроби детальний аналіз всіх проєктів у 10-Projects/, 
знайди залежності, побудуй граф зв'язків

# Результат: Claude Code сам розпаралелює по 10+ субагентах
```

```bash
# ant CLI — Managed Agents
ant create --name daily-digest --schedule "0 7 * * *" \
  --prompt "/daily-digest" --vault meweek-secrets
```

## Нові можливості Claude Code (червень 2026)

| Фіча | Опис |
|---|---|
| `/rewind` | Відновлення до стану перед `/clear` |
| `/cd` | Зміна директорії mid-session без rebuild cache |
| `--safe-mode` | Запуск без кастомізацій (для дебагу) |
| `fallbackModel` | До 3 запасних моделей у `settings.json` |
| "Dreaming" | Фонова сесія для рев'ю пам'яті агента |
| `claude-fable-5` | Mythos-class, `/model fable` |

## Як використати в Meweek

| Задача | Підхід |
|---|---|
| `daily-digest` | Managed Agents (розклад о 07:00 без cron) |
| `weekly-review` | `ultracode` для паралельного аналізу 10+ секцій |
| Захист від `/clear` | `/rewind` після випадкового очищення |
| Надійність рутин | `fallbackModel: ["claude-sonnet-4-6", "claude-haiku-4-5"]` |

## Джерела

- [Claude Code What's New](https://code.claude.com/docs/en/whats-new)
- [Code with Claude 2026: 5 New Agent Features](https://www.mindstudio.ai/blog/code-with-claude-2026-new-agent-features)
- [Anthropic Managed Agents Production](https://www.buildmvpfast.com/blog/anthropic-managed-agents-production-keynote-2026)
- [[AI Дайджест 2026-06-25]]
