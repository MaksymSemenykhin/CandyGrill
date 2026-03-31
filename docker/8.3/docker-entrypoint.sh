#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Mounted bind-mount is often owned by host UID; Git 2.35+ refuses it unless allow-listed.
git config --global --add safe.directory /var/www/html 2>/dev/null || true

if [ ! -f vendor/autoload.php ]; then
    echo "[candygrill] Installing Composer dependencies..."
    composer install --no-interaction --no-progress --prefer-dist
fi

exec "$@"
