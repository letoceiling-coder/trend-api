# Защита TA Admin в nginx

Ограничение доступа к админке TrendAgent на уровне nginx: **без изменений в приложении**, только конфигурация и (при необходимости) htpasswd. Все изменения обратимы: удаление/закомментирование location'ов и include возвращает доступ по умолчанию (защита на уровне приложения — X-Internal-Key).

---

## Production: пошаговые команды (copy-paste)

Выполнять на сервере под root или с `sudo`. Путь репозитория — пример (`/var/www/trend-api`); замените на свой.

### Шаг 1. Каталог и allowlist

```bash
sudo mkdir -p /etc/nginx/ta
sudo cp /var/www/trend-api/scripts/ta-admin-allowlist.conf.example /etc/nginx/ta/ta-admin-allowlist.conf
sudo nano /etc/nginx/ta/ta-admin-allowlist.conf
```

Оставить только нужные `allow ...;` (и при необходимости закомментировать лишние), в конце обязательно `deny all;`. Сохранить.

### Шаг 2. Basic Auth (для /admin/ta)

```bash
sudo apt-get update && sudo apt-get install -y apache2-utils
sudo htpasswd -c /etc/nginx/ta/htpasswd.ta-admin admin
sudo chown root:www-data /etc/nginx/ta/htpasswd.ta-admin
sudo chmod 640 /etc/nginx/ta/htpasswd.ta-admin
```

Добавить пользователя (без `-c`): `sudo htpasswd /etc/nginx/ta/htpasswd.ta-admin another_user`.

### Шаг 3. Включить фрагмент в server

Вставить внутрь своего `server { }` (vhost) **только** location'ы ниже (пути единые: `/etc/nginx/ta/`):

```nginx
# TA Admin: /api/ta/admin/* allowlist+deny all; /admin/ta satisfy any (Basic Auth OR allowlist)
location /api/ta/admin/ {
    include /etc/nginx/ta/ta-admin-allowlist.conf;
    try_files $uri $uri/ /index.php?$query_string;
}
location /admin/ta {
    satisfy any;
    auth_basic "TA Admin";
    auth_basic_user_file /etc/nginx/ta/htpasswd.ta-admin;
    include /etc/nginx/ta/ta-admin-allowlist.conf;
    try_files $uri $uri/ /index.html?$query_string;
}
location /admin/ta/ {
    satisfy any;
    auth_basic "TA Admin";
    auth_basic_user_file /etc/nginx/ta/htpasswd.ta-admin;
    include /etc/nginx/ta/ta-admin-allowlist.conf;
    try_files $uri $uri/ /index.html?$query_string;
}
```

