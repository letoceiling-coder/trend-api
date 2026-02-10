# Сравнение авторизации TrendAgent: проект AL vs TrendAgent (Parser)

## Как сделано в AL (100% рабочий функционал)

### 1. Клиент и куки

- **GuzzleHttp\Client** + **CookieJar** (одна банка на все запросы).
- **verify => false** (отключена проверка SSL).
- **allow_redirects** при GET `/login`: **true** (max 5–10), **track_redirects => true**.
- Таймаут 30 сек.

### 2. Заголовки (браузерные)

- В конструкторе клиента заданы: `User-Agent` (Chrome 142), `Accept`, `Accept-Language`, `Content-Type`, `Origin: https://sso.trend.tech`, `Referer: https://sso.trend.tech/`.
- При GET `/login` дополнительно: `Accept: text/html,...`, `Sec-Ch-Ua`, `Sec-Ch-Ua-Mobile`, `Sec-Ch-Ua-Platform`, `Sec-Fetch-Dest: document`, `Sec-Fetch-Mode: navigate`, `Sec-Fetch-Site: none`, `Sec-Fetch-User: ?1`, `Upgrade-Insecure-Requests: 1`.
- При POST `/v1/login`: `Referer: https://sso.trend.tech/login?app_id={app_id}`, `Sec-Fetch-Dest: empty`, `Sec-Fetch-Mode: cors`, `Sec-Fetch-Site: same-site`, `Priority: u=1, i`.

### 3. Получение app_id

- GET `https://sso.trend.tech/login` **с редиректами**.
- app_id из:
  1. **X-Guzzle-Redirect-History** — последний URL в цепочке редиректов, из query `app_id=...`;
  2. тело ответа (HTML) — несколько regex по шаблонам `app_id=`, `appId`, ссылки с `app_id`;
  3. при неудаче — **дефолт** `66d84f584c0168b8ccd281c3` (в коде AL).

Важно: в нашем проекте в .env указан **другой** app_id: `66d84ffc4c0168b8ccd281c7`. В AL по умолчанию — `66d84f584c0168b8ccd281c3`.

### 4. POST логин

- URL: `https://sso-api.trend.tech/v1/login?app_id={app_id}&lang=ru`.
- **allow_redirects => false** (куки и заголовки редиректа обрабатываются вручную).
- Тело: `phone` (отформатированный), `password`, `client => 'web'`.
- Телефон нормализуется: только цифры и `+`, `8...` → `+7...`, и т.д.

### 5. Обработка ответа

- 200/201 — успех.
- 3xx — достают токен из `Location` (query `auth_token`) или из кук.
- **403** — всё равно смотрят куки; если есть auth_token в куках — считают успехом, иначе — исключение.
- Токен берётся из: JSON (`auth_token`, `refresh_token`, `token`, `access_token`, `data.auth_token`), из кук, из Location.

### 6. Хранение и использование

- AL при каждом запросе к API (objects/list и т.д.) вызывает `authenticate(phone, password)` и затем `getAuthToken()` — без хранения refresh в БД. AuthTokenManager кеширует уже полученный auth_token (JWT, exp) и при истечении снова дергает `TrendSsoApiAuth->authenticate()` (телефон/пароль из env).

---

## Что не так в нашем проекте (TrendAgent Parser)

| Аспект | У нас | В AL | Риск 403 / причины |
|--------|--------|------|---------------------|
| GET /login | allow_redirects **false** | **true**, track_redirects | Нет кук с sso.trend.tech после редиректов → сервер может требовать «первый заход» через редирект |
| app_id | 66d84**ffc**4c0168b8ccd281**c7** | 66d84**f58**4c0168b8ccd281**c3** | Разные приложения; возможно, один только для браузера, другой — для сервера/интеграций |
| SSL | verify из config (по умолчанию true) | **verify => false** | На сервере при проблемах TLS запрос может не дойти или вести себя иначе |
| Заголовки POST | Origin, Referer, User-Agent, Accept, Accept-Language | + Sec-Ch-Ua*, Sec-Fetch-* | Защита от ботов может опираться на Sec-Fetch-* |
| Телефон | Как передан | formatPhone (нормализация) | Менее вероятно, но единый формат уменьшает риск отказов |

---

## План изменений в проекте TrendAgent (Parser)

### 1. TrendSsoClient (GET /login и куки)

