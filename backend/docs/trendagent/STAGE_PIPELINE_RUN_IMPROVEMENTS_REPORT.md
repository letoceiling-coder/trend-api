# STAGE: Улучшения /api/ta/admin/pipeline/run

## Цель

Добавить блокировку по (city_id, lang), audit-лог в таблицу ta_pipeline_runs, счётчики pipeline в health и тесты.

---

## Список изменённых/добавленных файлов

| Файл | Изменение |
|------|-----------|
| **database/migrations/2026_02_12_120000_create_ta_pipeline_runs_table.php** | **Новый.** Таблица ta_pipeline_runs: id (UUID PK), city_id, lang, requested_by (nullable), params (json), status (queued\|running\|success\|failed), started_at, finished_at (nullable), error_message (nullable), timestamps. Индексы по city_id, lang, status, (city_id, lang, started_at). |
| **app/Models/Domain/TrendAgent/TaPipelineRun.php** | **Новый.** Модель с fillable/casts; статический метод createRecord(params, status, requestedBy) создаёт запись с UUID. |
| **app/Http/Controllers/Api/TaAdmin/PipelineController.php** | Перед запуском проверка lock по ключу ta:pipeline:lock:{city_id}:{lang} (Cache, TTL 15 мин). При наличии lock — ответ **409** `{message: "Pipeline already running", meta: {lock_until: ISO8601}}`. После проверки: создание TaPipelineRun (status running при sync, queued при async), установка lock, выполнение или постановка в очередь. При sync: при успехе обновление записи (status success, finished_at); при исключении — status failed, error_message. requested_by формируется из IP и User-Agent. Ответ при async: run_id = id созданной записи (UUID). |
| **app/Domain/TrendAgent/TaHealthService.php** | В getHealthData() добавлены запросы к TaPipelineRun: pipeline_last_24h_count (started_at за последние 24 ч), pipeline_failed_last_24h_count (status=failed и started_at за 24 ч). Эти поля добавлены в возвращаемый массив. |
| **app/Console/Commands/TaSmokeCommand.php** | В вывод health добавлена строка pipeline_last_24h и pipeline_failed_last_24h. |
| **tests/Feature/Api/TaAdminApiTest.php** | В структуру health добавлены pipeline_last_24h_count, pipeline_failed_last_24h_count. test_admin_pipeline_run_with_key_dispatches_jobs: проверка run_id не пустой и assertDatabaseHas ta_pipeline_runs (id, city_id, lang, status queued). Добавлены test_admin_pipeline_run_second_request_same_city_lang_returns_409 (два запроса подряд с одинаковыми city_id, lang — второй 409, message, meta.lock_until) и test_admin_pipeline_run_creates_ta_pipeline_runs_record (проверка создания записи с params и status). |
| **docs/trendagent/STATUS.md** | В таблице Admin API: описание pipeline/run дополнено (lock 15 мин, 409, ta_pipeline_runs); в health добавлены pipeline_last_24h_count, pipeline_failed_last_24h_count. |
| **README.md** | В описании health — pipeline_last_24h_count, pipeline_failed_last_24h_count; в описании pipeline/run — lock 15 мин, 409, audit ta_pipeline_runs. |

---

## Логика

### Lock

- Ключ кэша: `ta:pipeline:lock:{city_id}:{lang}`.
- Значение: ISO8601 время окончания блокировки (lock_until).
- TTL: 15 минут (900 с).
- При входе в run(): если ключ есть — ответ 409 с message и meta.lock_until. Иначе после создания записи и перед выполнением/очередью вызывается Cache::put(key, lock_until, 900).

### Audit (ta_pipeline_runs)

- Запись создаётся в начале обработки (до установки lock и до dispatch/sync).
- id — UUID; city_id, lang — из запроса; requested_by — конкатенация IP и User-Agent (или null); params — json (city_id, lang, blocks_count, blocks_pages, apartments_pages, dispatch_details, detail_limit); status — running (sync) или queued (async); started_at — now().
- При синхронном выполнении: при успехе — update status=success, finished_at=now(); при исключении — update status=failed, finished_at=now(), error_message (обрезано до 1000 символов).

### Health

- pipeline_last_24h_count: количество записей в ta_pipeline_runs с started_at >= now() - 24h.
- pipeline_failed_last_24h_count: количество записей с status=failed и started_at за последние 24 ч.

---

## Команды проверки

```bash
php artisan migrate
php artisan test tests/Feature/Api/TaAdminApiTest.php --filter="pipeline"
php artisan test tests/Feature/Api/TaAdminApiTest.php --filter="health"
```

---

## Поведение API

- **POST /api/ta/admin/pipeline/run** с телом без lock: 200, data.run_id = UUID, запись в ta_pipeline_runs.
- Повторный **POST** с теми же city_id, lang в течение 15 мин: **409**, `{ "message": "Pipeline already running", "meta": { "lock_until": "2026-02-12T14:30:00+00:00" } }`.
- **GET /api/ta/admin/health**: в data присутствуют pipeline_last_24h_count и pipeline_failed_last_24h_count.
