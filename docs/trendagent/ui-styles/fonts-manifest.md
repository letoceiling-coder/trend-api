## Fonts manifest

Скомпилировано по:
- Network‑логам детальной страницы квартиры `/object/villa-marina/flat/65c9f8c023bccf8025bfdbc2`,
- анализу всех сохранённых CSS в `docs/trendagent/ui-styles/css/`:
  - `spb.trendagent.ru__styles.css`
  - `modules.trendagent.ru__apps__notfound__27__css__main.css`
  - `modules.trendagent.ru__apps__chats__147__css__main.css`
  - `modules.trendagent.ru__apps__notifications__12921__css__main.css`
  - `modules.trendagent.ru__apps__nps__12942__css__main.css`
  - `modules.trendagent.ru__apps__policies__12957__css__main.css`
  - `modules.trendagent.ru__apps__flatpage__12944__css__277.css`

### @font-face в сохранённых CSS

- Во всех перечисленных CSS‑файлах **отсутствуют** правила `@font-face` (поиск по строке `@font-face` дал 0 совпадений).

### Используемые font-family (по факту из CSS)

| Family stack | Источник | Notes |
|-------------|----------|-------|
| `BlinkMacSystemFont, -apple-system, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif` | `spb.trendagent.ru__styles.css` | Базовый стек шрифтов для тела страницы. |
| `"Circe-Regular", Arial, sans-serif` | `spb.trendagent.ru__styles.css` (блок `#onboarding`) | Используется для отдельных онбординговых экранов; при отсутствии локального `Circe-Regular` браузер падает на системные шрифты. |

### HTTP‑ресурсы шрифтов

- В автоматически сохранённом Network‑дампе **нет отдельных запросов** к файлам шрифтов (`.woff`, `.woff2`, `.ttf`, `.otf`).
- По состоянию на этот анализ:
  - **font URLs (как отдельные бинарные ресурсы) не зафиксированы**;
  - UI опирается на системный стек + возможные локально установленные шрифты (например, `Circe-Regular`).

Если в будущем будут обнаружены реальные шрифтовые HTTP‑ресурсы (через вкладку Network → Fonts или дополнительные CSS‑бандлы с `@font-face`), их необходимо добавить в таблицу выше с указанием `Font URL`, `format()` и источника.

