# Пропозиція навички: post-session-git-sync

## Назва навички
`post-session-git-sync` (або хук, а не навичка)

## Мета
Автоматично виконувати git commit + push після завершення кожної Claude Code сесії через новий `post-session` lifecycle hook (доданий у v2.1.169). Зараз `/sync` потрібно запускати вручну — це забуває кожен третій раз.

## Вхідні дані
- Жодних (хук запускається автоматично)
- Конфігурація: шаблон commit message (за замовчуванням: "сесія: автозбереження {{DATE}}")

## Вихідні дані
- git commit зі змінами сесії
- git push до origin
- Лог у файлі `.claude/logs/sync.log`

## Приклад воркфлоу

Self-hosted runner налаштування у `.claude/settings.json`:
```json
{
  "hooks": {
    "post-session": "cd /path/to/meweek && git add -A && git commit -m 'сесія: автозбереження $(date +%Y-%m-%d)' && git push origin HEAD"
  }
}
```

або через окремий скрипт `.claude/hooks/post-session.sh`.

## Оцінка цінності
- **Висока**: вирішує реальну проблему забутих комітів після сесій
- Meweek використовує git як спільну пам'ять між агентами — не-закомічені зміни невидимі для Codex/Gemini

## Складність реалізації
- **Низька**: `post-session` хук вже доступний з v2.1.169
- Потрібно лише налаштувати settings.json або написати shell скрипт

## Ризики
- Може закомітити небажані зміни, якщо сесія була тестовою
- Рішення: додати перевірку `git diff --stat` і комітити лише якщо є осмислені зміни

## Наступний крок
1. Перевірити документацію `post-session` хука в Claude Code
2. Написати скрипт з перевіркою наявності змін
3. Додати у `.claude/settings.json` (або `/update-config`)

## Джерела
- [Claude Code v2.1.169 Changelog](https://code.claude.com/docs/en/changelog)
- [[AI Дайджест 2026-06-12]]
- [[Claude Code — Рекурсивні субагенти]]
