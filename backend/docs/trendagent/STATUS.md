# TrendAgent: статус и архитектура

## Normalization и hashes

### Нормализованный слой

Данные из внешнего API приводятся к **нормализованному** виду и сохраняются в колонке `normalized` (JSON) в таблицах:

- **ta_blocks** — BlockNormalizer: block_id, guid, title, kind, status, min_price, max_price, deadline, developer_name, lat, lng, **images[]** (URLs).
- **ta_apartments** — ApartmentNormalizer: apartment_id, block_id, guid, title, rooms, area_total, floor, price, status, **images[]** (URLs).
- **ta_block_details** — BlockDetailNormalizer: unified + advantages, nearby_places, bank, geo_buildings, apartments_min_price.
- **ta_apartment_details** — ApartmentDetailNormalizer: unified + prices_totals, prices_graph.

Если payload неожиданной формы, в `normalized` пишется `null`, но **RAW всегда сохраняется** (в ta_payload_cache и/или в поле raw сущности). В sync_run при ошибке фиксируется **error_code** (без токенов).

### Хеши payload

- **payload_hash** = sha256(canonical_json(payload)).
- **Canonical JSON:** сортировка ключей, нормализация чисел (1.0 → 1). Одинаковое содержимое даёт один и тот же hash.
- **payload_hash** хранится:
  - в **ta_payload_cache** (каждый сохранённый ответ);
  - в **ta_blocks**, **ta_apartments**, **ta_block_details**, **ta_apartment_details** (для сущностей после sync).

По **payload_hash** можно проверять идентичность данных и дедупликацию.

### RAW storage

При любом запросе к TrendAgent (sync/probe), если включено сохранение RAW (`storeRaw=true`, по умолчанию без `--no-raw`), в **ta_payload_cache** пишется:

- scope, external_id, **endpoint**, **http_status**, **payload**, **payload_hash**, fetched_at.

Сохраняются и успешные, и ошибочные ответы (в т.ч. 4xx/5xx). В кэш не попадают auth_token и секреты; маскирование в логах/ошибках сохранено.

**API:** RAW в ответе только при `?debug=1` и валидном заголовке **X-Internal-Key**. Без ключа debug игнорируется.

---

## Contract Change Alerts

При сохранении RAW в **ta_payload_cache** вызывается **ContractChangeDetector**: сравнивается hash текущего payload с последним известным по (endpoint, city_id, lang). Состояние хранится в **ta_contract_state**; при изменении hash в **ta_contract_changes** пишется одна запись (old/new hash, top-level keys, data keys). Дубли не создаются (unique по endpoint, city_id, lang, old_hash, new_hash). Исключения в детекторе не прерывают сохранение RAW — только warning в лог (без payload и секретов).

**Где смотреть:** таблицы `ta_contract_state`, `ta_contract_changes`.

**Команды:**
- `php artisan ta:contract:check --since=24h` — изменения за период (например 24h, 7d), с группировкой по endpoint.
- `php artisan ta:contract:last [--limit=20]` — последние изменения по каждому (endpoint, city_id, lang).

---

## Data Quality Checks

Результаты проверок качества данных пишутся в **ta_data_quality_checks** (scope, entity_id, city_id, lang, check_name, status: pass|warn|fail, message, context). Контекст и сообщения санитизированы — токены/пароли не сохраняются.

**Scopes:** blocks, apartments, block_detail, apartment_detail (directories, unit_measurements пока без проверок).

**Правила (кратко):**
- **blocks:** обязательные block_id, title, city_id, lang; min_price/max_price >= 0; lat ∈ [-90, 90], lng ∈ [-180, 180] при наличии.
- **apartments:** apartment_id, price >= 0, title — скаляр.
- **block_detail / apartment_detail:** наличие unified_payload, fetched_at не null.

После успешного sync (SyncBlocksJob, SyncApartmentsJob, SyncBlockDetailJob, SyncApartmentDetailJob) автоматически запускаются лёгкие проверки по последним 20 записям (лимит записей за запуск — 100), без торможения sync.

**Команды:**
- `php artisan ta:quality:check [--scope=blocks|apartments|block_detail|apartment_detail|all] [--limit=200] [--cap=500]` — запуск проверок и запись в ta_data_quality_checks.
- `php artisan ta:quality:summary --since=24h` — сводка pass/warn/fail по scope за период.

---

## Telegram Alerts

При заданных **TA_ALERT_TELEGRAM_BOT_TOKEN** и **TA_ALERT_TELEGRAM_CHAT_ID** команда **ta:alerts:check** (по расписанию каждые 5 минут) отправляет в Telegram уведомления при:

- наличии **failed** sync runs за период (--since, по умолчанию 15m): одно сообщение с заголовком, количеством, топ scopes и подсказкой curl/GET на `/api/ta/admin/sync-runs?status=failed&since_hours=...`;
- росте числа **quality_fail** за 24 ч относительно предыдущего запуска (счётчик в кэше): сообщение с Previous → Current и ссылкой на quality-checks;
- отсутствии успешного sync по **blocks** или **apartments** за период: сообщение с перечнем scope и подсказкой на sync-runs.

