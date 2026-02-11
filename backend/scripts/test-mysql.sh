#!/usr/bin/env bash
# One-command MySQL test runner. Run from backend: ./scripts/test-mysql.sh
set -e
cd "$(dirname "$0")/.."
exec php artisan ta:test:mysql
