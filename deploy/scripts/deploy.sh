#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/gaz-armory/current}"
cd "$APP_DIR"

composer install --working-dir=backend --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci --prefix frontend
npm run build --prefix frontend

php backend/artisan down --retry=30
restore_application() {
  php backend/artisan up || true
}
trap restore_application EXIT

php backend/artisan migrate --force
php backend/artisan storage:link --force
php backend/artisan optimize
php backend/artisan queue:restart
php backend/artisan up
trap - EXIT

sudo systemctl restart gaz-armory-queue gaz-armory-scheduler
