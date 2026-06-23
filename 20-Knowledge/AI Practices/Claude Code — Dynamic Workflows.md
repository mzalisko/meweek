# Claude Code — Dynamic Workflows (Ultracode)

## Що це

Dynamic Workflows — вбудована можливість Claude Code (GA з червня 2026) для автоматичної паралельної оркестрації субагентів. Тригер: ключове слово `ultracode` у промпті або явний запит на workflow.

## Чому важливо

Замість лінійного виконання задачі Claude:
1. Планує задачу динамічно
2. Розбиває на паралельні підзадачі
3. Запускає субагенти одночасно
4. Верифікує результати перед зведенням

Результат: складні, тривалі задачі виконуються швидше з вищою якістю.

## Приклад

```
# Звичайний запит
/weekly-review

# З ultracode — Claude сам вирішує, коли розпаралелити
ultracode /weekly-review

# Або явно
Зроби ultracode-аналіз всіх проєктів Meweek і підготуй зведений звіт
```

## Як використати в Meweek

| Задача | Підхід |
|---|---|
| `/weekly-review` | `ultracode` — паралельний огляд розділів |
| Великий рефакторинг коду | `ultracode` — субагенти по файлах |
| Daily digest | Лінійний (не потрібен ultracode) |
| Аналіз кількох проєктів одночасно | `ultracode` |

## Застереження

- Витрати токенів суттєво вищі, ніж у звичайному режимі
- Починати з невеликих, добре обмежених задач
- Не використовувати для простих однокрокових запитів

## Джерела

- [Dynamic Workflows Announcement](https://claude.com/blog/introducing-dynamic-workflows-in-claude-code)
- [Офіційна документація](https://code.claude.com/docs/en/workflows)
- [InfoQ огляд](https://www.infoq.com/news/2026/06/dynamic-workflows-claude-code/)
- [[30-Agents/AI News Digest/Daily/2026-06-23]]
