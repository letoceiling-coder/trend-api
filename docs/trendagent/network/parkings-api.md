## parkings-api.trendagent.ru — парковки

Этот файл фиксирует наблюдаемые запросы для списка и деталей парковок.

---

### 1. Справочники и enums

- **UI‑страницы**:
  - `/parkings/list`, `/parkings/table`, `/parkings/map`
  - вкладка `#parkings` на `/object/{slug}`

```http
GET https://parkings-api.trendagent.ru/enums/contract_types?city={CITY_ID}&lang=ru
GET https://parkings-api.trendagent.ru/enums/parking_types?city={CITY_ID}&lang=ru
GET https://parkings-api.trendagent.ru/enums/payment_types?city={CITY_ID}&lang=ru
GET https://parkings-api.trendagent.ru/enums/place_types?city={CITY_ID}&lang=ru
GET https://parkings-api.trendagent.ru/directories/deadlines/?city={CITY_ID}&lang=ru
GET https://parkings-api.trendagent.ru/directories/sales_start/?city={CITY_ID}&lang=ru
```

- **Response shape (обобщённо)**:

```json
[
  { "id": 1, "code": "underground", "name": "Подземный" }
]
```

---

### 2. Поиск парковочных блоков / мест

- **UI‑страницы**:
  - `/parkings/list` и аналогичные представления.

```http
GET https://parkings-api.trendagent.ru/search/blocks
  ?city={CITY_ID}
  &lang=ru
  &...фильтры...
```

- **Примеры параметров** (по связке с enums):
  - `parking_type_ids[]=...`
  - `place_type_ids[]=...`
  - `contract_type_ids[]=...`
  - `payment_type_ids[]=...`
  - диапазоны `min_price`, `max_price`, `min_area`, `max_area` и т.п.

- **Response shape (логически)**:

```json
{
  "items": [
    {
      "id": "string",
      "block_id": "string",
      "parking_type_id": 1,
      "capacity": 100,
      "floors_total": 3
      /* агрегированная информация по парковочному блоку */
    }
  ],
  "total": 123
}
```

---

### 3. Detail парковочного места

- **UI‑страницы**:
  - `/parkingplace/{id}`

Точный endpoint detail‑запроса в логах не был явно зафиксирован, но логически ожидается вызов вида:

```http
GET https://parkings-api.trendagent.ru/places/{ID}?city={CITY_ID}&lang=ru
```

- **Важно**:
  - сам факт существования detail‑страницы и связи с parkings‑домена подтверждён;
  - конкретный путь и поля ответа требуют отдельного наблюдения в Network при следующем запуске анализа.

