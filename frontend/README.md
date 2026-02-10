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

