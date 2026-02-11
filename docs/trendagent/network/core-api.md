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

### 3. Поиск квартир (apartments/search)

- **UI‑страницы**:
  - `/objects/plans` (общий рынок)
  - вкладки планировок на `/object/{slug}`

#### 3.1. Реальный контракт (проверяется probe)

Диагностика: `php artisan trendagent:probe:apartments-search --count=50 --offset=0 --save-raw -vvv`

- **Endpoint**: `GET` или `POST` (определяется probe)  
  `https://api.trendagent.ru/v4_29/apartments/search/`
- **Query params** (при GET): `city`, `lang`, `auth_token`, `count`, `offset`, `sort`, `sort_order`
- **Body** (при POST): `count`, `offset`, `sort`, `sort_order` (остальное по результатам probe)

**Shape detector** (где может лежать массив квартир):
- `data.results`
- `data.items`
- `items`
- `result.items`
- `data.apartments`
- `apartments`

Элемент квартиры распознаётся по наличию `apartment_id`, `_id` или `id`. Поля маппинга: `block_id`, `title`/`name`/`number`, `rooms`, `area_total`/`area`, `floor`, `price`/`price_from`, `status`, `guid`/`slug`.

После запуска probe актуальные метод, параметры и структура ответа фиксируются в этом разделе и в STATUS.md.

#### 3.2. Документация по логам (до проверки probe)

- **Endpoint**: `POST https://api.trendagent.ru/v4_29/apartments/search?city={CITY_ID}&lang=ru`
- **Body (обобщённо)**:

```json
{
  "limit": 50,
  "offset": 0,
  "filters": {
    "block_ids": ["..."],
    "rooms": [1, 2],
    "area_total": { "min": 30, "max": 60 },
    "price": { "min": 5000000, "max": 9000000 },
    "floor": { "min": 2, "max": 20 },
    "finishings": ["..."],
    "contracts": ["..."]
  }
}
```

- **Response shape (логически)**: массив квартир в `items` или в другой вложенности (см. shape detector выше).

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

### 5. Детали квартиры (apartment detail)

- **UI‑страницы**: детальная страница квартиры (карточка квартиры).
- **Endpoints (проверяются probe:apartment-detail)**:

```http
GET https://api.trendagent.ru/v4_29/apartments/{APARTMENT_ID}/unified/
GET https://api.trendagent.ru/v4_29/prices/apartment/{APARTMENT_ID}/totals
GET https://api.trendagent.ru/v4_29/prices/apartment/{APARTMENT_ID}/graph
```

- **Query params**: `city={CITY_ID}`, `lang=ru`, `auth_token` (добавляется TrendHttpClient).
- **Контракт**:
  - `/unified/` — обязательный (required). При не-200 sync считается failed.
  - `/prices/apartment/{id}/totals` и `/graph` — опциональные. 404 не прерывает sync, в БД сохраняются как null.
- **Сохранение**: `ta_apartment_details` (apartment_id, city_id, lang, unified_payload, prices_totals_payload, prices_graph_payload, fetched_at). Unique по (apartment_id, city_id, lang).

---

### 6. Справочники и единицы измерения

- **UI‑использование**: подписи, единицы, статусы.

```http
GET https://api.trendagent.ru/v4_29/unit_measurements
GET https://api.trendagent.ru/v4_29/directories/finishing
GET https://api.trendagent.ru/v4_29/directories/rooms
```

- **Пример применения**:
  - `unit_measurements` — для корректного отображения м², процентов и др.
  - `directories/finishing`, `directories/rooms` — для маппинга сырых кодов в человекочитаемые подписи на detail‑страницах и в таблицах.

