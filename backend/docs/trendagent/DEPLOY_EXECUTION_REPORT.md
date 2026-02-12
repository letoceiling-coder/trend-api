# Отчёт деплоя и выполнения (Admin API, Pipeline)

## 1. Коммит

Выполнен коммит в репозитории:

```
feat(ta): Admin API, pipeline, contract/quality, UI normalized contract
- Admin: GET sync-runs, contract-changes, quality-checks, health (X-Internal-Key)
- POST /api/ta/admin/pipeline/run: one-shot blocks+apartments sync
- ContractChangeDetector, ta_contract_state/changes, ta_data_quality_checks
- Commands: ta:contract:check, ta:contract:last, ta:quality:check, ta:quality:summary
- Block/Apartment normalizers: ensure minimal UI keys
- Docs: STATUS.md, STAGE_2_MONITORING_PIPELINE_REPORT.md, RUN_REPORT
```

**Команда для пуша (выполнить при необходимости):**
```bash
cd /var/www/trend-api  # или путь к корню репозитория
git push origin main
```

---

## 2. Деплой на сервер

После обновления кода на сервере (git pull или копирование):

```bash
cd /var/www/trend-api/backend
composer install --no-dev
php artisan config:cache
php artisan route:cache
php artisan migrate --force
```

**Результат migrate:** заполнить в отчёте (см. ниже).

---

## 3. Команды для выполнения после деплоя

Подставить вместо `<INTERNAL_API_KEY>` значение из `.env` (INTERNAL_API_KEY), вместо `<domain>` — ваш домен (например `api.example.com`).

### 3.1. Миграции

```bash
cd /var/www/trend-api/backend
php artisan migrate --force
```

### 3.2. Health и мониторинг

```bash
# Сводка: sync, контракты, качество, очередь
curl -s -H "X-Internal-Key: <INTERNAL_API_KEY>" https://<domain>/api/ta/admin/health

# Последние sync runs
curl -s -H "X-Internal-Key: <INTERNAL_API_KEY>" https://<domain>/api/ta/admin/sync-runs

# Проверки качества со статусом fail
curl -s -H "X-Internal-Key: <INTERNAL_API_KEY>" "https://<domain>/api/ta/admin/quality-checks?status=fail"
```

### 3.3. Запуск pipeline (заполнение данных под фронт)

```bash
curl -s -X POST -H "Content-Type: application/json" -H "X-Internal-Key: <INTERNAL_API_KEY>" \
  -d '{"blocks_count":50,"blocks_pages":1,"apartments_pages":1,"dispatch_details":true,"detail_limit":50}' \
  https://<domain>/api/ta/admin/pipeline/run
```

При `QUEUE_CONNECTION=redis` задачи уйдут в очередь — должен быть запущен `php artisan queue:work`. При `sync` выполнение идёт в рамках запроса (может занять минуты).

---

## 4. Шаблон отчёта выполнения

Заполнить после выполнения на сервере.

| Шаг | Команда / действие | Результат (HTTP / вывод) |
|-----|--------------------|---------------------------|
| Миграции | `php artisan migrate --force` | _Например: X migrations run. / Nothing to migrate._ |
| Health | `curl .../api/ta/admin/health` | _Например: HTTP 200, в data есть sync, contract_changes_last_24h_count, quality_fail_last_24h_count, queue._ |
| Sync runs | `curl .../api/ta/admin/sync-runs` | _Например: HTTP 200, data: [], meta: { count, since_hours }._ |
| Quality (fail) | `curl .../api/ta/admin/quality-checks?status=fail` | _Например: HTTP 200, data: [], meta._ |
| Pipeline run | `curl -X POST .../api/ta/admin/pipeline/run` | _Например: HTTP 200, data: { queued: true/false, run_id }, meta: { city_id, lang, ... }._ |

**Проверка без ключа:** запрос без заголовка `X-Internal-Key` должен вернуть **401** и `{"message":"Unauthorized"}`.

**При ошибке pipeline (500):** в ответе только `{"message":"Pipeline failed"}`; детали смотреть в логах Laravel (без секретов).

---

## 5. Где смотреть ошибки

| Что проверить | Где |
|---------------|-----|
| Ошибки миграций | Вывод `php artisan migrate --force` |
| Ошибки sync | GET `/api/ta/admin/sync-runs`, поле `error_message` в `data` |
| Изменения контракта | GET `/api/ta/admin/contract-changes`, таблица `ta_contract_changes` |
| Качество данных | GET `/api/ta/admin/quality-checks?status=fail`, таблица `ta_data_quality_checks` |
| Общая сводка | GET `/api/ta/admin/health` |
| Логи приложения | `storage/logs/laravel.log` (на сервере) |

Секреты и токены в ответы API и в логи не выводятся.
