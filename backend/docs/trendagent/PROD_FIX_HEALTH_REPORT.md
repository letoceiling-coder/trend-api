# Production Fix Health — отчёт

**Дата:** 2026-02-12  
**Сервер:** root@89.169.39.244  
**Проект:** /var/www/trend-api/backend (Laravel)

---

## 1. APP_URL и активный nginx vhost

| Проверка | Результат |
|----------|-----------|
| `grep ^APP_URL= .env` | APP_URL=https://trendagent.siteaccess.ru (значение не логировалось) |
| Активный vhost по домену | `/etc/nginx/sites-enabled/trendagent.siteaccess.ru` |
| `server_name` | trendagent.siteaccess.ru |
| `root` | /var/www/trend-api/backend/public ✅ |
| `nginx -t` | syntax is ok |
| `systemctl is-active nginx` | active |

**Итог:** Домен из .env совпадает с server_name; root уже указывает на `backend/public`.

---

## 2. Laravel: маршруты и доступность

| Команда | Результат |
|---------|-----------|
| `php -v` | PHP 8.3.6 (cli) |
| `php artisan --version` | Laravel Framework 10.50.0 |
| `php artisan config:clear` | Configuration cache cleared successfully. |
| `php artisan route:clear` | Route cache cleared successfully. |
| `php artisan route:list \| grep ta/admin` | Маршруты **появились** после деплоя недостающих файлов (см. ниже). |

**Вывод `route:list` (фрагмент):**

```
GET|HEAD   api/health
GET|HEAD   api/ta/admin/contract-changes
GET|HEAD   api/ta/admin/coverage
GET|HEAD   api/ta/admin/health .......... Api\TaAdmin\HealthController@index
POST       api/ta/admin/pipeline/run
GET|HEAD   api/ta/admin/quality-checks
GET|HEAD   api/ta/admin/sync-runs
```

**Итог:** Маршрут `api/ta/admin/health` присутствует; запросы по домену доходят до Laravel (не HTML 404 от nginx).

---

## 3. Что было исправлено / задеплоено

На сервере была **старая версия кода**: не было блока `ta/admin` в `routes/api.php`, не было контроллеров TaAdmin и части Domain/Models. Выполнено:

| Действие | Файлы / путь |
|----------|----------------|
| Обновлён `routes/api.php` | Добавлен блок `Route::prefix('admin')->middleware('internal.key')` с health, sync-runs, pipeline/run и др. |
| Скопированы контроллеры TaAdmin | `app/Http/Controllers/Api/TaAdmin/*.php` (HealthController, SyncRunsController, PipelineController и др.) |
| Добавлен middleware TaUiGuard | `app/Http/Middleware/TaUiGuard.php`, в `Kernel.php` — алиас `ta_ui.guard` |
| Обновлён `Kernel.php` | Содержит `internal.key` и `ta_ui.guard` |
| Скопированы Domain-сервисы | `app/Domain/TrendAgent/TaHealthService.php`, TaCoverageService.php, TaAlertService.php |
| Скопированы модели | TaPipelineRun, TaReloginEvent, TaContractChange, TaDataQualityCheck, TaContractState в `app/Models/Domain/TrendAgent/` |
| Скопирован Job | `app/Jobs/TaQueueHeartbeatJob.php` |

**Nginx:** Файл `/etc/nginx/sites-enabled/trendagent.siteaccess.ru` **не менялся**: root уже был ` /var/www/trend-api/backend/public`, `try_files $uri $uri/ /index.php?$query_string;` и `location ~ \.php$` с `fastcgi_pass unix:/run/php/php-fpm.sock` присутствовали.

---

## 4. Проверка Health через домен (nginx)

| Проверка | Результат |
|----------|-----------|
| Запрос **без** заголовка X-Internal-Key | HTTP **403**, тело: `{"message":"Internal API key not configured"}` |
| Запрос **с** X-Internal-Key (значение из .env) | HTTP **403**, тело: `{"message":"Internal API key not configured"}` |

**Причина:** В `.env` на сервере **нет** строки `INTERNAL_API_KEY=...` (или она пустая). Middleware `InternalApiKey` при пустом `config('internal.api_key')` возвращает 403 и не проверяет заголовок.

**Что сделать для достижения DoD (401 без ключа, 200 с ключом):**

1. На сервере добавить в `/var/www/trend-api/backend/.env` строку (подставить свой секрет, не коммитить и не логировать):
   ```bash
   INTERNAL_API_KEY=***ваш_длинный_ключ***
   ```
2. Сбросить кэш конфигурации (чтобы Laravel прочитал .env):
   ```bash
   cd /var/www/trend-api/backend && php artisan config:clear
   ```
