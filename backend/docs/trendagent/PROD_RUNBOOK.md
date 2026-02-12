# TrendAgent Production Runbook (Ubuntu 24.04)

Окружение: проект в `/var/www/trend-api/backend`, пользователь `www-data`. Секреты хранятся только в `.env` на сервере (не в репозитории). Сервер: root@89.169.39.244 (пример).

---

## 0. ENV и первый запуск

Скопировать пример и заполнить на сервере (секреты не коммитить):

```bash
cp /var/www/trend-api/backend/docs/trendagent/env.prod.example /var/www/trend-api/backend/.env
nano /var/www/trend-api/backend/.env
```

**Обязательные переменные:**

| Переменная | Описание | Пример (без секретов) |
|------------|----------|------------------------|
| APP_KEY | `php artisan key:generate` | — |
| INTERNAL_API_KEY | Ключ для /api/ta/admin/* (X-Internal-Key) | задать свой длинный ключ |
| DB_DATABASE, DB_USERNAME, DB_PASSWORD | Подключение к MySQL | trend_api, user, *** |
| REDIS_HOST, REDIS_PORT | Для очереди и кэша при QUEUE_CONNECTION=redis | 127.0.0.1, 6379 |
| QUEUE_CONNECTION | redis (prod) или sync | redis |
| CACHE_DRIVER | redis или file (при redis-очереди лучше redis) | redis |

**Опционально:** TRENDAGENT_DEFAULT_PHONE, TRENDAGENT_DEFAULT_PASSWORD (для auto-relogin); TA_ALERT_TELEGRAM_BOT_TOKEN, TA_ALERT_TELEGRAM_CHAT_ID; TA_UI_ALLOW_IPS (comma-separated IPs); TA_ALERT_QUIET_HOURS (e.g. 23:00-08:00). TRENDAGENT_DETAIL_REFRESH_HOURS=24, TRENDAGENT_DETAIL_BATCH_SIZE=50 — уже в примере.

Полный список: **backend/docs/trendagent/env.prod.example**.

---

## 1. Деплой (пошаговые команды)

Выполнять от root или с sudo. Путь backend: `/var/www/trend-api/backend`.

```bash
cd /var/www/trend-api
git pull

cd /var/www/trend-api/backend
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

После деплоя перезапустить queue worker: `sudo systemctl restart trend-api-queue` (см. раздел 2).

---

## 2. Установка и активация systemd unit (queue worker)

```bash
sudo cp /var/www/trend-api/backend/deploy/trend-api-queue.service /etc/systemd/system/
sudo systemctl daemon-reload
```

Проверить и при необходимости отредактировать unit:

- `User=`, `Group=` — пользователь, от которого запускается воркер (например `www-data` или `trendapi`).
- `WorkingDirectory=` — путь к backend (`/var/www/trend-api/backend`).
- В `ExecStart` параметр `--queue=...` должен совпадать с `TRENDAGENT_QUEUE_NAME` в `.env` (по умолчанию `default`).

Включить автозапуск и запустить:

```bash
sudo systemctl enable trend-api-queue
sudo systemctl start trend-api-queue
```

---

## 3. Расписание (schedule:run каждую минуту)

**Вариант A — crontab пользователя www-data:**

```bash
sudo crontab -u www-data -e
```

Добавить строку:

```
* * * * * cd /var/www/trend-api/backend && /usr/bin/php artisan schedule:run >> /var/log/trend-api-schedule.log 2>&1
```

**Вариант B — файл в /etc/cron.d/:**

```bash
sudo cp /var/www/trend-api/backend/deploy/trend-api-schedule.cron /etc/cron.d/trend-api-schedule
```

В файле должна остаться одна строка расписания (без лишних пробелов в конце). При необходимости изменить пользователя (6-е поле) и путь.

Создать лог-файл и права для www-data:

```bash
sudo touch /var/log/trend-api-schedule.log
sudo chown www-data:www-data /var/log/trend-api-schedule.log
```

---

## 4. Команды управления (queue + просмотр расписания)

Скрипт из репозитория (запускать из корня backend или указать путь):

```bash
/var/www/trend-api/backend/deploy/trend-api-manage.sh start   # запустить воркер
/var/www/trend-api/backend/deploy/trend-api-manage.sh stop    # остановить
/var/www/trend-api/backend/deploy/trend-api-manage.sh restart  # перезапустить
/var/www/trend-api/backend/deploy/trend-api-manage.sh status  # статус воркера + наличие cron
/var/www/trend-api/backend/deploy/trend-api-manage.sh logs    # последние строки журнала воркера
```

Напрямую через systemctl:

```bash
sudo systemctl start trend-api-queue
sudo systemctl stop trend-api-queue
sudo systemctl restart trend-api-queue
sudo systemctl status trend-api-queue
```

---

## 5. Как проверить, что schedule и queue работают

### Через Admin Health API (runtime verification)

Эндпоинт **GET /api/ta/admin/health** (с заголовком **X-Internal-Key**) возвращает блок **data.runtime**:

- **schedule_ok** (bool) — `true`, если cron за последние 2 минуты выполнял `schedule:run` (в кэш пишется `ta:schedule:last_run_at` каждую минуту).
- **queue_ok** (bool) — при `QUEUE_CONNECTION=redis` это `true`, если воркер за последние 2 минуты выполнял heartbeat-задачу (в кэш пишется `ta:queue:worker_heartbeat`). При `sync` всегда `true`.
- **redis_ok** (bool) — при redis-очереди: доступность Redis (ping). При `sync` всегда `true`.
- **last_schedule_run_at**, **last_queue_heartbeat_at** — ISO8601-время последнего обновления или `null`.

**Проверка с сервера:**

```bash
curl -s -H "X-Internal-Key: YOUR_INTERNAL_KEY" https://YOUR_DOMAIN/api/ta/admin/health | jq '.data.runtime'
```

Если **schedule_ok** = `false` — cron для `schedule:run` не запускается или не доходит до кода (проверить crontab и логи).  
Если **queue_ok** = `false` при redis — воркер не обрабатывает очередь или не запущен (проверить `systemctl status trend-api-queue`).  
Если **redis_ok** = `false` — Redis недоступен (хост, порт, сеть).

### Классическая проверка

- **Queue worker:**  
  `sudo systemctl status trend-api-queue` — должен быть `active (running)`.  
  Либо: `php artisan ta:smoke` — в выводе есть проверка Redis (если `QUEUE_CONNECTION=redis`), блок **runtime** (schedule_ok, queue_ok, redis_ok) и подсказка про `systemctl status trend-api-queue`.

- **Schedule:**  
  Каждую минуту выполняется `php artisan schedule:run`. Проверить:
  - логи cron (если пишете в файл): `tail -f /var/log/trend-api-schedule.log`;
  - что задачи из `app/Console/Kernel.php` реально выполняются (например, по логам Laravel или по данным в БД);
  - **runtime.schedule_ok** в health — быстрый индикатор того, что schedule реально запускался.

- **Smoke-проверка:**  
  В каталоге backend:  
  `php artisan ta:smoke`  
  Вывод: health (last_success_at по scope, счётчики, queue, **runtime**), проверка подключения к Redis (если используется), подсказка по проверке воркера.  
  С опцией: `php artisan ta:smoke --dry-run` — то же плюс вывод параметров pipeline без запуска задач и без запросов к TrendAgent.

---

## 6. Логи

- **Queue worker (systemd):**  
  `sudo journalctl -u trend-api-queue -f`  
  или  
  `sudo journalctl -u trend-api-queue -n 200 --no-pager`

- **Schedule (если вывод в файл):**  
  `tail -f /var/log/trend-api-schedule.log`

- **Laravel (приложение):**  
  `tail -f /var/www/trend-api/backend/storage/logs/laravel.log`

Секреты и токены в логи не должны попадать.

---

## 7. Чеклист «после деплоя» (production)

| Шаг | Действие | Проверка |
|-----|----------|----------|
| 1 | Обновить код (git pull / ваш процесс деплоя). | — |
| 2 | В каталоге backend: `composer install --no-dev`. | Без ошибок. |
| 3 | `php artisan migrate --force`. | Миграции применены. |
| 4 | При необходимости: `php artisan config:cache`, `php artisan route:cache`. | — |
| 5 | **Queue:** `sudo systemctl enable trend-api-queue` (один раз), `sudo systemctl start trend-api-queue` (или restart после деплоя). | `sudo systemctl is-active trend-api-queue` → `active`. |
| 6 | **Schedule:** убедиться, что cron для `schedule:run` настроен (раздел 3). Альтернатива: systemd timer (см. ниже). | `sudo crontab -u www-data -l` содержит строку с `schedule:run`, либо проверка через health (см. шаг 7). Лог: `tail -1 /var/log/trend-api-schedule.log` — не пустой и свежий. |
| 7 | **Smoke и runtime:** от пользователя www-data или из backend: `php artisan ta:smoke`. Проверить runtime через API: `curl -s -H "X-Internal-Key: KEY" https://DOMAIN/api/ta/admin/health \| jq .data.runtime` — ожидается `schedule_ok: true`, `queue_ok: true` (при redis), `redis_ok: true`. | ta:smoke без фатальных ошибок; в `data.runtime` все флаги true (или queue_ok/redis_ok по конфигу). |
| 8 | При необходимости: запуск pipeline, проверка admin API (sync-runs, health, coverage). | См. раздел «Pipeline lock и audit» в PROD_FINAL_EXECUTION_REPORT. |

Полный чеклист команд с таблицей «ожидаемый / фактический результат» и раздел Rollback: **PROD_FINAL_EXECUTION_REPORT.md**.

### Альтернатива cron: systemd timer для schedule

Вместо crontab можно использовать systemd timer, вызывающий `php artisan schedule:run` каждую минуту. Пример unit и timer оставить в deploy/ при необходимости; проверка тогда по `data.runtime.schedule_ok` и логам сервиса.
