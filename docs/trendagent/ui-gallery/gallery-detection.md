## Детекция библиотеки viewer’а на странице квартиры

Страница: `https://spb.trendagent.ru/object/villa-marina/flat/65c9f8c023bccf8025bfdbc2`

### 1. DOM (Elements)

- Контейнер viewer’а планировки:
  - Корневой блок: `<div class="floorplan__container">…</div>`
  - Внутри: `<div class="floorplan__content floorplan__content_fetching">`
  - Основной viewport: `<div class="floorplan__map leaflet-container leaflet-touch leaflet-fade-anim …"></div>`
  - Наличие множества классов `leaflet-*`: `leaflet-container`, `leaflet-touch`, `leaflet-fade-anim`, `leaflet-touch-zoom`, `leaflet-control-container`, `leaflet-control-zoom-in`, `leaflet-control-rotate`, и т.п.
- Навигация по превью/кадрам:
  - Контейнер: `<div class="gallery-nav__menu" role="menu" aria-label="Gallery navigation">…</div>`
  - Элементы: `<div role="menuitem" class="gallery-nav__item" data-index="0">…</div>` с вложенными `img`.

**Вывод из DOM:** наличие стандартных классов `leaflet-container`, `leaflet-control-*`, `leaflet-pane` и глобальной структуры control‑панели однозначно указывает на использование **Leaflet** как основы для viewer’а планировки.

### 2. Глобальный объект и версия (Sources → runtime)

Через `window` в рантайме:

- `typeof window.L !== 'undefined'` → `true`
- `window.L.version` → `"1.9.3"`
- `Object.keys(window.L)` возвращает стандартные сущности Leaflet:
  - `Bounds`, `Browser`, `CRS`, `Map`, `Marker`, `ImageOverlay`, `LatLng`, `LatLngBounds`, `Layer`, `LayerGroup`, `Control`, `DomEvent`, и т.п.

**Вывод:** на странице подключён именно **Leaflet 1.9.3** (классический Leaflet API).

### 3. Network / бандлы (инициатор)

- SPA‑бандл страницы квартиры:
  - `https://modules.trendagent.ru/apps/flatpage/12944/flatpage.main.js` — модуль приложения `flatpage`, инициализирующий DOM блока `#flatpage`, включая `floorplan__container` и `floorplan__map`.
- Общее ядро и зависимости:
  - `https://spb.trendagent.ru/scripts/common-deps.js?v=1770725857172`
  - `https://modules.trendagent.ru/npm/system.min.js?v=1770725857172`
  - `https://modules.trendagent.ru/npm/single-spa.5.9.3.min.js`
  - Через эти бандлы подтягивается глобальный объект `L` с версией `1.9.3` и создаётся Leaflet‑карта в контейнере `.floorplan__map`.

**Инициатор viewer’а:** модуль `flatpage.main.js` (через SystemJS/single‑spa), который внутри вызывает `L.map(...)` / `L.ImageOverlay(...)` на DOM‑узле `.floorplan__map`.

### 4. Отсутствие других галерейных библиотек

- По DOM не обнаружены характерные классы/атрибуты:
  - нет `swiper`, `swiper-container`, `swiper-slide`;
  - нет `pswp`, `pswp__*` (PhotoSwipe);
  - нет `fancybox`, `data-fancybox`, `lg-*` (lightGallery), `splide`, `embla`, `glightbox`, и т.п.
- В списке загруженных JS/CSS‑ресурсов (см. `gallery-network.md`) нет файлов с именами `swiper.js`, `photoswipe.js`, `lightgallery.js`, `fancybox.css` и т.д.

**Вывод:** для viewer’а планировок/изображений на детальной странице квартиры сторонние lightbox/slider‑библиотеки (Swiper, PhotoSwipe, lightGallery, Fancybox и др.) не используются — всё построено поверх **Leaflet 1.9.3**.

### 5. Как используется Leaflet в UI

- **Контейнер карты/viewer’а:**
  - Корневой блок: `.floorplan__container`
  - Viewport: `.floorplan__map.leaflet-container`
- **Управление:**
  - Zoom‑кнопки: `.leaflet-control-zoom-in`, `.leaflet-control-zoom-out`
  - Плагин поворота: `.leaflet-control-rotate` / `.leaflet-control-rotate-toggle` (надстройка над Leaflet для поворота изображения планировки).
- **Навигация по слайдам/планам:**
  - Список превью: `.gallery-nav__menu` (role=`menu`, `aria-label="Gallery navigation"`)
  - Элементы: `.gallery-nav__item[data-index]` с вложенными `img` (кадры/ракурсы планировки или фото).
- **Режимы отображения:**
  - Переключатели «Поэтажный план» / «Отделка»: `.apartment-photogallery__slider_btn-floorplan`, `.apartment-photogallery__slider_btn` (текущий — с модификатором `current`).

В сумме viewer планировок — это Leaflet‑карта (версия **1.9.3**) с image overlay и дополнительным rotate‑контролом, обёрнутая в UI‑обвязку `floorplan__*` и `gallery-nav__*` внутри SPA‑модуля `flatpage`.