3. Проверить:
   - Без ключа: `curl -i https://trendagent.siteaccess.ru/api/ta/admin/health` → ожидается **401** JSON `{"message":"Unauthorized"}`.
   - С ключом: `curl -s -H "X-Internal-Key: ***" https://trendagent.siteaccess.ru/api/ta/admin/health` → ожидается **200** и JSON с `data.runtime`, `data.sync` и т.д.

Секреты в отчёте и в командах заменены на `***`.

---

## 5. Права и логи

| Действие | Результат |
|----------|-----------|
| `chown -R www-data:www-data storage bootstrap/cache` | Выполнено |
| `chmod 775` для каталогов storage, `664` для файлов | Выполнено |
| `chmod -R 775 bootstrap/cache` | Выполнено |
| Тест записи от www-data | `sudo -u www-data touch .../storage/logs/_perm_test.log` → **write_ok** |

**Где смотреть логи:**

- Laravel: `/var/www/trend-api/backend/storage/logs/laravel.log`
- Nginx: `/var/log/nginx/access.log`, `/var/log/nginx/error.log`
- Queue: `journalctl -u trend-api-queue -f`
- Schedule: `/var/log/trend-api-schedule.log`

---

## 6. Schedule и queue

| Проверка | Результат |
|----------|-----------|
| `systemctl is-active trend-api-queue` | **active** |
| `tail -n 5 /var/log/trend-api-schedule.log` | Лог существует; записи вида "No scheduled commands are ready to run" при запуске schedule:run |
| `sudo -u www-data php artisan schedule:run -v` | INFO No scheduled commands are ready to run. (корректно) |

**Итог:** Очередь и расписание работают.

---

## 7. Pipeline lock test

Не выполнялся в автоматическом режиме (требуется заданный INTERNAL_API_KEY в .env).

**После установки INTERNAL_API_KEY** выполнить на сервере:

```bash
cd /var/www/trend-api/backend
INTERNAL_KEY=$(grep '^INTERNAL_API_KEY=' .env | cut -d= -f2-)
APP_URL=https://trendagent.siteaccess.ru
payload='{"city_id":"test","lang":"ru","blocks_count":1,"blocks_pages":1,"apartments_pages":1,"dispatch_details":false,"detail_limit":1}'

# 1-й запрос — ожидается 200, в ответе run_id
curl -i -X POST -H "X-Internal-Key: $INTERNAL_KEY" -H "Content-Type: application/json" -d "$payload" "$APP_URL/api/ta/admin/pipeline/run"

# 2-й запрос (тот же city_id/lang) — ожидается 409, lock_until
curl -i -X POST -H "X-Internal-Key: $INTERNAL_KEY" -H "Content-Type: application/json" -d "$payload" "$APP_URL/api/ta/admin/pipeline/run"
```

Секрет в командах заменить на свой; в отчёте не печатать.

---

## 8. Если домен не тот (server_name mismatch)

- Убедиться, что в `.env` `APP_URL` совпадает с доменом, по которому заходите в браузере/curl.
- Проверить, какой vhost обрабатывает запрос: `grep -R server_name /etc/nginx/sites-enabled` и выбрать конфиг с нужным `server_name`.
- Убедиться, что в этом конфиге:
  - `root /var/www/trend-api/backend/public;`
  - `location / { try_files $uri $uri/ /index.php?$query_string; }`
  - `location ~ \.php$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:/run/php/php-fpm.sock; }`
- После правок: `nginx -t && systemctl reload nginx`.

---

## 9. Краткий итог

| Критерий | Статус |
|----------|--------|
| `/api/ta/admin/health` возвращает JSON (не HTML 404) | ✅ Запрос доходит до Laravel |
| 401 без ключа | ⚠️ После добавления INTERNAL_API_KEY в .env и config:clear |
| 200 с ключом | ⚠️ После добавления INTERNAL_API_KEY в .env и config:clear |
| `php artisan route:list \| grep ta/admin/health` | ✅ Маршрут есть |
| Queue worker (systemd) | ✅ active |
| Cron schedule пишет в лог | ✅ /var/log/trend-api-schedule.log |
| Права storage, www-data пишет логи | ✅ Проверено |
| Pipeline lock (200 → 409) | ⚠️ Выполнить вручную после настройки INTERNAL_API_KEY |

**Итог:** Проект на сервере приведён в рабочее состояние: маршруты admin/health и pipeline/run задеплоены, nginx и PHP-FPM отдают запросы в Laravel, очередь и расписание работают, права на storage выставлены. Для полного DoD остаётся **один шаг**: задать **INTERNAL_API_KEY** в `.env` и выполнить `php artisan config:clear`, после чего повторить проверки health (401/200) и при необходимости — pipeline lock test.
