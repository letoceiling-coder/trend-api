<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

---

## Установка и тесты

**Прод (без dev-зависимостей):**
```bash
composer install --no-dev
```

**С dev-зависимостями (для тестов):**
```bash
composer install
```

**Запуск тестов** (команды `php artisan test` в проекте нет, используется PHPUnit напрямую). Два режима:

1. **SQLite in-memory (по умолчанию, быстро):**
```bash
php vendor/bin/phpunit -c phpunit.xml --testdox
php vendor/bin/phpunit -c phpunit.xml --filter TaApiTest --testdox
php vendor/bin/phpunit -c phpunit.xml --filter TrendAuthServiceTest --testdox
```

2. **MySQL тестовая БД** (отдельная БД, не production):
```bash
php artisan ta:test:mysql
```
или `./scripts/test-mysql.sh`. Вручную: `./vendor/bin/phpunit -c phpunit.mysql.xml --testdox`.

### Тестовое окружение (в т.ч. на сервере)

Тесты **не используют production БД**. **phpunit.xml** — SQLite in-memory. **phpunit.mysql.xml** — MySQL (параметры из `.env.testing.mysql` при запуске через `ta:test:mysql`). **.env.testing.mysql** — в `.gitignore`; пароли не коммитить.

Если с `-c phpunit.xml` подхватывается MySQL из `.env`, задайте `DB_CONNECTION=sqlite` и `DB_DATABASE=:memory:`.

---

### Запуск тестов MySQL на сервере

Один раз настроить, затем запускать одной командой.

#### 1. Файл .env.testing.mysql

Переменные для тестов берутся **только** из `backend/.env.testing.mysql` (не из `.env`).

```bash
cd /var/www/trend-api/backend
cp .env.testing.mysql.example .env.testing.mysql
```

Заполните в `.env.testing.mysql`:
- `DB_DATABASE=trend_api_test` (обязательно, иначе команда не запустится)
- `DB_USERNAME` — пользователь MySQL для тестов
- `DB_PASSWORD` — пароль (не коммитить; в консоль и логи не выводится)
- **`DB_HOST=127.0.0.1`** — рекомендуется вместо `localhost` (избегаем сокет-авторизации)

Остальные переменные (APP_KEY, CACHE_STORE, QUEUE_CONNECTION и т.д.) можно оставить из примера.

#### 2. Инициализация тестовой БД и пользователя (init)

Команда подключается к MySQL по **DB_HOST** и **DB_PORT** из `.env.testing.mysql`. Если подключение не удаётся (нет DB_INIT_* или ошибка соединения) — выводит SQL для ручного выполнения (не падает с ошибкой).

**Права по умолчанию — только localhost/127.0.0.1:** создаются пользователи `user@'localhost'` и при `DB_HOST=127.0.0.1` также `user@'127.0.0.1'`. **GRANT для `user@'%'` по умолчанию не выдаётся** (безопаснее). Удалённый доступ включается явно:
- в `.env.testing.mysql`: **`DB_ALLOW_REMOTE=true`**, или
- флаг команды: **`--allow-remote`**

Рекомендуется **DB_HOST=127.0.0.1** (не `localhost`).

**Вариант A — только вывести SQL:**
```bash
php artisan ta:test:mysql:init
```
Выведет инструкцию и блок SQL под текущий DB_HOST. Только сырой SQL:
```bash
php artisan ta:test:mysql:init --show-sql
```

**Вариант B — автоматическое создание** (если заданы `DB_INIT_USERNAME` и при необходимости `DB_INIT_PASSWORD` в `.env.testing.mysql`):
```bash
php artisan ta:test:mysql:init
```
Создаются база `trend_api_test`, пользователь и GRANT только для localhost/127.0.0.1 (и для `%` при `DB_ALLOW_REMOTE=true` или `--allow-remote`). Пароли в консоль и логи не выводятся.

#### 3. Запуск тестов одной командой

```bash
cd /var/www/trend-api/backend
php artisan ta:test:mysql
```

