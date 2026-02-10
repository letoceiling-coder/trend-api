## STATUS — фиксация текущего состояния ревёрс‑инжиниринга TrendAgent

Этот файл фиксирует, что уже собрано, что подтверждено логами, чего не обнаружено и какие архитектурные решения приняты осознанно.

---

## 1. Что собрано

- **UI‑структура всех ключевых страниц TrendAgent (SPB)**:
  - list / table / map / plans / checkerboard / detail,
  - различия между страницами и их унифицированные шаблоны.
- **Карта страниц и типов сущностей**:
  - для объектов, квартир, парковок, домов, посёлков, проектов домов, коммерции.
- **Схема авторизации и токенов**:
  - работа SSO, роли `auth_token` и `refresh_token`,
  - endpoint `GET /v1/auth_token`.
- **Полный каталог API‑доменов и ключевых эндпоинтов**, включая:
  - ядро (`api.trendagent.ru`),
  - apartment‑api, parkings‑api, commerce‑api, rewards‑api,
  - файлы, видео, контакты, ипотека, 3D‑туры, вебинары.
- **Сопоставление UI‑фильтров и справочников**:
  - какие filters/directories где используются,
  - что зависит от города (city‑scoped),
  - подтверждение server‑side фильтрации.
- **Доменные сущности и связи**:
  - Block, Apartment, CommercePremise, ParkingBlock/Place, Village, Plot,
  - текстовая ER‑диаграмма и описание корневых агрегатов.
- **Проектная архитектура**:
  - слои системы (External API, Domain, Cache, Storage, Frontend),
  - модель взаимодействия Laravel ↔ TrendAgent,
  - причины, почему backend не должен управлять токенами.

Все эти данные зафиксированы в:

- `docs/trendagent/01-site-structure.md`
- `docs/trendagent/02-auth-and-token.md`
- `docs/trendagent/03-api-catalog.md`
- `docs/trendagent/04-filters-and-directories.md`
- `docs/trendagent/05-domain-model.md`
- `docs/trendagent/network/core-api.md`
- `docs/trendagent/network/apartment-api.md`
- `docs/trendagent/network/parkings-api.md`
- `docs/trendagent/network/commerce-api.md`
- `docs/trendagent/network/sso-api.md`
- `docs/trendagent/network/pages.md`
- `docs/trendagent/schemas/entities.ts`
- `docs/trendagent/schemas/directories.ts`
- `docs/architecture.md`
- `README.md`

**Команды авторизации и проверки сессии** (login, save-refresh, status, **check** — проверка работоспособности по реальному запросу к API): см. **`docs/trendagent/STATUS.md`**.

---

## 2. Что подтверждено логами

Под «подтверждено логами» понимается наличие реальных HTTP‑запросов в Network‑трейсах.

- **Авторизация и токены**:
  - `POST https://sso-api.trend.tech/v1/login?app_id=...&lang=ru` — логин по телефону/паролю.
  - `GET https://sso-api.trend.tech/v1/oauth?...` и редиректы на `spb.trendagent.ru/oauth?code=...`.
  - `GET https://sso-api.trend.tech/v1/auth_token/?city=...&lang=ru` — обновление `auth_token`.
  - Наличие `refresh_token` в cookie и `auth_token` в query‑параметрах запросов.

- **Ядро объектов и квартир**:
  - `https://api.trendagent.ru/v4_29/blocks/search`, `/blocks/search/count`, `/blocks/search/id`.
  - `https://api.trendagent.ru/v4_29/apartments/search`.
  - `https://api.trendagent.ru/v4_29/blocks/{id}/unified`, `/advantages`, `/nearby_places`, `/bank`, `/apartments/min-price`, `/geo/buildings`.
  - `https://api.trendagent.ru/v4_29/unit_measurements`, `directories/finishing`, `directories/rooms`.

- **Справочники и фильтры**:
  - `https://apartment-api.trendagent.ru/v1/directories?types=...&city=...&lang=ru`.
  - `https://parkings-api.trendagent.ru/enums/*`, `/directories/*`.
  - `https://commerce-api.trendagent.ru/filters?name=...&city=...&lang=ru`.

