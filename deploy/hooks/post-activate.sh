#!/usr/bin/env bash
# Dijalankan SETELAH symlink `current` menunjuk release baru.
# Kalau health check gagal, keluar dengan kode 1 supaya workflow rollback.
set -euo pipefail

PORT="${WEB_PORT:-3002}"
TRIES="${HEALTH_TRIES:-30}"

log() { printf '[post-activate] %s\n' "$*"; }

# ------------------------------------------------------------------
# 1. Restart service web (systemd user unit, tanpa sudo)
# ------------------------------------------------------------------
if systemctl --user list-unit-files 2>/dev/null | grep -q '^sistlabum-web\.service'; then
  log "restart sistlabum-web"
  systemctl --user restart sistlabum-web.service
else
  log "PERINGATAN: unit sistlabum-web tidak ditemukan; restart dilewati"
fi

# ------------------------------------------------------------------
# 2. Health check
# ------------------------------------------------------------------
for i in $(seq 1 "$TRIES"); do
  code=$(curl -s -o /dev/null -w '%{http_code}' -m 3 "http://127.0.0.1:$PORT/" 2>/dev/null || echo 000)
  case "$code" in
    200|204|301|302|307|308)
      log "web sehat (HTTP $code) setelah ${i} percobaan"
      exit 0
      ;;
  esac
  sleep 2
done

log "FATAL: web tidak sehat setelah $((TRIES * 2)) detik (HTTP terakhir: $code)"
exit 1
