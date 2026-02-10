## Доменные сущности TrendAgent

Этот документ описывает основные сущности, их связи и корневую структуру домена TrendAgent. Используется как база для проектирования собственной БД и доменного слоя.

---

## 1. Обзор сущностей

### 1.1. Block

- Представляет:
  - ЖК (многоквартирный комплекс),
  - корпус/секцию/очередь,
  - загородный посёлок,
  - коммерческий комплекс,
  - домовой комплекс.
- Основные поля (на уровне идеи):
  - `id` — внутренний идентификатор.
  - `guid` — slug (используется в URL `/object/{slug}`).
  - `name` — название.
  - `type` — тип блока (жильё, загородка, коммерция, парковка и т.п.).
  - `city_id` — город.
  - `location` — координаты, адрес.
  - `developer` — застройщик.
  - `deadlines` — сроки сдачи/передачи.
  - `class` — класс недвижимости (эконом, комфорт, бизнес, элитный, и т.д.).
  - `attributes` — различные характеристики (фасад, тип дома, наличие паркинга, лифты и т.п.).

### 1.2. Apartment

- Представляет квартиру/юнит в объекте.
- Основные поля:
  - `id` — идентификатор квартиры.
  - `block_id` — ссылка на Block.
  - `number` — номер квартиры.
  - `rooms` — количество комнат.
  - `area_total`, `area_kitchen`, `area_living` — площади.
  - `price` — цена.
  - `floor`, `floors_total` — этаж/этажность.
  - `balcony_type`, `finishing`, `window_type`, `view` и др.
  - `status` — свободна, забронирована, продана.
  - `contract_type`, `payment_types`, `mortgage_types` — условия сделки.

### 1.3. CommercePremise

- Представляет коммерческое помещение.
- Основные поля:
  - `id`
  - `block_id` — ссылка на Block.
  - `number` — номер помещения.
  - `purpose` — назначение (стрит‑ритейл, офис, склад и др.).
  - `area_total` — площадь.
  - `price` — цена/ставка.
  - `floor`, `levels` — этаж(и).
  - `ceiling_height`
  - `entrances` — число входов.
  - `ventilation_type`, `piping_type`, `finishing_type`, `window_type`, `view`.
  - `contract_type`, `payment_types`, `buyer_requirements`.
  - `status` — доступно/арендовано/продано.

### 1.4. Parking / ParkingPlace

- `ParkingBlock`:
  - блок парковки, привязанный к Block (ЖК).
  - содержит агрегированную информацию: количество мест, типы мест, этажность, тип паркинга.
- `ParkingPlace`:
  - отдельное машиноместо.
  - Основные поля:
    - `id`
    - `parking_block_id` — ссылка на ParkingBlock.
    - `block_id` — ссылка на Block (ЖК).
    - `number` — номер места.
    - `area` — площадь.
    - `floor`, `floors_total`.
    - `place_type` — стандарт, мотоместо, кладовка и т.п.
    - `has_storage_box`, `has_lift`, `has_ev_socket` — опции.
    - `price`, `price_per_m2`.
    - `status` — свободно, забронировано, продано.
    - `contract_type`, `payment_types`.

### 1.5. Village и Plot

- `Village`:
  - выражает посёлок/коттеджный проект за городом.
  - Основные поля:
    - `id`
    - `name`
    - `city_id`
    - `location` — привязка к нас. пункту/шоссе.
    - `developer`
    - `purpose_types` — допустимые назначения земли.
    - `infrastructure` — школы, детсады, магазины и т.д.
    - `communications` — вода, канализация, электричество, газ.
    - `contract_type`, `payment_types` и т.п.
- `Plot`:
  - участок земли внутри Village.
  - Основные поля:
    - `id`
    - `village_id`
    - `cadastral_number`
    - `area_sotka` — площадь в сотках.
    - `price`
    - `land_purpose` — ИЖС/ДНП/ДНТ и др.
    - `water`, `sewerage`, `electricity`, `gas` — типы коммуникаций.
    - `road_type` — подъездная дорога.
    - `status`
    - `contract_type`, `payment_types`, `escrow`.

---

## 2. Связи между сущностями

### 2.1. Корневые сущности

Корневыми считаем:

- `Block` — точка входа для большинства UI‑страниц (`/objects/*`, `/commerce/*`, часть `/houses/*`, `/villages/*`).
- `Village` — для специфических загородных сценариев (но часто также реализуется как Block).

Практически все остальные сущности являются **подчинёнными**:

