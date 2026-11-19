#!/usr/bin/env bash
# Setup sekali jalan di VPS. Jalankan dengan sudo.
#   sudo ./deploy/setup-vps.sh [domain-web] [domain-api]
set -euo pipefail

DOMAIN_WEB="${1:-sistlabum.local}"
DOMAIN_API="${2:-api.sistlabum.local}"
APP="sistlabum"
ROOT="/var/www/$APP"
DEPLOY_USER="deploy"
PHP_SOCK="/run/php/php8.4-fpm.sock"
REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"

say() { printf '\n==> %s\n' "$*"; }

# ---------------------------------------------------------------- user
say "user deploy"
if ! id "$DEPLOY_USER" &>/dev/null; then
  adduser --system --group --home "/home/$DEPLOY_USER" --shell /bin/bash "$DEPLOY_USER"
fi
usermod -aG www-data "$DEPLOY_USER"

# ------------------------------------------------------------- skeleton
say "skeleton $ROOT"
install -d -o "$DEPLOY_USER" -g www-data -m 2775 "$ROOT"/{releases,shared,logs}
install -d -o "$DEPLOY_USER" -g www-data -m 2775 "$ROOT/shared/api-storage"/{app/public,framework/cache,framework/sessions,framework/views,logs}

if [[ ! -f "$ROOT/shared/api.env" ]]; then
  install -o "$DEPLOY_USER" -g www-data -m 640 "$REPO_DIR/apps/api/.env.example" "$ROOT/shared/api.env"
  echo "    dibuat dari apps/api/.env.example — WAJIB diedit (DB, APP_KEY, mail, dsb)"
fi
if [[ ! -f "$ROOT/shared/web.env" ]]; then
  install -o "$DEPLOY_USER" -g www-data -m 640 "$REPO_DIR/apps/web/.env.example" "$ROOT/shared/web.env"
  echo "    dibuat dari apps/web/.env.example — sesuaikan NEXT_PUBLIC_API_BASE_URL"
fi

# ------------------------------------------------------------ deploy kit
say "deploy kit di /home/$DEPLOY_USER/deploy"
SRC="$REPO_DIR/deploy/kit"
install -d -o "$DEPLOY_USER" -g "$DEPLOY_USER" -m 755 "/home/$DEPLOY_USER/deploy"
if [[ -x "$SRC/bin/release.sh" ]]; then
  cp -r "$SRC/bin" "$SRC/lib" "/home/$DEPLOY_USER/deploy/"
  chown -R "$DEPLOY_USER:$DEPLOY_USER" "/home/$DEPLOY_USER/deploy"
  chmod +x "/home/$DEPLOY_USER/deploy/bin/"*.sh
  echo "    kit disalin dari $SRC"
else
  echo "    PERINGATAN: deploy/kit tidak ditemukan di repo"
fi

# ------------------------------------------------------ systemd user unit
say "systemd user unit (tanpa sudo untuk restart)"
install -d -o "$DEPLOY_USER" -g "$DEPLOY_USER" -m 755 "/home/$DEPLOY_USER/.config/systemd/user"
install -o "$DEPLOY_USER" -g "$DEPLOY_USER" -m 644 \
  "$REPO_DIR/deploy/systemd/sistlabum-web.service" \
  "/home/$DEPLOY_USER/.config/systemd/user/sistlabum-web.service"
loginctl enable-linger "$DEPLOY_USER"
echo "    unit terpasang; start pertama akan dilakukan saat deploy pertama"

# ----------------------------------------------------------- apache vhost
say "vhost apache"
sed -e "s|sistlabum\.local|$DOMAIN_WEB|g" "$REPO_DIR/deploy/apache/sistlabum-web.conf" \
  > "/etc/apache2/sites-available/$APP-web.conf"
sed -e "s|api\.sistlabum\.local|$DOMAIN_API|g" "$REPO_DIR/deploy/apache/sistlabum-api.conf" \
  > "/etc/apache2/sites-available/$APP-api.conf"
a2enmod proxy proxy_http proxy_fcgi rewrite headers >/dev/null
a2ensite "$APP-web" "$APP-api" >/dev/null
apache2ctl configtest
systemctl reload apache2

# ------------------------------------------------------------------ done
say "selesai"
cat <<EOF

Langkah manual yang tersisa:

1. Edit konfigurasi:
     sudo -u $DEPLOY_USER nano $ROOT/shared/api.env
     sudo -u $DEPLOY_USER nano $ROOT/shared/web.env

2. Pasang kunci deploy:
     sudo install -d -o $DEPLOY_USER -g $DEPLOY_USER -m 700 /home/$DEPLOY_USER/.ssh
     sudo install -o $DEPLOY_USER -g $DEPLOY_USER -m 600 deploy_key.pub \\
          /home/$DEPLOY_USER/.ssh/authorized_keys

3. Secrets GitHub (Settings -> Secrets -> Actions):
     DEPLOY_HOST, DEPLOY_USER=$DEPLOY_USER, DEPLOY_SSH_KEY

4. Host lokal (opsional, untuk tes tanpa DNS):
     echo "127.0.0.1 $DOMAIN_WEB $DOMAIN_API" | sudo tee -a /etc/hosts

5. Deploy pertama: push ke branch develop, atau jalankan workflow manual.
EOF
