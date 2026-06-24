# Loop Engineering — дизайн агентних циклів

## Що це

Loop Engineering — практика проєктування **системи**, яка автономно промптує AI-агента, а не ручного написання кожного промпту. Людина пише один раз: умову запуску, критерій завершення, спосіб передачі результату. Все між — повторювані тики циклу, якими керує код/конфіг.

Термін сформулювали **Борис Черні** (Anthropic, голова Claude Code) і **Addy Osmani** (Google Chrome) у червні 2026.

## Чому важливо

До 2026 більшість AI-роботи = людина пише промпт → читає → виправляє → пише знову. Це нескалюється. Черні показав: якщо правильно визначити цикл, Claude Code генерує 100% PR без ручного втручання між тиками.

**Ефект**: Wall-clock time → паралельний. Увага людини → лише верифікація фінального результату.

## Ключові елементи циклу

```
визначити мету → [prompt агента] → [читати вивід] → [перевірити критерій] → 
   якщо ні: prompt знову (з контекстом/помилкою)
   якщо так: зупинитись, передати результат
```

Людина знаходиться тільки на вході (мета) і виході (review результату).

## Приклад

```bash
# Проста реалізація через Claude Code /loop
/loop 5m /weekly-review
```

Або власний скрипт:
```python
while not is_done(output):
    output = claude.run(task, context=output)
```

## Як використовувати в Meweek

- **Рутини** (daily-digest, weekly-review, fitness): вже є часткова реалізація через Claude Code SDK — перетворити на справжні loops з критерієм завершення.
- **Code review**: loop завершується, коли всі коментарі адресовані + тести зелені.
- **навичка `loop-verifier`**: приймає задачу + критерій → запускає цикл → повертає "done / retry N".

## Підводні камені

- Нескінченний цикл без exit condition → витрати токенів без обмеження.
- Занадто складний критерій → агент ніколи не "завершує".
- Відсутність контексту між тиками → агент повторює помилки.

**Рекомендація**: завжди вказувати `max_iterations` і `exit_on_error`.

## Джерела

- [The New Stack — Loop Engineering](https://thenewstack.io/loop-engineering/)
- [explainx.ai — Loop Engineering 2026 Guide](https://explainx.ai/blog/loop-engineering-coding-agents-claude-code-guide-2026)
- [Medium — From Prompts to Loops](https://medium.com/@KilgortTrout/from-prompts-to-loops-a-practical-guide-to-building-agentic-workflows-in-codex-and-claude-0b57234452ed)
- Пов'язана нотатка: [[Harness Engineering — агентні цикли]]
