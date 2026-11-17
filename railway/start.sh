#!/bin/sh
set -eu
cd /app

if [ "${AUTO_SETUP:-true}" = "true" ]; then
  : "${TELEGRAM_BOT_TOKEN:?Set TELEGRAM_BOT_TOKEN in Railway Variables}"
  : "${TELEGRAM_ADMIN_ID:?Set TELEGRAM_ADMIN_ID in Railway Variables}"
  : "${PANEL_ADMIN_PASSWORD:?Set PANEL_ADMIN_PASSWORD in Railway Variables before first deployment}"
  if [ -z "${APP_URL:-}" ] && [ -z "${RAILWAY_PUBLIC_DOMAIN:-}" ]; then
    echo "Set APP_URL or generate a Railway public domain before first startup." >&2
    exit 1
  fi
fi

PORT="${PORT:-8080}"
sed -i "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

mkdir -p /app/storage/cache
chmod -R 775 /app/storage || true

# Wait for the Railway MySQL service to become reachable before running schema setup.
if [ "${AUTO_SETUP:-true}" = "true" ]; then
  echo "[Mirza] Waiting for database..."
  ready=0
  i=0
  while [ "$i" -lt 45 ]; do
    if php -r 'require "/app/config.php"; echo "DB_OK";' 2>/dev/null | grep -q DB_OK; then
      ready=1
      break
    fi
    i=$((i + 1))
    sleep 2
  done
  if [ "$ready" -ne 1 ]; then
    echo "[Mirza] Database did not become ready. Verify MySQL variables." >&2
    exit 1
  fi

  echo "[Mirza] Applying database schema and registering Telegram webhook..."
  php /app/table.php
  # The legacy installer is not used on Railway; remove its public entry points.
  rm -rf /app/install
fi

# Mirza ships its own per-minute dispatcher. Keep it running alongside Apache.
(
  while true; do
    php /app/cronbot/run.php || echo "[Mirza cron] dispatcher returned an error" >&2
    sleep 60
  done
) &

# Enforce exactly one Apache MPM at runtime as well as during image build.
# This also repairs stale/extra module symlinks that may remain in the image.
echo "[Mirza] Enforcing Apache MPM: mpm_prefork"
for module in mpm_event mpm_worker mpm_prefork; do
  a2dismod "$module" >/dev/null 2>&1 || true
done
rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf
a2enmod mpm_prefork >/dev/null

MPM_COUNT="$(apache2ctl -M 2>&1 | grep -Ec 'mpm_(event|worker|prefork)_module' || true)"
if [ "$MPM_COUNT" -ne 1 ]; then
  echo "[Mirza] ERROR: Expected exactly one Apache MPM, found ${MPM_COUNT}." >&2
  ls -l /etc/apache2/mods-enabled/mpm_* 2>/dev/null || true
  apache2ctl -M 2>&1 || true
  exit 1
fi

apache2ctl -t

echo "[Mirza] Starting Apache on port ${PORT}"
exec apache2-foreground
