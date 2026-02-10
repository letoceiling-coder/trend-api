# Статус интеграции TrendAgent

## Авторизация SSO (актуально)

Реализован флоу по образцу рабочего проекта AL для устранения HTTP 403 при логине с сервера.

### Поведение

- **GET** `sso_web_base/login` выполняется **с редиректами** (allow_redirects, max 10), с браузерными заголовками (Sec-Fetch-*, Sec-Ch-Ua*). Один CookieJar для GET и POST.
- **app_id**: из финального URL редиректа (on_stats) → из HTML (regex) → из config → при 403 повтор с `app_id_alternative` (из AL: `66d84f584c0168b8ccd281c3`).
- **POST** `/v1/login`: form_params phone (нормализованный formatPhone), password, client=web; заголовки Origin, Referer, Sec-*.
- **Ответ**: 200/201/3xx — успех; при **403** проверяются CookieJar, Set-Cookie, JSON, Location на наличие **refresh_token** или **auth_token** — при наличии токена возвращается успех.
- Токены в ответах/логах не логируются (sanitize).

### Команды

| Команда | Описание |
|--------|----------|
| `php artisan trendagent:auth:login` | Логин по TRENDAGENT_DEFAULT_PHONE / TRENDAGENT_DEFAULT_PASSWORD (без интерактива). При 403/нет токена выводит инструкцию. |
| `php artisan trendagent:auth:save-refresh "<token>"` | Сохранить refresh_token из браузера в БД (шифруется в ta_sso_sessions). Извлекает и сохраняет app_id из JWT. |
| `php artisan trendagent:auth:status` | Показать текущую сессию. |
| `php artisan trendagent:auth:check` | Проверить сессию: GET /v1/auth_token через Guzzle с app_id из JWT payload. AUTH OK + token_len + exp/iat при успехе; AUTH FAIL + reason (http_status, body_preview) при ошибке. Exit 0 только при полученном auth_token. С опцией **-vvv** выводит cityId, lang, sso_base, verify, url, session.app_id (db), refresh_token.payload.app_id (jwt), chosenAppId used. Токены не выводятся. Автоматический retry при session_app_id_doesnt_match. |
| `php artisan trendagent:auth:debug` | Диагностика ta_sso_sessions: количество записей, id последней, refresh_token raw is null? yes/no, decrypt ok? yes/no + token_len, city_id, app_id, last_login_at. Токены не выводятся. |

### Переменные окружения (.env)

- `TRENDAGENT_SSO_BASE` — SSO API (по умолчанию https://sso-api.trend.tech).
- `TRENDAGENT_SSO_WEB_BASE` — страница логина (по умолчанию https://sso.trend.tech).
- `TRENDAGENT_SSO_VERIFY` — проверка SSL (true/false; по умолчанию true).
- `TRENDAGENT_APP_ID` — app_id (опционально; иначе из страницы логина).
- `TRENDAGENT_APP_ID_ALTERNATIVE` — альтернативный app_id при 403 (по умолчанию 66d84f584c0168b8ccd281c3 из AL).
- `TRENDAGENT_DEFAULT_PHONE`, `TRENDAGENT_DEFAULT_PASSWORD` — для команды login.
- `TRENDAGENT_DEFAULT_LANG` — язык (по умолчанию ru).

### Проверка на сервере

```bash
# Из папки backend
cd /var/www/trend-api/backend   # или ваш путь к backend

# 1) Залогиниться (если ещё не сделано)
php artisan trendagent:auth:login
# При успехе (refresh_token реально сохранён в БД) — "TrendAgent SSO login successful." и "Has refresh_token: yes".
# При 403 без токена — инструкция по trendagent:auth:save-refresh

# 2) Убедиться, что сессия реально рабочая (запрос к API с auth_token)
php artisan trendagent:auth:check
# OK — сессия валидна, API отвечает 200.
# NOT AUTHENTICATED — нет сессии или refresh_token; выполнить auth:login или auth:save-refresh.
# AUTH TOKEN INVALID — API вернул 401/403; перелогиниться или обновить refresh_token.
```

Требуется `TRENDAGENT_DEFAULT_CITY_ID` в .env (для check используется при запросе к API).

Если 403 при логине сохраняется: проверить `TRENDAGENT_APP_ID` / `TRENDAGENT_APP_ID_ALTERNATIVE`, при проблемах TLS временно `TRENDAGENT_SSO_VERIFY=false` (только для диагностики).
