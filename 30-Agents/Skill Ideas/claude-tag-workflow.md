# Skill Idea: claude-tag-workflow

## Назва навички
`claude-tag-workflow`

## Призначення
Організація мультиагентної роботи в Slack-стилі (за моделлю Claude Tag): один Claude на задачу, проактивний follow-up незавершених тредів, розбивка на автономні етапи.

## Проблема
Зараз Meweek-рутини — лінійні скрипти: почали → виконали → закінчили. Але реальні задачі нелінійні: треба слідкувати за статусом, нагадувати про незавершене, делегувати підзадачі і зводити результат. Claude Tag від Anthropic реалізує саме цю модель.

## Inputs
- Задача або ціль (текст)
- Список підзадач або критерії розбивки
- Список учасників / агентів (опціонально)
- Дедлайн або умова завершення

## Outputs
- Структурований план виконання по етапах
- Статус-звіт для кожного етапу
- Follow-up нагадування про незавершені гілки
- Фінальний звіт із посиланнями на результати

## Приклад workflow
```
/claude-tag-workflow "Підготувати weekly-review за тиждень"
→ Агент 1: зібрати всі зміни з git log
→ Агент 2: проаналізувати незакриті задачі
→ Агент 3: сформувати звіт
→ Orchestrator: звести + follow-up якщо Агент 2 завис
```

## Де fits у Meweek
- weekly-review: замість одного лінійного скрипту → паралельні гілки з follow-up
- fitness: слідкування за незавершеними звітами тренувань
- daily-digest: якщо якесь джерело не відповіло — retry через follow-up

## Оцінка цінності
- **Impact**: High — нелінійна робота зараз не автоматизована
- **Difficulty**: Medium — потребує orchestrator + loop logic
- **Priority**: 🟡 Useful

## Джерела натхнення
- [Fortune: Claude Tag — virtual employee in Slack](https://fortune.com/2026/06/23/anthropic-claude-tag-virtual-employee-tool-slack/)
- [TechCrunch: Claude Tag learning your company](https://techcrunch.com/2026/06/23/anthropics-claude-tag-is-learning-your-company-one-slack-message-at-a-time/)
- Boris Cherny: "Моя робота — писати цикли, що промптять Claude"
