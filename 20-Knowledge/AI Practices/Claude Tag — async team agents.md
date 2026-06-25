# Claude Tag — async team agents

## Що це

Claude Tag — бета-функція Anthropic (23 червня 2026), яка додає @Claude як спільного члена Slack-каналу для всієї команди. Архітектурно відрізняється від IDE-копілотів: агент діє **асинхронно у спільному комунікаційному просторі**.

## Ключові характеристики

- **Async**: призначаєш задачу → відходиш → Claude публікує результат у тред
- **Multiplayer context**: вся команда бачить роботу Claude (не лише ініціатор)
- **Ambient mode**: Claude сам надсилає оновлення по застряглих тредах
- Модель: Opus 4.8
- Платформа: Enterprise і Team план (30-днів міграції зі старого Claude in Slack)
- Внутрішні дані: 65% коду Anthropic-продукт-команди генерується через внутрішній Claude Tag

## Чому важливо

**Нова операційна модель**: агент як член команди, а не особистий асистент. Це означає:
1. Агент бачить весь командний контекст — не лише окремий промпт
2. Командна роботи з агентом — прозора і відтворювана (у тредах)
3. Async = не потрібно чекати результату, як при синхронних CLI-запитах

**Архітектурна відмінність від Claude Code**: Claude Code = синхронний персональний агент у терміналі. Claude Tag = асинхронний командний агент у комунікаційному каналі.

## Паттерн "Team Channel Agent"

```
Людина → @Claude виконай X → Claude присвоює собі таску
↓
Claude працює асинхронно (може зайняти хвилини)
↓
Claude → публікує результат у тред (бачать всі)
↓
Ambient mode: якщо тред застряг → Claude сам надсилає апдейт
```

## Як застосувати у Meweek

Поки функція доступна лише для Enterprise/Team. Але патерн вже зараз можна реалізувати через:
- **Scheduled рутини** (Routines в Claude Code web) — агент виконує задачу і публікує PR/звіт
- **GitHub Actions + Claude** — аналог "ambient" агента у code review контексті
- Якщо з'явиться доступ до Claude Tag — протестувати для async огляду нотаток Meweek

## Зв'язки

- [[Harness Engineering — агентні цикли]] — async loops як технічна основа
- [[Claude Code — Рекурсивні субагенти]] — архітектурний контекст

## Джерела

- [Anthropic — Introducing Claude Tag](https://www.anthropic.com/news/introducing-claude-tag)
- [TechCrunch — Claude Tag](https://techcrunch.com/2026/06/23/anthropics-claude-tag-is-learning-your-company-one-slack-message-at-a-time/)
- [Fortune — Claude Tag](https://fortune.com/2026/06/23/anthropic-claude-tag-virtual-employee-tool-slack/)
