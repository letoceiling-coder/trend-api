## Фильтры и справочники TrendAgent

Этот документ фиксирует соответствие UI‑фильтров и справочников API, а также подчёркивает, что фильтрация выполняется на сервере и привязана к городу.

---

## 1. Общие принципы

- Все фильтры в интерфейсе (тип квартиры, срок сдачи, цена, тип паркинга, назначение коммерции и т.д.) основаны на **справочниках**, загружаемых с бэкенда.
- Для каждой страницы поиска/подбора:
  - сначала загружаются справочники (directories/filters),
  - затем выполняется `search`‑запрос с набором query‑параметров.
- Фильтрация **server‑side**:
  - клиент не загружает «все сущности» и не фильтрует локально.

---

## 2. Справочники для квартир и объектов (apartment-api)

### 2.1. Endpoint

```http
GET https://apartment-api.trendagent.ru/v1/directories
  ?types=rooms
  &types=balcony_types
  &types=banks
  &types=building_types
  &types=cardinals
  &types=contracts
  &types=deadlines
  &types=deadline_keys
  &types=delta_prices
  &types=region_registrations
  &types=subway_distances
  &types=elevator_types
  &types=escrow_banks
  &types=finishings
  &types=without_initial_fee
  &types=installment_tags
  &types=level_types
  &types=locations
  &types=mortgage_types
  &types=nearby_place_types
  &types=parking_types
  &types=payment_types
  &types=premise_types
  &types=regions
  &types=sales_start
  &types=subways
  &types=view_places
  &types=window_views
  &types=window_types
  &city={CITY_ID}
  &lang=ru
```

### 2.2. Соответствие UI → types (квартиры/объекты)

- **Тип квартиры** (студия, 1к, 2к, ...) → `rooms`
- **Тип дома** (монолит, панель, кирпич и т.п.) → `building_types`
- **Срок сдачи** (квартал/год) → `deadlines`, `deadline_keys`
- **Дата начала продаж** → `sales_start`
- **Регион регистрации** → `region_registrations`
- **Локация (район, локация)** → `locations`, `regions`
- **Метро** → `subways`
- **Расстояние до метро** → `subway_distances`
- **Тип балкона** → `balcony_types`
- **Тип этажа** (первый, последний, средний) → `level_types`
- **Вид из окна** → `view_places`, `window_views`
- **Тип окна** → `window_types`
- **Наличие/тип парковки** → `parking_types`
- **Отделка** → `finishings`
- **Тип сделки / договор** → `contracts`
- **Тип оплаты** (ипотека, рассрочка, 100%) → `payment_types`
- **Ипотечные программы** → `mortgage_types`
- **Банки** → `banks`, `escrow_banks`
- **Условные признаки**:
  - «Без первоначального взноса» → `without_initial_fee`
  - теги рассрочек → `installment_tags`

### 2.3. Страницы, где используется

- `/objects/list`, `/objects/table`, `/objects/plans`, `/objects/map`.
- `/houses/*` — специфические подмножества тех же справочников.
- `/villages/*` — частично (regions, locations и т.п.).
- `/commerce/*` — базовые локационные/банковские признаки.

---

## 3. Фильтры парковок (parkings-api)

### 3.1. Справочники

```http
GET https://parkings-api.trendagent.ru/enums/contract_types?city=...&lang=ru
GET https://parkings-api.trendagent.ru/enums/parking_types?city=...&lang=ru
GET https://parkings-api.trendagent.ru/enums/payment_types?city=...&lang=ru
GET https://parkings-api.trendagent.ru/enums/place_types?city=...&lang=ru
GET https://parkings-api.trendagent.ru/directories/deadlines/?city=...&lang=ru
GET https://parkings-api.trendagent.ru/directories/sales_start/?city=...&lang=ru
```

### 3.2. Соответствие UI → справочники

- **Тип паркинга** (подземный, наземный, крытый и т.п.) → `parking_types`
- **Тип места** (стандарт, мотоместо, кладовка и др.) → `place_types`
- **Тип договора** → `contract_types`
- **Тип оплаты** → `payment_types`
- **Срок сдачи** → `deadlines`
- **Старт продаж** → `sales_start`

### 3.3. Где используется

- `/parkings/list`, `/parkings/table`, `/parkings/map`.
- Вкладки парковок на `/object/{slug}#parkings`.

### 3.4. Связь с search‑запросами

- После выбора фильтра отправляется запрос:
  - `GET parkings-api.trendagent.ru/search/blocks?...` или аналогичный `search` для мест.
- Все пользовательские значения маппятся в query‑параметры:
  - например, `parking_type_ids[]=...`, `contract_type_ids[]=...`, диапазоны цен/площади — в `min_price`, `max_price`, `min_area`, `max_area` и т.д.  
  (точные имена параметров зависят от внутреннего контракта API; факт их отправки подтверждён логами `search` сразу после изменения фильтров).

