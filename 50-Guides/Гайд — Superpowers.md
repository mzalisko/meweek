---
tags: [гайд, навички, claude-code]
оновлено: 2026-06-11
---

# Гайд — Superpowers

[obra/superpowers](https://github.com/obra/superpowers) — методологія розробки для Claude Code: примусове планування перед кодом, тести перед реалізацією, самоперевірка перед «готово». З січня 2026 — в офіційному маркетплейсі плагінів Anthropic.

## Встановлення (Claude Code, термінал)

Виконується **слеш-командами всередині сесії** Claude Code — відкрий термінал у `C:\Dev\Meweek`, запусти `claude` і набери:

```
/plugin install superpowers@claude-plugins-official
```

Альтернатива (маркетплейс автора, свіжіші версії):

```
/plugin marketplace add obra/superpowers-marketplace
/plugin install superpowers@superpowers-marketplace
```

## Перевірка

```
/plugin          ← список встановлених плагінів
/superpowers     ← команди методології (якщо є)
```

Або спитати агента: «який workflow superpowers активний?»

## Коли використовувати

- Розробка NMDB (центр, DataBridge, WP-плагін) — brainstorm → plan → TDD-реалізація.
- Будь-який код, де ціна помилки висока (безпека NMDB!).

## Примітка

Слеш-команди `/plugin` виконуються лише людиною в інтерактивній сесії Claude Code. Агент може підготувати все інше (наприклад, перевірити встановлення, читати навички плагіна).
