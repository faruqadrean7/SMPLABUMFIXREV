#!/usr/bin/env bash
# Dijalankan SETELAH symlink `current` menunjuk release baru.
# Kalau health check gagal, keluar dengan kode 1 supaya workflow rollback.
set -euo pipefail

PORT="${WEB_PORT:-3100}"
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
# 1b. Reload PHP-FPM — WAJIB.
# Tanpa ini opcache FPM tetap memakai bytecode lama: route/controller baru
# tidak terdaftar (404) sampai FPM di-reload manual, sehingga deploy PHP
# tampak sukses tetapi tidak berefek.
# ------------------------------------------------------------------
if systemctl --user list-unit-files 2>/dev/null | grep -q '^sistlabum-fpm\.service'; then
  log "reload sistlabum-fpm (bersihkan opcache)"
  systemctl --user reload-or-restart sistlabum-fpm.service
else
  log "PERINGATAN: unit sistlabum-fpm tidak ditemukan; opcache tidak dibersihkan"
fi

# ------------------------------------------------------------------
# 2. Health check
# ------------------------------------------------------------------
for i in $(seq 1 "$TRIES"); do
  code=$(curl -s -o /dev/null -w '%{http_code}' -m 3 "http://127.0.0.1:$PORT/" 2>/dev/null || echo 000)
  api_code=$(curl -s -o /dev/null -w '%{http_code}' -m 3 "http://127.0.0.1:8000/api/v1/health" 2>/dev/null || echo 000)
  case "$code" in
    200|204|301|302|307|308)
      if [ "$api_code" = "200" ]; then
        log "web sehat (HTTP $code) & API sehat (HTTP $api_code) setelah ${i} percobaan"
        exit 0
      fi
      log "web OK tetapi API belum sehat (HTTP $api_code) — percobaan ${i}"
      ;;
  esac
  sleep 2
done

log "FATAL: web/API tidak sehat setelah $((TRIES * 2)) detik (web: $code, api: $api_code)"
exit 1