---

## 4. Фильтры коммерции (commerce-api)

### 4.1. Справочники

```http
GET https://commerce-api.trendagent.ru/filters
  ?block_id={BLOCK_ID}
  &name=buildings
  &name=window_view_types
  &name=cardinals
  &name=property_types
  &name=building_types
  &name=start_sales
  &name=deadline_keys
  &name=entrances
  &name=finishing_types
  &name=bathroom_types
  &name=balconies_types
  &name=window_types
  &name=piping_types
  &name=levels
  &name=ventilation_types
  &name=payment_types
  &name=banks
  &name=contract_types
  &name=buyer_requirements
  &name=deadlines
  &name=purposes
  &name=ceiling_heights
  &name=level_types
  &city={CITY_ID}
  &lang=ru
```

### 4.2. Соответствие UI → filters

- **Назначение** (свободное, торговля, услуги и т.п.) → `purposes`
- **Тип недвижимости** → `property_types`
- **Тип здания** → `building_types`
- **Высота потолков** → `ceiling_heights`
- **Дата старта продаж** → `start_sales`
- **Срок сдачи** → `deadlines`, `deadline_keys`
- **Количество входов** → `entrances`
- **Тип отделки** → `finishing_types`
- **Тип санузла** → `bathroom_types`
- **Тип балкона** → `balconies_types`
- **Тип окон** → `window_types`
- **Вид из окна** → `window_view_types`, `cardinals`
- **Тип разводки труб** → `piping_types`
- **Количество уровней** → `levels`, `level_types`
- **Тип вентиляции** → `ventilation_types`
- **Финансовые условия**:
  - тип оплаты → `payment_types`
  - банки → `banks`
  - тип договора → `contract_types`
  - требования к покупателю → `buyer_requirements`

### 4.3. Search с фильтрами

- Поиск помещений:

```http
GET https://commerce-api.trendagent.ru/search/{BLOCK_ID}/premises
  ?city={CITY_ID}
  &lang=ru
  &...фильтры...
```

- Для карты:

```http
GET https://commerce-api.trendagent.ru/search/map/buildings
  ?blocks={BLOCK_ID}
  &city={CITY_ID}
  &lang=ru
  &...фильтры...
```

Фильтрация полностью server‑side: изменения на UI порождают новые запросы к `search` с соответствующими параметрами.

---

## 5. Привязка фильтров к городу (city‑scoped)

### 5.1. City‑scoped справочники

Во всех справочниках и filters присутствуют параметры:

- `city={CITY_ID}`
- `lang=ru`

Это касается:

- `apartment-api.trendagent.ru/v1/directories`
- `parkings-api.trendagent.ru/enums/*` и `directories/*`
- `commerce-api.trendagent.ru/filters`

**Следствие:**

- списки метро, районов, банков, типов земли, целей использования и т.п. зависят от города;
- нельзя переиспользовать справочники между городами; для каждого города их нужно запрашивать отдельно.

---

## 6. Server‑side фильтрация и debounce

### 6.1. Server‑side

- Все страницы:
  - `/objects/*`, `/parkings/*`, `/houses/*`, `/villages/*`, `/commerce/*`
  - при первой загрузке **сразу** запрашивают `search`‑эндпоинт с параметрами по умолчанию.
- Клиент не загружает полный набор сущностей и не делает фильтрацию в памяти.

### 6.2. Поведение при изменении фильтров

- При изменении каждого фильтра:
  - (при необходимости) загружается справочник для этого фильтра;
  - отправляется новый `search`‑запрос с обновлённым набором query‑параметров;
  - результатом становится новый JSON‑список сущностей, который перерисовывает UI.

### 6.3. Debounce

- Из логов видно, что:
  - нет взрывного числа `search`‑запросов при единичном действии;
  - запросы к `search` возникают после завершённых действий пользователя.
- Вывод:
  - debounce реализован на фронтенде (внутри SPA‑модулей), но на контракт API это не влияет.

---

## 7. Соответствие UI → query‑параметры (общее)

Паттерн:

- Переключатели (чекбоксы/чипы/селекты):
  - конвертируются в массивы ID или булевы флаги (`..._ids[]=`, `has_xxx=true`).
- Диапазоны (цена, площадь, расстояние):
  - передаются как `min_*/max_*` (`min_price`, `max_price`, `min_area`, `max_area` и т.п.).
- Геофильтры (карты):
  - параметры `bounds`, `zoom`, `center` и др., плюс `blocks`/`city`.

Конкретные имена параметров зависят от конкретного поискового эндпоинта, но **обязательное правило**:

- каждый UI‑фильтр имеет явное отражение в query‑строке `search`‑запроса,
- фильтры не дублируются и не «угадываются» на клиенте после загрузки данных.

