## Каталог API TrendAgent

Этот документ фиксирует все обнаруженные API‑домены и ключевые эндпоинты, а также соответствие страницам и доменным сущностям.

---

## 1. Обзор доменов

- `https://api.trendagent.ru` — ядро объектов/квартир/домов и общих справочников.
- `https://apartment-api.trendagent.ru` — справочники и фильтры для квартир/объектов.
- `https://parkings-api.trendagent.ru` — парковки: блоки и машиноместа.
- `https://commerce-api.trendagent.ru` — коммерческая недвижимость.
- `https://rewards-api.trendagent.ru` — вознаграждения (комиссии).
- `https://files.trendagent.ru` — файлы/документы.
- `https://video.trendagent.ru` — видео по объектам.
- `https://contacts-api.trendagent.ru` — контакты (ответственные менеджеры по объектам).
- `https://mortgage-api.trendagent.ru` — ипотечные программы.
- `https://3d-tour-api.trendagent.ru` — 3D‑туры по объектам.
- `https://webinars-api.trendagent.ru` — мероприятия/вебинары.
- `https://chat.trendagent.ru`, `https://online.trendagent.ru` — чаты и онлайн‑сокеты.

Для всех доменов бизнес‑запросы включают:

- `auth_token={JWT}`
- `city={CITY_ID}`
- `lang=ru`

---

## 2. api.trendagent.ru

### 2.1. Базовый URL

`https://api.trendagent.ru/v4_29/`

### 2.2. Ключевые эндпоинты

- **Поиск блоков/объектов:**
  - `blocks/search/`  
    - Пример:  
      `/v4_29/blocks/search/?show_type=list&count=20&sort=price&sort_order=asc&city=...&lang=ru&auth_token=...`
  - `blocks/search/count/` — количество блоков для table‑view.
  - `blocks/search/id/` — поиск блока по `guid` (slug объекта).

- **Детали блока (ЖК/объект):**
  - `blocks/{block_id}/unified/?ch=false&formating=true&city=...` — основные данные блока.
  - `blocks/{block_id}/advantages/` — преимущества объекта.
  - `blocks/{block_id}/nearby_places/` — окрестности (школы, магазины и т.п.).
  - `blocks/{block_id}/bank/` — банковские программы по объекту.
  - `blocks/{block_id}/geo/buildings/` — геометрия зданий, используется для карт.
  - `blocks/{block_id}/apartments/min-price/?onrequest=true&reservation=true` — минимальные цены по квартирам.

- **Квартиры:**
  - `apartments/search/`  
    - Пример: `/v4_29/apartments/search/?sort=price&sort_order=asc&count=50&city=...&lang=ru&auth_token=...`

- **Справочники:**
  - `unit_measurements` — единицы измерения (м², сотки).
  - `directories/finishing` — варианты отделки.
  - `directories/rooms` — количество комнат.

- **Верхнеуровневые данные:**
  - `notices` — уведомления/баннеры.
  - `tariffs/` — тарифы и права пользователя.
  - `prelaunches`, `prelaunches/exists` — анонсы.
  - `exclusives` — эксклюзивные предложения.
  - `apartments/count/total/` — общее количество квартир.
  - `contacts/group/?group_code=agency_manager` — контакты ответственных.

### 2.3. Где используется

- `/objects/list`, `/objects/plans`, `/objects/map` — `blocks/search`.
- `/objects/table` — `apartments/search`, `blocks/search/count`.
- Все `object/{slug}`/`flat/{id}` — `blocks/{id}/unified/...`, `apartments/...`.
- `/houses/*`, `/villages/*`, частично `/commerce/*` — через те же `blocks` и справочники.

### 2.4. Главные сущности

- `Block` — ЖК/дом/посёлок/комплекс.
- `Apartment` — квартира, unit внутри блока.

---

## 3. apartment-api.trendagent.ru

### 3.1. Базовый URL

`https://apartment-api.trendagent.ru/v1/`

### 3.2. Ключевые эндпоинты

- `directories` — батч‑загрузка справочников для фильтров:
  - Параметр `types` может включать:  
    `rooms`, `balcony_types`, `banks`, `building_types`, `cardinals`, `contracts`, `deadlines`, `deadline_keys`, `delta_prices`, `region_registrations`, `subway_distances`, `elevator_types`, `escrow_banks`, `finishings`, `without_initial_fee`, `installment_tags`, `level_types`, `locations`, `mortgage_types`, `nearby_place_types`, `parking_types`, `payment_types`, `premise_types`, `regions`, `sales_start`, `subways`, `view_places`, `window_views`, `window_types`.

### 3.3. Где используется

- Все страницы поиска/фильтрации:
  - `/objects/*`, `/houses/*`, `/villages/*`, `/commerce/*` и др.

### 3.4. Главные сущности

- Набор справочников (directories) для:
  - квартир, объектов, домов, участков, коммерции.

---

## 4. parkings-api.trendagent.ru

### 4.1. Базовый URL

`https://parkings-api.trendagent.ru/`

### 4.2. Ключевые эндпоинты

- Справочники:
  - `enums/contract_types`
  - `enums/parking_types`
  - `enums/payment_types`
  - `enums/place_types`
  - `directories/deadlines/`
  - `directories/sales_start/`

- Поиск:
  - `search/blocks?count=...&offset=...&sort=price&sort_order=asc&city=...` — блоки парковок.

