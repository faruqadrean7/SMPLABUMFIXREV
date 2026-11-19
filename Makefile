# Perintah operasional SISTLABUM
SHELL := /bin/bash
API := /home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api
WEB := /home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/web
UNITS := sistlabum-caddy sistlabum-fpm sistlabum-web sistlabum-pg sistlabum-queue

.PHONY: help status health logs backup test restart deploy-check cache clear

help:
	@echo "make status       ringkasan service + timer"
	@echo "make health       cek web, API, database"
	@echo "make logs         ikuti log semua service"
	@echo "make backup       backup sekarang"
	@echo "make test         test suite backend (DB terpisah)"
	@echo "make restart      restart seluruh stack"
	@echo "make cache        config:clear + config:cache"
	@echo "make deploy-check cek release aktif + symlink"

status:
	@systemctl --user list-units 'sistlabum*' --no-legend --no-pager | awk '{printf "%-34s %-9s %s\n", $$1, $$3, $$4}'
	@echo
	@systemctl --user list-timers 'sistlabum*' --no-pager | head -8

health:
	@printf 'web  : %s\n' "$$(curl -s -o /dev/null -w '%{http_code}' -m 10 http://127.0.0.1:3100/)"
	@printf 'api  : %s\n' "$$(curl -s -o /dev/null -w '%{http_code}' -m 10 http://127.0.0.1:8000/api/v1/health)"
	@curl -s -m 10 http://127.0.0.1:8000/api/v1/health | head -c 160; echo
	@/usr/lib/postgresql/17/bin/pg_isready -h 127.0.0.1 -p 5433

logs:
	journalctl --user -f -u sistlabum-caddy -u sistlabum-fpm -u sistlabum-web -u sistlabum-queue

backup:
	@/home/j1nkisaragi/.local/bin/sistlabum-backup.sh

restart:
	systemctl --user restart $(UNITS)
	@sleep 5
	@$(MAKE) --no-print-directory health

cache:
	cd $(API) && php artisan config:clear && php artisan config:cache && php artisan route:cache

test:
	cd $(API) && php artisan config:clear
	cd $(API) && DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5433 \
	  DB_DATABASE=sistlabum_testing DB_USERNAME=postgres DB_PASSWORD= \
	  vendor/bin/phpunit --colors=never

deploy-check:
	@readlink -f /var/www/sistlabum/current 2>/dev/null || echo "(belum ada release di /var/www)"
	@ls -1 /var/www/sistlabum/releases 2>/dev/null | tail -3
