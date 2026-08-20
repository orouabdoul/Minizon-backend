#!/bin/bash
set -e

# Cache Laravel config (fast, no DB needed)
php artisan config:cache 2>/dev/null || true
php artisan route:cache  2>/dev/null || true
php artisan view:cache   2>/dev/null || true

# Run migrations in the background so Apache starts immediately
# Logs go to stderr so Render captures them
(
    sleep 5  # Give Apache a moment to bind the port first
    php artisan migrate --force 2>&1 | while IFS= read -r line; do
        echo "[migrate] $line" >&2
    done
    echo "[migrate] Done." >&2
) &

# Start Apache in the foreground — Render health checks pass immediately
exec apache2-foreground
