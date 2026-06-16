# Пропозиція навички: github-agentic-workflows

## Назва навички
`github-agentic-workflows`

## Мета
Налаштувати GitHub Agentic Workflows для автоматизації reasoning-based задач у репозиторії meweek: тріаж issues, аналіз CI-помилок, оновлення документації — через Claude всередині GitHub Actions без ручного запуску.

## Вхідні дані
- Назва репозиторію (за замовчуванням: `mzalisko/meweek`)
- Тип задачі: `issue-triage` / `ci-analysis` / `doc-update`
- Опціонально: модель (за замовчуванням Sonnet для економії)

## Вихідні дані
- Markdown-файл конфігурації агента у `.github/agents/<task>.md`
- Зміна у `.github/workflows/` якщо потрібен тригер
- Звіт: які задачі тепер автоматизовано і як їх моніторити

## Приклад воркфлоу

```
/github-agentic-workflows issue-triage
→ Читає існуючі issues у meweek
→ Створює .github/agents/issue-triage.md з правилами класифікації
→ Налаштовує тригер на нові issues
→ Claude автоматично додає мітки і короткий аналіз
```

## Ключові факти (станом на 15 червня 2026)
- GitHub Agentic Workflows у публічному превью з 11 червня 2026
- Більше не потребує PAT — використовує вбудований `GITHUB_TOKEN`
- Конфігурація у природній мові Markdown → компілюється в Actions YAML
- Підтримує: Claude (Anthropic), GitHub Copilot, Gemini, OpenAI Codex

## Оцінка цінності
- **Висока**: автоматизація рутинних GitHub задач без ручного тригера
- Особливо корисно для: тріажу PR від рутин `claude/*`, аналізу failed CI

## Складність реалізації
- **Низька-Середня**: потрібен доступ до GitHub Settings та дозволи на `GITHUB_TOKEN`
- Обмеження: тільки для репозиторіїв із підключеним GitHub Actions

## Джерела
- [GitHub Agentic Workflows Public Preview](https://github.blog/changelog/2026-06-11-github-agentic-workflows-is-now-in-public-preview/)
- [Agentic Workflows no longer need PAT](https://github.blog/changelog/2026-06-11-agentic-workflows-no-longer-need-a-personal-access-token/)
- [GitHub Agentic Workflows Overview (The New Stack)](https://thenewstack.io/github-agentic-workflows-overview/)
- [[AI Дайджест 2026-06-16]]
