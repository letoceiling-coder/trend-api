# Отчёт: архитектура RAW + NORMALIZED + контракт API

## Цель

«Правильно получать все данные, железобетонно сохранять и выводить в таком же виде»: RAW storage, слой нормализации, стабильный внутренний контракт API.

---

## 1. RAW storage (как пришло)

- **Canonical JSON:** рекурсивная сортировка ключей, один и тот же формат (числа нормализованы: 1.0 → 1). Класс `App\Domain\TrendAgent\Payload\CanonicalPayload`.
- **payload_hash** = sha256(canonical_json(payload)). Хранится в ta_payload_cache и в сущностях (ta_blocks, ta_apartments, ta_block_details, ta_apartment_details).
- **ta_payload_cache** расширена полями: **endpoint**, **http_status**, **payload_hash** (миграция `2026_02_11_140000_add_endpoint_http_status_payload_hash_to_ta_payload_cache.php`).
- При **storeRaw=true** во всех probe/sync запись в ta_payload_cache выполняется всегда (в т.ч. при ошибках). Без auth_token и без секретов в payload; маскирование в логах/ошибках сохранено.

---

## 2. Normalization layer

- **Нормализаторы:**  
  `app/Domain/TrendAgent/Normalizers/BlockNormalizer.php`,  
  `ApartmentNormalizer.php`,  
  `BlockDetailNormalizer.php`,  
  `ApartmentDetailNormalizer.php`.
- Каждый возвращает строгий массив: скаляры, числа, координаты, цены, статус, **images[]** (массив URL строк).
- Добавлена JSON-колонка **normalized** в таблицах (миграции add_normalized_* или `2026_02_11_150000_add_normalized_payload_hash_to_ta_entities.php`):
  - ta_blocks.normalized  
  - ta_apartments.normalized  
  - ta_block_details.normalized  
  - ta_apartment_details.normalized  
- В sync после получения payload заполняется **normalized** и сохраняется в одной транзакции с raw/detail.

---

## 3. Internal API contract

