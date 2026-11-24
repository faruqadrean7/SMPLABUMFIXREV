#!/usr/bin/env bash
# Dijalankan di dalam release baru, SEBELUM symlink `current` dipindah.
# Kalau skrip ini gagal, swap dibatalkan dan versi lama tetap melayani user.
set -euo pipefail

REL_ROOT="$(pwd)"
SHARED="${SHARED_ROOT:-/var/www/sistlabum/shared}"
API="$REL_ROOT/apps/api"
WEB="$REL_ROOT/apps/web"

log() { printf '[pre-activate] %s\n' "$*"; }

# ------------------------------------------------------------------
# 1. Berkas yang tidak boleh ikut deploy: .env & storage
# ------------------------------------------------------------------
mkdir -p "$SHARED/api-storage/app/public" "$SHARED/api-storage/framework"/{cache,sessions,views} "$SHARED/api-storage/logs"

[[ -f "$SHARED/api.env" ]] || { log "FATAL: $SHARED/api.env belum ada"; exit 1; }
[[ -f "$SHARED/web.env" ]] || { log "FATAL: $SHARED/web.env belum ada"; exit 1; }

ln -sfn "$SHARED/api.env" "$API/.env"
rm -rf "$API/storage"
ln -sfn "$SHARED/api-storage" "$API/storage"
ln -sfn "$SHARED/web.env" "$WEB/.env.production"
log "shared state terpasang"

# ------------------------------------------------------------------
# 2. Backend Laravel
# ------------------------------------------------------------------
cd "$API"
log "composer install"
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-progress

log "migrate"
php artisan migrate --force

log "warm cache"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link >/dev/null 2>&1 || true

# ------------------------------------------------------------------
# 3. Frontend Next.js (build di release baru, bukan di folder live)
# ------------------------------------------------------------------
cd "$WEB"
log "npm ci"
npm ci --no-audit --no-fund

log "next build"
# Muat env web tanpa `source` — .env bukan skrip shell yang aman di-source.
load_env() {
  local f="$1" line key val
  while IFS= read -r line || [[ -n "$line" ]]; do
    case "$line" in ''|\#*) continue ;; esac
    [[ "$line" == *=* ]] || continue
    key="${line%%=*}"
    val="${line#*=}"
    [[ "$key" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]] || continue
    val="${val%\"}"; val="${val#\"}"
    export "$key=$val"
  done < "$f"
}
load_env "$SHARED/web.env"
NODE_ENV=production npm run build

log "release siap dipindah"
