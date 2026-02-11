## SSO API — авторизация и токены

Этот файл фиксирует наблюдаемые запросы к SSO TrendAgent и связывает их с UI‑сценариями.

---

### 1. Логин по телефону и паролю

- **UI‑сценарий**: форма логина перед входом в личный кабинет / SPA.
- **Endpoint**:

```http
POST https://sso-api.trend.tech/v1/login
  ?app_id={APP_ID}
  &lang=ru
```

- **Headers**:
  - `Content-Type: application/json`
- **Body (форма, наблюдаемая логически)**:

```json
{
  "phone": "+79045393434",
  "password": "********"
}
```

- **Response shape (высокоуровнево)**:
  - HttpOnly cookie `refresh_token` (основной результат логина).
  - JSON с признаком успеха/ошибки авторизации.

- **Пример curl**:

```bash
curl -X POST "https://sso-api.trend.tech/v1/login?app_id={APP_ID}&lang=ru" \
  -H "Content-Type: application/json" \
  -d '{
    "phone": "+79045393434",
    "password": "********"
  }' \
  -c cookies.txt
```

> Примечание: конкретное содержимое JSON‑ответа не было зафиксировано полностью; ключевой артефакт — установка refresh‑cookie.

---

### 2. OAuth‑редирект в приложение

- **UI‑сценарий**: успешный логин и возврат на `spb.trendagent.ru`.
- **Последовательность** (по логам Network):
  1. `GET https://sso-api.trend.tech/v1/oauth?...` → редирект.
  2. `302`/`3xx` на `https://spb.trendagent.ru/oauth?code=...`.

Тело этих ответов для интеграции не используется; важно лишь, что после них браузер уже содержит HttpOnly `refresh_token`.

---

### 3. Получение / продление auth_token

- **UI‑сценарий**: первый вход в SPA и периодическое продление токена (примерно раз в 5 минут).
- **Endpoint (подтверждён логами)**:

```http
GET https://sso-api.trend.tech/v1/auth_token/
  ?city={CITY_ID}
  &lang=ru
```

- **Headers**:
  - `Authorization: Bearer {OLD_AUTH_TOKEN}` — если старый токен есть.
  - `Cookie: refresh_token=...` — HttpOnly, автоматически отправляется браузером при `withCredentials=true`.
- **Query params**:
  - `city` — обязательный идентификатор города.
  - `lang` — язык интерфейса, обычно `ru`.

- **Response JSON (обозначено в предыдущем анализе)**:

```json
{
  "auth_token": "JWT_STRING",
  "ttl": 300
}
```

- **Пример curl** (для отладки в браузерной сессии):

```bash
curl "https://sso-api.trend.tech/v1/auth_token/?city={CITY_ID}&lang=ru" \
  -H "Authorization: Bearer {OLD_AUTH_TOKEN}" \
  -b cookies.txt
```

- **Использование в UI**:
  - SPA вызывает этот endpoint при старте и при 401/403 от бизнес‑API.
  - Полученный `auth_token` хранится только в памяти и приклеивается к последующим запросам к `*.trendagent.ru`/`*.trend.tech`.

---

### 4. Итог по SSO API

- **Server‑to‑server**:
  - прямой сценарий S2S через эти же endpoints возможен только при наличии валидных cookies и соблюдении контекста сессии;
  - в рамках проектируемой архитектуры принято решение **не** реализовывать SSO на бекенде и использовать только фронтовый refresh.
- **Связь с UI**:
  - все экраны после логина (`/objects/*`, `/parkings/*`, `/commerce/*` и др.) зависят от корректной работы цепочки:
    - `POST /v1/login` → редирект → `GET /v1/auth_token/` → бизнес‑API.

