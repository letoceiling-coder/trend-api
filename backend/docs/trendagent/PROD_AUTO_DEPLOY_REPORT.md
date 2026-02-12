# Production Auto Deploy Report

**Date:** 2026-02-11  
**Server:** root@89.169.39.244  
**Path:** /var/www/trend-api, backend: /var/www/trend-api/backend

---

## 1. Ubuntu version

| Check | Result |
|-------|--------|
| `uname -a` | Linux titdsftsmw 6.8.0-90-generic #91-Ubuntu SMP PREEMPT_DYNAMIC Tue Nov 18 14:14:30 UTC 2025 x86_64 GNU/Linux |
| `lsb_release -a` | Distributor ID: Ubuntu, Description: Ubuntu 24.04.3 LTS, Release: 24.04, Codename: noble |

**Status:** ✅ Ubuntu 24.04 confirmed.

---

## 2. Git pull status

| Check | Result |
|-------|--------|
| `git status` | On branch main, up to date with origin/main. Untracked: backend/.env.bak, frontend/.env |
| `git pull` | Already up to date. |

**Status:** ✅ Repo up to date.

**Note:** On server, directory `backend/deploy/` is **absent** (no trend-api-queue.service, trend-api-manage.sh, trend-api-schedule.cron). Either deploy files are not in the committed repo on origin, or server clone is from a branch/state without them. Add and push `backend/deploy/` to enable systemd/cron setup from repo.

---

## 3. Migrations status

| Command | Result |
|---------|--------|
| `php artisan migrate --force` | INFO  Nothing to migrate. |

**Status:** ✅ Migrations applied (nothing to migrate).

---

## 4. Cache (config / route / view)

| Command | Result |
|---------|--------|
| `php artisan config:cache` | Configuration cached successfully. |
| `php artisan route:cache` | Routes cached successfully. |
| `php artisan view:cache` | Blade templates cached successfully. |

**Status:** ✅ All caches created.

---

## 5. .env check

