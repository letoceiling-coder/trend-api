# STAGE: Безопасный self-healing авторизации TrendAgent

## Цель

Ограничить частоту и последствия автоматического relogin при падении сессии: rate-limit, cooldown при частых неудачах, аудит в БД, метрики в health и алерт «Auth relogin failing».

---

## Список изменённых/добавленных файлов

| Файл | Изменение |
|------|-----------|
| **database/migrations/2026_02_12_130000_create_ta_relogin_events_table.php** | Таблица **ta_relogin_events**: id, attempted_at, success (boolean), city_id (nullable, index), timestamps, index по attempted_at. |
| **app/Models/Domain/TrendAgent/TaReloginEvent.php** | Модель: fillable attempted_at, success, city_id; casts attempted_at → datetime, success → boolean. |
| **app/Integrations/TrendAgent/Auth/TrendAuthService.php** | В **ensureAuthenticated**: rate-limit по ключу `ta:auth:relogin:last:{hash(phone:city_id)}` (TTL 10 мин); глобальный cooldown `ta:auth:relogin:cooldown` (TTL 30 мин) при ≥2 неудачах по слоту; запись в **ta_relogin_events** при каждой попытке (успех/неудача); счётчик неудач по слоту `ta:auth:relogin:failures:{hash}`; логи только masked phone. |
| **app/Domain/TrendAgent/TaHealthService.php** | В **getHealthData()**: запросы к TaReloginEvent за последние 24 ч → **relogin_attempts_last_24h**, **relogin_failed_last_24h**. |
| **app/Console/Commands/TaAlertsCheckCommand.php** | Новая проверка **checkAuthReloginFailing**: при relogin_failed_last_24h > 0 отправляется алерт «TA Alert: Auth relogin failing» (reason auth_relogin, dedupe, quiet hours). Зависимость от TaHealthService в handle(). |
| **tests/Unit/Integrations/TrendAgent/TrendAuthServiceTest.php** | Тесты: **test_relogin_rate_limited_second_call_does_not_login** (при установленном rate key — исключение «Relogin rate-limited», login не вызывается); **test_relogin_cooldown_after_failures_does_not_login** (при установленном cooldown — «Relogin in cooldown», login не вызывается); **test_relogin_two_failures_set_cooldown_and_record_events** (две неудачные попытки → cooldown установлен, в ta_relogin_events две записи success=false). |
| **tests/Unit/Domain/TrendAgent/TaHealthServiceTest.php** | **test_get_health_data_includes_relogin_counts**: проверка наличия и типа relogin_attempts_last_24h, relogin_failed_last_24h в getHealthData(). |

---

## Логика

### Rate-limit

- Ключ: `ta:auth:relogin:last:{sha256(phone:city_id)}`.
- TTL: 10 минут.
- Устанавливается только при **успешном** relogin.
- Если ключ есть при входе в relogin → выбрасывается `TrendAgentNotAuthenticatedException` с сообщением «Relogin rate-limited (max once per 10 min).», **login не вызывается**.

### Cooldown

- Ключ: `ta:auth:relogin:cooldown` (глобальный).
- TTL: 30 минут.
- Устанавливается при достижении порога неудач по слоту: счётчик `ta:auth:relogin:failures:{sha256(phone:city_id)}` (TTL 10 мин); при count ≥ 2 ставится cooldown.
- Если cooldown установлен → при входе в relogin выбрасывается «Relogin in cooldown (too many recent failures).», **login не вызывается**.

### Аудит (ta_relogin_events)

- При каждой попытке relogin (успех или неудача) создаётся запись: **attempted_at**, **success**, **city_id**.
- Запись создаётся до повторного вызова getAuthToken при успехе и в catch при неудаче.

### Логи

- В логах используется только **masked phone** (например `799***67`), сырой телефон не пишется.

### Health

- **relogin_attempts_last_24h**: количество записей в ta_relogin_events с attempted_at за последние 24 ч.
- **relogin_failed_last_24h**: количество записей с success=false за последние 24 ч.

### Алерт

- Условие: `relogin_failed_last_24h > 0` (данные из TaHealthService::getHealthData()).
- Текст: «TA Alert: Auth relogin failing» + количество за 24 ч + подсказка по логам и ta_relogin_events.
- Используются те же dedupe (fingerprint по reason + count) и quiet hours, что и для остальных алертов.

---

## Проверка

```bash
php artisan migrate
php artisan test tests/Unit/Integrations/TrendAgent/TrendAuthServiceTest.php --filter=relogin
php artisan test tests/Unit/Domain/TrendAgent/TaHealthServiceTest.php --filter=relogin
```

Health endpoint: `GET /api/ta/admin/health` (X-Internal-Key) — в ответе должны быть поля **relogin_attempts_last_24h**, **relogin_failed_last_24h**.

Алерт срабатывает при следующем запуске `ta:alerts:check` после появления хотя бы одной неудачной попытки relogin за последние 24 ч.
