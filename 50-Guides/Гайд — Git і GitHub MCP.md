---
tags: [гайд, mcp, git]
оновлено: 2026-06-11
---

# Гайд — Git і GitHub MCP

## Git у цьому сховищі (вже налаштовано)

- Репозиторій: https://github.com/mzalisko/meweek, гілка `main`, remote `origin`.
- Коміти українською: `розділ: що зроблено`. Push — вручну або на прохання.
- Звичайний git агентам доступний без MCP — Claude Code / Codex виконують `git add/commit/diff/log` напряму в терміналі.

## Коли потрібен GitHub MCP

Коли агент має працювати з **GitHub-боку**: issues, pull requests, рев'ю, releases, CI. Для локальних комітів MCP не потрібен.

## Підключення GitHub MCP до Claude Code — можна доручити агенту

Потрібен GitHub PAT (Settings → Developer settings → Fine-grained tokens, доступ до meweek).

```bash
claude mcp add -s user --transport http github https://api.githubcopilot.com/mcp -H "Authorization: Bearer <ТВІЙ_PAT>"
```

Перевірка:

```bash
claude mcp list      # github має бути в списку
claude mcp get github
```

**Формулювання для агента:** «Підключи GitHub MCP командою claude mcp add, токен дам у термінал сам» (токен у чат не вставляти — це секрет!).

## Cowork (десктоп)

Settings → Connectors → GitHub — підключається через OAuth, без PAT. У цій сесії GitHub-конектор уже доступний через плагін engineering.

## Безпека

PAT — це секрет: не зберігати у нотатках, комітах чи `.claude/`. Лише в конфігу MCP (поза репозиторієм) або змінних середовища. Див. правила в `AGENTS.md`.

Джерела: [github-mcp-server — install Claude](https://github.com/github/github-mcp-server/blob/main/docs/installation-guides/install-claude.md), [Claude Code MCP docs](https://code.claude.com/docs/en/mcp)
