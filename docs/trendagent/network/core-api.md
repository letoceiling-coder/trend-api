## api.trendagent.ru — ядро объектов и квартир

Этот файл фиксирует наблюдаемые запросы к `api.trendagent.ru/v4_29/*` и их связь с UI.

---

### 1. Поиск блоков (объектов / домов / посёлков)

- **UI‑страницы**:
  - `/objects/list`, `/objects/table`, `/objects/map`, `/objects/plans`
  - `/houses/list`, `/houses/table`, `/houses/plans`, `/houses/map`
  - `/villages/list`, `/villages/map`

#### 1.1. Реальный контракт (проверено probe 2026-02-10)

- **Endpoint**: `GET https://api.trendagent.ru/v4_29/blocks/search`
- **HTTP метод**: **GET** (POST возвращает 404)
- **Query params** (все обязательные):
  - `city={CITY_ID}`
  - `lang=ru`
  - `auth_token={AUTH_TOKEN}`
  - `show_type=list|map|plans` (default: list)
  - `count=20` (items per page)
  - `offset=0` (pagination)
  - `sort=price` (sort field)
  - `sort_order=asc|desc`

- **Response structure**:

```json
{
  "errors": null,
  "data": {
    "bookedApartmentsCount": 2568,
    "apartmentsCount": 56231,
    "viewApartmentsCount": 2434,
    "blocksCount": 345,
    "prelaunchesCount": 9,
    "prelaunchesEoiCount": 0,
    "results": [
      {
        "block_id": "65c8b45523bccfa820bfaf73",
        "guid": "villa-marina",
        "title": "Villa Marina",
        "city": {...},
        "min_price": 1,
        "max_price": 1,
        "location": {...},
        "developer": {...}
        /* полный объект блока */
      }
    ]
  }
}
```

**Ключевые находки**:
- Блоки лежат в `data.results` (НЕ в `items` или `data.items`)
- Поле идентификатора: `block_id` (строка 24 символа)
- GET с query params работает, POST не поддерживается

#### 1.2. Старая документация (несоответствует реальности)

Ниже — оригинальная документация из Network логов (оставлена для сравнения). Реальный API использует GET с query params (см. 1.1).

- **Headers**:
  - `Authorization: Bearer {AUTH_TOKEN}` (в query как auth_token=...)
  - `Content-Type: application/json`
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
  }
}
```

- **Пример curl** (НЕ работает, POST возвращает 404):

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
- **Основные endpoints (проверено probe 2026-02-10)**:

```http
GET https://api.trendagent.ru/v4_29/blocks/{BLOCK_ID}/unified
GET https://api.trendagent.ru/v4_29/blocks/{BLOCK_ID}/advantages
GET https://api.trendagent.ru/v4_29/blocks/{BLOCK_ID}/nearby_places
GET https://api.trendagent.ru/v4_29/blocks/{BLOCK_ID}/bank
GET https://api.trendagent.ru/v4_29/blocks/{BLOCK_ID}/apartments/min-price
GET https://api.trendagent.ru/v4_29/blocks/{BLOCK_ID}/geo/buildings
```

- **Общие query params**:
  - `city={CITY_ID}` (обязательно)
  - `lang=ru` (обязательно)
  - `auth_token={AUTH_TOKEN}` (автоматически добавляется TrendHttpClient)

- **Дополнительные параметры для `/unified`**:
  - `formating=true` (рекомендуется для форматированных данных)
  - `ch=false` (отключить кеширование)

- **Response (детали)**:
  - `/unified` (required) — агрегированный объект (данные блока, описания, картинки, ключевые параметры, полная детальная информация).
  - `/advantages` (optional) — список преимуществ объекта.
  - `/nearby_places` (optional) — объекты инфраструктуры рядом (школы, магазины, парки).
  - `/bank` (optional) — сведения о банках/ипотеке для объекта.
  - `/apartments/min-price` (optional) — минимальные цены на квартиры разных типов в объекте.
  - `/geo/buildings` (optional) — геометрия корпусов/секций для карты.

**Статус**: Unified endpoint обязательный (required) для детальной карточки. Остальные опциональные — их ошибки не прерывают синхронизацию.

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

