#!/usr/bin/env bash
# Linux/macOS equivalent of start-dev.bat
set -e
ROOT="$(cd "$(dirname "$0")" && pwd)"
(cd "$ROOT/backend" && php artisan serve --port=8000) &
(cd "$ROOT/frontend" && npm run dev) &
trap 'kill 0' EXIT
wait