- `Apartment` принадлежит `Block`.
- `CommercePremise` принадлежит `Block`.
- `ParkingBlock` принадлежит `Block`.
- `ParkingPlace` принадлежит `ParkingBlock` (и через него `Block`).
- `Plot` принадлежит `Village` (который может быть либо отдельной сущностью, либо представлен как `Block` определённого типа).

### 2.2. Текстовая ER‑диаграмма

В терминах реляционной схемы (без указания типов полей):

- `City` (внешний по отношению к TrendAgent, но критичен):
  - `City (id, name, region, ...)`

- `Block`:
  - `Block (id, city_id, guid, name, type, developer_id, ... )`
  - FK: `Block.city_id -> City.id`

- `Apartment`:
  - `Apartment (id, block_id, number, rooms, area_total, area_kitchen, area_living, floor, floors_total, price, status, contract_type_id, finishing_id, ... )`
  - FK: `Apartment.block_id -> Block.id`

- `CommercePremise`:
  - `CommercePremise (id, block_id, number, purpose_id, area_total, price, floor, levels, ceiling_height_id, entrance_count, ventilation_type_id, piping_type_id, finishing_type_id, window_type_id, view_id, status, contract_type_id, payment_type_id, buyer_requirement_id, ... )`
  - FK: `CommercePremise.block_id -> Block.id`

- `ParkingBlock`:
  - `ParkingBlock (id, block_id, parking_type_id, floors_total, capacity, ... )`
  - FK: `ParkingBlock.block_id -> Block.id`

- `ParkingPlace`:
  - `ParkingPlace (id, parking_block_id, block_id, number, area, floor, floors_total, place_type_id, has_storage_box, has_lift, has_ev_socket, price, status, contract_type_id, payment_type_id, ... )`
  - FK: `ParkingPlace.parking_block_id -> ParkingBlock.id`
  - FK: `ParkingPlace.block_id -> Block.id`

- `Village`:
  - Вариант А (отдельная сущность):  
    `Village (id, city_id, name, location, developer_id, ...)`  
    FK: `Village.city_id -> City.id`
  - Вариант Б (на базе Block):  
    `Block.type = 'village'` и village‑специфические поля хранятся в отдельной таблице `VillageExtension (block_id, ...)`.

- `Plot`:
  - `Plot (id, village_id, cadastral_number, area_sotka, price, land_purpose_id, water_type_id, sewerage_type_id, electricity_type_id, gas_type_id, road_type_id, status, contract_type_id, payment_type_id, escrow_flag, ... )`
  - FK: `Plot.village_id -> Village.id`

Справочники (`rooms`, `building_types`, `contracts`, `purposes`, `parking_types` и др.) в TrendAgent приходят из `directories` и `filters`, в собственной схеме целесообразно завести отдельные таблицы или enum‑типы.

---

## 3. Что является корнем домена

### 3.1. На уровне агрегатов

- **Block** — корневой агрегат для:
  - квартир (`Apartment`),
  - коммерции (`CommercePremise`),
  - парковок (`ParkingBlock`/`ParkingPlace`),
  - файлов (`File`),
  - видео (`Video`),
  - вознаграждений (`RewardSettings`),
  - контактов (`Contact`),
  - туров (`Tour`).

- **Village** (или `Block` типа «посёлок»):
  - корень для участков (`Plot`).

### 3.2. На уровне пользовательского UI

- Страницы уровня списка (list/table/map/plans) почти всегда опираются:
  - на поиск по `Block` (объекты/посёлки/комплексы),
  - или на поиск по unit‑сущностям (`Apartment`, `ParkingPlace`, `CommercePremise`, `Plot`).
- Detail‑страницы:
  - для unit‑сущностей всегда подтягивают и сам `Block`/`Village`, и расширенную информацию по окружению, файлам, вознаграждению и т.п.

---

## 4. Связи в текстовом виде

- `City 1 — N Block`
- `Block 1 — N Apartment`
- `Block 1 — N CommercePremise`
- `Block 1 — N ParkingBlock`
- `ParkingBlock 1 — N ParkingPlace`
- `Block 1 — 0..1 VillageExtension` (если посёлок реализован на Block)
- `Village 1 — N Plot`
- `Block 1 — N File`
- `Block 1 — N Video`
- `Block 1 — N RewardSettings`
- `Block 1 — N Contact`
- `Block 1 — N Tour`

Эта схема соответствует как UI‑структуре страниц, так и фактическому использованию API, где `block_id` и `city_id` выступают основными ключами для агрегации и связывания данных.

