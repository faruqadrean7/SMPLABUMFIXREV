# Operasional SISTLABUM

Panduan harian untuk server ini. Semua service berjalan sebagai **systemd user unit**
milik `j1nkisaragi` (linger aktif), jadi hidup otomatis setelah reboot tanpa perlu login.

## Arsitektur

```
Internet
   │  https://smplab.fadrean.web.id
   ▼
Cloudflare (TLS, WAF, Access)
   │  Cloudflare Tunnel (cloudflared.service, root)
   ├── path  /            → 127.0.0.1:3100  Caddy ──► Next.js 127.0.0.1:3101
   └── path  ^/api        → 127.0.0.1:8000  Caddy ──► PHP-FPM (unix socket) ──► Laravel
                                                          │
                                                     PostgreSQL 127.0.0.1:5433
                                                     Redis 127.0.0.1:6379
```

Tidak ada port yang terbuka ke LAN. Semua bind ke `127.0.0.1`.

## Service

| Unit | Fungsi |
| --- | --- |
| `sistlabum-caddy` | reverse proxy + TLS header, port 3100 & 8000 |
| `sistlabum-fpm` | PHP-FPM pool (24 worker, opcache+JIT) |
| `sistlabum-web` | Next.js `next start` di 3101 |
| `sistlabum-pg` | PostgreSQL user-space, port 5433 |
| `sistlabum-queue` | `queue:work`, restart tiap 1 jam |
| `sistlabum-scheduler.timer` | `schedule:run` tiap menit |
| `sistlabum-backup.timer` | backup harian 02:30 |
| `sistlabum-watchdog.timer` | cek kesehatan tiap 2 menit |

## Perintah harian

```bash
make status      # ringkasan semua service + timer
make health      # cek web, API, database
make logs        # ikuti log semua service
make backup      # backup sekarang (database + storage)
make test        # jalankan test suite backend
make restart     # restart seluruh stack
```

Atau manual:

```bash
systemctl --user restart sistlabum-caddy sistlabum-fpm sistlabum-web
journalctl --user -u sistlabum-web -n 100 --no-pager
tail -f ~/.local/log/caddy-web.log
```

## Deploy versi baru

Lewat GitHub Actions (`.github/workflows/deploy.yml`): push ke `develop` →
test jalan → rilis baru di `/var/www/sistlabum/releases/<id>` → symlink ditukar.
Kalau gagal, `rollback.sh` mengembalikan versi sebelumnya.

Rollback manual:

```bash
~/deploy/bin/rollback.sh sistlabum --list
~/deploy/bin/rollback.sh sistlabum
```

## Backup & restore

Backup harian: `~/.local/share/sistlabum/backups/` — `db-<stamp>.dump` (pg_dump -Fc),
`storage-<stamp>.tar.gz`, dan `MANIFEST` berisi sha256. Retensi 14 hari.

Restore database:

```bash
/usr/lib/postgresql/17/bin/pg_restore -h 127.0.0.1 -p 5433 -U postgres \
  --clean --if-exists -d sistlabum_db db-20261119-143147.dump
```

Restore storage:

```bash
tar -xzf storage-20261119-143147.tar.gz -C /home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api
```

**Uji restore secara berkala** — backup yang belum pernah direstore bukan backup.

## Troubleshooting

| Gejala | Cek pertama |
| --- | --- |
| Situs 502/1033 | `systemctl --user status sistlabum-caddy sistlabum-web` |
| API 500 | `journalctl --user -u sistlabum-fpm -n 50` + `apps/api/storage/logs/laravel.log` |
| `database: unreachable` | `systemctl --user status sistlabum-pg` |
| Login admin 404 | pastikan buka `/admin-masuk`, bukan `/admin` |
| Halaman kosong 200 | Caddy tidak menemukan site block → cek Host header di Caddyfile |
| Queue menumpuk | `systemctl --user status sistlabum-queue`, cek tabel `jobs` |

## Catatan penting

- `php artisan serve` **tidak dipakai lagi** — sudah diganti Caddy + PHP-FPM.
- Setelah mengubah `.env`, jalankan `php artisan config:clear` lalu
  `php artisan config:cache`, dan restart `sistlabum-fpm` + `sistlabum-queue`.
- **Jangan** menjalankan test saat config ter-cache: phpunit tidak bisa menimpa env
  dan test akan menulis ke database produksi. Selalu `php artisan config:clear` dulu.
- Cloudflare Access belum aktif; siapa pun yang tahu URL bisa membuka aplikasi.
