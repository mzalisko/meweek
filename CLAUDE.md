# CLAUDE.md — роутер середовища Meweek

Цей файл тримає вказівники, а не знання: «потрібно X → дивись там».

## Правила

Спільні правила всіх агентів — @AGENTS.md (мова, структура, git, безпека, контекст).

## Куди дивитись

| Потрібно | Дивись |
|---|---|
| Загальна навігація | `Старт.md` |
| Список проєктів | `10-Projects/Проєкти.md` |
| Стан конкретного проєкту | `10-Projects/<Назва>/<Назва> — Контекст.md` (читати першим) |
| Створити проєкт | навичка `.claude/skills/new-project/SKILL.md` або команда `/new-project` |
| Зберегти контекст перед /clear | навичка `.claude/skills/save-context/SKILL.md` або `/save-context` |
| Розбір сховища | навичка `.claude/skills/weekly-review/SKILL.md` або `/weekly-review` |
| Налаштувати навички / MCP / superpowers | `30-Agents/Гайди для агентів/Гайди для агентів.md` |
| Що вміє кожна навичка (простими словами) | `50-Guides/AI/Гайд — Навички Meweek.md` |
| Вибрати модель під задачу / витрати | навичка `.claude/skills/model-budget/SKILL.md` або `/model-budget` |
| Впровадити ідею навички / покращити скіл | навичка `.claude/skills/skill-forge/SKILL.md` або `/skill-forge` |
| Слеш-команди | `.claude/commands/` + `50-Guides/AI/Гайд — Команди Meweek.md` |
| Синхронізація стану (pull/push) | навичка `.claude/skills/git-sync/SKILL.md` або `/sync` |
| Docker під проєкт | навичка `.claude/skills/docker-project/SKILL.md` |
| Методологія середовища | `20-Knowledge/AI OS — практики Four Cs.md` |
| Профіль агента | `30-Agents/Claude.md` |

## Додатково для Claude

- Документи (docx/pdf/pptx/xlsx) — через відповідні навички (skills).
- Технічні рішення — як ADR (`_Templates/Шаблон ADR.md`) у папці проєкту.
- Читати точково: контекст проєкту, а не все сховище.
