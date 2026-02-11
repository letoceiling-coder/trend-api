## Gallery / floorplan viewer — network artifacts

Страница: `https://spb.trendagent.ru/object/villa-marina/flat/65c9f8c023bccf8025bfdbc2`

### JS / CSS бандлы, связанные с flatpage

- **JS‑приложение страницы квартиры**
  - `https://modules.trendagent.ru/apps/flatpage/12944/flatpage.main.js`
  - Именно этот бандл отвечает за рендер всего блока `#flatpage`, включая контейнер `floorplan__container` с Leaflet‑viewer планировки.

- **CSS‑стили flatpage**
  - `https://modules.trendagent.ru/apps/flatpage/12944/css/main.css`
  - Содержит стили для классов `floorplan__container`, `floorplan__content`, `floorplan__map`, `gallery-nav__menu` и др.

- **Общие зависимости single‑spa / SystemJS**
  - `https://spb.trendagent.ru/scripts/common-deps.js?v=1770725857172`
  - `https://modules.trendagent.ru/npm/system.min.js?v=1770725857172`
  - `https://modules.trendagent.ru/npm/single-spa.5.9.3.min.js`
  - Через эти бандлы подмешиваются общие зависимости, включая глобальный объект `L` (Leaflet).

### Дополнительные ресурсы

- **Иконки управления viewer’ом**
  - `https://modules.trendagent.ru/icons/svg/Maximize_20.svg` — кнопка «увеличить» в управлении просмотром.
  - `https://modules.trendagent.ru/icons/svg/ChevronLeft_20.svg` / `ChevronRight_20.svg` — стрелки переключения, используемые в навигации по слайдам/кадрам.

### Итог по Network

- Viewer/галерея планировки создаётся внутри SPA‑модуля **`flatpage.main.js`** (инициатор для DOM‑дерева с `floorplan__container`).
- Сам Leaflet не грузится отдельным `leaflet.js` файлом с CDN — он поставляется как часть общих бандлов (`common-deps.js` / npm‑пакеты под `modules.trendagent.ru`), но в рантайме проявляется как глобальный объект `window.L` c версией `1.9.3`.

