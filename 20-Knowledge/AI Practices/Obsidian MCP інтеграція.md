# Obsidian MCP інтеграція

## Що це

Два способи дати AI-агентам прямий доступ до Obsidian vault через MCP (Model Context Protocol):

1. **Obsidian CLI** (лютий 2026) — зовнішній CLI-інструмент, що читає/пише vault ззовні Obsidian.
2. **aaronsb/obsidian-mcp-plugin** — плагін всередині Obsidian, запускає MCP-сервер по HTTP.

## Чому це важливо

Зараз агенти в Meweek читають файли через `Read/Write` інструменти напряму. MCP-інтеграція дає:
- Traversal wikilink-графу (навігація за `[[посиланнями]]`)
- Dataview запити для агрегації даних по нотатках
- Obsidian Bases (структуровані дані)
- Не потрібно знати точний шлях до файлу

## Приклад

Агент може запитати: "Покажи всі нотатки, пов'язані з [[DBManager]], які оновлені за останній тиждень" — і отримати відповідь через граф, а не шукати файли вручну.

## Як використати в Meweek

1. Встановити `aaronsb/obsidian-mcp-plugin` у vault (через Community Plugins).
2. Додати MCP-сервер у `.claude/settings.json`:
   ```json
   {
     "mcpServers": {
       "obsidian": {
         "url": "http://localhost:PORT/mcp"
       }
     }
   }
   ```
3. Агенти отримують інструменти: `read_note`, `write_note`, `search_vault`, `get_backlinks`, `run_dataview`.

## Статус

- obsidian-mcp-plugin: активний, останні коміти березень 2026.
- Потрібно перевірити сумісність з поточною версією Obsidian та тестувати в sandbox.

## Оновлення 30 червня 2026

- **jacksteamdev/obsidian-mcp-tools** (~87k встановлень, #1 MCP-плагін для Obsidian) — автор відходить від проєкту. Перед вибором плагіна варто дочекатись форку/наступника.
- **MCPVault / mcp-obsidian.org** — альтернатива з soft-delete нотаток та AST-aware YAML frontmatter.
- **MCP Spec RC** (фінал 28.07.2026): протокол переходить на stateless (прибрано initialize/session-handshake), Roots/Sampling/Logging — deprecated. Будь-який обраний Obsidian MCP-плагін варто перевіряти на сумісність із цією специфікацією, а не з поточною stateful-версією.

## Джерела

- [aaronsb/obsidian-mcp-plugin](https://github.com/aaronsb/obsidian-mcp-plugin)
- [Obsidian + AI: From Simple Plugin to Full Agent Integration](https://3sztof.github.io/posts/obsidian-smart-connections-mcp/)
- [jacksteamdev/obsidian-mcp-tools](https://github.com/jacksteamdev/obsidian-mcp-tools)
- [MCPVault](https://mcp-obsidian.org/)
- [MCP Blog — 2026-07-28 Release Candidate](https://blog.modelcontextprotocol.io/posts/2026-07-28-release-candidate/)
- [[AI Дайджест 2026-06-12]]
- [[AI Дайджест 2026-07-01]]
