# STAGE: Runtime verification для production

## Цель

Добавить в production проверки того, что **schedule** и **queue worker** реально работают, и что **Redis** доступен. Результат доступен в **GET /api/ta/admin/health** в блоке **data.runtime**.

---

## Список изменённых/добавленных файлов

| Файл | Изменение |
|------|-----------|
| **app/Console/Kernel.php** | Добавлены: callback каждую минуту — запись в Cache `ta:schedule:last_run_at` (TTL 10 мин); job каждую минуту — `TaQueueHeartbeatJob` (воркер при выполнении пишет `ta:queue:worker_heartbeat`). |
| **app/Jobs/TaQueueHeartbeatJob.php** | **Новый.** Job: при выполнении пишет `Cache::put('ta:queue:worker_heartbeat', now(), 600)`. |
| **app/Domain/TrendAgent/TaHealthService.php** | Добавлены: метод `getRuntimeData(string $queueConnection)`, методы `getLastScheduleRunAt()`, `getLastQueueHeartbeatAt()`, `pingRedis()`. В `getHealthData()` добавлен ключ `runtime` с полями: `schedule_ok`, `queue_ok`, `redis_ok`, `last_schedule_run_at`, `last_queue_heartbeat_at`. |
| **app/Console/Commands/TaSmokeCommand.php** | В вывод добавлен блок runtime (schedule_ok, queue_ok, redis_ok, last_schedule_run_at, last_queue_heartbeat_at). |
| **docs/trendagent/PROD_RUNBOOK.md** | Раздел 4 расширен: «Как проверить, что schedule и queue работают» — подраздел «Через Admin Health API (runtime verification)» с описанием полей `data.runtime` и примером `curl` + `jq`. |
| **tests/Feature/Api/TaAdminApiTest.php** | В `test_admin_health_with_key_returns_200_and_expected_keys` в ожидаемую структуру добавлен блок `runtime` с ключами: `schedule_ok`, `queue_ok`, `redis_ok`, `last_schedule_run_at`, `last_queue_heartbeat_at`. |
| **tests/Unit/Domain/TrendAgent/TaHealthServiceTest.php** | **Новый.** Unit-тесты: `getRuntimeData` для schedule_ok (пустой кэш, свежий run, старый run), для queue_ok (sync, redis+свежий heartbeat, redis+нет heartbeat, redis+устаревший heartbeat); `getLastScheduleRunAt` / `getLastQueueHeartbeatAt` (null и ISO-строка). |

---

## Логика проверок

- **schedule_ok**: в кэше есть ключ `ta:schedule:last_run_at` и значение обновлялось за последние **2 минуты**. Пишется из Kernel schedule каждую минуту при выполнении `schedule:run` (cron).
- **queue_ok**: при `QUEUE_CONNECTION=redis` — в кэше есть `ta:queue:worker_heartbeat` и значение за последние **2 минуты**. При `sync` всегда `true`. Heartbeat пишется воркером при выполнении `TaQueueHeartbeatJob`, который диспатчится из Kernel каждую минуту.
- **redis_ok**: при очереди redis — успешный `Redis::connection()->ping()`. При `sync` всегда `true`.
- **last_schedule_run_at** / **last_queue_heartbeat_at**: ISO8601-строка или `null`.

---

## Команды проверки

### 1. Health API (runtime блок)

```bash
curl -s -H "X-Internal-Key: YOUR_INTERNAL_KEY" https://YOUR_DOMAIN/api/ta/admin/health | jq '.data.runtime'
```

Ожидаемый фрагмент при работающих schedule и воркере (redis):

```json
{
  "schedule_ok": true,
  "queue_ok": true,
  "redis_ok": true,
  "last_schedule_run_at": "2026-02-10T12:34:56+00:00",
  "last_queue_heartbeat_at": "2026-02-10T12:34:55+00:00"
}
```

### 2. Smoke-команда (в каталоге backend)

```bash
php artisan ta:smoke
```

В выводе должны быть строки вида:

- `runtime: schedule_ok=true, queue_ok=true, redis_ok=true`
- `last_schedule_run_at: ...`
- `last_queue_heartbeat_at: ...`

### 3. Unit- и feature-тесты

```bash
php artisan test tests/Unit/Domain/TrendAgent/TaHealthServiceTest.php
php artisan test tests/Feature/Api/TaAdminApiTest.php --filter="test_admin_health"
```

Оба набора должны проходить без ошибок.

### 4. Ручная проверка schedule (локально)

```bash
php artisan schedule:run
# затем сразу:
php artisan tinker --execute="var_dump(\Illuminate\Support\Facades\Cache::get('ta:schedule:last_run_at'));"
# или запрос к health и проверка last_schedule_run_at
```

### 5. Ручная проверка heartbeat (если воркер запущен)

Убедиться, что воркер обрабатывает очередь (например, после `schedule:run` диспатчится `TaQueueHeartbeatJob`). Через 1–2 минуты в health поле `last_queue_heartbeat_at` должно обновиться, а `queue_ok` — быть `true`.

---

## Зависимости

- Для **schedule_ok** нужен cron (или аналог), который раз в минуту запускает `php artisan schedule:run`.
- Для **queue_ok** при redis нужен запущенный воркер (`php artisan queue:work` или systemd unit), обрабатывающий очередь, в которую уходит `TaQueueHeartbeatJob` (по умолчанию `default`).
- Кэш должен быть общий для процесса cron и воркера (например, Redis при `CACHE_STORE=redis`), иначе heartbeat и last_run_at не будут видны в одном месте.
