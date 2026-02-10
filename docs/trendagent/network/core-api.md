## api.trendagent.ru — ядро объектов и квартир

Этот файл фиксирует наблюдаемые запросы к `api.trendagent.ru/v4_29/*` и их связь с UI.

---

### 1. Поиск блоков (объектов / домов / посёлков)

- **UI‑страницы**:
  - `/objects/list`, `/objects/table`, `/objects/map`, `/objects/plans`
  - `/houses/list`, `/houses/table`, `/houses/plans`, `/houses/map`
  - `/villages/list`, `/villages/map`
- **Endpoint**:

```http
POST https://api.trendagent.ru/v4_29/blocks/search
```

- **Headers**:
  - `Authorization: Bearer {AUTH_TOKEN}`
  - `Content-Type: application/json`
- **Query params**:
  - `city={CITY_ID}`
  - `lang=ru`
- **Body (обобщённо, по логам и UI)**:

```json
{
  "limit": 50,
  "offset": 0,
  "sort": "price_asc",
  "filters": {
    "rooms": [1, 2, 3],
    "deadline_keys": ["2026_Q4"],
    "price": { "min": 5000000, "max": 15000000 },
    "locations": ["..."],
    "subways": ["..."],
    "building_types": ["monolith"],
    "class": ["comfort"]
    /* остальные поля соответствуют справочникам apartment-api (см. 04-filters-and-directories.md) */
  }
}
```

- **Response shape (логически, без полного дампа)**:

```json
{
  "items": [
    {
      "id": "string",
      "guid": "string",
      "name": "string",
      "city_id": "string",
      "type": "string",
      "location": { "lat": 0, "lng": 0, "address": "string" },
      "min_price": 0,
      "min_price_per_m2": 0,
      "deadline": "string",
      "developer": { "id": "string", "name": "string" }
      /* доп. атрибуты блока, см. domain-model */
    }
  ],
  "total": 123
}
```

- **Пример curl**:

```bash
curl -X POST "https://api.trendagent.ru/v4_29/blocks/search?city={CITY_ID}&lang=ru" \
  -H "Authorization: Bearer {AUTH_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{ "limit": 50, "offset": 0, "filters": { "rooms": [1,2,3] } }'
```

---

### 2. Счётчик блоков

- **UI‑использование**: отображение «N объектов найдено» над листингом.

```http
POST https://api.trendagent.ru/v4_29/blocks/search/count
  ?city={CITY_ID}
  &lang=ru
```

- **Body**: тот же набор `filters`, что и у `/blocks/search`.
- **Response JSON**:

```json
{ "count": 123 }
```

---

### 3. Поиск квартир

- **UI‑страницы**:
  - `/objects/plans` (общий рынок)
  - вкладки планировок на `/object/{slug}`
- **Endpoint**:

```http
POST https://api.trendagent.ru/v4_29/apartments/search
  ?city={CITY_ID}
  &lang=ru
```

- **Body (обобщённо)**:

```json
{
  "limit": 50,
  "offset": 0,
  "filters": {
    "block_ids": ["..."],      // при поиске по конкретному объекту
    "rooms": [1, 2],
    "area_total": { "min": 30, "max": 60 },
    "price": { "min": 5000000, "max": 9000000 },
    "floor": { "min": 2, "max": 20 },
    "finishings": ["..."],
    "contracts": ["..."]
  }
}
```

- **Response shape (логически)**:

```json
{
  "items": [
    {
      "id": "string",
      "block_id": "string",
      "number": "string",
      "rooms": 1,
      "area_total": 0,
      "area_kitchen": 0,
      "price": 0,
      "floor": 0,
      "floors_total": 0,
      "status": "free"
      /* см. сущность Apartment в domain-model */
    }
  ],
  "total": 123
}
```

---

### 4. Детали блока (object detail)

- **UI‑страницы**:
  - `/object/{slug}` (карточка объекта)
  - любые detail‑вкладки, завязанные на объект.
- **Основные endpoints (по логам)**:

```http
GET https://api.trendagent.ru/v4_29/blocks/{BLOCK_ID}/unified
GET https://api.trendagent.ru/v4_29/blocks/{BLOCK_ID}/advantages
GET https://api.trendagent.ru/v4_29/blocks/{BLOCK_ID}/nearby_places
GET https://api.trendagent.ru/v4_29/blocks/{BLOCK_ID}/bank
GET https://api.trendagent.ru/v4_29/blocks/{BLOCK_ID}/apartments/min-price
GET https://api.trendagent.ru/v4_29/blocks/{BLOCK_ID}/geo/buildings
```

- **Общие query params**:
  - `city={CITY_ID}`
  - `lang=ru`

- **Response (высокоуровнево)**:
  - `/unified` — агрегированный объект (данные блока, описания, картинки, ключевые параметры).
  - `/advantages` — список преимуществ (`[{ id, title, description }]`).
  - `/nearby_places` — объекты инфраструктуры рядом.
  - `/bank` — сведения о банках/ипотеке.
  - `/apartments/min-price` — минимальная цена квартиры в объекте.
  - `/geo/buildings` — геометрия корпусов/секций для карты.

---

### 5. Справочники и единицы измерения

- **UI‑использование**: подписи, единицы, статусы.

```http
GET https://api.trendagent.ru/v4_29/unit_measurements
GET https://api.trendagent.ru/v4_29/directories/finishing
GET https://api.trendagent.ru/v4_29/directories/rooms
```

- **Пример применения**:
  - `unit_measurements` — для корректного отображения м², процентов и др.
  - `directories/finishing`, `directories/rooms` — для маппинга сырых кодов в человекочитаемые подписи на detail‑страницах и в таблицах.

