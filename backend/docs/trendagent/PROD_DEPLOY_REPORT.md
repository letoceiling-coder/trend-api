# Отчёт: подготовка production эксплуатации TrendAgent TA

## Добавленные и изменённые файлы

| Файл | Назначение |
|------|------------|
| **app/Domain/TrendAgent/TaHealthService.php** | Сервис здоровья: агрегация last_success_at по scope, счётчики contract/quality за 24 ч, данные очереди. Используется HealthController и ta:smoke. |
| **app/Http/Controllers/Api/TaAdmin/HealthController.php** | Переведён на использование TaHealthService (без изменения контракта ответа). |
| **app/Console/Commands/TaSmokeCommand.php** | Команда `ta:smoke`: вызов TaHealthService и краткий отчёт; проверка подключения Redis при queue=redis; подсказка по проверке воркера; `--dry-run` — вывод параметров pipeline без запросов и без постановки jobs. |
| **deploy/trend-api-queue.service** | Systemd unit для `php artisan queue:work redis --queue=default --sleep=3 --tries=3`. User/WorkingDirectory задаются в unit (по умолчанию www-data, /var/www/trend-api/backend). |
| **deploy/trend-api-schedule.cron** | Пример cron-записи: раз в минуту `php artisan schedule:run` от пользователя www-data, лог в /var/log/trend-api-schedule.log. |
| **deploy/trend-api-manage.sh** | Скрипт управления: start/stop/status/restart/logs для trend-api-queue; status выводит также наличие cron для schedule. |
| **docs/trendagent/PROD_RUNBOOK.md** | Runbook: установка и активация unit и cron, проверка schedule и queue, просмотр логов, чеклист после деплоя. |

---

## Команды на сервере

### Установка unit и запуск воркера

```bash
sudo cp /var/www/trend-api/backend/deploy/trend-api-queue.service /etc/systemd/system/
sudo systemctl daemon-reload
# при необходимости отредактировать User, WorkingDirectory, --queue=
sudo systemctl enable trend-api-queue
sudo systemctl start trend-api-queue
```

### Установка расписания (cron)

Вариант через crontab:

```bash
sudo crontab -u www-data -e
# добавить: * * * * * cd /var/www/trend-api/backend && /usr/bin/php artisan schedule:run >> /var/log/trend-api-schedule.log 2>&1
```

Вариант через /etc/cron.d/:

```bash
sudo cp /var/www/trend-api/backend/deploy/trend-api-schedule.cron /etc/cron.d/trend-api-schedule
sudo touch /var/log/trend-api-schedule.log && sudo chown www-data:www-data /var/log/trend-api-schedule.log
```

### Управление и проверки

```bash
# Управление воркером
/var/www/trend-api/backend/deploy/trend-api-manage.sh start
/var/www/trend-api/backend/deploy/trend-api-manage.sh stop
/var/www/trend-api/backend/deploy/trend-api-manage.sh restart
/var/www/trend-api/backend/deploy/trend-api-manage.sh status
/var/www/trend-api/backend/deploy/trend-api-manage.sh logs

# Smoke-проверка (в каталоге backend)
cd /var/www/trend-api/backend
php artisan ta:smoke
php artisan ta:smoke --dry-run
```

### Логи

```bash
sudo journalctl -u trend-api-queue -f
tail -f /var/log/trend-api-schedule.log
tail -f /var/www/trend-api/backend/storage/logs/laravel.log
```

---

Секреты не хранятся в репозитории; конфигурация — в `.env` на сервере.
