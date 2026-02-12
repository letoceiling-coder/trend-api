# Production Final Execution Report (TrendAgent TA)

Отчёт о проверках при финализации production-эксплуатации. Заполнить колонку «Фактический результат» на сервере после выполнения команд. Секреты в репозиторий не попадают; все команды с ключами/доменами — с плейсхолдерами.

**Сервер:** Ubuntu 24.04, root@89.169.39.244 (или ваш хост). Путь backend: `/var/www/trend-api/backend`. Пользователь для cron/worker: `www-data`. Подставить `YOUR_INTERNAL_KEY`, `YOUR_DOMAIN` только на сервере.

---

## Чеклист выполнения (на сервере)

| Шаг | Команда (root или www-data) | Ожидаемый результат | Фактический результат |
|-----|-----------------------------|---------------------|------------------------|
| 0 | Подготовка .env: `cp backend/docs/trendagent/env.prod.example backend/.env` и заполнить APP_KEY, INTERNAL_API_KEY, DB_*, REDIS_* | Файл .env создан, `php artisan key:generate` при необходимости | |
| 1 | `cd /var/www/trend-api && git pull` затем `cd backend && composer install --no-dev` | Код обновлён, зависимости установлены без ошибок | |
| 2 | `cd /var/www/trend-api/backend && php artisan migrate --force` | Миграции применены (Nothing to migrate / Ran ... migrations) | |
| 3 | `php artisan config:cache && php artisan route:cache && php artisan view:cache` | Кэш создан без ошибок | |
| 4 | `sudo systemctl enable trend-api-queue && sudo systemctl start trend-api-queue` | enable: done; start: без ошибок | |
| 5 | `sudo systemctl is-active trend-api-queue` | Вывод: `active` | |
| 6 | `sudo crontab -u www-data -l \| grep schedule:run` | Строка с `schedule:run` и путём к backend | |
| 7 | `tail -1 /var/log/trend-api-schedule.log` | Не пусто, запись за последние минуты (или пусто до первого запуска cron) | |
| 8 | `cd /var/www/trend-api/backend && php artisan ta:smoke` | Вывод health, runtime (schedule_ok, queue_ok, redis_ok), без фатальных ошибок | |
| 9 | `curl -s -H "X-Internal-Key: YOUR_INTERNAL_KEY" https://YOUR_DOMAIN/api/ta/admin/health \| jq .data.runtime` | JSON: **schedule_ok**, **queue_ok**, **redis_ok** = true (чеклист runtime) | |
| 10 | `curl -s -H "X-Internal-Key: YOUR_INTERNAL_KEY" "https://YOUR_DOMAIN/api/ta/admin/sync-runs?since_hours=24" \| jq .` | Ответ с `data` (массив sync runs) и `meta` | |
| 11 | `curl -s -H "X-Internal-Key: YOUR_INTERNAL_KEY" https://YOUR_DOMAIN/api/ta/admin/health \| jq .data.coverage` | JSON с полями coverage (blocks_total, apartments_total, …) | |
| 12 | Первый POST pipeline/run, затем второй с теми же city_id/lang | 200 + run_id; 409 + lock_until (см. раздел Pipeline в runbook) | |
| 13 | `cd /var/www/trend-api/backend && php artisan ta:alerts:check --since=15m` | Выполняется без падения; при настроенных Telegram — сообщение в чат или «Sent N alert(s)» | |

---

## Проверка nginx (TA admin security)

| Шаг | Действие | Ожидаемый результат | Фактический результат |
|-----|----------|----------------------|------------------------|
| N1 | С allowlist и фрагментом в server: запрос к `/api/ta/admin/health` **с IP не из allowlist** (без ключа или с ключом) | **403 Forbidden** (nginx, до приложения) | |
| N2 | С того же IP запрос к `/api/ta/admin/health` с **X-Internal-Key** после добавления этого IP в allowlist и `nginx -t && systemctl reload nginx` | 200 и JSON health | |
| N3 | Запрос к `/admin/ta` с IP не из allowlist, без Basic Auth | 401 (логин) или 403 (если deny раньше) | |
| N4 | Запрос к `/admin/ta` с Basic Auth (логин/пароль из htpasswd) | 200 (или отдача SPA) | |

---

## Rollback

### Nginx (откат защиты TA admin)

- Закомментировать или удалить в конфиге nginx добавленные `location /api/ta/admin/`, `location /admin/ta`, `location /admin/ta/` (и include фрагмента, если использовался).
- Выполнить: `sudo nginx -t && sudo systemctl reload nginx`.
- Доступ к `/api/ta/admin/*` снова только по приложению (X-Internal-Key). Публичный `/api/ta/*` не менялся.

### Queue worker (systemd)

- Остановить: `sudo systemctl stop trend-api-queue`.
- Отключить автозапуск: `sudo systemctl disable trend-api-queue`.
- Удалить unit (по желанию): `sudo rm /etc/systemd/system/trend-api-queue.service && sudo systemctl daemon-reload`.
- Очередь перестанет обрабатываться; при `QUEUE_CONNECTION=sync` фоновые задачи не затронуты.

### Schedule (cron)

- Удалить строку с `schedule:run` из crontab: `sudo crontab -u www-data -e`.
- Либо удалить файл: `sudo rm /etc/cron.d/trend-api-schedule` (если использовался вариант из cron.d).
- Расписание (ta:alerts:check, heartbeat и т.д.) перестанет выполняться по крону; команды можно запускать вручную.

---

## Что проверить в health/runtime после деплоя (чеклист)

- **data.runtime.schedule_ok** — должен быть **true** (cron за последние ~2 мин вызывал schedule:run).
- **data.runtime.queue_ok** — должен быть **true** (воркер при redis делал heartbeat).
- **data.runtime.redis_ok** — должен быть **true** (Redis доступен при redis-очереди).
- **data.sync** — last_success_at по scope (blocks, apartments).
- **data.coverage** — blocks_total, apartments_total и др. отображаются.
- **data.pipeline_last_24h_count**, **data.pipeline_failed_last_24h_count** — счётчики pipeline.
- **data.relogin_attempts_last_24h**, **data.relogin_failed_last_24h** — счётчики relogin за 24 ч.

Команда для быстрой проверки runtime:

```bash
curl -s -H "X-Internal-Key: YOUR_INTERNAL_KEY" https://YOUR_DOMAIN/api/ta/admin/health | jq '.data.runtime'
```