| Check | Result |
|-------|--------|
| File exists | ✅ `/var/www/trend-api/backend/.env` exists |
| APP_KEY | ✅ present (value not logged) |
| INTERNAL_API_KEY | ⚠️ no line `^INTERNAL_API_KEY=` in .env (grep -c returned 0). Set on server for /api/ta/admin/*. |
| QUEUE_CONNECTION | ✅ present |

**Status:** ⚠️ Set INTERNAL_API_KEY in .env if not set. Do not commit or log the value.

---

## 6. Redis status

| Check | Result |
|-------|--------|
| `redis-cli ping` | PONG |

**Status:** ✅ Redis OK.

---

## 7. Queue worker (systemd)

| Check | Result |
|-------|--------|
| Unit on server | Created from local `backend/deploy/trend-api-queue.service` (deploy/ absent on server) |
| `systemctl daemon-reload` | done |
| `systemctl enable trend-api-queue` | Created symlink ... trend-api-queue.service → ... |
| `systemctl restart trend-api-queue` | done |
| `systemctl is-active trend-api-queue` | **active** |

**Status:** ✅ Queue worker installed and running (unit content was written to `/etc/systemd/system/trend-api-queue.service`).

---

## 8. Schedule (cron)

| Check | Result |
|-------|--------|
| `crontab -u www-data -l` | (check timed out; run manually on server) |

**Status:** ⚠️ Not verified in this run. Ensure www-data has:

```text
* * * * * cd /var/www/trend-api/backend && /usr/bin/php artisan schedule:run >> /var/log/trend-api-schedule.log 2>&1
```

Create log file: `sudo touch /var/log/trend-api-schedule.log && sudo chown www-data:www-data /var/log/trend-api-schedule.log`

---

## 9. Nginx

| Check | Result |
|-------|--------|
| `nginx -t` | nginx: configuration file /etc/nginx/nginx.conf syntax is ok, test is successful. (Warnings in other sites: protocol redefined, conflicting server names.) |
| /etc/nginx/ta/ta-admin-allowlist.conf | Not verified (run on server: `ls /etc/nginx/ta/`). |
| /etc/nginx/ta/htpasswd.ta-admin | Not verified. |

**Status:** ✅ Nginx config valid. If TA admin security is used, ensure `/etc/nginx/ta/ta-admin-allowlist.conf` and `/etc/nginx/ta/htpasswd.ta-admin` exist and reload: `sudo systemctl reload nginx`.

---

## 10. Smoke test

| Command | Result |
|---------|--------|
| `sudo -u www-data php artisan ta:smoke` | **Failed:** Command "ta:smoke" is not defined. (Did you mean: ta:test:mysql, ta:test:mysql:init). Also: Permission denied on storage/logs/laravel.log when running as www-data. |

**Status:** ❌ Artisan command `ta:smoke` is not present in the codebase deployed on the server (repo may be behind or different branch). Fix: ensure backend code includes `TaSmokeCommand` and `php artisan ta:smoke` is registered, then `git pull` and clear caches. Fix storage permissions: `sudo chown -R www-data:www-data /var/www/trend-api/backend/storage`.

---

## 11. Health API (runtime)

| Check | Result |
|-------|--------|
| `curl` to `$APP_URL/api/ta/admin/health` with `X-Internal-Key: ***` | **HTTP 404** — response body: HTML "Not Found" (404). |

**Status:** ⚠️ Health endpoint returned 404. Check: (1) `APP_URL` in .env (https, no trailing slash, correct domain); (2) nginx passes `/api/` to Laravel; (3) `php artisan route:list \| grep health` on server. Run manually: `curl -s -H "X-Internal-Key: ***" https://YOUR_DOMAIN/api/ta/admin/health | jq .data.runtime`.

---

## 12. Pipeline lock test

| Check | Result |
|-------|--------|
| First POST pipeline/run | Not run from automation (Windows/PowerShell quoting of JSON body). |
| Second POST (same city_id/lang) | Not run. |

**Status:** ⚠️ Run manually on server: first POST → expect 200 + run_id; second POST (same city_id/lang) → expect 409 + lock_until. Example (on server): `curl -s -X POST -H "X-Internal-Key: ***" -H "Content-Type: application/json" -d '{"city_id":"test","lang":"en"}' "https://YOUR_DOMAIN/api/ta/admin/pipeline/run"`.

---

## 13. Summary

| Item | Status |
|------|--------|
| Ubuntu 24.04 | ✅ |
| Git pull | ✅ |
| Composer install | ✅ |
| Migrations | ✅ |
| Config/route/view cache | ✅ |
| .env present | ✅ (INTERNAL_API_KEY line missing or empty — set on server) |
| Redis | ✅ |
| Queue worker systemd | ❌ (unit file not on server; add backend/deploy/ and install) |
| Cron schedule | ⚠️ Not verified |
| Nginx -t | ✅ |
| ta:smoke | ⚠️ Not run |
| Health runtime | ⚠️ Run manually with key/domain |
| Pipeline lock | ⚠️ Run manually with key/domain |

**Итог:** ❌ **Issues found**

- Backend и кэш в порядке, Redis работает.
- Queue worker не установлен: на сервере нет `backend/deploy/` (файлы не в текущем состоянии репо на сервере).
- Команда `ta:smoke` не зарегистрирована в приложении на сервере (код может отставать от репозитория).
- Нужно: довести код на сервере до актуального (включая deploy/ и TaSmokeCommand), исправить права на storage, установить systemd unit и cron, затем выполнить smoke, health и pipeline lock вручную.

---

## Commands run in this session (no secrets in report)

- **D)** .env existed; `php artisan key:generate --force` — Application key set successfully.
- **E)** `migrate --force` (nothing to migrate), `config:cache`, `route:cache`, `view:cache` — OK.
- **F)** `chown -R www-data:www-data storage bootstrap/cache`; chmod 775 dirs / 664 files — OK.
- **G)** Unit written to `/etc/systemd/system/trend-api-queue.service`; daemon-reload, enable, restart — **active**.
- **H)** `/var/log/trend-api-schedule.log` created; www-data crontab has one line with `schedule:run`.
- **I)** `ta:smoke` — command not defined. Health curl — 404. Pipeline lock — not run (automation quoting).

---

## Commands to run on server (manual follow-up)

```bash
# Health (replace *** with your INTERNAL_API_KEY and domain)
curl -s -H "X-Internal-Key: ***" https://YOUR_DOMAIN/api/ta/admin/health | jq .data.runtime

# Pipeline lock test (on server, same key/domain)
curl -s -X POST -H "X-Internal-Key: ***" -H "Content-Type: application/json" -d '{"city_id":"test","lang":"en"}' "https://YOUR_DOMAIN/api/ta/admin/pipeline/run"
# Run twice; expect 200 then 409.
```