- При **GET** `sso_web_base/login` использовать **allow_redirects => true** (max 5), **track_redirects => true**.
- Извлекать app_id:
  - из истории редиректов (если Guzzle отдаёт `X-Guzzle-Redirect-History` или аналог — взять последний URL и query `app_id`);
  - иначе из HTML (текущие regex) и затем fallback на config/env.
- Один и тот же **CookieJar** использовать для GET и для POST (уже так, но важно не создавать новый клиент без кук между GET и POST).

### 2. Заголовки POST /v1/login

- Добавить к запросу логина заголовки как в AL:
  - `Sec-Ch-Ua`, `Sec-Ch-Ua-Mobile`, `Sec-Ch-Ua-Platform`
  - `Sec-Fetch-Dest: empty`, `Sec-Fetch-Mode: cors`, `Sec-Fetch-Site: same-site`
- Referer оставить: `{sso_web_base}/login?app_id={app_id}`.

### 3. app_id

- Добавить в config/trendagent второй вариант app_id (из AL): например ключ `app_id_fallback` или в .env **TRENDAGENT_APP_ID_ALTERNATIVE** = `66d84f584c0168b8ccd281c3`.
- Логика: при 403 при следующей попытке (или в одной сессии) попробовать альтернативный app_id. Либо просто дать возможность в .env переключить app_id на значение из AL и проверить, пропадёт ли 403.

### 4. SSL verify

- Оставить **TRENDAGENT_SSO_VERIFY** (как сейчас).
- В **документации** и в комментарии в config указать: на окружениях с проблемами TLS (например только на сервере) можно временно выставить `false` для проверки; в проде предпочтительно `true`.

### 5. Нормализация телефона

- Ввести в TrendSsoClient метод **formatPhone** по образцу AL (оставить только цифры и `+`, 8 → +7 и т.д.) и передавать в POST уже отформатированный номер.

### 6. Обработка 403

- Как в AL: при 403 проверять Set-Cookie и CookieJar на наличие refresh_token/auth_token; если есть — считать логин успешным и вернуть `needs_manual_token: false` с этим токеном; иначе возвращать `needs_manual_token: true` без исключения.

### 7. Тесты и конфиг

- Добавить в конфиг опциональный `app_id_alternative` (или env).
- Unit-тест на formatPhone; при необходимости — тест на извлечение app_id из «цепочки» URL (эмуляция X-Guzzle-Redirect-History).

---

## Порядок внедрения (рекомендуемый)

1. **Нормализация телефона** (formatPhone) и использование её в login.
2. **GET /login с allow_redirects true** и извлечение app_id из финального URL редиректа (и при отсутствии — из HTML и config).
3. **Добавление Sec-* заголовков** к POST логина.
4. **Опция app_id из AL**: TRENDAGENT_APP_ID_ALTERNATIVE и попытка с ним при 403 (или ручная смена в .env для проверки).
5. **Обработка 403**: при 403 не бросать исключение, а проверять куки и при наличии токена возвращать успех.
6. **Опционально**: TRENDAGENT_SSO_VERIFY=false только для dev/отладки и описать в README.

После этого на сервере стоит перезапустить команду логина и при необходимости временно попробовать app_id из AL и verify false для диагностики.

---

## Реализация (выполнено)

- **TrendSsoClient**: GET `/login` с `allow_redirects` (max 10), один CookieJar для GET и POST; app_id из effective URI (on_stats), HTML, config; при 403 — повтор с `app_id_alternative`; `formatPhone()`; браузерные заголовки Sec-Ch-Ua / Sec-Fetch-* на GET и POST; `TRENDAGENT_SSO_VERIFY` из config (по умолчанию true).
- **Обработка ответа**: 200/201/3xx — успех; 403 — проверка CookieJar/Set-Cookie/JSON/Location на refresh_token или auth_token; при наличии токена возвращается успех (`needs_manual_token=false`).
- **Команды**: `trendagent:auth:login` без интерактива, phone/password из TRENDAGENT_DEFAULT_PHONE/PASSWORD, lang из TRENDAGENT_DEFAULT_LANG; при `needs_manual_token=true` выводится инструкция для `trendagent:auth:save-refresh`. `trendagent:auth:save-refresh` сохраняет токен в `ta_sso_sessions` (шифрование через модель).
- **Тесты**: formatPhone, extractAppIdFromRedirectHistory, extractAppIdFromHtml, 403 с токеном в Set-Cookie.
- Токены в логах не выводятся (sanitizeRaw/sanitizePreview).
