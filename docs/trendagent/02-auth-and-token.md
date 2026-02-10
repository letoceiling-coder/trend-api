## Авторизация и токены TrendAgent SSO

Этот документ фиксирует реальную схему авторизации TrendAgent через SSO и работу с access/refresh токенами, подтверждённую Network‑логами.

---

## 1. Общая схема SSO

### 1.1. Участники

- **Браузер / Vue‑SPA** — инициирует логин, хранит `auth_token` в памяти, отвечает за refresh.
- **SSO**: `sso.trend.tech`, `sso-api.trend.tech` — аутентификация по телефону/паролю и выдача токенов.
- **Приложение**: `spb.trendagent.ru` — SPA‑оболочка, работающая через внешние API‑домены.

### 1.2. Поток логина

1. Пользователь переходит на `https://spb.trendagent.ru` и нажимает «Войти».
2. Происходит редирект на:
   - `https://sso.trend.tech/login?return_oauth_url=...&return_url=...&app_id=...`
3. Браузер отправляет:
   - `POST https://sso-api.trend.tech/v1/login?app_id=...&lang=ru`  
     body: телефон + пароль.
4. При успешном логине:
   - SSO создаёт сессионный `refresh_token` (HttpOnly cookie) и `auth_token`.
   - Выполняется OAuth‑обмен:
     - `GET https://sso-api.trend.tech/v1/oauth?app_id=...&lang=ru&auth_token=...`
     - `GET https://sso-api.trend.tech/v1/oauth/{code}?redirect_url=https://spb.trendagent.ru/oauth?...`
     - `GET https://spb.trendagent.ru/oauth?return_url=...&code=...`
5. Пользователь возвращается на `spb.trendagent.ru`, где SPA начинает использовать `auth_token` для вызова доменных API.

---

## 2. Типы токенов и хранение

### 2.1. refresh_token

- Хранится в **HttpOnly cookie**:
  - `refresh_token=eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...`
- JWT‑payload содержит:
  - `_id`, `token_id`, `client: "web"`, `app_id`, `user{...}`, `type: "session"`, `iat`, `exp`.
- **Недоступен** из JS:
  - Нельзя прочитать через `document.cookie` (HttpOnly).
  - Нельзя сохранять/передавать вручную.
- Используется только сервером SSO при вызове `GET /v1/auth_token/` с `withCredentials=true`.

### 2.2. auth_token (access token)

- Передаётся во **внешние API** только как **query‑параметр**:
  - `auth_token=eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...`
- Живёт порядка **5 минут** (подтверждено частой ротацией токена и 401 при устаревшем токене).
- **Не хранится** на клиенте перманентно:
  - корректная интеграция — только в runtime‑памяти (в нашем проекте — в модуле Vue).
  - не класть в `localStorage`/`sessionStorage`.

### 2.3. City / Region

- Город передаётся:
  - в cookie: `activeCity=58c665588b6aa52311afa01b`;
  - в каждом запросе: `city=58c665588b6aa52311afa01b`.
- Домен (`spb.trendagent.ru`) **не является** надёжным источником города — всегда использовать `city_id` явно.

---

## 3. Endpoint продления токена

### 3.1. Описание

```http
GET https://sso-api.trend.tech/v1/auth_token/
  ?city={CITY_ID}
  &lang=ru
```

**Требования:**

- HTTP‑заголовок:  
  `Authorization: Bearer <EXPIRED_OR_SOON_TOKEN>`
- Запрос должен включать cookies браузера:
  - `withCredentials: true` (чтобы на SSO ушёл HttpOnly `refresh_token`).

**Ответ (JSON):**

```json
{
  "auth_token": "NEW_ACCESS_TOKEN",
  "user": { ... }
}
```

---

## 4. Алгоритм работы с токеном (frontend‑ориентированный)

### 4.1. Получение первичного auth_token

1. Пользователь логинится через SSO (см. п.1.2).
2. После возврата на `spb.trendagent.ru` фронтенд (через `sso-api.trend.tech/v1/auth_token`) получает `auth_token` с использованием имеющегося `refresh_token` в cookie.
3. `auth_token` сохраняется **только в памяти** (например, модуль JS/ Vue‑store).

### 4.2. Каждый API‑запрос (через наш backend‑прокси)

1. Vue формирует запрос к нашему Laravel‑бэкенду (например, `/api/trendagent/...`).
2. В запрос добавляется:
   - `Authorization: Bearer <auth_token>` (заголовок к нашему бэкенду).
   - `withCredentials: true` (бэк прокинет куки к SSO / TrendAgent).
3. Laravel **не управляет** токеном:
   - он просто проксирует header и cookies дальше к TrendAgent.

### 4.3. Обработка успешного ответа

- Если ответ TrendAgent API — 2xx:
  - возвращаем данные в Vue.
  - ничего не меняем в токене.

### 4.4. Обработка 401 / 403 (expired token)

1. Vue‑axios interceptor получает `401` / `403`.
2. Проверяет:
   - не выполняется ли уже refresh сейчас (через глобальную переменную/мьютекс).
