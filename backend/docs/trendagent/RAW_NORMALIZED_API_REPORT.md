# Отчёт: RAW storage, Normalization, Internal API contract

## 1. Изменённые файлы

### RAW storage (payload_hash, ta_payload_cache)

| Файл | Изменение |
|------|-----------|
| `app/Domain/TrendAgent/Payload/CanonicalPayload.php` | **Новый.** Канонический JSON (сортировка ключей, нормализация чисел) и `payloadHash()`. |
| `app/Domain/TrendAgent/Payload/PayloadCacheWriter.php` | **Новый.** Единая запись в ta_payload_cache с endpoint, http_status, payload_hash. |
| `database/migrations/2026_02_11_140000_add_endpoint_http_status_payload_hash_to_ta_payload_cache.php` | **Новый.** Колонки endpoint, http_status, payload_hash в ta_payload_cache. |
| `app/Models/Domain/TrendAgent/TaPayloadCache.php` | Добавлены fillable: endpoint, http_status, payload_hash. |
| `app/Domain/TrendAgent/Sync/TrendAgentSyncService.php` | Все записи в кэш через PayloadCacheWriter; при storeRaw сохраняются и ошибки (4xx/5xx); в saveBlocks/saveApartments добавлены normalized, payload_hash. |
| `app/Console/Commands/TrendagentProbeBlocksSearch.php` | Использует PayloadCacheWriter. |
| `app/Console/Commands/TrendagentProbeApartmentsSearch.php` | Использует PayloadCacheWriter. |
| `app/Console/Commands/TrendagentProbeBlockDetail.php` | Использует PayloadCacheWriter. |
| `app/Console/Commands/TrendagentProbeApartmentDetail.php` | Использует PayloadCacheWriter. |

### Normalization

| Файл | Изменение |
|------|-----------|
| `app/Domain/TrendAgent/Normalizers/BlockNormalizer.php` | **Новый.** Нормализация блока (block_id, title, prices, lat/lng, status и т.д.). |
| `app/Domain/TrendAgent/Normalizers/ApartmentNormalizer.php` | **Новый.** Нормализация квартиры. |
| `app/Domain/TrendAgent/Normalizers/BlockDetailNormalizer.php` | **Новый.** Нормализация block detail (unified + остальные payload). |
| `app/Domain/TrendAgent/Normalizers/ApartmentDetailNormalizer.php` | **Новый.** Нормализация apartment detail. |
| `database/migrations/2026_02_11_150000_add_normalized_payload_hash_to_ta_entities.php` | **Новый.** Колонки normalized, payload_hash в ta_blocks, ta_apartments, ta_block_details, ta_apartment_details. |
| `database/migrations/2026_02_11_150001_add_error_code_to_ta_sync_runs.php` | **Новый.** Колонка error_code в ta_sync_runs. |
| `app/Models/Domain/TrendAgent/TaBlock.php` | fillable/casts: normalized, payload_hash. |
| `app/Models/Domain/TrendAgent/TaApartment.php` | fillable/casts: normalized, payload_hash. |
| `app/Models/Domain/TrendAgent/TaBlockDetail.php` | fillable/casts: normalized, payload_hash. |
| `app/Models/Domain/TrendAgent/TaApartmentDetail.php` | fillable/casts: normalized, payload_hash. |
| `app/Models/Domain/TrendAgent/TaSyncRun.php` | fillable: error_code. |
| `app/Domain/TrendAgent/Sync/SyncRunner.php` | finishFail() принимает опциональный error_code. |

### Internal API

| Файл | Изменение |
|------|-----------|
| `app/Http/Controllers/Api/Ta/TaBlocksController.php` | index/show возвращают data из normalized (или fallback Resource); meta.source (fetched_at, payload_hash); RAW только при debug=1 и (X-Internal-Key или APP_ENV=local). |
| `app/Http/Controllers/Api/Ta/TaApartmentsController.php` | Аналогично. |

### Тесты

| Файл | Изменение |
|------|-----------|
| `tests/Unit/Domain/TrendAgent/Payload/CanonicalPayloadTest.php` | **Новый.** Стабильность canonical_json и payload_hash. |
| `tests/Unit/Domain/TrendAgent/Normalizers/BlockNormalizerTest.php` | **Новый.** |
| `tests/Unit/Domain/TrendAgent/Normalizers/ApartmentNormalizerTest.php` | **Новый.** |
| `tests/Feature/Api/TaApiTest.php` | Тесты meta.source, отсутствие raw без debug; правка дубликата и моков. |
| `tests/Unit/Domain/TrendAgent/Sync/TrendAgentSyncBlocksTest.php` | Ожидание TaBlock::count() = 2. |
| `tests/Unit/Domain/TrendAgent/Sync/TrendAgentSyncServiceTest.php` | Mock get() -> twice() для upsert. |
| `tests/Unit/Domain/TrendAgent/Sync/TrendAgentSyncApartmentDetailTest.php` | Mock ensureAuthenticated. |
| `tests/Unit/Domain/TrendAgent/Sync/TrendAgentSyncApartmentsTest.php` | Mock ensureAuthenticated. |

### Документация

| Файл | Изменение |
|------|-----------|
| `README.md` | Блок «RAW и нормализованные данные», режим отладки, проверка payload_cache (уже были/дополнены). |
| `docs/trendagent/STATUS.md` | Раздел Normalization + hashes (уже был). |

---

## 2. Команды для сервера

```bash
# Миграции
cd /var/www/trend-api/backend
php artisan migrate --force

# Синк (с сохранением RAW и заполнением normalized/payload_hash)
php artisan trendagent:sync:blocks --city=58c665588b6aa52311afa01b --lang=ru
php artisan trendagent:sync:apartments --city=58c665588b6aa52311afa01b --lang=ru

# Проверка API (без debug — без raw)
curl -s "https://your-domain.com/api/ta/blocks?city_id=58c665588b6aa52311afa01b&lang=ru&count=2" | jq '.data[0], .meta'
curl -s "https://your-domain.com/api/ta/blocks/BLOCK_ID" | jq '.data, .meta.source'

# С debug и внутренним ключом (raw в ответе)
curl -s -H "X-Internal-Key: YOUR_INTERNAL_API_KEY" "https://your-domain.com/api/ta/blocks/BLOCK_ID?debug=1" | jq '.data.raw | keys'
```

---

## 3. Примеры ответа API

### Обычный ответ (GET /api/ta/blocks/{block_id})

```json
{
  "data": {
    "block_id": "abc123",
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
    "detail": {
      "unified": { ... },
      "advantages": [ ... ]
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

### Ответ с debug=1 и X-Internal-Key (то же + raw)

```json
{
  "data": {
    "block_id": "abc123",
    "title": "ЖК Пример",
    "detail": {
      "unified": {...},
      "advantages": [...],
      "raw": {
        "unified_payload": {...},
        "advantages_payload": [...],
        ...
      }
    },
    "raw": { "_id": "abc123", "title": "...", "min_price": 5000000, ... }
  },
  "meta": {
    "source": {
      "fetched_at": "2026-02-11T12:00:00+00:00",
      "payload_hash": "a1b2c3d4e5f6..."
    }
  }
}
```

Без `debug=1` или без валидного `X-Internal-Key` / `APP_ENV=local` ключа **raw** в ответ не попадает.
