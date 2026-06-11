---
tags: [гайд, навички]
оновлено: 2026-06-11
---

# Гайд — Навички з репозиторію Anthropic

Офіційний репозиторій: https://github.com/anthropics/skills — готові навички від Anthropic. Документація: https://platform.claude.com/docs/en/agents-and-tools/agent-skills/overview

## Що варто підключити для Meweek

| Навичка                       | Навіщо нам                                               |
| ----------------------------- | -------------------------------------------------------- |
| `docx`, `pdf`, `pptx`, `xlsx` | Документи (у Cowork уже є)                               |
| `skill-creator`               | Створювати власні навички (вже є)                        |
| `mcp-builder`                 | Знадобиться для DBManager, якщо робитимемо власний MCP-сервер |
| `webapp-testing`              | Тестування веб-застосунків (DBManager адмінка, WP-плагін)     |
| `artifacts-builder`           | Складні HTML-інтерфейси                                  |

## Як підключити

### Cowork (десктоп)

Settings → Capabilities → увімкнути потрібні навички. Вручну, агент не може.

### Claude Code (термінал) — можна доручити агенту

```bash
# особисті навички (для всіх проєктів)
git clone https://github.com/anthropics/skills /tmp/anthropic-skills
cp -r /tmp/anthropic-skills/skills/<назва> ~/.claude/skills/<назва>

# або навички проєкту (тільки Meweek)
cp -r /tmp/anthropic-skills/skills/<назва> C:\Dev\Meweek\.claude\skills\<назва>
```

Перевірка: у сесії Claude Code набрати `/skills` або спитати «які навички доступні».

**Формулювання для агента:** «Склонуй anthropics/skills і встанови навичку <назва> у .claude/skills цього проєкту».

## Власні навички середовища (вже створені)

`new-project`, `save-context`, `weekly-review` — у `.claude/skills/`. Опис: [[30-Agents/Конектори і навички]].

## Правила якості (з практик Anthropic)

1. SKILL.md короткий (< 300 рядків), деталі — в окремих файлах поруч (progressive disclosure).
2. Description «пушна»: що робить + конкретні фрази-тригери.
3. Правило + чому, а не КАПС.