- Детали:
  - `parkings/block/{block_id}` — информация по парковке объекта.
  - `parkings/block/{block_id}/geo/` — геоданные для карты.
  - `parkings/block/{block_id}` (без geo) — используются на вкладках и detail‑страницах.

### 4.3. Где используется

- `/parkings/list`, `/parkings/table`, `/parkings/map`.
- `/object/.../#parkings` — вкладки парковок на карточке объекта.
- `/parkingplace/{id}` — detail машиноместа (через `parkings-api`).

### 4.4. Главные сущности

- `ParkingBlock` — блок парковки, связанный с Block (ЖК).
- `ParkingPlace` — конкретное машиноместо/кладовка.

---

## 5. commerce-api.trendagent.ru

### 5.1. Базовый URL

`https://commerce-api.trendagent.ru/`

### 5.2. Ключевые эндпоинты

- `filters`  
  - Пример:  
    `/filters?block_id={BLOCK_ID}&name=buildings&name=window_view_types&...&city=...&lang=ru`
  - Набор фильтров по коммерческим параметрам.

- `search/{block_id}/premises`  
  - Пример: `/search/66ce4335ed7175f303a8898d/premises?city=...&lang=ru`
  - Выборка коммерческих помещений для блока.

- `search/map/buildings`  
  - Пример: `/search/map/buildings?blocks={BLOCK_ID}&city=...&lang=ru`
  - Данные для карты коммерческих объектов.

### 5.3. Где используется

- `/commerce/list`, `/commerce/table`, `/commerce/plan`, `/commerce/map`.
- Вкладка `#commerce` на карточке объекта.
- Detail‑страницы `/commerce-premise/{id}`.

### 5.4. Главные сущности

- `CommercePremise` — коммерческое помещение.
- Сопутствующие справочники: property_types, purposes, ceiling_heights, contract_types и др.

---

## 6. rewards-api.trendagent.ru

### 6.1. Базовый URL

`https://rewards-api.trendagent.ru/`

### 6.2. Ключевые эндпоинты

- `directories?city=...&lang=ru` — справочники вознаграждений.
- `builder-reward-settings?block={BLOCK_ID}&city=...` — настройки вознаграждений по блоку.
- `blocks/{block_id}/commerce?builder={BUILDER_ID}&city=...` — вознаграждения по коммерции.

### 6.3. Где используется

- Detail‑страницы квартир, коммерции, участков, парковок (блоки «Ваше вознаграждение»).
- Вкладки/страницы, где показывается шкала и срок выплат.

### 6.4. Главные сущности

- `RewardSettings` — схема вознаграждения для блока/сущности.

---

## 7. files.trendagent.ru

### 7.1. Базовый URL

`https://files.trendagent.ru/fs/`

### 7.2. Ключевые эндпоинты

- `breadcrumbs/block/{block_id}` — хлебные крошки файлов по объекту.
- `list/block/{block_id}` — список файлов (PDF, презентации, договоры).

### 7.3. Где используется

- Вкладка документов на карточке объекта.

### 7.4. Главные сущности

- `File` — документ, связанный с Block.

---

## 8. video.trendagent.ru

### 8.1. Базовый URL

`https://video.trendagent.ru/`

### 8.2. Ключевые эндпоинты

- `videos/block/{block_id}` — видео‑материалы по объекту.
- `categories` — категории видео.

### 8.3. Где используется

- Детальные страницы объектов (видео‑блоки).

### 8.4. Главные сущности

- `Video` — видеофайл, связанный с Block.

---

## 9. contacts-api.trendagent.ru

### 9.1. Базовый URL

`https://contacts-api.trendagent.ru/`

### 9.2. Ключевые эндпоинты

- `contacts/blocks/{block_id}` — список контактов по объекту (отвечающие менеджеры, телефоны и т.п.).

### 9.3. Где используется

- Блок «Контакты» на карточках объектов, квартир, коммерции, парковок, участков.

### 9.4. Главные сущности

- `Contact` — контактное лицо/служба по объекту.

---

## 10. mortgage-api.trendagent.ru

### 10.1. Базовый URL

`https://mortgage-api.trendagent.ru/`

### 10.2. Ключевые эндпоинты

- `types/?auth_token=...&city=...&lang=ru` — типы ипотек.

### 10.3. Где используется

- Блоки «Актуальная ипотека» на detail‑страницах квартир/помещений.

### 10.4. Главные сущности

- `MortgageType` — тип ипотечной программы.

---

## 11. 3d-tour-api.trendagent.ru

### 11.1. Базовый URL

`https://3d-tour-api.trendagent.ru/`

### 11.2. Ключевые эндпоинты

- `v1/blocks/{block_id}?city=...&lang=ru` — 3D‑туры по объекту.

### 11.3. Где используется

- Детальные страницы объектов (кнопки 3D‑туров).

### 11.4. Главные сущности

- `Tour` — 3D‑тур по объекту.

---

## 12. Прочие вспомогательные домены

### 12.1. webinars-api.trendagent.ru

- `v1/events` — мероприятия.
- `v1/webinar_types` — типы вебинаров.
- Используется на главной и в верхних блоках‑афишах.

### 12.2. chat.trendagent.ru / online.trendagent.ru

- `chats/unread-messages` — количество непрочитанных сообщений.
- `socket.io` — online‑события (чат, уведомления).

