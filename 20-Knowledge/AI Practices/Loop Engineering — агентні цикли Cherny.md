# Loop Engineering — агентні цикли (Boris Cherny)

## Що це

Loop Engineering — парадигма розробки агентних систем, запропонована Boris Cherny (творець Claude Code, Anthropic). Суть: замість написання окремих промптів для кожного завдання — пишеш **цикли**, які самостійно вирішують коли, як і які промпти генерувати і виконувати.

Формула Cherny: *"Я не промпчу Claude. У мене є цикли, що промптять Claude і з'ясовують, що робити. Моя робота — писати цикли."*

## Чому важливо

- Це наступний рівень абстракції після single-agent промптингу.
- Цикл = автономна система з вбудованою логікою повторень, умовами зупинки та budget-контролем.
- Від початку 2026 Anthropic у 8 разів збільшив обсяг коду, продуктивність інженерів виросла на ~70% — за рахунок циклів, а не одноразових промптів.
- Boris Cherny одночасно керує **десятками тисяч паралельних агентів** через цю систему.

## Структура Loop Engineering

```
Loop = trigger → умови запуску
     + prompt_generator → динамічний промпт під поточний стан
     + agent_call → виконання (Claude / субагент)
     + evaluator → чи виконано умову виходу?
     + budget_guard → не перевищити ліміт токенів
     + repeat / stop
```

## Приклад

Замість: *"проаналізуй changelog Claude Code і скажи що нового"*

Цикл:
1. Щодня о 07:00 отримати поточну версію Claude Code з GitHub.
2. Порівняти з версією з попереднього дня.
3. Якщо є нові записи — згенерувати промпт для їх аналізу.
4. Виконати аналіз і зберегти результат у `20-Knowledge/AI Practices/`.
5. Якщо без змін — нічого не робити.
6. Повторити завтра.

## Як я можу використати

- **daily-digest**: цикл замість щоденного запуску агента вручну.
- **weekly-review**: цикл, що сам визначає які проєкти потребують огляду.
- **fitness-reports**: цикл, що перевіряє чи є нові дані і генерує звіт лише при наявності.
- **changelog-monitor**: цикл для автоматичного відстеження нових фіч Claude Code.

## Зв'язки в Meweek

- [[Harness Engineering — агентні цикли]] — технічна реалізація в Claude Code SDK
- [[Claude Code — Рекурсивні субагенти]] — вкладені агенти всередині циклу
- [[30-Agents/Skill Ideas/loop-engineering.md]] — ідея навички

## Джерела

- [Boris Cherny — Fortune Interview (June 8, 2026)](https://fortune.com/2026/06/08/anthropics-boris-cherny-creator-of-claude-code-says-there-are-days-he-manages-tens-of-thousands-of-ai-agents-at-once/)
- [Loop Engineering article — TechTimes](https://www.techtimes.com/articles/318828/20260622/claude-code-loop-engineering-stop-prompting-start-designing-autonomous-agent-workflows.htm)
- [How Boris Uses Claude Code](https://howborisusesclaudecode.com/)