Либо скопировать содержимое файла **scripts/nginx-ta-security.conf.example** (там только location'ы, без `server {}`).

### Шаг 4. Проверка и перезагрузка nginx

```bash
sudo nginx -t && sudo systemctl reload nginx
```

При ошибке синтаксиса reload не выполнится.

### Откат (rollback)

Закомментировать или удалить добавленные `location /api/ta/admin/`, `location /admin/ta` и `location /admin/ta/` (и строку include фрагмента, если использовали). Затем:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

Доступ к admin снова только по X-Internal-Key (приложение).

---

## Что защищаем

| Путь | Правило | Результат для остальных |
|------|---------|---------------------------|
| **/api/ta/admin/** | Только allowlist IP | **403 Forbidden** |
| **/admin/ta** (frontend) | Basic Auth **или** allowlist IP (satisfy any) | 401 (basic) или 403 (deny) |

---

## 1. /api/ta/admin/* — только allowlist

- Доступ разрешён только с IP из списка.
- Все остальные получают **403**.
- Проверка выполняется до передачи запроса в Laravel.

**Единые пути (обязательно):**
- allowlist: **/etc/nginx/ta/ta-admin-allowlist.conf**
- htpasswd: **/etc/nginx/ta/htpasswd.ta-admin**

Файл allowlist должен содержать только директивы `allow`/`deny` и заканчиваться на `deny all;`:

```nginx
allow 10.0.0.0/8;
allow 192.168.0.0/16;
allow 127.0.0.1;
# allow 203.0.113.50;
deny all;
```

---

## 2. /admin/ta — Basic Auth или allowlist

- Доступ разрешён, если **либо** пройдена Basic Auth (htpasswd), **либо** IP в allowlist.
- Иначе — 401 (запрос логина) или 403.

См. фрагмент выше (Шаг 3 и блок copy-paste).

- **satisfy any** — достаточно одного условия: успешная Basic Auth **или** IP из allowlist.
- Если фронт отдаётся через Laravel (один entrypoint), замените `/index.html` на `/index.php?$query_string` по необходимости.

---

## Пример полного фрагмента server (пути единые: /etc/nginx/ta/)

```nginx
server {
    listen 443 ssl;
    server_name example.com;
    root /var/www/trend-api/backend/public;
    index index.php index.html;

    location /api/ta/admin/ {
        include /etc/nginx/ta/ta-admin-allowlist.conf;
        try_files $uri $uri/ /index.php?$query_string;
    }
    location /admin/ta {
        satisfy any;
        auth_basic "TA Admin";
        auth_basic_user_file /etc/nginx/ta/htpasswd.ta-admin;
        include /etc/nginx/ta/ta-admin-allowlist.conf;
        try_files $uri $uri/ /index.html?$query_string;
    }
    location /admin/ta/ {
        satisfy any;
        auth_basic "TA Admin";
        auth_basic_user_file /etc/nginx/ta/htpasswd.ta-admin;
        include /etc/nginx/ta/ta-admin-allowlist.conf;
        try_files $uri $uri/ /index.html?$query_string;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php-fpm.sock;
    }
}
```

Готовые примеры в репозитории:

- **scripts/nginx-ta-security.conf.example** — только location'ы (без `server {}`); вставить внутрь своего server.
- **scripts/ta-admin-allowlist.conf.example** — скопировать в **/etc/nginx/ta/ta-admin-allowlist.conf** и отредактировать.

---

## Allowlist из ENV

nginx не читает переменные окружения напрямую. Варианты:

1. **Ручное редактирование**  
   Скопировать `ta-admin-allowlist.conf.example` в `/etc/nginx/ta-admin-allowlist.conf` и прописать нужные `allow`/`deny`.

2. **Генерация при деплое из ENV**  
   В скрипте деплоя или systemd unit задать переменную, например:
   ```bash
   export TA_ADMIN_ALLOWLIST_IPS="203.0.113.50 198.51.100.0/24"
   ```
   И сгенерировать конфиг:
   ```bash
   TA_ADMIN_ALLOWLIST_IPS="${TA_ADMIN_ALLOWLIST_IPS:-127.0.0.1}"
   {
     for ip in $TA_ADMIN_ALLOWLIST_IPS; do
       printf 'allow %s;\n' "$ip"
     done
     echo 'deny all;'
   } > /etc/nginx/ta-admin-allowlist.conf
   ```
   Затем проверить и перезагрузить nginx (см. ниже).

---

## Команды

### Создать/обновить htpasswd (путь: /etc/nginx/ta/htpasswd.ta-admin)

```bash
sudo apt-get update && sudo apt-get install -y apache2-utils
sudo htpasswd -c /etc/nginx/ta/htpasswd.ta-admin admin
sudo chown root:www-data /etc/nginx/ta/htpasswd.ta-admin
sudo chmod 640 /etc/nginx/ta/htpasswd.ta-admin
```

Добавить пользователя (без `-c`): `sudo htpasswd /etc/nginx/ta/htpasswd.ta-admin another_user`

### Allowlist (файл в /etc/nginx/ta/)

```bash
sudo mkdir -p /etc/nginx/ta
sudo cp /var/www/trend-api/scripts/ta-admin-allowlist.conf.example /etc/nginx/ta/ta-admin-allowlist.conf
sudo nano /etc/nginx/ta/ta-admin-allowlist.conf
```

### Проверка и перезагрузка nginx

```bash
sudo nginx -t && sudo systemctl reload nginx
```

При ошибке в конфиге reload не выполнится.

---

## Кратко

- **/api/ta/admin/** — доступ только с IP из allowlist, иначе 403.
- **/admin/ta** — доступ при успешной Basic Auth **или** при IP из того же allowlist.
- Allowlist — отдельный файл с `allow`/`deny`; при желании его можно генерировать из ENV при деплое.
- Приложение не меняется; защита только в nginx и (опционально) в htpasswd.
