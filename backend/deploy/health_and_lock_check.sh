#!/bin/bash
# Read .env and run health + pipeline lock checks. Do not echo secrets.
set -e
cd /var/www/trend-api/backend
[ -f .env ] || { echo "NO_ENV"; exit 1; }
source <(grep -E '^(INTERNAL_API_KEY|APP_URL)=' .env | sed 's/^/export /')
export INTERNAL_API_KEY=$(grep '^INTERNAL_API_KEY=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")
export APP_URL=$(grep '^APP_URL=' .env | cut -d= -f2- | tr -d ' ')
[ -n "$INTERNAL_API_KEY" ] && [ -n "$APP_URL" ] || { echo "MISSING_KEY_OR_URL"; exit 2; }
echo "=== HEALTH ==="
CODE=$(curl -s -o /tmp/health.json -w '%{http_code}' -H "X-Internal-Key: $INTERNAL_API_KEY" "${APP_URL}/api/ta/admin/health")
echo "HTTP $CODE"
[ -f /tmp/health.json ] && cat /tmp/health.json | head -c 800
echo ""
echo "=== PIPELINE LOCK (1st) ==="
CODE1=$(curl -s -o /tmp/pipe1.json -w '%{http_code}' -X POST -H "X-Internal-Key: $INTERNAL_API_KEY" -H "Content-Type: application/json" -d '{"city_id":"test-lock","lang":"en"}' "${APP_URL}/api/ta/pipeline/run")
echo "HTTP $CODE1"
[ -f /tmp/pipe1.json ] && cat /tmp/pipe1.json | head -c 400
echo ""
echo "=== PIPELINE LOCK (2nd, expect 409) ==="
CODE2=$(curl -s -o /tmp/pipe2.json -w '%{http_code}' -X POST -H "X-Internal-Key: $INTERNAL_API_KEY" -H "Content-Type: application/json" -d '{"city_id":"test-lock","lang":"en"}' "${APP_URL}/api/ta/pipeline/run")
echo "HTTP $CODE2"
[ -f /tmp/pipe2.json ] && cat /tmp/pipe2.json | head -c 400
