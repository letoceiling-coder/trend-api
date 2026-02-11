# Отчёт этапа: Contract Change Alerts + Data Quality

## Что добавлено

### 1. Contract Change Alerts
- **Таблицы:** `ta_contract_state` (последний известный hash по endpoint+city_id+lang), `ta_contract_changes` (история изменений с old/new hash и ключами).
- **Сервис:** `ContractChangeDetector` — вызывается из `PayloadCacheWriter::create` после сохранения RAW; при смене hash пишет в `ta_contract_changes` и обновляет `ta_contract_state`. Не бросает исключения наружу — только `Log::warning` при ошибке.
- **Команды:**
  - `ta:contract:check --since=24h` — изменения за период.
  - `ta:contract:last [--limit=20]` — последние изменения по каждому endpoint.

### 2. Data Quality Checks
- **Таблица:** `ta_data_quality_checks` (scope, entity_id, city_id, lang, check_name, status, message, context).
- **Сервис:** `DataQualityRunner` — методы `checkBlocks`, `checkApartments`, `checkBlockDetail`, `checkApartmentDetail`; запись результатов с лимитом записей за запуск (cap).
- **Команды:**
  - `ta:quality:check --scope=... --limit=200 [--cap=500]` — запуск проверок.
  - `ta:quality:summary --since=24h` — сводка pass/warn/fail по scope.
- **Интеграция:** после успешного завершения SyncBlocksJob, SyncApartmentsJob, SyncBlockDetailJob, SyncApartmentDetailJob вызываются лёгкие проверки (последние 20 записей, cap 100).

### 3. Документация
- В `docs/trendagent/STATUS.md` добавлены разделы «Contract Change Alerts» и «Data Quality Checks» с описанием и командами.

---

## Как запустить на сервере

1. **Миграции** (один раз):
   ```bash
   cd /path/to/backend
   php artisan migrate --force
   ```
   Будут созданы (если ещё нет): `ta_contract_state`, `ta_contract_changes`, `ta_data_quality_checks`.

2. **Просмотр изменений контракта:**
   ```bash
   php artisan ta:contract:last
   php artisan ta:contract:check --since=24h
   ```

3. **Проверки качества (ручной запуск):**
   ```bash
   php artisan ta:quality:check --scope=blocks --limit=200
   php artisan ta:quality:summary --since=24h
   ```

Автоматические проверки качества выполняются при успешном sync (jobs), отдельно ничего поднимать не нужно.

---

## Примеры вывода команд

### ta:contract:last
```
+---------------------------+----------------------------------+-----+--------------+--------------+----------------------+
| endpoint                  | city_id                          | lang| old_hash     | new_hash     | detected_at          |
+---------------------------+----------------------------------+-----+--------------+--------------+----------------------+
| /v4_29/blocks/search      | 58c665588b6aa52311afa01b        | ru  | a1b2c3d4e5.. | f6e7d8c9b0.. | 2026-02-12T10:00:00+00:00 |
+---------------------------+----------------------------------+-----+--------------+--------------+----------------------+
```

### ta:contract:check --since=24h
Группы по endpoint (city_id, lang), список изменений с old/new hash и ключами (old_top_keys, new_top_keys, old_data_keys, new_data_keys).

### ta:quality:check --scope=blocks --limit=200
```
Scope blocks: 42 check(s) recorded.
Total: 42 check(s) recorded.
```

### ta:quality:summary --since=24h
```
+----------------+------+------+------+
| scope          | pass | warn | fail |
+----------------+------+------+------+
| blocks         | 120  | 2    | 1    |
| apartments     | 85   | 0    | 0    |
| block_detail   | 30   | 0    | 0    |
| apartment_detail| 15  | 0    | 0    |
+----------------+------+------+------+
```

---

Секреты и токены в логи и отчёты не попадают.
