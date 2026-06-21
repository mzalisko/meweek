# Claude Code — Dynamic Workflows

## Що це

Dynamic Workflows — механізм Claude Code (GA, червень 2026) для координації великої кількості AI-агентів у межах одного воркфлоу. Claude самостійно генерує план оркестрації на основі мети, запускає паралельних агентів, верифікує результати та ітерує до збіжності.

На відміну від Harness Engineering (де людина пише цикл), Dynamic Workflows — Claude сам будує свій цикл.

## Чому важливо

Один агент у одному потоці не масштабується:
- 50 файлів на перевірку → 50 послідовних кроків → повільно
- Dynamic Workflows → 50 паралельних агентів → у рази швидше

**Реальний приклад:** Jarred Sumner портував Bun із Zig на Rust — 750 000 рядків Rust, 99.8% тестів пройшло, **11 днів**.

## Базові патерни

| Патерн | Коли застосовувати |
|---|---|
| Sequential | Прості лінійні задачі |
| Operator | Один оркестратор → кілька виконавців |
| Split-and-merge | Розбити на частини → зібрати результат |
| Agent Teams | Спеціалізовані команди агентів |
| Headless | Без інтерактивності, для автоматизації |

Узагальнений шаблон: **fan out → reduce → synthesize**

## Як використати в Meweek

| Задача Meweek | Dynamic Workflow підхід |
|---|---|
| weekly-review | Паралельний огляд кожного проєкту окремим агентом |
| code-review | Fan out по файлах → reduce: список проблем |
| Fitness підбий тиждень | Агент на кожен тип даних (тренування, харчування, сон) |
| Inbox processing | Класифікація нотаток паралельно |

## Важливо

- Dynamic Workflows витрачають **суттєво більше токенів** ніж стандартна сесія
- Починати завжди з обмеженої тестової задачі
- Після billing split стежити за кредитами при headless використанні

## Джерела

- [Anthropic Blog: Dynamic Workflows](https://claude.com/blog/introducing-dynamic-workflows-in-claude-code)
- [InfoQ: Claude Code Dynamic Workflows](https://www.infoq.com/news/2026/06/dynamic-workflows-claude-code/)
- [MindStudio: 5 Workflow Patterns](https://www.mindstudio.ai/blog/claude-code-agentic-workflow-patterns)
- [[AI Дайджест 2026-06-21]]