**Production-ready:**
- **Dedupe:** для каждого типа алерта (failed_runs, no_success, quality_growth, auth_relogin) вычисляется fingerprint (hash), хранится в кэше 30 минут; при совпадении повтор не отправляется.
- **Quiet hours:** ENV **TA_ALERT_QUIET_HOURS** (например `23:00-08:00`) и **TA_ALERT_QUIET_HOURS_TIMEZONE** (по умолчанию `Europe/Kiev`). В этот интервал алерты не отправляются, а счётчик и топ причин копятся в кэше; после выхода из quiet hours отправляется одно сводное сообщение: «During quiet hours we suppressed X alerts» + top reasons.

Сервис **TaAlertService::send($message, $context)** отправляет одно сообщение в чат; текст санитизируется (без секретов/токенов/raw payload). Токены не логируются. Расписание: `ta:alerts:check --since=15m` каждые 5 минут, withoutOverlapping(5).

**Команда:** `php artisan ta:alerts:check [--since=15m]`

**Как включить алерты в production:**

1. В `.env` на сервере (не в репозитории) задать:
   - `TA_ALERT_TELEGRAM_BOT_TOKEN` — токен бота.
   - `TA_ALERT_TELEGRAM_CHAT_ID` — ID чата/группы для уведомлений.
2. Опционально: `TA_ALERT_QUIET_HOURS=23:00-08:00`, `TA_ALERT_QUIET_HOURS_TIMEZONE=Europe/Kiev` — в этот интервал алерты не отправляются, копятся в кэше; после выхода из quiet hours отправляется сводка.
3. Dedupe: один и тот же алерт не дублируется 30 минут (ключ в кэше).
4. В расписании Laravel уже должна быть задача `ta:alerts:check --since=15m` каждые 5 минут (см. `app/Console/Kernel.php`). Убедиться, что cron выполняет `schedule:run` каждую минуту.
5. Ручная проверка: `php artisan ta:alerts:check --since=15m` — при наличии условий отправит сообщение в Telegram (или выведет ошибку отправки).
6. **Fallback при недоступности Telegram:** приложение не падает; ошибка отправки логируется (без токенов). Мониторинг можно дублировать по логам Laravel и по данным health/sync-runs.

**Безопасность:** токены и секреты в Telegram и в логи **никогда не попадают**; сообщения санитизируются.

---

## Admin Monitoring API

Все эндпоинты под префиксом **/api/ta/admin/** защищены заголовком **X-Internal-Key** (тот же ключ, что в `config/internal.php`). Без ключа возвращается **401 Unauthorized**.

| Метод | URL | Назначение |
|-------|-----|------------|
| GET | `/api/ta/admin/sync-runs` | Последние sync runs (scope, status, since_hours, limit). Ответ: data[], meta. |
| GET | `/api/ta/admin/contract-changes` | Изменения контракта API (endpoint, since_hours, limit). |
| GET | `/api/ta/admin/quality-checks` | Результаты проверок качества (scope, status, since_hours, limit). |
| GET | `/api/ta/admin/health` | Сводка: last_success_at по scope, contract_changes_last_24h_count, quality_fail_last_24h_count, pipeline_last_24h_count, pipeline_failed_last_24h_count, **relogin_attempts_last_24h**, **relogin_failed_last_24h**, queue (connection, queue_name), coverage, runtime. |
| POST | `/api/ta/admin/pipeline/run` | One-shot pipeline: запуск sync blocks + apartments (и при необходимости detail jobs). Тело: city_id?, lang?, blocks_count?, blocks_pages?, apartments_pages?, dispatch_details?, detail_limit?. При `QUEUE_CONNECTION=sync` задачи выполняются в рамках запроса; иначе — постановка в очередь и ответ `queued: true`, `run_id` = UUID из ta_pipeline_runs. **Lock:** на (city_id, lang) блокировка 15 мин; при повторном запуске в течение срока — **409** `{message: "Pipeline already running", meta: {lock_until: ISO8601}}`. **Audit:** каждая попытка пишется в **ta_pipeline_runs** (id UUID, city_id, lang, requested_by, params, status: queued|running|success|failed, started_at, finished_at, error_message). В **health** добавлены `pipeline_last_24h_count` и `pipeline_failed_last_24h_count`. |

Используются для панели наблюдения за sync, ошибками, изменениями контрактов и качеством данных. Секреты в ответы не попадают (error_message санитизируется).

---

## Auth self-healing (auto-relogin)

При включённом **TRENDAGENT_AUTO_RELOGIN** сервис при отсутствии/отклонении сессии может выполнить один programmatic login за вызов (**ensureAuthenticated**). Ограничения:

- **Rate-limit:** не чаще одного relogin в 10 минут на (phone, city_id); ключ кэша `ta:auth:relogin:last:{hash}`.
- **Cooldown:** при двух и более неудачах relogin по слоту выставляется глобальный cooldown 30 минут (`ta:auth:relogin:cooldown`); в течение cooldown повторный login не вызывается.
- **Аудит:** каждая попытка (успех/неудача) пишется в **ta_relogin_events** (attempted_at, success, city_id).
- В логах используется только **masked phone** (сырой телефон не пишется).

Подробнее: `docs/trendagent/STAGE_AUTH_SELF_HEALING_REPORT.md`.

---

## UI-стабильность normalized

Нормализаторы блоков и квартир гарантируют **минимальный набор ключей** в выходном normalized (ensure-слой):

- **Blocks:** id (block_id), title, guid, city_id, lang, prices (min_price, price_from), coordinates (lat, lng), images[] — при отсутствии данных значения null или [].
- **Apartments:** id (apartment_id), block_id, title, guid, city_id, lang, price, status, images[].

Фронт может опираться на наличие этих полей при использовании данных из `data.normalized`.
