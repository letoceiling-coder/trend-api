## TrendAgent Integration Project

Этот проект фиксирует и использует результаты ревёрс‑инжиниринга платформы TrendAgent (SPB) для построения собственного сервиса на Laravel + Vue.

---

## Цель проекта

- Реплицировать ключевой функционал TrendAgent:
  - поиск объектов/квартир/парковок/коммерции/посёлков,
  - детальные карточки сущностей,
  - работу с фильтрами, картами, планировками и шахматками,
  - отображение вознаграждений, документов, видео, туров.
- Сделать это:
  - на собственной архитектуре (Laravel backend, Vue SPA frontend),
  - полностью опираясь на **официальные API TrendAgent**, без парсинга HTML.

Все выводы и схемы зафиксированы в `docs/trendagent/` и **считаются финальными**.

---

## Источник данных

- Источником данных является связка API TrendAgent:
  - `api.trendagent.ru` (ядро объектов и квартир),
  - `apartment-api.trendagent.ru` (справочники),
  - `parkings-api.trendagent.ru` (парковки),
  - `commerce-api.trendagent.ru` (коммерция),
  - `rewards-api.trendagent.ru` (вознаграждения),
  - `files.trendagent.ru`, `video.trendagent.ru`, `contacts-api.trendagent.ru`, `mortgage-api.trendagent.ru`, `3d-tour-api.trendagent.ru` и др.
- Подробный каталог эндпоинтов: `docs/trendagent/03-api-catalog.md`.

---

## Как работает авторизация

- Используется SSO TrendAgent:
  - логин на `sso.trend.tech` по телефону и паролю;
  - сессионный `refresh_token` хранится в HttpOnly cookie (недоступен JS);
  - короткоживущий `auth_token` (`~5 минут`) используется для доступа к API.
- **Продление токена**:
  - выполняется фронтендом через:
    - `GET https://sso-api.trend.tech/v1/auth_token/?city={CITY_ID}&lang=ru`
    - с заголовком `Authorization: Bearer <OLD_TOKEN>` и `withCredentials=true`;
  - результат — новый `auth_token`, который хранится **только в памяти**.
- Laravel **не управляет** токенами:
  - не логинится,
  - не обновляет токены,
  - только проксирует `Authorization` и cookies дальше к TrendAgent.

Детали SSO и refresh‑алгоритма: `docs/trendagent/02-auth-and-token.md`.

---

## Добавление новых доменов (commerce / parkings / villages и др.)

1. **Проанализировать API‑домен**:
   - задокументировать эндпоинты в `docs/trendagent/03-api-catalog.md` (если ещё не описаны);
   - описать сущности и связи в `docs/trendagent/05-domain-model.md`.
2. **Добавить сервисы на Laravel**:
   - создать соответствующий сервис/репозиторий (например, `TrendAgentCommerceService`);
   - реализовать методы, инкапсулирующие вызовы `search`, `directories`, `detail` и т.п.;
   - учитывать `city_id` и `lang` как обязательные параметры.
3. **Обновить фронтенд (Vue)**:
   - добавить страницы/компоненты, использующие готовый axios‑клиент с refresh‑логикой;
   - маппить UI‑фильтры в query‑параметры так, как это делает оригинальный TrendAgent.
4. **При необходимости**:
   - расширить Domain Layer и Cache Layer (см. `docs/architecture.md`).

---

## Backend (Laravel 10, папка `backend/`)

Бэкенд развёрнут как отдельное приложение Laravel 10 в подкаталоге `backend/` (корневой `docs/` и существующие файлы не трогаются).

### Установка и запуск

1. Перейти в каталог backend и установить зависимости:

   ```bash
   cd backend
   composer install
   ```

2. Создать и настроить файл окружения:

   ```bash
   copy .env.example .env   # Windows PowerShell/CMD
   ```

   В `.env` задать параметры БД (должны совпадать с локальной MySQL):

   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=trendmirror
   DB_USERNAME=root
   DB_PASSWORD=

   CACHE_DRIVER=file
   QUEUE_CONNECTION=sync
   ```

3. Создать базу данных `trendmirror` в MySQL:

   ```sql
   CREATE DATABASE trendmirror CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

4. Сгенерировать ключ приложения и (опционально) выполнить миграции:

   ```bash
   php artisan key-generate
   php artisan migrate
   ```

