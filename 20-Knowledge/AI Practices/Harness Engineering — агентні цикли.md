# Harness Engineering — агентні цикли

## Що це

Harness Engineering — підхід до AI-автоматизації, де замість одноразових промптів будується *програма-оболонка* (harness), що запускає агента у повторюваних циклах: спостереження → планування → дія → рефлексія.

Термін введений Борисом Черні (глава Claude Code, Anthropic) у 2026 р.

## Чому важливо

Одноразовий промпт — це запит. Harness — це продукт. Різниця:
- Агент може працювати годинами або днями без участі людини
- Цикл автоматично обробляє помилки, компактує контекст, продовжує роботу
- Масштабується: Черні керує тисячами агентів одночасно через harness

## Приклад

```bash
# Простий harness: запускати claude -p кожні 30 хвилин
# для обробки вхідних нотаток у Meweek

while true; do
  claude -p "Переглянь 00-Inbox/, розклади нотатки по розділах, оновлюй контекст" \
    --output-format json >> harness.log
  sleep 1800
done
```

Або через cron:
```
0 7 * * * claude -p "/daily-digest" >> ~/meweek/logs/digest.log
```

## Ключові компоненти harness

1. **Тригер** — cron, webhook, файлова подія
2. **Контекст** — передається агенту на кожному запуску (мінімальний, точний)
3. **Guardrails** — обмеження дій (які файли можна чіпати, які ні)
4. **Skills/tools** — набір можливостей агента всередині циклу
5. **Logging** — запис дій для аудиту та дебагу
6. **Exit condition** — умова завершення циклу

## Як використати в Meweek

| Рутина | Harness-підхід |
|---|---|
| AI Дайджест | `claude -p "/daily-digest"` за cron о 07:00 |
| Fitness звіти | `claude -p "fitness Микола підбий тиждень"` щонеділі |
| Weekly Review | `claude -p "/weekly-review"` кожного понеділка |
| Inbox processing | `claude -p` при появі нових файлів у `00-Inbox/` |

## Важливо після 15 червня 2026

`claude -p` (headless режим) переходить на Agent SDK Credit Pool. Обліковувати використання, щоб не перевищити ліміт кредитів ($20/міс для Pro).

## Loop Engineering (еволюція, червень 2026)

Черні (червень 2026): «Я більше не пишу промпти — Claude пише промпт, а я говорю з тим Claude, що координує». Це наступний рівень після Prompt Engineering — **Loop Engineering**: людина задає цілі та контролює цикл, агент формує деталі виконання.

Практично: замість написання промпту → описати мету → Claude будує власний промпт → людина ревʼює цикл.

## Джерела

- [Boris Cherny — Fortune, 11 червня 2026](https://fortune.com/2026/06/11/anthropic-claude-boris-cherny-doesnt-write-code-by-hand-anymore/)
- [Boris Cherny — Fortune, 8 червня 2026](https://fortune.com/2026/06/08/anthropics-boris-cherny-creator-of-claude-code-says-there-are-days-he-manages-tens-of-thousands-of-ai-agents-at-once/)
- [Loop Engineering Guide (explainx.ai)](https://explainx.ai/blog/what-is-loop-engineering-ai-agents-2026)
- [[30-Agents/AI News Digest/Daily/2026-06-23]]