Команда:
- проверяет наличие `.env.testing.mysql` (если нет — выводит инструкцию и exit 1);
- читает переменные только из этого файла;
- проверяет safety: `DB_DATABASE` должен быть ровно `trend_api_test` (иначе exit 1);
- проверяет подключение к MySQL;
- выполняет `migrate:fresh --force` в тестовой БД;
- запускает `./vendor/bin/phpunit -c phpunit.mysql.xml --testdox`.

**Альтернатива — bash-скрипт:**
```bash
./scripts/test-mysql.sh
```

#### 4. Ручной запуск PHPUnit по MySQL

Если БД и миграции уже готовы:
```bash
./vendor/bin/phpunit -c phpunit.mysql.xml --testdox
```
Учётные данные — из окружения или из `phpunit.mysql.xml` (по умолчанию trend_api_test / root / пустой пароль).

#### 5. Меры безопасности

- **Блокировка по имени БД:** команда `ta:test:mysql` не запустится, если в `.env.testing.mysql` указано `DB_DATABASE` отличное от `trend_api_test`.
- **Отдельный конфиг:** используется только `.env.testing.mysql`, production `.env` не подмешивается.
- **Пароли:** `DB_PASSWORD` и `DB_INIT_PASSWORD` нигде не выводятся в консоль и не пишутся в логи.
- **APP_ENV:** если в загруженном окружении `APP_ENV` не `testing`, выводится предупреждение (не блокировка); PHPUnit сам выставляет `APP_ENV=testing` через `phpunit.mysql.xml`.

**Миграции и MySQL:** для таблиц `ta_sync_runs` и `ta_payload_cache` на MySQL используются индексы с префиксами полей (лимит длины ключа InnoDB).

---

## Internal REST API для фронта (TrendAgent, ta_*)

Фронт получает данные только из локальных таблиц `ta_*` через внутреннее API. **Внешний TrendAgent API из этих эндпоинтов не вызывается.**

**Базовый URL:** `GET /api/ta/...` (относительно корня приложения, например `https://your-domain.com/api/ta/...`).

**Формат ответа везде единый:** строго `{ "data", "meta" }`.

- **data** — для списков (blocks, apartments, unit-measurements) массив объектов; для одного ресурса (show, directories) — один объект.
- **meta** — объект. Для списков всегда есть `meta.pagination` с полями:
  - **total** — общее число записей по фильтрам;
  - **count** — число записей в текущей выборке;
  - **offset** — смещение (offset), с которого взята выборка.
- Для единичного ресурса (show по block_id/apartment_id, directories) в `meta` передаётся пустой объект `{}` (без pagination).

**Пример ответа списка (index):**

```json
{
  "data": [ { "block_id": "...", "title": "...", ... } ],
  "meta": {
    "pagination": {
      "total": 100,
      "count": 20,
      "offset": 0
    }
  }
}
```

**Пример ответа show (блок/квартира):** при наличии записи в `ta_block_details` / `ta_apartment_details` в `data` подмешивается ключ **detail**:

```json
{
  "data": {
    "block_id": "abc",
    "title": "...",
    "detail": {
      "block_id": "abc",
      "unified_payload": { ... },
      "fetched_at": "..."
    }
  },
  "meta": {}
}
```

### Эндпоинты

| Метод | URL | Описание |
|-------|-----|----------|
| GET | `/api/ta/blocks` | Список блоков (ЖК) |
| GET | `/api/ta/blocks/{block_id}` | Один блок по `block_id` |
| GET | `/api/ta/apartments` | Список квартир |
| GET | `/api/ta/apartments/{apartment_id}` | Одна квартира по `apartment_id` |
| GET | `/api/ta/directories` | Один справочник по `type` + `city_id` + `lang` |
| GET | `/api/ta/unit-measurements` | Список единиц измерения |

### Параметры запроса (index)

Валидация через FormRequest:

- **city_id** — nullable, string, max 64.
- **lang** — nullable, string; при указании допускаются только: `ru`, `en`, `uk`, `kz`, `by`.
- **count** — int, min 1, max 200 (по умолчанию для списков 20, для unit-measurements 100).
- **offset** — int, min 0 (по умолчанию 0).
- **sort** — nullable, string, max 64.
- **sort_order** — nullable, `asc` или `desc` (по умолчанию `asc`).