- В **/api/ta/*** ресурсы возвращают:
  - **data.normalized** — всегда (или fallback из полей модели, если normalized пустой).
  - **meta.source:** fetched_at, payload_hash (run_id — при наличии, сейчас не сохраняется на сущностях).
- **RAW** в ответе только при **debug=1** и переданном валидном **X-Internal-Key** (ключ из `INTERNAL_API_KEY`). Без ключа параметр debug игнорируется (RAW не отдаётся). Формат ответа **{ data, meta }** сохранён.

---

## 4. Тесты

- **Unit:** canonical_json + стабильность hash (`CanonicalPayloadTest`).
- **Unit:** нормализаторы на разных формах payload: отсутствующие ключи, массивы/объекты, images (`BlockNormalizerTest`, `ApartmentNormalizerTest`).
- **Feature:** GET /api/ta/blocks/{id} и /api/ta/apartments/{id} возвращают normalized и meta.source.
- **Feature:** RAW не отдаётся без debug; с debug=1 без X-Internal-Key RAW нет; с debug=1 и валидным X-Internal-Key RAW есть.

---

## 5. Список изменённых файлов

| Категория | Файлы |
|-----------|--------|
| **Payload** | `app/Domain/TrendAgent/Payload/CanonicalPayload.php`, `PayloadCacheWriter.php` |
| **Normalizers** | `app/Domain/TrendAgent/Normalizers/BlockNormalizer.php` (добавлен images[]), `ApartmentNormalizer.php` (добавлен images[]), `BlockDetailNormalizer.php`, `ApartmentDetailNormalizer.php` |
| **Sync / Probe** | `TrendAgentSyncService.php`, все Probe-команды (запись через PayloadCacheWriter) |
| **Migrations** | `2026_02_11_140000_add_endpoint_http_status_payload_hash_to_ta_payload_cache.php`, `2026_02_11_150000_add_normalized_payload_hash_to_ta_entities.php`, `2026_02_11_150001_add_error_code_to_ta_sync_runs.php` |
| **Models** | TaPayloadCache, TaBlock, TaApartment, TaBlockDetail, TaApartmentDetail, TaSyncRun |
| **API** | `app/Http/Controllers/Api/Ta/TaBlocksController.php`, `TaApartmentsController.php` (data.normalized, meta.source, RAW только при debug + X-Internal-Key) |
| **Tests** | `CanonicalPayloadTest.php`, `BlockNormalizerTest.php` (в т.ч. images), `ApartmentNormalizerTest.php`, `TaApiTest.php` (meta.source, RAW только с ключом) |
| **Docs** | `README.md`, `docs/trendagent/STATUS.md` |

---

## 6. Миграции

Выполнить на сервере (если ещё не применены):

```bash
php artisan migrate --force
```

- Добавляют в **ta_payload_cache:** endpoint, http_status, payload_hash.
- Добавляют в **ta_blocks, ta_apartments, ta_block_details, ta_apartment_details:** normalized (json), payload_hash (string).
- Добавляют в **ta_sync_runs:** error_code.

---

## 7. Команды для сервера

```bash
cd /var/www/trend-api/backend

# Миграции
php artisan migrate --force

# Синк (RAW + normalized)
php artisan trendagent:sync:blocks --city=58c665588b6aa52311afa01b --lang=ru
php artisan trendagent:sync:apartments --city=58c665588b6aa52311afa01b --lang=ru

# Проверка API (обычный ответ, без raw)
curl -s "https://your-domain.com/api/ta/blocks?city_id=58c665588b6aa52311afa01b&lang=ru&count=2" | jq '.data[0], .meta'
curl -s "https://your-domain.com/api/ta/blocks/BLOCK_ID" | jq '.data, .meta.source'

# Ответ с RAW (только с валидным ключом)
curl -s -H "X-Internal-Key: YOUR_INTERNAL_API_KEY" "https://your-domain.com/api/ta/blocks/BLOCK_ID?debug=1" | jq '.data.raw | keys'
```

---

## 8. Примеры ответа API

### Обычный ответ (без debug)

```json
{
  "data": {
    "block_id": "abc",
    "guid": "zhk-example",
    "title": "ЖК Пример",
    "kind": "residential",
    "status": "sales",
    "min_price": 5000000,
    "max_price": 15000000,
    "deadline": "2026_Q4",
    "developer_name": "Застройщик",
    "lat": 55.75,
    "lng": 37.62,
    "images": ["https://cdn.example.com/img1.jpg", "https://cdn.example.com/img2.jpg"],
    "detail": {
      "unified": { "description": "..." },
      "advantages": []
    }
  },
  "meta": {
    "source": {
      "fetched_at": "2026-02-11T12:00:00+00:00",
      "payload_hash": "a1b2c3d4e5f6..."
    }
  }
}
```

### Ответ с debug=1 и X-Internal-Key (дополнительно data.raw)

```json
{
  "data": {
    "block_id": "abc",
    "title": "ЖК Пример",
    "images": ["https://cdn.example.com/img1.jpg"],
    "detail": { "unified": {...}, "advantages": [...] },
    "raw": {
      "_id": "abc",
      "title": "ЖК Пример",
      "min_price": 5000000,
      "gallery": [...]
    }
  },
  "meta": {
    "source": {
      "fetched_at": "2026-02-11T12:00:00+00:00",
      "payload_hash": "a1b2c3d4e5f6..."
    }
  }
}
```

Без заголовка **X-Internal-Key** (или с неверным ключом) при `?debug=1` ключ **raw** в ответ не включается.

---

## Ограничения

- Новые пакеты не добавлялись.
- Используются только Laravel и стандартный PHP.
- Существующие команды и контракты не менялись без обратной совместимости.
