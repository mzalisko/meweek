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

## Джерела

- [aaronsb/obsidian-mcp-plugin](https://github.com/aaronsb/obsidian-mcp-plugin)
- [Obsidian + AI: From Simple Plugin to Full Agent Integration](https://3sztof.github.io/posts/obsidian-smart-connections-mcp/)
- [[AI Дайджест 2026-06-12]]
