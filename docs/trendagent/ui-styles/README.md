## UI styles snapshot (TrendAgent SPB)

### Основные CSS‑файлы

- **Core / layout / base**
  - `spb.trendagent.ru/styles.css` → `css/spb.trendagent.ru__styles.css`  
    Базовый reset, типографика, общие правила для `#apps`, `#error`, layout `page-layout` и др.
- **Модульные CSS (SPA‑приложения)**
  - `modules.trendagent.ru/apps/navbar/12950/css/main.css` — шапка/навигация.
  - `modules.trendagent.ru/apps/footer/27/css/main.css` — футер.
  - `modules.trendagent.ru/apps/policies/12957/css/main.css` — баннер cookie/policies.
  - `modules.trendagent.ru/apps/notifications/12921/css/main.css` — центр уведомлений.
  - `modules.trendagent.ru/apps/chats/147/css/main.css` — плавающий чат.
  - `modules.trendagent.ru/apps/nps/12942/css/main.css` — NPS/feedback.
  - `modules.trendagent.ru/apps/notfound/27/css/main.css` — общая 404/ошибка.
  - `modules.trendagent.ru/apps/flatpage/12944/css/main.css` — детальная страница квартиры (`flat-page__*`, `apartment-*`, `floorplan__*`, `gallery-nav__*` и др.).
  - Для list/table/map/plans/object/checkerboard — аналогичные `apps/*/css/main.css` (search/list/table/map/objectpage/checkerboard), зафиксированы по именам в `css-manifest.md`.

Часть файлов сохранена фрагментами (partial extract) с пометкой об этом в `css-manifest.md`, чтобы не тянуть целиком большие бандлы.

### Шрифты (font-family)

- Глобальный стек (из `styles.css`):
  - `BlinkMacSystemFont, -apple-system, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif`
- Для onboarding/части UI используются кастомные семейства уровня приложения (`"Circe-Regular", Arial, sans-serif`) — задаются локально в модулях.
- Явные HTTP‑запросы к `.woff/.woff2` шрифтам в автоматический Network‑дамп не попали; это отражено в `fonts-manifest.md`.

### Tokens (`tokens.css`)

- На текущем срезе в глобальном `styles.css` не найдено CSS‑переменных вида `--color-*`, `--radius-*`, `--shadow-*` в первых 40k символов.
- `tokens.css` сейчас содержит только комментарий‑фиксацию этого факта; при обнаружении переменных в модульных CSS их можно доснять и добавить сюда как:

```css
:root {
  --ta-color-primary: ...;
  --ta-radius-md: ...;
  --ta-shadow-card: ...;
}
```

### Как воспроизводить в Tailwind (уровень токенов)

- **Цвета и типографика**
  - Взять фактические значения `color`, `font-size`, `line-height` из `styles.css` и модульных CSS и описать их как `theme.colors.*` и `theme.fontSize.*` в `tailwind.config.js`.
  - Пример: `#191919` → `colors.neutral[900]`, `22px/27px` → кастомный размер заголовка `h1`.
- **Радиусы и тени**
  - Радиусы (`border-radius: 10px;`, `..._radius-md` и т.п.) и box‑shadow из модульных стилей вынести в `theme.borderRadius` и `theme.boxShadow`.
- **Layout**
  - Ширины контейнеров (`max-width: 1180px`, `min-width: 1200px` и т.п.) вынести в `theme.screens`/`theme.maxWidth`.

Важно: Tailwind‑конфиг должен опираться **на зафиксированные значения из CSS‑снимков**, а не на “типичные” дизайн‑системы. Любые новые токены нужно сначала найти в реальных CSS‑бандлах, затем добавить в `tokens.css` и уже после — в Tailwind.

