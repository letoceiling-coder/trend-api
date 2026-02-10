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
| `php artisan trendagent:auth:save-refresh "<token>"` | Сохранить refresh_token из браузера в БД (шифруется в ta_sso_sessions). |
| `php artisan trendagent:auth:status` | Показать текущую сессию. |

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
# После деплоя
php artisan trendagent:auth:login
# При успехе — "TrendAgent SSO login successful."
# При 403 без токена — инструкция по trendagent:auth:save-refresh
```

Если 403 сохраняется: проверить `TRENDAGENT_APP_ID` / `TRENDAGENT_APP_ID_ALTERNATIVE`, при проблемах TLS временно `TRENDAGENT_SSO_VERIFY=false` (только для диагностики).
