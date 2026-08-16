#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/gaz-armory/current}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/gaz-armory}"
KEEP_DAYS="${KEEP_DAYS:-14}"
ENV_FILE="${ENV_FILE:-/etc/gaz-armory/backup.env}"
STAMP="$(date +%Y-%m-%d_%H-%M-%S)"

set -a
source "$ENV_FILE"
set +a

: "${DB_HOST:?DB_HOST is required}"
: "${DB_PORT:?DB_PORT is required}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"

mkdir -p "$BACKUP_DIR"
umask 077

PGPASSWORD="$DB_PASSWORD" pg_dump \
  --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" \
  --format=custom --file="$BACKUP_DIR/database_$STAMP.dump" "$DB_DATABASE"

tar -C "$APP_DIR/backend" -czf "$BACKUP_DIR/uploads_$STAMP.tar.gz" storage/app/public
find "$BACKUP_DIR" -type f -mtime "+$KEEP_DAYS" -delete
