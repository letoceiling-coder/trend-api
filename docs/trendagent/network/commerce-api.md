## commerce-api.trendagent.ru — коммерческие помещения

Этот файл конкретизирует работу фильтров и поиска по коммерции.

---

### 1. Справочники фильтров

- **UI‑страницы**:
  - `/commerce/list`, `/commerce/table`, `/commerce/plan`, `/commerce/map`
  - вкладка `#commerce` на `/object/{slug}`

```http
GET https://commerce-api.trendagent.ru/filters
  ?block_id={BLOCK_ID}    // для объект-специфичных фильтров
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

- **Response shape (обобщённо)**:

```json
{
  "purposes": [
    { "id": 1, "code": "retail", "name": "Стрит-ритейл" }
  ],
  "property_types": [
    { "id": 10, "code": "office", "name": "Офис" }
  ]
  /* аналогично для остальных name */
}
```

---

### 2. Поиск помещений по объекту

- **UI‑страницы**:
  - вкладки коммерции на `/object/{slug}` и страницы `/commerce/*`, когда выбран конкретный объект.

```http
GET https://commerce-api.trendagent.ru/search/{BLOCK_ID}/premises
  ?city={CITY_ID}
  &lang=ru
  &...фильтры...
```

- **Примеры параметров**:
  - `purpose_ids[]=...`
  - `property_type_ids[]=...`
  - `building_type_ids[]=...`
  - `min_area`, `max_area`
  - `min_price`, `max_price`
  - `ceiling_height_ids[]=...`
  - `ventilation_type_ids[]=...`
  - `payment_type_ids[]=...`
  - `contract_type_ids[]=...`

- **Response shape (логически)**:

```json
{
  "items": [
    {
      "id": "string",
      "block_id": "string",
      "number": "string",
      "purpose_id": 1,
      "area_total": 0,
      "price": 0,
      "floor": 1,
      "levels": 1,
      "status": "free"
      /* см. сущность CommercePremise в domain-model */
    }
  ],
  "total": 123
}
```

---

### 3. Поиск для карты

- **UI‑страницы**:
  - `/commerce/map`

```http
GET https://commerce-api.trendagent.ru/search/map/buildings
  ?blocks={BLOCK_ID1},{BLOCK_ID2}
  &city={CITY_ID}
  &lang=ru
  &...фильтры...
```

- **Response shape (обобщённо)**:

```json
{
  "buildings": [
    {
      "block_id": "string",
      "location": { "lat": 0, "lng": 0 },
      "premises_count": 10,
      "min_price": 0
    }
  ]
}
```

---

### 4. Detail коммерческого помещения

- **UI‑страница**:
  - `/commerce-premise/{id}`

Полный путь детального endpoint в логах не сохранялся текстом, но по связям с commerce‑доменом ожидается вызов вида:

```http
GET https://commerce-api.trendagent.ru/premises/{ID}
  ?city={CITY_ID}
  &lang=ru
```

- **Статус**:
  - факт существования detail‑страницы и участия commerce‑доменов подтверждён;
  - конкретная структура detail‑JSON требует повторного точечного наблюдения.

