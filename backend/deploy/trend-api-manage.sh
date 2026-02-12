#!/usr/bin/env bash
# TrendAgent production: start/stop/status/restart queue worker + schedule info
# Usage: ./deploy/trend-api-manage.sh {start|stop|status|restart|logs}
# Run as root or with sudo for systemctl; status/logs can be run as any user with access.

set -e
SVC=trend-api-queue

case "${1:-status}" in
  start)
    sudo systemctl start "$SVC"
    echo "Started $SVC."
    ;;
  stop)
    sudo systemctl stop "$SVC"
    echo "Stopped $SVC."
    ;;
  restart)
    sudo systemctl restart "$SVC"
    echo "Restarted $SVC."
    ;;
  status)
    echo "=== $SVC (systemd) ==="
    if sudo systemctl is-active --quiet "$SVC" 2>/dev/null; then
      echo "active"
    else
      echo "inactive or not found"
    fi
    sudo systemctl status "$SVC" --no-pager 2>/dev/null || true
    echo ""
    echo "=== Schedule (cron) ==="
    echo "Expected: cron runs 'php artisan schedule:run' every minute for user www-data."
    (sudo crontab -u www-data -l 2>/dev/null | grep -E 'schedule:run|trend-api' || echo "No trend-api cron for www-data.") || true
    if [ -f /var/log/trend-api-schedule.log ]; then
      echo ""
      echo "=== Schedule log (last line) ==="
      tail -1 /var/log/trend-api-schedule.log 2>/dev/null || echo "(empty or unreadable)"
    fi
    echo ""
    echo "=== Runtime (health) ==="
    echo "Example: curl -s -H 'X-Internal-Key: YOUR_INTERNAL_KEY' https://YOUR_DOMAIN/api/ta/admin/health | jq .data.runtime"
    ;;
  logs)
    echo "=== Journal (last 100 lines) ==="
    sudo journalctl -u "$SVC" -n 100 --no-pager
    ;;
  *)
    echo "Usage: $0 {start|stop|status|restart|logs}"
    exit 1
    ;;
esac
