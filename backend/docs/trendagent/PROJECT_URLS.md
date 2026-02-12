# URL проекта TrendAgent (production)

**Базовый URL:** `https://trendagent.siteaccess.ru`

**Полная документация (как парсятся объекты, регионы, интерфейс, последние обновления):** см. в корне репозитория файл `docs/ПОЛНАЯ_ДОКУМЕНТАЦИЯ.md`.

---

## Просмотр в браузере (SPA)

Интерфейс доступен по тому же домену:

| Страница | URL |
|----------|-----|
| Главная | https://trendagent.siteaccess.ru/ |
| Список комплексов | https://trendagent.siteaccess.ru/objects/list |
| Таблица квартир | https://trendagent.siteaccess.ru/objects/table |
| Карта | https://trendagent.siteaccess.ru/objects/map |
| Планировки | https://trendagent.siteaccess.ru/objects/plans |
| Карточка комплекса | https://trendagent.siteaccess.ru/object/{block_id} |
| Карточка квартиры | https://trendagent.siteaccess.ru/flat/{apartment_id} |
| Шахматка по корпусу | https://trendagent.siteaccess.ru/object/{block_id}/checkerboard |
| Админка TA | https://trendagent.siteaccess.ru/admin/ta |
| Health check | https://trendagent.siteaccess.ru/health |

---

## API (для запросов)

Для запросов к админским эндпоинтам добавьте заголовок (значение в `.env` на сервере, ключ `INTERNAL_API_KEY`):
```http
X-Internal-Key: <ваш INTERNAL_API_KEY>
```

### Публичные (без ключа)

| Описание | Метод | URL |
|----------|--------|-----|
| Проверка живости API | GET | https://trendagent.siteaccess.ru/api/health |
| Список блоков | GET | https://trendagent.siteaccess.ru/api/ta/blocks |
| Один блок | GET | https://trendagent.siteaccess.ru/api/ta/blocks/{block_id} |
| Список квартир | GET | https://trendagent.siteaccess.ru/api/ta/apartments |
| Одна квартира | GET | https://trendagent.siteaccess.ru/api/ta/apartments/{apartment_id} |
| Справочники | GET | https://trendagent.siteaccess.ru/api/ta/directories |
| Единицы измерения | GET | https://trendagent.siteaccess.ru/api/ta/unit-measurements |

---

## С ключом X-Internal-Key (админ / внутренние)

| Описание | Метод | URL |
|----------|--------|-----|
| Здоровье (sync, queue, redis, pipeline) | GET | https://trendagent.siteaccess.ru/api/ta/admin/health |
| Последние sync runs | GET | https://trendagent.siteaccess.ru/api/ta/admin/sync-runs |
| Изменения контракта API | GET | https://trendagent.siteaccess.ru/api/ta/admin/contract-changes |
| Проверки качества данных | GET | https://trendagent.siteaccess.ru/api/ta/admin/quality-checks |
| Покрытие (блоки, квартиры) | GET | https://trendagent.siteaccess.ru/api/ta/admin/coverage |
| Запуск пайплайна (sync blocks/apartments) | POST | https://trendagent.siteaccess.ru/api/ta/admin/pipeline/run |
| Обновить блок (по id) | POST | https://trendagent.siteaccess.ru/api/ta/blocks/{block_id}/refresh |
| Обновить квартиру (по id) | POST | https://trendagent.siteaccess.ru/api/ta/apartments/{apartment_id}/refresh |

---

## Примеры (в браузере или curl)

- **Проверка, что API живой:**  
  https://trendagent.siteaccess.ru/api/health

- **Список блоков:**  
  https://trendagent.siteaccess.ru/api/ta/blocks

- **Health (в терминале с ключом из .env):**  
  ```bash
  curl -s -H "X-Internal-Key: ВАШ_КЛЮЧ" https://trendagent.siteaccess.ru/api/ta/admin/health | jq
  ```

Ключ **INTERNAL_API_KEY** сгенерирован и записан в `.env` на сервере; значение нигде не выводится. Посмотреть его можно на сервере:  
`grep INTERNAL_API_KEY /var/www/trend-api/backend/.env` (не логировать и не коммитить).
