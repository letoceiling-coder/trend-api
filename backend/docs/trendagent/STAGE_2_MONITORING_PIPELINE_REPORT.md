# Отчёт этапа 2: Admin Monitoring + Pipeline + UI contract

## Что добавлено

### 1. Admin Monitoring API

Контроллеры в `App\Http\Controllers\Api\TaAdmin\`:

- **SyncRunsController** — GET `/api/ta/admin/sync-runs` (scope?, status?, since_hours=24, limit=50, max 200). Ответ: data[] с id, scope, status, items_fetched, items_saved, error_message (санитизирован), created_at, finished_at; meta (count, since_hours).
- **ContractChangesController** — GET `/api/ta/admin/contract-changes` (endpoint?, since_hours=168, limit=100). Сортировка по detected_at, latest first. Поля: endpoint, city_id, lang, old_hash, new_hash, old_top_keys, new_top_keys, detected_at.
- **QualityChecksController** — GET `/api/ta/admin/quality-checks` (scope?, status?, since_hours=24, limit=200). Поля: scope, entity_id, check_name, status, message, created_at.
- **HealthController** — GET `/api/ta/admin/health`. data: sync (last_success_at по blocks, apartments, block_detail, apartment_detail), contract_changes_last_24h_count, quality_fail_last_24h_count, queue (connection, queue_name).

Все маршруты под `Route::prefix('ta/admin')->middleware('internal.key')`.

### 2. Pipeline endpoint

- **POST /api/ta/admin/pipeline/run** (X-Internal-Key обязателен).  
  Тело (JSON): city_id?, lang?, blocks_count (50), blocks_pages (1), apartments_pages (1), dispatch_details (true), detail_limit (50).

Поведение:

- При `QUEUE_CONNECTION=sync`: выполнение SyncBlocksJob и SyncApartmentsJob через `dispatchSync`, при `dispatch_details=true` — постановка detail jobs по свежим blocks/apartments (до detail_limit). Ответ: `data: { queued: false, run_id }`, meta (city_id, lang, counts).
- При очереди (redis и т.п.): dispatch обоих jobs, ответ: `data: { queued: true, run_id: 'dispatched' }`, meta.

При ошибке — 500, `message: "Pipeline failed"`, без секретов в ответе.

### 3. UI-стабильность normalized

- **BlockNormalizer:** добавлен слой `ensureBlockMinimalKeys()`: в выходе всегда есть id, title, guid, city_id, lang, prices (min_price, price_from), coordinates (lat, lng), images[] (при отсутствии — null или []).
- **ApartmentNormalizer:** добавлен слой `ensureApartmentMinimalKeys()`: id, block_id, title, guid, city_id, lang, price, status, images[].

Сырой payload не меняется; недостающие ключи добавляются с null/[].

### 4. Тесты

- Feature: без X-Internal-Key → 401 для admin и pipeline; с ключом → 200, структура data/meta; health содержит ожидаемые ключи; pipeline при Queue::fake() проверяет dispatch SyncBlocksJob и SyncApartmentsJob.
- Unit: нормализованный блок/квартира содержат обязательные UI-ключи даже при минимальном входе.

---

## Как запускать на сервере

Переменная окружения: `INTERNAL_API_KEY` (то же значение подставлять в заголовок X-Internal-Key).

### Примеры curl

```bash
# Health
curl -s -H "X-Internal-Key: YOUR_KEY" "https://your-host/api/ta/admin/health"

# Sync runs за последние 24 ч
curl -s -H "X-Internal-Key: YOUR_KEY" "https://your-host/api/ta/admin/sync-runs?since_hours=24&limit=50"

# Изменения контракта за неделю
curl -s -H "X-Internal-Key: YOUR_KEY" "https://your-host/api/ta/admin/contract-changes?since_hours=168"

# Проверки качества (fail за 24 ч)
curl -s -H "X-Internal-Key: YOUR_KEY" "https://your-host/api/ta/admin/quality-checks?status=fail&since_hours=24"

# Запуск pipeline (one-shot)
curl -s -X POST -H "X-Internal-Key: YOUR_KEY" -H "Content-Type: application/json" \
  -d '{"city_id":"58c665588b6aa52311afa01b","lang":"ru","blocks_count":50,"blocks_pages":1,"apartments_pages":1,"dispatch_details":true,"detail_limit":50}' \
  "https://your-host/api/ta/admin/pipeline/run"
```

На сервере замените `YOUR_KEY` и `https://your-host` на реальные значения.

---

## Где смотреть ошибки

| Что смотреть | Источник |
|--------------|----------|
| Ошибки sync | GET `/api/ta/admin/sync-runs` (поле error_message в data), таблица `ta_sync_runs` |
| Изменения контракта API | GET `/api/ta/admin/contract-changes`, таблица `ta_contract_changes`, команды `ta:contract:last`, `ta:contract:check --since=24h` |
| Проблемы качества данных | GET `/api/ta/admin/quality-checks?status=fail`, таблица `ta_data_quality_checks`, команда `ta:quality:summary --since=24h` |
| Общая сводка | GET `/api/ta/admin/health` (last_success_at по scope, счётчики за 24 ч) |

Секреты и токены в ответы API и логи не выводятся.
