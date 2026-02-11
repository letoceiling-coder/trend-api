# Отчёт о выполнении: RAW + Normalized + Internal API contract

## Цель

Система должна «правильно получать все данные, железобетонно сохранять и отдавать в таком же виде»: RAW storage, слой нормализации, стабильный контракт API.

---

## 1) RAW storage (100% как пришло) — выполнено

- **payload_hash** = `sha256(canonical_json(payload))`. Класс `App\Domain\TrendAgent\Payload\CanonicalPayload`: сортировка ключей, нормализация чисел, стабильная сериализация.
- Во всех TrendAgent-запросах (Sync + Probe) запись в **ta_payload_cache** через `PayloadCacheWriter::create()` с полями: **scope, external_id, endpoint, http_status, payload, payload_hash, fetched_at**.
- **Canonical JSON:** рекурсивная сортировка ключей объектов, числа вида 1.0 → 1.
- **--no-raw** сохранён: при `storeRaw=false` в кэш не пишем. При **storeRaw=true** сохраняем всегда, в т.ч. ответы с ошибками (4xx/5xx).

**Файлы:**  
`app/Domain/TrendAgent/Payload/CanonicalPayload.php`, `PayloadCacheWriter.php`, миграция `2026_02_11_140000_add_endpoint_http_status_payload_hash_to_ta_payload_cache.php`, модель `TaPayloadCache`, `TrendAgentSyncService` (все места записи в кэш), все Probe-команды.

---

## 2) Normalization layer — выполнено

- **Нормализаторы:** `BlockNormalizer`, `ApartmentNormalizer`, `BlockDetailNormalizer`, `ApartmentDetailNormalizer` — приводят payload к одной структуре (скаляры, числа, координаты, цены, статус).
- В **ta_blocks, ta_apartments, ta_block_details, ta_apartment_details** добавлена JSON-колонка **normalized** и колонка **payload_hash**; заполняются при sync в той же транзакции.
- Если payload неожиданной формы → **normalized = null**, RAW всё равно сохраняется; в **ta_sync_runs** при ошибке записывается **error_code** (без токенов).

**Файлы:**  
`app/Domain/TrendAgent/Normalizers/*.php`, миграции `2026_02_11_150000_add_normalized_payload_hash_to_ta_entities.php`, `2026_02_11_150001_add_error_code_to_ta_sync_runs.php`, модели TaBlock, TaApartment, TaBlockDetail, TaApartmentDetail, TaSyncRun, `SyncRunner::finishFail($run, $e, $context, $errorCode)`.

---

## 3) Internal API contract — выполнено