- **Поиски по доменам**:
  - `https://parkings-api.trendagent.ru/search/blocks?...`
  - `https://commerce-api.trendagent.ru/search/{block_id}/premises?...`
  - `https://commerce-api.trendagent.ru/search/map/buildings?...`

- **Дополнительные сервисы**:
  - `https://rewards-api.trendagent.ru/*` — вознаграждения по объекту/коммерции.
  - `https://files.trendagent.ru/fs/list/block/{id}` — файлы.
  - `https://video.trendagent.ru/videos/block/{id}` и `/categories`.
  - `https://contacts-api.trendagent.ru/contacts/blocks/{id}` — контакты.
  - `https://mortgage-api.trendagent.ru/types`.
  - `https://3d-tour-api.trendagent.ru/v1/blocks/{id}`.
  - `https://webinars-api.trendagent.ru/v1/events`, `/webinar_types`.

---

## 3. Что НЕ обнаружено

В Network‑логах **не было обнаружено** (на момент анализа):

- отдельных доменов:
  - `villages-api.trendagent.ru`
  - `houses-api.trendagent.ru`
  - `houseprojects-api.trendagent.ru`

при этом:

- функциональность домов/посёлков/проектов реализована через:
  - обобщённый слой `api.trendagent.ru/v4_29/blocks/*`,
  - общие справочники `apartment-api.trendagent.ru/v1/directories`,
  - дополнительные фильтры по типам блоков/земли/дома.

**Фиксация:**  
Если в дальнейшем будут обнаружены дополнительные специализированные домены, их необходимо явно добавить в `docs/trendagent/03-api-catalog.md`.

---

## 4. Осознанно принятые решения

- **Источник правды — API, а не HTML**:
  - все данные (объекты, квартиры, парковки, коммерция, участки, вознаграждения) берутся из JSON‑API;
  - HTML‑страницы лишь рендерят данные и не являются источником данных для интеграции.

- **Разделение ответственности между фронтом и бекендом**:
  - фронтенд (Vue) полностью отвечает за:
    - хранение `auth_token` в памяти;
    - refresh‑алгоритм через `sso-api.trend.tech/v1/auth_token`;
    - повтор запросов после обновления токена;
  - бекенд (Laravel) не:
    - хранит `refresh_token`,
    - не вызывает `v1/auth_token` самостоятельно,
    - не логинится к SSO.

- **Город как обязательный параметр**:
  - все справочники и поиски зависят от `city_id`;
  - домен (`spb.trendagent.ru`) не используется как единственный источник информации о городе;
  - `city_id` должен храниться и передаваться явно.

- **Фильтрация — только server‑side**:
  - никакой локальной пост‑фильтрации «в лоб»;
  - UI‑фильтры всегда отражаются на query‑параметрах `search`‑запросов.

- **Документация — артефакт, а не побочный продукт**:
  - все выводы и схемы закреплены в markdown‑файлах;
  - любые будущие изменения в интеграции должны сопровождаться обновлением этих файлов.

---

## 5. Статус фиксации артефактов

- ✅ **Маршруты и типы UI**:
  - зафиксированы в `01-site-structure.md` и `docs/trendagent/network/pages.md`.
- ✅ **Основные API‑домены и ключевые эндпоинты**:
  - зафиксированы в `03-api-catalog.md` и файлах `docs/trendagent/network/*.md`.
- ✅ **Сущности и связи (Domain Model)**:
  - зафиксированы в `05-domain-model.md` и `docs/trendagent/schemas/entities.ts`.
- ✅ **Справочники и фильтры**:
  - зафиксированы в `04-filters-and-directories.md` и `docs/trendagent/schemas/directories.ts`.
- ✅ **Auth / SSO**:
  - зафиксированы в `02-auth-and-token.md` и `docs/trendagent/network/sso-api.md`.
