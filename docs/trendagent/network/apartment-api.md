## apartment-api.trendagent.ru — справочники квартир и объектов

Этот файл дополняет `04-filters-and-directories.md` конкретными примерами запросов.

---

### 1. Общий запрос справочников

- **UI‑страницы**:
  - все страницы, где есть фильтры по квартирам/объектам:
    - `/objects/list`, `/objects/table`, `/objects/plans`, `/objects/map`
    - часть `/houses/*`, `/villages/*`, `/commerce/*`

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

- **Response shape (усреднённо)**:

```json
{
  "rooms": [
    { "id": 0, "code": "studio", "name": "Студия" },
    { "id": 1, "code": "1", "name": "1-комнатная" }
  ],
  "building_types": [
    { "id": 10, "code": "monolith", "name": "Монолит" }
  ],
  "contracts": [
    { "id": 1, "code": "ddu", "name": "ДДУ" }
  ]
  /* аналогично для остальных types */
}
```

- **Пример curl**:

```bash
curl "https://apartment-api.trendagent.ru/v1/directories?types=rooms&types=building_types&city={CITY_ID}&lang=ru" \
  -H "Authorization: Bearer {AUTH_TOKEN}"
```

---

### 2. Связь с UI‑фильтрами

См. детальное соответствие в `04-filters-and-directories.md`. Важно:

- каждый элемент справочника даёт:
  - `id`/`code` — значения для query‑параметров в `search`;
  - `name` — подпись в UI.
- изменение фильтра в интерфейсе:
  - берёт `id`/`code` из справочника;
  - добавляет/удаляет соответствующий параметр в теле/строке запроса к `api.trendagent.ru` или специализированному домену (parkings, commerce и т.д.).

