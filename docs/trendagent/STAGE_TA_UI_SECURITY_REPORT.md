# STAGE: отчёт по усилению безопасности TA-UI refresh

## Цель

Ограничить доступ к эндпоинтам обновления блоков и квартир (`/api/ta-ui/*` refresh): по умолчанию требовать X-Internal-Key; опционально разрешать вызовы без ключа только с allowlist IP и с rate-limit.

## Что сделано

### 1. Конфигурация

- **Файл:** `backend/config/trendagent.php`
- Добавлен параметр `ta_ui_allow_ips`: массив IP из переменной окружения **TA_UI_ALLOW_IPS** (значения через запятую, пробелы обрезаются). Пустая строка → пустой массив.

Пример в `.env`:

```dotenv
TA_UI_ALLOW_IPS=127.0.0.1,10.0.0.5
```

### 2. Middleware TaUiGuard

- **Файл:** `backend/app/Http/Middleware/TaUiGuard.php`
- **Логика:**
  1. Если передан заголовок **X-Internal-Key** и он совпадает с `config('internal.api_key')` (INTERNAL_API_KEY) → пропуск.
  2. Иначе если IP клиента (`$request->ip()`) входит в `config('trendagent.ta_ui_allow_ips')` → пропуск.
  3. Иначе ответ **401 Unauthorized**.

Ключ и токены в логах не пишутся.

### 3. Маршруты

- **Файл:** `backend/routes/api.php`
- Группа `ta-ui` защищена: `ta_ui.guard` и `throttle:10,1` (10 запросов в минуту).

### 4. Фронтенд (Vue TaAdmin)

- **taAdmin.ts:** добавлены функции `refreshBlock(blockId)` и `refreshApartment(apartmentId)`, выполняющие POST на `/api/ta-ui/blocks/{id}/refresh` и `/api/ta-ui/apartments/{id}/refresh` с заголовком **X-Internal-Key** (ключ берётся из `getAdminKey()`). Использовать только при `hasAdminKey() === true`.
- **TaAdmin.vue:** при нажатии «Refresh block» / «Refresh apartment»:
  - если ключ задан — вызов `taAdminRefreshBlock` / `taAdminRefreshApartment` (с ключом);
  - иначе — вызов `refreshBlockDetail` / `refreshApartmentDetail` из `ta.ts` (без ключа; сработает только при доступе с allowlist IP).

### 5. Тесты

- **TaUiGuardTest** (`tests/Feature/TaUiGuardTest.php`):
  - без ключа и без allowlist → 401;
  - с валидным X-Internal-Key → 200 (blocks и apartments refresh);
  - с allowlist IP (127.0.0.1) и без ключа → 200;
  - IP не в allowlist → 401.
- **TaApiTest:** тесты ta-ui refresh обновлены: передаётся X-Internal-Key и выставляется `INTERNAL_API_KEY`, ожидается 200/404.

## Как настроить

| Сценарий | Настройка |
|----------|-----------|
| Только по ключу (по умолчанию) | INTERNAL_API_KEY задан; TA_UI_ALLOW_IPS не задан или пустой. Фронт/скрипты передают X-Internal-Key. |
| Разрешить UI refresh без ключа с доверенных IP | Задать TA_UI_ALLOW_IPS=IP1,IP2. Запросы без ключа с этих IP проходят; остальные получают 401. Rate-limit 10/мин по-прежнему действует. |

## Итог

- Refresh по умолчанию требует X-Internal-Key.
- При необходимости можно разрешить refresh без ключа только с allowlist IP (TA_UI_ALLOW_IPS) с сохранением rate-limit.
- Фронт TaAdmin при наличии ключа всегда отправляет X-Internal-Key при refresh; без ключа работает только с allowlist IP.
