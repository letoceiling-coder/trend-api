# Отчёт выполнения команд (migrate, contract, quality)

**Дата:** 2026-02-10  
**Окружение:** локальный backend (на сервере использовать путь `/var/www/trend-api/backend`).

---

## Команды для выполнения на сервере

```bash
cd /var/www/trend-api/backend
php artisan migrate --force

# посмотреть изменения контрактов
php artisan ta:contract:last
php artisan ta:contract:check --since=24h

# проверить качество данных
php artisan ta:quality:check --scope=blocks --limit=200
php artisan ta:quality:summary --since=24h
```

---

## Результаты выполнения (локально)

### 1. Миграции

```text
   INFO  Running migrations.
  2026_02_12_110000_create_ta_data_quality_checks_table ...................... 14,609ms DONE
```

**Итог:** Миграции выполнены успешно. При первом запуске на сервере могут выполниться все недостающие миграции (в т.ч. `ta_contract_state`, `ta_contract_changes`, `ta_data_quality_checks`), если их ещё не было.

---

### 2. Изменения контрактов

**ta:contract:last**
```text
No contract changes recorded yet.
```

**ta:contract:check --since=24h**
```text
No contract changes in the last 24h
```

**Итог:** Записей об изменениях контракта нет. Это нормально, если sync с сохранением RAW ещё не выполнялся или hash ответов не менялся. После реальных запросов к API и смены формата ответов здесь появятся строки.

---

### 3. Качество данных

**ta:quality:check --scope=blocks --limit=200**
```text
Scope blocks: 0 check(s) recorded.
Total: 0 check(s) recorded.
```

**ta:quality:summary --since=24h**
```text
No quality checks in the given period.
```

**Итог:** Проверок по блокам не записано, так как в таблице `ta_blocks` нет записей (пустая БД). После синхронизации блоков (`trendagent:dispatch:blocks` и успешный sync) при следующем запуске `ta:quality:check --scope=blocks` появятся записи в `ta_data_quality_checks`, а `ta:quality:summary --since=24h` покажет сводку pass/warn/fail.

---

## Рекомендации

| Действие | Команда / что смотреть |
|----------|-------------------------|
| Раз в день смотреть изменения контракта | `php artisan ta:contract:last` и `ta:contract:check --since=24h` |
| После sync проверить качество по scope | `php artisan ta:quality:check --scope=blocks --limit=200` (и при необходимости apartments, block_detail, apartment_detail) |
| Сводка по качеству за период | `php artisan ta:quality:summary --since=24h` или `--since=7d` |

Секреты и токены в вывод команд не попадают.
