#!/usr/bin/env bash
# Arahkan runtime (Caddy, PHP-FPM, Next.js) dari working copy ke
# /var/www/sistlabum/current — layout release + rollback.
#
# Jalankan sebagai user pemilik service (bukan sudo), SETELAH deploy CI pertama.
set -euo pipefail

OLD="${OLD_PATH:-/home/j1nkisaragi/projects/SMPLABUMFIXREV}"
NEW="${NEW_PATH:-/var/www/sistlabum/current}"

CADDYFILE="$HOME/.config/caddy/Caddyfile"
FPM_CONF="$HOME/.config/php-fpm/sistlabum.conf"
WEB_UNIT="$HOME/.config/systemd/user/sistlabum-web.service"
QUEUE_UNIT="$HOME/.config/systemd/user/sistlabum-queue.service"
SCHED_UNIT="$HOME/.config/systemd/user/sistlabum-scheduler.service"

log() { printf '[switch] %s\n' "$*"; }

[[ -e "$NEW" ]] || { echo "FATAL: $NEW belum ada. Jalankan deploy CI dulu."; exit 1; }
[[ -f "$NEW/apps/api/public/index.php" ]] || { echo "FATAL: $NEW/apps/api/public/index.php tidak ada"; exit 1; }

log "cek keterbacaan $NEW"
[[ -r "$NEW/apps/api/.env" ]] || log "PERINGATAN: $NEW/apps/api/.env tidak terbaca — cek keanggotaan grup www-data"
[[ -x "$NEW/apps/web/node_modules/.bin/next" || -x "$NEW/node_modules/.bin/next" ]] || \
  log "PERINGATAN: next binary belum ada di release (hook build belum jalan?)"

backup() { cp -a "$1" "$1.bak.$(date +%Y%m%d%H%M%S)"; }

log "backup konfigurasi"
for f in "$CADDYFILE" "$FPM_CONF" "$WEB_UNIT" "$QUEUE_UNIT" "$SCHED_UNIT"; do
  [[ -f "$f" ]] && backup "$f"
done

log "tulis ulang path"
sed -i "s|$OLD|$NEW|g" "$CADDYFILE" "$FPM_CONF" "$WEB_UNIT" "$QUEUE_UNIT" "$SCHED_UNIT"

# Next butuh node_modules di root release atau apps/web; pakai yang tersedia
if [[ -x "$NEW/node_modules/.bin/next" ]]; then
  sed -i "s|$NEW/node_modules/.bin/next|$NEW/node_modules/.bin/next|" "$WEB_UNIT"
elif [[ -x "$NEW/apps/web/node_modules/.bin/next" ]]; then
  sed -i "s|$NEW/node_modules/.bin/next|$NEW/apps/web/node_modules/.bin/next|" "$WEB_UNIT"
fi

log "validasi konfigurasi"
/usr/sbin/php-fpm8.4 -t -y "$FPM_CONF" >/dev/null 2>&1 || { echo "FATAL: konfigurasi PHP-FPM invalid"; exit 1; }
systemctl --user daemon-reload

log "restart stack"
systemctl --user restart sistlabum-fpm.service
systemctl --user restart sistlabum-caddy.service
systemctl --user restart sistlabum-web.service
systemctl --user restart sistlabum-queue.service
sleep 8

log "health check"
code_web=$(curl -s -o /dev/null -w '%{http_code}' -m 15 http://127.0.0.1:3100/ || echo 000)
code_api=$(curl -s -o /dev/null -w '%{http_code}' -m 15 http://127.0.0.1:8000/api/v1/health || echo 000)
printf '  web : %s\n  api : %s\n' "$code_web" "$code_api"

if [[ "$code_web" == "200" && "$code_api" == "200" ]]; then
  log "sukses. Runtime sekarang: $NEW"
else
  log "GAGAL. Kembalikan dengan: cp <file>.bak.<stamp> <file> lalu restart"
  exit 1
fi