- ✅ **HTML‑снимки UI‑страниц (ui-snapshots закрыт)**:
  - каталог `docs/trendagent/ui-snapshots/` заполнен реальными DOM‑снимками базовых UI‑шаблонов (после загрузки данных в браузере).
  - Для каждого шаблона: `<name>.html`, `<name>.selectors.json`, `<name>.meta.json` (UI contract, dataSources из network/pages.md и core-api).
  - Созданные файлы:
    - **base-list-complex** — `/objects/list` (карточки блоков + квартир).
    - **base-list-entity** — `/villages/list` (entity-карточки).
    - **base-table** — `/objects/table` (таблица, пагинация, фильтры, переключатель вида).
    - **base-map** — `/objects/map` (sidebar, карта, тулбар, пин).
    - **base-plans-grid** — `/objects/plans` (плитки планировок, CTA).
    - **checkerboard** — `/object/villa-marina/checkerboard` (сетка, секции, этажи, ячейки квартир, фильтры шахматки).
    - **detail-object-tabs** — `/object/villa-marina` (галерея, CTA, вкладки, контент активной вкладки).
    - **detail-two-column** — `/object/{slug}/flat/{id}` (двухколоночный layout): снимок обновлён с валидной страницы `/object/villa-marina/flat/65c9f8c023bccf8025bfdbc2` (левая/правая колонки, viewer планировки, цена/CTA, атрибуты, блок об объекте/ЖК).
  - DOM снят только из реального браузера; генерация «по памяти» не использовалась.
- ✅ **UI‑стили и дизайн‑токены (ui-styles закрыт, с фиксацией ограничений FULL_SAVED)**:
  - `docs/trendagent/ui-styles/css-manifest.md` — манифест основных CSS‑ресурсов (core `styles.css`, модульные `apps/*/css/main.css` и чанки `css/*.css`), с пометкой `FULL_SAVED = true/false` и `SOURCE_METHOD` для каждого URL. Полностью сохранены:
    - `spb.trendagent.ru/styles.css`,
    - модульные CSS: `apps/notfound/27/css/main.css`, `apps/chats/147/css/main.css`, `apps/notifications/12921/css/main.css`, `apps/nps/12942/css/main.css`, `apps/policies/12957/css/main.css`, `apps/flatpage/12944/css/277.css`.
    Для `apps/navbar/12950/css/main.css`, `apps/footer/27/css/main.css`, `apps/flatpage/12944/css/main.css` зафиксированы ограничения: тело ответа доступно только уже загруженному браузеру (curl/schannel из среды агента даёт TLS‑ошибку), поэтому в манифесте помечены как `FULL_SAVED = false` с `SOURCE_METHOD = DevTools Network (URL only)`.
  - `docs/trendagent/ui-styles/css/` — полные текстовые снимки всех доступных CSS (core + перечисленные модульные файлы).
  - `docs/trendagent/ui-styles/fonts-manifest.md` — манифест шрифтовых ресурсов по доступным Network‑логам и всему набору сохранённых CSS: явных `@font-face` и отдельных `.woff/.woff2` запросов не обнаружено; UI опирается на системный стек шрифтов (`BlinkMacSystemFont, -apple-system, "Segoe UI", Roboto, ...`) и, точечно, `"Circe-Regular", Arial, sans-serif`.
  - `docs/trendagent/ui-styles/tokens.css` — после повторной проверки всех сохранённых CSS custom properties вида `--*` (CSS‑переменные) не используются; встречаются только BEM‑модификаторы `.page--lock` и т.п.
- ❌ **Полные JSON‑ответы detail‑эндпоинтов**:
  - для отдельных unit‑detail (квартира, участок, парковочное место, houseproject) зафиксированы только формы запросов и сущностей, без полного JSON‑дампа;
  - требуется отдельный Network‑захват для каждого detail‑вызова.

---

## 6. Флаг заморозки анализа

ANALYSIS_FROZEN = true

Все дальнейшие проектные решения должны опираться **только** на зафиксированные артефакты. Любые новые наблюдения по API/UI требуют:

1. Обновления соответствующих файлов в `docs/trendagent/` или поддиректориях `network/`, `schemas/`, `ui-snapshots/`.
2. Явного отражения изменений в этом `STATUS.md` (включая пересмотр статуса ✅/❌ для артефактов).


