# Deploy SISTLABUM

Deploy atomik ke VPS: kode baru masuk ke folder `releases/<id>`, diuji, baru dipindah lewat
symlink `current`. Tidak ada downtime, dan rollback cuma satu perintah.

```
/var/www/sistlabum/
├── releases/20261118-134122-e7832b1/     # tiap deploy = folder baru
│   ├── apps/api/                          # Laravel
│   ├── apps/web/                          # Next.js
│   └── deploy/hooks/                      # hook yang ikut versi kode
├── current -> releases/...                # symlink, ditukar atomik
├── shared/
│   ├── api.env                            # .env Laravel, tidak pernah ditimpa
│   ├── web.env                            # env Next.js
│   └── api-storage/                       # storage Laravel (upload, log, cache)
└── logs/                                  # log Apache
```

## Alur satu deploy

1. Push ke `develop` (atau `main`)
2. Job **Test** jalan: `php artisan test` (Postgres service container), lalu
   `npm run typecheck`, `npm run lint`, `npm run build`. Kalau merah, berhenti — server tidak disentuh.
3. Job **Deploy**:
   - buat `releases/<UTC>-<sha7>`
   - `rsync` source ke folder itu (tanpa `node_modules`, `vendor`, `.env`, `storage`)
   - SSH: `~/deploy/bin/release.sh sistlabum <id> --skip-build`
4. `release.sh` menjalankan hook `deploy/hooks/pre-activate.sh` **di dalam release baru**:
   symlink `.env` & `storage`, `composer install`, `migrate --force`, warm cache,
   `npm ci`, `next build`
5. Kalau semua sukses → symlink `current` ditukar (atomik, satu `mv -Tf`)
6. Hook `post-activate.sh`: restart service web, health check port 3002
7. Release lama dipangkas (simpan 5 terbaru + current + previous)
8. Kalau ada langkah yang gagal → symlink **tidak** ditukar, versi lama tetap jalan;
   workflow juga memanggil `rollback.sh` sebagai jaring kedua

## Setup pertama (sekali, butuh sudo)

```bash
sudo ./deploy/setup-vps.sh sistlabum.local api.sistlabum.local
```

Yang dilakukan:
- user `deploy` (grup `www-data`), skeleton `/var/www/sistlabum`
- `shared/api.env` & `shared/web.env` dari `.env.example` → **wajib diedit**
- kit deploy ke `/home/deploy/deploy`
- systemd *user unit* `sistlabum-web` + `loginctl enable-linger` (restart tanpa sudo)
- vhost Apache `sistlabum-web` (proxy ke :3002) dan `sistlabum-api` (PHP-FPM)

Setelah itu:

```bash
sudo -u deploy nano /var/www/sistlabum/shared/api.env
sudo -u deploy nano /var/www/sistlabum/shared/web.env
sudo install -d -o deploy -g deploy -m 700 /home/deploy/.ssh
sudo install -o deploy -g deploy -m 600 deploy_key.pub /home/deploy/.ssh/authorized_keys
```

## Secrets GitHub

Settings → Secrets and variables → Actions:

- `DEPLOY_HOST` — IP atau domain VPS
- `DEPLOY_USER` — `deploy`
- `DEPLOY_SSH_KEY` — private key ed25519 khusus deploy

Variables (opsional): `NEXT_PUBLIC_API_BASE_URL`, `ADMIN_LOGIN_PATH`.

## Operasional

```bash
~/deploy/bin/rollback.sh sistlabum --list   # daftar release, tanda * = aktif
~/deploy/bin/rollback.sh sistlabum          # balik ke release sebelumnya
systemctl --user status sistlabum-web       # status web
journalctl --user -u sistlabum-web -n 50    # log web
tail -50 /var/www/sistlabum/logs/api-error.log
```

## Menyesuaikan

Semua langkah build ada di hook, bukan di workflow — jadi bisa kamu ubah tanpa menyentuh CI:

- `deploy/hooks/pre-activate.sh` — composer, migrate, cache, npm, next build
- `deploy/hooks/post-activate.sh` — restart service, health check

Hook apa pun yang `exit 1` akan membatalkan swap. Jadi kalau butuh langkah tambahan
(misal `php artisan queue:restart`, atau seeding), taruh di situ.

Menambah app lain: salin `deploy/apache/*.conf`, tambah unit systemd, lalu panggil
`release.sh <app> <id>` dengan `DEPLOY_ROOT` berbeda.

## Seeding paket CBT (manual, bukan bagian deploy)

Paket soal tidak lagi didekripsi di CI — passphrase-nya tidak boleh ada di repo.
Jalankan sekali di server, ketik passphrase secara interaktif:

```bash
cd /var/www/sistlabum/current/apps/api
openssl enc -d -aes-256-cbc -pbkdf2 -md sha256 -iter 100000 \
  -in deploy/cbt/mtk-pas-9-genap-2526.json.enc \
  -out storage/app/import/mtk-pas-9-genap-2526.json
php artisan cbt:import-package storage/app/import/mtk-pas-9-genap-2526.json
php artisan cbt:attach-mtk-figures
rm -f storage/app/import/mtk-pas-9-genap-2526.json
```

## Yang belum beres (silakan lanjut)

- [ ] Migrasi DB masih `--force` tiap deploy → jadikan migrasi *backward-compatible*
      (tambah kolom, jangan hapus/rename dalam satu release)
- [ ] Queue worker belum dijalankan sebagai service (`php artisan queue:work`)
- [ ] Belum ada backup database sebelum `migrate`
- [ ] Health check API belum ada (Laravel belum punya route `/health`)
- [ ] Passphrase CBT masih ada di git history — perlu rotasi + `git filter-repo`
- [ ] `19_KREDENSIAL_AKUN_TESTING.md` masih publik — pindahkan atau hapus
