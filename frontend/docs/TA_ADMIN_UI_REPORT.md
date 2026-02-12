# Отчёт: Admin TA UI

## Список файлов

| Файл | Назначение |
|------|------------|
| `src/api/taAdmin.ts` | API-клиент для `/api/ta/admin/*`: getAdminKey(), setAdminKey(), clearAdminKey(), hasAdminKey(); getHealth(), getSyncRuns(), getContractChanges(), getQualityChecks(), runPipeline(). Типы TS, ответы `{ data, meta }`. Ключ: dev → sessionStorage(TA_ADMIN_KEY), prod → VITE_TA_ADMIN_KEY. При отсутствии ключа — throw Error('Admin key not configured'). |
| `src/pages/TaAdmin.vue` | Один экран `/admin/ta`: AdminKeyGate, HealthCard, ActionsPanel (pipeline + refresh block/apartment через ta-ui), таблицы SyncRuns, ContractChanges, QualityFails; фильтры; polling 15 с; Copy curl (YOUR_KEY); ошибки без секретов. |
| `src/router/index.ts` | Маршрут `{ path: '/admin/ta', name: 'ta-admin', component: TaAdmin }`. |
| `src/App.vue` | Ссылка «Admin TA» в навигации при `import.meta.env.DEV` или при заданном `VITE_TA_ADMIN_KEY`. |
| `README.md` | Раздел «Admin TA (/admin/ta)»: как открыть, как задать ключ (dev/prod), предупреждение про sessionStorage. |

## Как запустить локально

1. Установить зависимости и запустить dev-сервер:

```bash
cd frontend
npm install
npm run dev
```

2. Открыть в браузере: `http://localhost:5173/admin/ta` (или перейти по ссылке «Admin TA» в шапке, если она видна).

3. Backend должен быть доступен (по умолчанию прокси на `http://localhost:3000` или `VITE_API_URL`). В dev при первом заходе отобразится форма ввода ключа — ввести значение X-Internal-Key и нажать Save (ключ сохранится в sessionStorage).

4. Сборка и проверка типов:

```bash
npm run build
```

## Описание блоков UI

- **AdminKeyGate:** форма с полем типа password и кнопками Save / Clear key. Показывается, если ключ не задан (нет ни sessionStorage в dev, ни VITE_TA_ADMIN_KEY в prod). После Save ключ сохраняется в sessionStorage и начинается загрузка данных.
- **HealthCard:** блок с last_success_at по scope (blocks, apartments, block_detail, apartment_detail), contract_changes_last_24h_count, quality_fail_last_24h_count, queue (connection, queue_name). Кнопки Refresh и Copy curl.
- **ActionsPanel:** форма pipeline (city_id, lang, blocks_count, blocks_pages, apartments_pages, dispatch_details, detail_limit) и кнопка Run pipeline; вывод queued/run_id; поля block_id и apartment_id с кнопками Refresh block / Refresh apartment (вызовы ta-ui без X-Internal-Key). Copy curl для pipeline.
- **SyncRunsTable:** фильтры scope, status, since_hours, limit; кнопка Apply и Copy curl; таблица (id, scope, status, items_fetched, items_saved, error_message, finished_at). Строки со status failed/error подсвечены красным.
- **ContractChangesTable:** фильтры endpoint, since_hours, limit; Apply и Copy curl; таблица endpoint, city_id, lang, old_hash, new_hash, detected_at.
- **QualityFailsTable:** по умолчанию status=fail, since_hours=24, limit=200; фильтры scope, since_hours, limit; таблица scope, entity_id, check_name, status, message, created_at.

Polling: каждые 15 с при активной вкладке обновляются health и sync-runs. Copy curl везде подставляет `YOUR_KEY` вместо реального ключа.

## Чеклист

| Проверка | Статус |
|----------|--------|
| Health: загрузка и отображение last_success_at, счётчиков, queue | Реализовано в HealthCard, loadHealth() |
| Tables: Sync Runs с фильтрами и подсветкой failed | SyncRunsTable, loadSyncRuns(), Apply, красный ряд при status failed/error |
| Tables: Contract Changes с фильтрами | ContractChangesTable, loadContractChanges() |
| Tables: Quality Fails (по умолчанию status=fail, since_hours=24) | QualityFailsTable, qualityFilters.status=fail, loadQualityChecks() |
| Actions: Run pipeline → ответ queued/run_id, обновление Health + SyncRuns | runPipelineAction(), затем loadHealth() + loadSyncRuns() |
| Actions: Refresh block (ta-ui, без ключа) | doRefreshBlock() через refreshBlockDetail() из ta.ts |
| Actions: Refresh apartment (ta-ui, без ключа) | doRefreshApartment() через refreshApartmentDetail() из ta.ts |
| Ключ только в sessionStorage (dev), не в localStorage | setAdminKey()/clearAdminKey() работают с sessionStorage; в README предупреждение |
| Prod: ключ из VITE_TA_ADMIN_KEY | getAdminKey() в prod читает import.meta.env.VITE_TA_ADMIN_KEY |
| Без ключа методы бросают Error('Admin key not configured') | getAdminKey() throw; createClient() вызывает getAdminKey() |
| Copy curl с placeholder YOUR_KEY | copyCurl() формирует строку с YOUR_KEY, без подстановки реального ключа |
| Typecheck + build | `npm run build` (vue-tsc -b && vite build) |

Тесты: в проекте нет фронтовых тестов — ограничились typecheck и build. При появлении Vitest/Jest можно добавить unit-тест на поведение getAdminKey() (mock import.meta.env и sessionStorage).
