# Отчёт: выполнение ta:test:mysql:init и ta:test:mysql после деплоя

## Результат проверки на сервере

**Дата проверки:** 2026-02-11 (по запросу).

**Сервер:** root@89.169.39.244, путь `/var/www/trend-api/backend`.

### Выполненные команды (SSH)

```bash
cd /var/www/trend-api/backend
php artisan ta:test:mysql:init --show-sql
php artisan ta:test:mysql
```

### Результат

| Команда | Статус | Вывод |
|--------|--------|--------|
| `ta:test:mysql:init --show-sql` | **FAIL** | `File .env.testing.mysql not found. Copy from .env.testing.mysql.example first.` |
| `ta:test:mysql` | **FAIL** | `File .env.testing.mysql not found.` + подсказка скопировать example и задать DB_* |

**Причина:** на сервере нет файла `.env.testing.mysql` (он в `.gitignore` и не деплоится). Команды на сервере **есть** и отрабатывают корректно — требуют предварительной настройки конфига.

---

## Что сделать, чтобы команды заработали на сервере

### Шаг 1. Закоммитить и задеплоить

Локально (или в среде, где есть доступ к репо и настроенному `.env` для деплоя):

```bash
cd /path/to/backend   # или c:\OSPanel\domains\Parser\TrendAgent\backend
git add app/Console/Commands/TaTestMysqlCommand.php app/Console/Commands/TaTestMysqlInitCommand.php
git add scripts/test-mysql.sh
git add README.md
git commit -m "Add ta:test:mysql and ta:test:mysql:init commands"
git push origin main
php artisan deploy
```

Убедитесь, что в `backend/.env` заданы `DEPLOY_SSH_HOST`, `DEPLOY_REMOTE_BACKEND_PATH`, `DEPLOY_REMOTE_FRONTEND_PATH` (и при необходимости остальные `DEPLOY_*`).

### Шаг 2. На сервере: подготовить .env.testing.mysql

После успешного деплоя по SSH на сервер:

```bash
cd /var/www/trend-api/backend
cp .env.testing.mysql.example .env.testing.mysql
```

Отредактировать `.env.testing.mysql`: задать `DB_DATABASE=trend_api_test`, `DB_USERNAME`, `DB_PASSWORD`, `DB_HOST=127.0.0.1` (и при необходимости `DB_PORT`). Остальное можно оставить из примера.

### Шаг 3. На сервере: выполнить команды

**Вывести SQL для ручного создания БД/пользователя:**
```bash
cd /var/www/trend-api/backend
php artisan ta:test:mysql:init --show-sql
```

Выполнить выведенный SQL в MySQL (под учётной записью с правами CREATE DATABASE / CREATE USER / GRANT), затем убедиться, что в `.env.testing.mysql` указаны те же `DB_USERNAME` и `DB_PASSWORD`.

**Запуск тестов одной командой:**
```bash
cd /var/www/trend-api/backend
php artisan ta:test:mysql
```

Команда проверит наличие `.env.testing.mysql`, что `DB_DATABASE=trend_api_test`, подключение к MySQL, выполнит `migrate:fresh --force` и запустит `./vendor/bin/phpunit -c phpunit.mysql.xml --testdox`.

---

## Ожидаемый вид вывода после настройки

**ta:test:mysql:init --show-sql** (при `DB_HOST=127.0.0.1`, без `--allow-remote`):
```
CREATE DATABASE IF NOT EXISTS `trend_api_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'trend_api_test'@'127.0.0.1' IDENTIFIED BY 'YOUR_PASSWORD';
GRANT ALL PRIVILEGES ON `trend_api_test`.* TO 'trend_api_test'@'127.0.0.1';
CREATE USER IF NOT EXISTS 'trend_api_test'@'localhost' IDENTIFIED BY 'YOUR_PASSWORD';
GRANT ALL PRIVILEGES ON `trend_api_test`.* TO 'trend_api_test'@'localhost';
FLUSH PRIVILEGES;
```

**ta:test:mysql** (при корректном `.env.testing.mysql` и созданной БД/пользователе):
```
Using database: trend_api_test on 127.0.0.1
Running migrate:fresh...
...
PHPUnit 10.x ...
OK (88 tests, ...)
```

---

## Краткий итог

| Действие | Статус |
|----------|--------|
| Выполнить на сервере `ta:test:mysql:init --show-sql` | Выполнено — команда есть; выход с ошибкой: нет `.env.testing.mysql` |
| Выполнить на сервере `ta:test:mysql` | Выполнено — команда есть; выход с ошибкой: нет `.env.testing.mysql` |
| Причина | На сервере не создан файл `.env.testing.mysql` (не коммитится, нужно создать вручную) |
| Что делать | На сервере: **`php artisan ta:test:mysql:setup`** (или cp + правка вручную), затем **`ta:test:mysql:init`** (или --show-sql и выполнить SQL), затем **`ta:test:mysql`** |
