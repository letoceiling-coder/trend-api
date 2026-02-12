## Frontend (Vue 3 + Vite + Tailwind)

Минимальный frontend на Vue 3 + Vite + TypeScript с TailwindCSS.

### Установка зависимостей

```bash
cd frontend
npm install
```

### Запуск dev-сервера

```bash
cd frontend
npm run dev
```

По умолчанию Vite поднимает dev-сервер на:

```text
http://localhost:5173
```

### Конфигурация API

Фронтенд ожидает, что backend Laravel доступен по адресу
`http://localhost:8000`. Базовый URL API настраивается через
переменную окружения:

```dotenv
VITE_API_BASE=http://localhost:8000
```

Создайте локальный файл окружения для Vite (например, `.env` или
`.env.development`) в каталоге `frontend/` и пропишите там эту
переменную.

### Страницы

- **Home** — корневая страница, показывает заголовок “TrendMirror UI”.
- **HealthCheck** — страница с кнопкой **Check API**, которая делает
  запрос `GET {VITE_API_BASE}/api/health` и показывает JSON‑ответ.
- **Admin TA** (`/admin/ta`) — панель мониторинга TrendAgent: health, sync runs, contract changes, quality checks, запуск pipeline и refresh block/apartment.

### Admin TA (/admin/ta)

Страница доступна по маршруту **/admin/ta**. В навигации ссылка «Admin TA» отображается в dev-режиме или если задана переменная `VITE_TA_ADMIN_KEY`.

**Ключ доступа (X-Internal-Key):**

- Все запросы к `/api/ta/admin/*` требуют заголовок `X-Internal-Key`. Ключ **не хранится в репозитории** и **не выводится в консоль/логи**.
- **В dev:** при первом заходе на страницу отображается форма ввода ключа. Ключ сохраняется **только в sessionStorage** (не в localStorage). Кнопка «Clear key» удаляет его.
- **В prod:** ключ задаётся через переменную окружения сборки `VITE_TA_ADMIN_KEY` (в `.env.production` или на CI; реальные значения не коммитить). Если переменная задана, форма ввода не показывается.

**Важно:** не используйте localStorage для ключа — sessionStorage очищается при закрытии вкладки.