5. Запустить HTTP‑сервер Laravel:

   ```bash
   php artisan serve
   ```

   API будет доступен по адресу `http://127.0.0.1:8000`, health‑эндпоинт:

   ```text
   GET /api/health  ->  {"ok": true, "time": "..."}
   ```

### CORS для фронтенда

В `backend/config/cors.php` разрешены запросы с следующих origin’ов:

```php
'allowed_origins' => [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
],
```

Это позволяет фронтенду на Vite/Vue (`http://localhost:5173`) ходить к API на `http://127.0.0.1:8000/api/*`.

### TrendAgent SSO: логин

Команда логина **без интерактивного ввода** (удобно на сервере и в CI):

```bash
cd backend
php artisan trendagent:auth:login
```

- **Телефон**: если не передан `--phone`, берётся из `TRENDAGENT_DEFAULT_PHONE` в `.env`. Если ни то ни другое — команда выведет ошибку и завершится.
- **Пароль**: аналогично `--password` или `TRENDAGENT_DEFAULT_PASSWORD`.
- **Язык**: `--lang` или `TRENDAGENT_DEFAULT_LANG` (по умолчанию `ru`).

Пример с опциями:

```bash
php artisan trendagent:auth:login --phone=+79045393434 --password=secret --lang=ru
```

Если SSO блокирует запрос (например 403), команда выведет сообщение и инструкцию: сохранить `refresh_token` из браузера через:

```bash
php artisan trendagent:auth:save-refresh <token>
```

(при необходимости указать `--phone=` если не задан `TRENDAGENT_DEFAULT_PHONE`).

**Безопасность:** телефон и пароль не логируются; в выводе команды номер маскируется. Не коммитьте `.env` в репозиторий и не добавляйте `TRENDAGENT_DEFAULT_PASSWORD` в `.env.example`.

### Деплой на сервере

На сервере (после `git pull` или вручную) обновить код и зависимости одной командой:

```bash
cd backend
php artisan deploy:server
```

По умолчанию выполняются: `composer install --no-dev`, миграции, `config:cache`, `route:cache`, `view:cache`, `queue:restart`, сборка фронтенда (`frontend/npm ci && npm run build`).

Опции:

- `--pull` — перед деплоем выполнить `git pull` в корне репозитория;
- `--skip-composer` — не запускать composer;
- `--skip-migrate` — не запускать миграции;
- `--skip-cache` — не кешировать config/route/view;
- `--skip-frontend` — не собирать фронтенд;
- `--skip-queue` — не выполнять `queue:restart`;
- `--dry-run` — только показать команды, не выполнять.

Команда `php artisan deploy` (без `:server`) предназначена для запуска с локальной машины: push в git и выполнение шагов по SSH на сервере (см. `config/deploy.php` и переменные `DEPLOY_*` в `.env`).

---

## Frontend (Vue 3 + Vite, папка `frontend/`)

1. Установить зависимости:

   ```bash
   cd frontend
   npm install
   ```

2. Создать файл окружения для Vite (например, `.env` или `.env.development`) в каталоге `frontend/` и задать базовый URL backend API:

   ```dotenv
   VITE_API_BASE=http://localhost:8000
   ```

3. Запустить dev‑сервер фронтенда:

   ```bash
   cd frontend
   npm run dev
   ```

   По умолчанию Vite поднимает фронтенд на `http://localhost:5173`.

4. Страницы:
   - `Home` — корневая страница, показывает заголовок «TrendMirror UI».
   - `HealthCheck` — страница с кнопкой **Check API**, которая вызывает `GET {VITE_API_BASE}/api/health` и отображает JSON‑ответ.

---

## Source of Truth

- **Единственный источник правды по интеграции с TrendAgent** — файлы в `docs/trendagent/` и `docs/STATUS.md`.
- Вся дальнейшая разработка (Domain / DTO / Repository / API / UI) **обязана** опираться только на зафиксированные там данные.
- Запрещено:
  - делать предположения «по аналогии» без отражения в документации;
  - использовать знания, не оформленные в виде файлов (память, устные договорённости и т.п.).
- Любое новое наблюдение (новый endpoint, поле, фильтр, шаблон UI) сначала:
  1. фиксируется в соответствующем markdown/схеме (`network/`, `schemas/`, `ui-snapshots/`, `01‑05*.md`);
  2. затем только после этого может использоваться в коде или архитектурных решениях.