- В **/api/ta/*** в **data** всегда отдаётся **normalized** (или fallback из полей модели, если normalized пустой).
- В ответах show добавлен **meta.source**: **fetched_at**, **payload_hash**.
- **RAW** в ответе только при **debug=1** и (заголовок **X-Internal-Key** с ключом из `INTERNAL_API_KEY` **или** `APP_ENV=local`).

**Файлы:**  
`app/Http/Controllers/Api/Ta/TaBlocksController.php`, `TaApartmentsController.php` (метод `allowRawInResponse()`).

---

## 4) Тесты — выполнено

- **Unit:** `CanonicalPayloadTest` — стабильность canonical_json и payload_hash.
- **Unit:** `BlockNormalizerTest`, `ApartmentNormalizerTest` — разные форматы, отсутствующие ключи, массивы/объекты.
- **Feature:** `TaApiTest` — `/api/ta/blocks/{id}` и `/api/ta/apartments/{id}` возвращают normalized и **meta.source**; проверка, что **raw** не отдаётся без debug.

**Файлы:**  
`tests/Unit/Domain/TrendAgent/Payload/CanonicalPayloadTest.php`, `tests/Unit/Domain/TrendAgent/Normalizers/BlockNormalizerTest.php`, `ApartmentNormalizerTest.php`, `tests/Feature/Api/TaApiTest.php` (доп. тесты meta.source и отсутствия raw).

---

## 5) Документация — выполнено

- **README:** раздел «RAW и нормализованные данные», как включить debug, как проверить payload_cache и payload_hash.
- **docs/trendagent/STATUS.md:** раздел «Normalization и hashes» (нормализаторы, payload_hash, RAW storage).

---

## Список изменённых/добавленных файлов

| Категория | Файлы |
|-----------|--------|
| **Payload** | `app/Domain/TrendAgent/Payload/CanonicalPayload.php`, `PayloadCacheWriter.php` |
| **Normalizers** | `app/Domain/TrendAgent/Normalizers/BlockNormalizer.php`, `ApartmentNormalizer.php`, `BlockDetailNormalizer.php`, `ApartmentDetailNormalizer.php` |
| **Sync** | `app/Domain/TrendAgent/Sync/TrendAgentSyncService.php`, `SyncRunner.php` |
| **Migrations** | `2026_02_11_140000_add_endpoint_http_status_payload_hash_to_ta_payload_cache.php`, `2026_02_11_150000_add_normalized_payload_hash_to_ta_entities.php`, `2026_02_11_150001_add_error_code_to_ta_sync_runs.php` |
| **Models** | `TaPayloadCache.php`, `TaBlock.php`, `TaApartment.php`, `TaBlockDetail.php`, `TaApartmentDetail.php`, `TaSyncRun.php` |
| **Controllers** | `TaBlocksController.php`, `TaApartmentsController.php` |
| **Probe** | `TrendagentProbeBlocksSearch.php`, `TrendagentProbeApartmentsSearch.php`, `TrendagentProbeBlockDetail.php`, `TrendagentProbeApartmentDetail.php` |
| **Tests** | `CanonicalPayloadTest.php`, `BlockNormalizerTest.php`, `ApartmentNormalizerTest.php`, `TaApiTest.php`, правки в Sync-тестах |
| **Docs** | `README.md`, `docs/trendagent/STATUS.md`, `docs/trendagent/RAW_NORMALIZED_API_REPORT.md` |

---

## Команды для сервера

```bash
cd /var/www/trend-api/backend

# Миграции
php artisan migrate --force

# Синк (RAW + normalized)
php artisan trendagent:sync:blocks --city=58c665588b6aa52311afa01b --lang=ru
php artisan trendagent:sync:apartments --city=58c665588b6aa52311afa01b --lang=ru

# Проверка API (без raw)
curl -s "https://your-domain.com/api/ta/blocks?city_id=58c665588b6aa52311afa01b&lang=ru&count=2" | jq '.data[0], .meta'
curl -s "https://your-domain.com/api/ta/blocks/BLOCK_ID" | jq '.data, .meta.source'

# С debug (raw в ответе)
curl -s -H "X-Internal-Key: YOUR_KEY" "https://your-domain.com/api/ta/blocks/BLOCK_ID?debug=1" | jq '.data.raw | keys'
```

---

## Примеры ответа API

**Обычный (без debug):**
```json
{
  "data": {
    "block_id": "abc",
    "title": "ЖК Пример",
    "min_price": 5000000,
    "detail": { "unified": {...}, "advantages": [...] }
  },
  "meta": {
    "source": {
      "fetched_at": "2026-02-11T12:00:00+00:00",
      "payload_hash": "a1b2c3..."
    }
  }
}
```

**С debug=1 и X-Internal-Key:** в `data` дополнительно появляется ключ **raw** (сырой payload). Без debug или без ключа **raw** не отдаётся.

---

## Ограничения

- Внешние пакеты не добавлялись.
- Использованы только Laravel и стандартный PHP.

**Доправка в этой сессии:** в `syncUnitMeasurements` оставшийся вызов `TaPayloadCache::create` заменён на `PayloadCacheWriter::create` для единообразия записи в ta_payload_cache (endpoint, http_status, payload_hash).
