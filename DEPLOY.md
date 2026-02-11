# Deploy (php artisan deploy)

Команда выполняет **локальный** этап (commit + push в GitHub) и **удалённый** этап на сервере по SSH (git pull, composer, миграции, сборка фронта, кэши).

## Требования

- Git настроен, remote `origin` указывает на репозиторий (например `https://github.com/letoceiling-coder/trend-api.git`).
- SSH-доступ на сервер без пароля (ключ в `~/.ssh`).
- На сервере: клонирован тот же репозиторий, установлены PHP, Composer, Node/npm.

## Настройка

### 1. SSH-ключ

Если ключа ещё нет:

```bash
ssh-keygen -t ed25519 -C "deploy" -f ~/.ssh/deploy_trend -N ""
```

Скопировать публичный ключ на сервер:

```bash
ssh-copy-id -i ~/.ssh/deploy_trend.pub root@89.169.39.244
```

Проверка (должен войти без пароля):

```bash
ssh -i ~/.ssh/deploy_trend root@89.169.39.244 "echo OK"
```

Если используете ключ по умолчанию (`~/.ssh/id_rsa` или `~/.ssh/id_ed25519`), дополнительно настраивать не нужно.

### 2. Git remote

Из корня репозитория (папка с `backend/` и `frontend/`):

```bash
git remote -v
```

Если `origin` нет или он другой:

```bash
git remote add origin https://github.com/letoceiling-coder/trend-api.git
# или
git remote set-url origin https://github.com/letoceiling-coder/trend-api.git
```

### 3. Переменные окружения (backend)

В **backend/.env** добавьте (или скопируйте из блока ниже):

```env
# --- Deploy ---
DEPLOY_SSH_HOST=89.169.39.244
DEPLOY_SSH_USER=root
DEPLOY_SSH_PORT=22
DEPLOY_GIT_REMOTE=origin
DEPLOY_GIT_BRANCH=main

# Корень репозитория на сервере (одна папка с backend и frontend)
DEPLOY_REMOTE_REPO_ROOT=/var/www/trend-api
DEPLOY_REMOTE_BACKEND_PATH=/var/www/trend-api/backend
DEPLOY_REMOTE_FRONTEND_PATH=/var/www/trend-api/frontend

# Исполняемые файлы на сервере (при необходимости измените)
DEPLOY_REMOTE_PHP=/usr/bin/php
DEPLOY_REMOTE_COMPOSER=/usr/bin/composer
DEPLOY_REMOTE_NODE=/usr/bin/node
DEPLOY_REMOTE_NPM=/usr/bin/npm
DEPLOY_REMOTE_WEB_USER=www-data

# Опции
DEPLOY_RUN_QUEUE_RESTART=true
DEPLOY_FRONTEND_BUILD_CMD="npm ci && npm run build"
DEPLOY_BACKEND_COMPOSER_CMD="composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev"
```

Пути `DEPLOY_REMOTE_*` должны совпадать с реальным размещением проекта на сервере.

## Запуск

Из папки **backend** (где лежит `artisan`):

```bash
php artisan deploy
```

С сообщением коммита:

```bash
php artisan deploy --message="deploy: fix auth"
```

Только удалённый этап (без commit/push):

```bash
php artisan deploy --only=server
```

Только push (без выполнения команд на сервере):

```bash
php artisan deploy --only=push
```

Показать команды без выполнения:

```bash
php artisan deploy --dry-run
```

Пропуск отдельных шагов:

```bash
php artisan deploy --skip-migrate --skip-frontend
```

Если есть незакоммиченные изменения и нужно их закоммитить и отправить:

```bash
php artisan deploy --force
```

## Флаги

| Флаг | Описание |
|------|----------|
| `--message="..."` | Сообщение коммита (по умолчанию: `deploy: YYYY-MM-DD HH:MM`) |
| `--branch=main` | Ветка (переопределяет `DEPLOY_GIT_BRANCH`) |
| `--skip-push` | Не делать commit и push |
| `--skip-migrate` | Не запускать миграции на сервере |
| `--skip-frontend` | Не собирать фронт на сервере |
| `--skip-composer` | Не запускать composer на сервере |
| `--skip-cache` | Не выполнять config/route/view cache |
| `--skip-queue` | Не выполнять queue:restart |
| `--only=all\|push\|server` | Что выполнять: всё, только push, только сервер |
| `--dry-run` | Вывести команды, не выполняя их |
| `--force` | Разрешить коммит при наличии незакоммиченных изменений |

## Поведение

- **Локальный этап:** проверяется, что текущая папка — git-репозиторий и настроен remote. Если есть изменения и не передан `--force`, команда останавливается с подсказкой. Если изменений нет, выполняется только `git push`.
- **Удалённый этап:** по SSH выполняется последовательность: `git fetch` + `git reset --hard origin/<branch>` в корне репо на сервере, затем в `backend` — composer, миграции, кэши, при необходимости queue:restart; в `frontend` — сборка (по умолчанию `npm ci && npm run build`).

## Ошибки

- **"Deploy configuration is incomplete"** — в `backend/.env` не заданы обязательные переменные: `DEPLOY_SSH_HOST`, `DEPLOY_REMOTE_BACKEND_PATH`, `DEPLOY_REMOTE_FRONTEND_PATH`.
- **"Not a git repository"** — команда запущена не из корня репо или в папке нет `.git`. Запускайте из корня (где лежат `backend/` и `frontend/`): для вызова artisan перейдите в `backend/`, но сама проверка git идёт по корню репо (определяется автоматически).
- **"Git remote ... is not set"** — добавьте remote: `git remote add origin https://github.com/letoceiling-coder/trend-api.git`.
- **SSH timeout / Permission denied** — проверьте ключ и доступ: `ssh root@89.169.39.244 "echo OK"`.
- **На сервере путь не найден** — проверьте `DEPLOY_REMOTE_REPO_ROOT`, `DEPLOY_REMOTE_BACKEND_PATH`, `DEPLOY_REMOTE_FRONTEND_PATH`; на сервере должны существовать эти каталоги после клонирования репозитория.

Команда определяет корень репозитория как родительскую папку от `backend/` (где находится Laravel). Фронтенд ожидается в `frontend/` в этом же корне.