3. Если refresh не запущен:
   - запускается `GET /v1/auth_token/?city={CITY_ID}&lang=ru` с:
     - `Authorization: Bearer <OLD_TOKEN>`
     - `withCredentials: true`
4. Если refresh уже идёт:
   - текущий запрос подписывается на окончание refresh и **ждёт** (через Promise / очередь).

### 4.5. После успешного refresh

1. Из ответа берём `auth_token` и перезаписываем хранилище токена в памяти.
2. Все ожидающие запросы получают новый токен и **повторяют ОРИГИНАЛЬНЫЙ запрос** к Laravel.
3. Laravel так же проксирует новый токен к TrendAgent.

### 4.6. Ошибка refresh (401 или иная)

- Если `GET /v1/auth_token/` вернул 401:
  - считаем сессию пользователя истёкшей.
  - очищаем локальное состояние (Vue‑store, runtime‑token).
  - делаем redirect на страницу логина.
- При других фатальных ошибках refresh (5xx, network):
  - можно:
    - либо пытаться повторить refresh по политике retry,
    - либо считать, что пользователь временно не может работать.

---

## 5. Server‑to‑server сценарий

### 5.1. Реализация в проекте (backend)

В этом проекте серверный логин реализован по образцу AL: GET страницы логина с редиректами и браузерными заголовками, POST `/v1/login`, при 403 проверка cookies/Set-Cookie на токен. Команды: `trendagent:auth:login` (без интерактива, из .env), `trendagent:auth:save-refresh` (сохранение токена из браузера в БД с шифрованием). Подробно: **STATUS.md**, **auth-al-comparison-and-plan.md**.

### 5.2. Допустимый подход (общий)

- Для **серверного** доступа к TrendAgent API (фоновая синхронизация/крон):
  1. Завести отдельный сервисный аккаунт или использовать уже существующий логин.
  2. Ручным / отдельным скриптом получить `auth_token` (и при необходимости `refresh_token` в конфигурации) один раз.
  3. Хранить только `auth_token` (без `refresh_token`) в защищённом конфиге.
  4. Периодически обновлять этот `auth_token` через тот же endpoint `v1/auth_token` только в рамках контролируемой среды (если есть доступ к refresh‑cookie в этой среде).

### 5.3. Чего делать нельзя

- Нельзя:
  - хранить `refresh_token` в базе/конфиге;
  - дёргать `/v1/auth_token/` с backend’а без участия браузера, если нет корректных куки и контекста пользователя;
  - подменять TTL токена (хардкодить время жизни);
  - логиниться повторно «по расписанию» вместо корректного refresh‑флоу.

### 5.4. app_id: извлечение из JWT и избежание session_app_id_doesnt_match

**Проблема:** При запросе `GET /v1/auth_token/` SSO проверяет соответствие `app_id` между `refresh_token` (JWT payload) и контекстом запроса (Referer/Origin). Если `app_id` не совпадает, SSO возвращает `401 {"message":"session_app_id_doesnt_match"}`.

**Решение (реализовано в `TrendSsoClient`):**

1. **Извлечение `app_id` из `refresh_token` JWT payload** (метод `extractAppIdFromRefreshToken`):
   - JWT payload декодируется без валидации подписи (только base64url decode + json_decode).
   - Из payload извлекается поле `app_id` (24-символьный hex-строка).
   - Извлечённый `app_id` сохраняется в `ta_sso_sessions.app_id` при логине.

2. **Использование `app_id` при запросе `/v1/auth_token`**:
   - Выбирается `app_id` в порядке приоритета:
     - явный параметр `$appId` (если передан)
     - `app_id` из JWT payload (`$appIdJwt`)
     - fallback: `config('trendagent.app_id')` или `app_id_alternative`
   - Выбранный `app_id` используется в `Referer: https://sso.trend.tech/login?app_id={chosenAppId}`.

3. **Retry при несоответствии**:
   - Если запрос возвращает `401` с `session_app_id_doesnt_match` и `$appIdJwt` отличается от `$chosenAppId`, выполняется повторная попытка с `$appIdJwt`.
   - Это защищает от случаев, когда в БД был сохранён старый/неверный `app_id`, но JWT содержит правильный.

**Диагностика:** команда `trendagent:auth:check -vvv` показывает:
- `session.app_id (db)` — сохранённый в БД
- `refresh_token.payload.app_id (jwt)` — извлечённый из JWT
- `chosenAppId used` — итоговый выбранный для запроса

---

## 6. Роли слоёв: Vue vs Laravel

### 6.1. Vue (frontend)

- Хранит `auth_token` только в памяти.
- Добавляет `Authorization: Bearer` к запросам.
- Гарантирует `withCredentials: true`, чтобы refresh‑cookie доходил до SSO.
- Реализует:
  - refresh‑алгоритм;
  - retry оригинальных запросов;
  - мьютекс/очередь для единственного refresh при множественных 401.

### 6.2. Laravel (backend)

- Не знает про жизненный цикл токена.
- Не выполняет ни логин, ни refresh.
- Просто:
  - принимает запрос от SPA;
  - проксирует headers, cookies, query, body к API TrendAgent;
  - возвращает ответ как есть (с возможной минимальной нормализацией/логированием, но без вмешательства в токены).

