#!/usr/bin/env bash
# Bootstrap sekali jalan (butuh sudo): user deploy, layout release di /var/www,
# kit deploy, kunci SSH, dan salinan konfigurasi dari working copy.
#
#   sudo ./deploy/bootstrap-vps.sh [PUBKEY_PATH]
#
# Aman dijalankan berulang (idempotent).
set -euo pipefail

APP=sistlabum
ROOT=/var/www/$APP
DEPLOY_USER=deploy
REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PUBKEY="${1:-}"

[[ $EUID -eq 0 ]] || { echo "jalankan dengan sudo"; exit 1; }

say() { printf '\n==> %s\n' "$*"; }

say "user $DEPLOY_USER"
if ! id "$DEPLOY_USER" &>/dev/null; then
  adduser --system --group --home "/home/$DEPLOY_USER" --shell /bin/bash "$DEPLOY_USER"
  echo "    dibuat"
else
  echo "    sudah ada"
fi
usermod -aG www-data "$DEPLOY_USER"

say "layout $ROOT"
install -d -o "$DEPLOY_USER" -g www-data -m 2775 "$ROOT"/{releases,shared,logs}
install -d -o "$DEPLOY_USER" -g www-data -m 2775 "$ROOT/shared/api-storage"/{app/public,framework/cache,framework/sessions,framework/views,logs}

say "konfigurasi ke shared/"
if [[ ! -f "$ROOT/shared/api.env" && -f "$REPO_DIR/apps/api/.env" ]]; then
  install -o "$DEPLOY_USER" -g www-data -m 640 "$REPO_DIR/apps/api/.env" "$ROOT/shared/api.env"
  echo "    api.env disalin dari working copy"
fi
if [[ ! -f "$ROOT/shared/web.env" && -f "$REPO_DIR/apps/web/.env.local" ]]; then
  install -o "$DEPLOY_USER" -g www-data -m 640 "$REPO_DIR/apps/web/.env.local" "$ROOT/shared/web.env"
  echo "    web.env disalin dari working copy"
fi

say "storage aplikasi"
storage_empty=1
[[ -n "$(ls -A "$ROOT/shared/api-storage/app" 2>/dev/null)" ]] && storage_empty=0
if [[ -d "$REPO_DIR/apps/api/storage" && $storage_empty -eq 1 ]]; then
  cp -a "$REPO_DIR/apps/api/storage/." "$ROOT/shared/api-storage/"
  chown -R "$DEPLOY_USER:www-data" "$ROOT/shared/api-storage"
  echo "    storage disalin"
else
  echo "    dilewati (sudah ada isi)"
fi

say "kit deploy"
install -d -o "$DEPLOY_USER" -g "$DEPLOY_USER" -m 755 "/home/$DEPLOY_USER/deploy"
cp -r "$REPO_DIR/deploy/kit/bin" "$REPO_DIR/deploy/kit/lib" "/home/$DEPLOY_USER/deploy/"
chown -R "$DEPLOY_USER:$DEPLOY_USER" "/home/$DEPLOY_USER/deploy"
chmod +x "/home/$DEPLOY_USER/deploy/bin/"*.sh

say "kunci SSH deploy"
if [[ -n "$PUBKEY" && -f "$PUBKEY" ]]; then
  install -d -o "$DEPLOY_USER" -g "$DEPLOY_USER" -m 700 "/home/$DEPLOY_USER/.ssh"
  install -o "$DEPLOY_USER" -g "$DEPLOY_USER" -m 600 "$PUBKEY" "/home/$DEPLOY_USER/.ssh/authorized_keys"
  echo "    public key dipasang"
else
  echo "    belum dipasang — jalankan ulang dengan path public key, atau:"
  echo "    sudo install -o $DEPLOY_USER -g $DEPLOY_USER -m 600 <key.pub> /home/$DEPLOY_USER/.ssh/authorized_keys"
fi

say "izin grup untuk user kamu"
TARGET_USER="${SUDO_USER:-$USER}"
if ! id -nG "$TARGET_USER" | tr ' ' '\n' | grep -qx www-data; then
  usermod -aG www-data "$TARGET_USER"
  echo "    $TARGET_USER ditambahkan ke grup www-data (perlu re-login)"
else
  echo "    $TARGET_USER sudah di grup www-data"
fi

cat <<EOF

Selesai. Langkah berikutnya:

1. Isi secrets GitHub (Settings -> Secrets -> Actions):
     DEPLOY_HOST = <ip/host vps>
     DEPLOY_USER = $DEPLOY_USER
     DEPLOY_SSH_KEY = isi file private key deploy

2. Push branch, lalu jalankan workflow "Deploy".

3. Setelah deploy pertama sukses ($ROOT/current ada), jalankan:
     ./deploy/switch-runtime.sh

EOF