Для списков **blocks** и **apartments** используются city_id/lang из конфига, если не переданы.

Только для **blocks**:

- **show_type** — опционально: `list`, `map`, `plans` (принимается, фильтрация по нему при необходимости можно расширить).

Только для **apartments**:

- **block_id** — опционально; фильтр по блоку (ЖК).

Для **directories** обязателен:

- **type** — тип справочника (required), например `rooms`, `deadlines`, `regions`.
- **city_id**, **lang** — как выше (nullable, lang с тем же in).

Для **unit-measurements** — только **count** и **offset** (остальные параметры не используются).

**Примеры запросов с фронта:**

```text
GET /api/ta/blocks?city_id=58c665588b6aa52311afa01b&lang=ru&count=20&offset=0&sort=min_price&sort_order=asc
GET /api/ta/blocks/abc-block-guid
GET /api/ta/apartments?count=10&offset=0
GET /api/ta/directories?type=rooms&city_id=58c665588b6aa52311afa01b&lang=ru
GET /api/ta/unit-measurements
```

Фронт должен передавать те же заголовки, что и для остального API приложения (например `Accept: application/json`). При ошибках валидации возвращается `422` с полем `errors`; при отсутствии ресурса — `404`.

---

## TrendAgent: очереди и автосинхронизация

Синхронизация с TrendAgent вынесена в **очереди (Jobs)**. Локально задачи выполняются синхронно (`sync`), на сервере — через **Redis**.

### Переменные окружения

| Переменная | Описание | Локально | Сервер |
|------------|----------|----------|--------|
| `QUEUE_CONNECTION` | Драйвер очереди | `sync` | `redis` |
| `TRENDAGENT_QUEUE_NAME` | Имя очереди для Jobs | по умолчанию `default` | по желанию |
| `TRENDAGENT_DETAIL_JOB_DELAY_SECONDS` | Задержка между dispatch деталей (сек) | 2 | по желанию |

Для Redis нужны `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT` (и при необходимости `REDIS_QUEUE` в `config/queue.php`).

### Команды dispatch (постановка в очередь)

- `php artisan trendagent:dispatch:blocks` — синхронизация списка блоков (опции: `--city`, `--lang`, `--count`, `--max-pages`, `--show-type`, `--no-dispatch-details`).
- `php artisan trendagent:dispatch:block-detail {block_id}` — детали одного блока.
- `php artisan trendagent:dispatch:apartments` — синхронизация списка квартир (опции: `--city`, `--lang`, `--count`, `--max-pages`, `--sort`, `--sort-order`, `--no-dispatch-details`).
- `php artisan trendagent:dispatch:apartment-detail {apartment_id}` — детали одной квартиры.

После успешного выполнения списка блоков/квартир в очередь автоматически ставятся Jobs на обновление деталей (только что обновлённые записи, с лимитом и задержкой).

### Scheduler (cron)

В `app/Console/Kernel.php` настроено:

- **Список блоков** — каждые 15 минут (`trendagent:dispatch:blocks`).
- **Список квартир** — каждые 15 минут (`trendagent:dispatch:apartments`).
- **Детали** — обновляются по очереди: после каждого списка диспатчатся Jobs на детали (новые/обновлённые), без отдельной cron-задачи.

На сервере добавьте в crontab:

```bash
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

### Запуск worker (на сервере с Redis)

Обработка очереди:

```bash
php artisan queue:work redis --queue=default
```

Или с указанием очереди из конфига:

```bash
php artisan queue:work redis --queue=default --tries=3
```

Для долгой работы под супервизором или systemd используйте эту же команду (`queue:work`).

### Логи и ошибки

- В логах приложения **не пишутся токены** (только `run_id`, `city_id`, `lang`, `block_id`/`apartment_id`, счётчики).
- Ошибки синхронизации фиксируются через **SyncRunner** в таблице `ta_sync_runs` (`status`, `error_message`, `error_context`). По `run_id` из логов можно найти запись в БД.

---

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
