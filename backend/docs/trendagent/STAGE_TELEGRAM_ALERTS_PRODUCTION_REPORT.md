# STAGE: Telegram alerts production-ready

## Цель

Сделать алерты Telegram пригодными для production: дедупликация, тихие часы (quiet hours), улучшенный формат сообщений и тесты.

---

## Список изменённых/добавленных файлов

| Файл | Изменение |
|------|-----------|
| **config/trendagent.php** | В `alerts` добавлены `quiet_hours` (ENV `TA_ALERT_QUIET_HOURS`, напр. `23:00-08:00`) и `quiet_hours_timezone` (ENV `TA_ALERT_QUIET_HOURS_TIMEZONE`, по умолчанию `Europe/Kiev`). |
| **app/Console/Commands/TaAlertsCheckCommand.php** | Dedupe: для типов `failed_runs`, `no_success`, `quality_growth` вычисляется fingerprint (sha256 от канонического payload), кэш `ta:alert:dedupe:{type}` TTL 30 мин; при совпадении отправка пропускается. Quiet hours: парсинг `TA_ALERT_QUIET_HOURS` и проверка времени в заданной timezone; в тихие часы алерты не отправляются, вместо этого вызывается `recordSuppressed(reason)` — инкремент счётчика и топ reasons в кэше `ta:alert:quiet_suppressed` (TTL 12 ч). В начале `handle()` при выходе из quiet hours отправляется сводка «During quiet hours we suppressed X alerts» + top reasons и кэш очищается. Формат сообщений: заголовок (TA Alert: ...), Count/Previous→Current, Top scopes, строка Details с подсказкой GET и X-Internal-Key. |
| **tests/Feature/TaAlertsCheckCommandTest.php** | Добавлены: `test_dedupe_prevents_duplicate_alert` (первый run — 1 send и запись в dedupe cache, второй run с новым Http::fake — 0 send); `test_quiet_hours_suppresses_alert_and_increments_counter` (время 02:00 Kiev, quiet 23:00–08:00, алерт не уходит, в кэше count=1, reasons failed_runs=1); `test_after_quiet_hours_sends_summary` (кэш с suppressed, время 10:00 Kiev — отправляется 1 сводка, кэш очищается). В тесте after_quiet_hours добавлены успешные sync runs и качество, чтобы не срабатывали другие алерты. |
| **docs/trendagent/STATUS.md** | Раздел «Telegram Alerts» обновлён: описание формата (заголовок, подсказка curl/GET), блок «Production-ready» (dedupe, quiet hours, ENV). |
| **README.md** | В таблицу переменных добавлены `TA_ALERT_QUIET_HOURS` и `TA_ALERT_QUIET_HOURS_TIMEZONE`; в абзац про алерты — дедуп 30 мин, quiet hours, сводка после, формат сообщений. |

---

## Логика

### Dedupe

- Типы: `failed_runs`, `no_success`, `quality_growth`.
- Fingerprint: `failed_runs` — sha256 от `failed_runs:` + json_encode(scope counts, ksort); `no_success` — sha256 от `no_success:` + sorted missing scopes; `quality_growth` — sha256 от `quality_growth:previous:current`.
- Ключ кэша: `ta:alert:dedupe:{type}`, значение — fingerprint, TTL 1800 с (30 мин).
- Если текущий fingerprint совпадает с кэшем — отправка не выполняется.

### Quiet hours

- Формат ENV: `23:00-08:00` (start-end в заданной timezone). Интервал может переходить через полночь (23:00–08:00 = с 23:00 до 08:00 следующего дня).
- Текущее время берётся как `Carbon::now($timezone)`.
- В quiet hours: вместо отправки вызывается `recordSuppressed($reason)` — в кэше `ta:alert:quiet_suppressed` хранится `['count' => N, 'reasons' => ['failed_runs' => n1, ...]]`, TTL 12 ч.
- При первом запуске команды **вне** quiet hours: если в кэше есть `ta:alert:quiet_suppressed` и count > 0 — отправляется одно сообщение «During quiet hours we suppressed X alert(s). Top reasons: failed_runs: N, ...», затем кэш очищается.

### Формат сообщений

- **Failed sync runs:** заголовок «TA Alert: Failed sync runs», строка Count, строка Top scopes, строка Details: `GET /api/ta/admin/sync-runs?status=failed&since_hours=N (add X-Internal-Key header)`.
- **Quality growth:** «TA Alert: Quality fail count increased (24h)», Previous → Current, Details: `GET /api/ta/admin/quality-checks?status=fail&since_hours=24 (...)`.
- **No success:** «TA Alert: No successful sync in period», Scopes: ..., Details: `GET /api/ta/admin/sync-runs?since_hours=N (...)`.

---

## Команды проверки

```bash
# Все тесты алертов
php artisan test tests/Feature/TaAlertsCheckCommandTest.php

# Отдельно: dedupe, quiet hours, summary
php artisan test tests/Feature/TaAlertsCheckCommandTest.php --filter="test_dedupe"
php artisan test tests/Feature/TaAlertsCheckCommandTest.php --filter="test_quiet_hours"
php artisan test tests/Feature/TaAlertsCheckCommandTest.php --filter="test_after_quiet_hours"
```

---

## Переменные окружения

| Переменная | Описание |
|------------|----------|
| `TA_ALERT_QUIET_HOURS` | Интервал тихих часов, например `23:00-08:00`. Пусто — отключено. |
| `TA_ALERT_QUIET_HOURS_TIMEZONE` | Часовой пояс для проверки (по умолчанию `Europe/Kiev`). |

Кэш для dedupe и quiet_suppressed должен быть общий для всех запусков команды (например Redis на проде), иначе дедуп и сводка за тихие часы не будут работать корректно.
