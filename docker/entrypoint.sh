#!/bin/bash
# Entrypoint for php artisan serve deployment (Railway / Docker)
# NEVER call exit before the web server starts — let errors be warnings.

APP_ROOT="/var/www/html"
cd "$APP_ROOT"

# ── 1. Warn on critical missing vars (non-fatal) ─────────────────────────────
for var in APP_KEY DB_CONNECTION DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD; do
    if [ -z "${!var:-}" ]; then
        echo "[entrypoint] WARNING: $var is not set"
    fi
done

# ── 2. Wait for database (TCP probe, 15 retries, non-fatal) ──────────────────
if [ -n "${DB_HOST:-}" ]; then
    DB_PORT_NUM="${DB_PORT:-3306}"
    echo "[entrypoint] Waiting for DB at ${DB_HOST}:${DB_PORT_NUM} ..."
    for i in $(seq 1 15); do
        if (exec 3<>"/dev/tcp/${DB_HOST}/${DB_PORT_NUM}") 2>/dev/null; then
            exec 3>&-
            echo "[entrypoint] DB is reachable."
            break
        fi
        exec 3>&- 2>/dev/null
        echo "[entrypoint] DB not ready (attempt ${i}/15), retrying in 2s..."
        sleep 2
    done
fi

# ── 3. Run migrations (non-fatal) ────────────────────────────────────────────
if [ "${SKIP_MIGRATIONS:-false}" != "true" ]; then
    echo "[entrypoint] Running migrations..."
    php artisan migrate --force --no-interaction 2>&1 || \
        echo "[entrypoint] WARNING: migrations failed, continuing anyway"
fi

# ── 4. Laravel caches (non-fatal, only when APP_KEY is set) ──────────────────
if [ -n "${APP_KEY:-}" ]; then
    php artisan config:cache  || true
    php artisan route:cache   || true
    php artisan view:cache    || true
    php artisan event:cache   || true
fi

# ── 5. Queue worker in background (optional) ─────────────────────────────────
if [ "${ENABLE_QUEUE_WORKER:-true}" = "true" ]; then
    echo "[entrypoint] Starting queue worker in background..."
    php artisan queue:work --sleep=3 --tries=3 --timeout=90 --queue=default &
fi

# ── 6. Scheduler in background (optional) ────────────────────────────────────
if [ "${ENABLE_SCHEDULER:-true}" = "true" ]; then
    echo "[entrypoint] Starting scheduler in background..."
    php artisan schedule:work &
fi

# ── 7. Start web server (foreground — Railway injects $PORT) ─────────────────
PORT="${PORT:-8080}"
echo "[entrypoint] Starting php artisan serve on 0.0.0.0:${PORT}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
