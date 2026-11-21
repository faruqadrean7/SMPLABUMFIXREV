# SISTLABUM — FINAL DEPLOYMENT READINESS & OPERATIONS MANUAL

## 1. Executive Summary
Laporan ini menguraikan kesiapan infrastruktur produksi, topologi sistem, konfigurasi variabel lingkungan, manajemen proses daemon, penjadwalan berkala (*cron jobs*), serta prosedur observabilitas dan rollback untuk peluncuran backend SISTLABUM ke lingkungan *production*.

- **Kesiapan Infrastruktur**: **READY FOR DEPLOYMENT**
- **Sistem Operasi Target**: Ubuntu 24.04 LTS / Debian 12 / Rocky Linux 9
- **Web Server**: Nginx 1.26+ (dengan HTTP/2 & TLS 1.3)
- **Application Engine**: PHP 8.5 FPM (dengan opcache JIT aktif)
- **Database Engine**: PostgreSQL 17.0
- **Cache & Message Broker**: Redis 7.2+
- **Process Manager**: Supervisor 4.2+

---

## 2. Topologi Infrastruktur Produksi

```
[ Internet / Klien Web & Mobile ]
                │
                ▼
      [ Nginx Reverse Proxy ]
     (TLS 1.3, Rate-Limiting)
         /              \
        ▼                ▼
 [ Static Assets / S3 ]   [ PHP 8.5-FPM Pool ]
                               │
               ┌───────────────┼───────────────┐
               ▼               ▼               ▼
      [ PostgreSQL 17 ]  [ Redis 7.2 ]  [ S3 Object Storage ]
       (Port 5432)        (Port 6379)     (Berkas Privat)
                               ▲
                               │
                 [ Supervisor Daemon Workers ]
               (Horizon, Queues, Notifikasi WA)
```

---

## 3. Audit Konfigurasi Variabel Lingkungan (`.env`)
Seluruh variabel wajib pada `apps/api/.env.example` telah diverifikasi untuk menjamin kepatuhan keamanan dan fungsionalitas produksi:

| Variabel Lingkungan | Nilai Produksi Wajib | Rationale Keamanan / Operasional |
|---|---|---|
| `APP_ENV` | `production` | Menonaktifkan mode debug dan fitur pengujian lokal |
| `APP_DEBUG` | `false` | Mencegah kebocoran stack trace dan kredensial |
| `APP_KEY` | `base64:...(32 bytes random)` | Enkripsi cookie, session, dan model attributes |
| `APP_URL` | `https://api.labschool.sch.id` | Menjamin tautan signed URL dan notifikasi menggunakan HTTPS |
| `DB_CONNECTION` | `pgsql` | Menghubungkan ke engine PostgreSQL 17 produksi |
| `DB_HOST` | `127.0.0.1` / Private VPC IP | Mencegah eksposur database ke internet publik |
| `HASH_DRIVER` | `argon2id` | Enforcing standar keamanan password tahan GPU-cracking |
| `QUEUE_CONNECTION`| `redis` | Pemrosesan background jobs yang cepat dan non-blocking |
| `CACHE_STORE` | `redis` | Caching performa tinggi untuk RBAC & CBT sessions |
| `SESSION_DRIVER` | `redis` | Penanganan sesi terpusat antar multi-worker PHP-FPM |
| `SESSION_SECURE_COOKIE`| `true` | Enforcing flag `Secure` pada cookie sesi |
| `SANCTUM_STATEFUL_DOMAINS`| `portal.labschool.sch.id,...` | Mengizinkan SPA autentikasi hanya dari origin resmi |

---

## 4. Konfigurasi Supervisor untuk Queue & Background Worker
Simpan konfigurasi berikut pada `/etc/supervisor/conf.d/sistlabum-worker.conf`:

```ini
[program:sistlabum-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/sistlabum/apps/api/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/sistlabum-worker.log
stopwaitsecs=3600
```

---

## 5. Konfigurasi Crontab untuk Scheduler Laravel
Tambahkan satu baris crontab berikut pada user `www-data` (`crontab -e -u www-data`):

```cron
* * * * * cd /var/www/sistlabum/apps/api && php artisan schedule:run >> /dev/null 2>&1
```

### Tugas Berkala yang Dijalankan Scheduler:
1. `cbt:cleanup-sessions`: Menutup otomatis sesi ujian yang telah melewati batas waktu durasi (setiap 5 menit).
2. `files:clean-temporary`: Menghapus berkas sementara yang diunggah tetapi tidak dikonfirmasi dalam 24 jam (setiap hari jam 02:00).
3. `attendance:generate-summary`: Mengkalkulasi rekapitulasi presensi bulanan siswa dan staf (setiap tanggal 1 jam 01:00).
4. `sanctum:prune-expired`: Menghapus personal access token kadaluarsa (setiap hari jam 03:00).

---

## 6. Prosedur Health Check & Liveness Probe
Sistem menyediakan endpoint health check terintegrasi untuk pemantauan uptime (Nagios, Datadog, UptimeKuma, atau Kubernetes Liveness Probe):

- **Endpoint**: `GET /api/v1/health`
- **Respon Normal (200 OK)**:
  ```json
  {
    "status": "healthy",
    "timestamp": "2026-10-12T02:20:30+00:00",
    "services": {
      "database": "ok",
      "redis": "ok"
    },
    "version": "1.1.0"
  }
  ```
- **Kondisi Degraded / Unhealthy (503 Service Unavailable)**: Jika database PostgreSQL tidak dapat dihubungi, endpoint mengembalikan HTTP 503 dengan status `"unhealthy"`.

---

## 7. Production Deployment Runbook (Safe & Battle-Tested)

### Fase A: Persiapan Kredensial & Secrets Management
1. **Dilarang Keras** menyalin berkas `.env.example` langsung sebagai `.env` produksi.
2. Seluruh secret wajib di-generate secara independen dan disimpan dalam Secret Store aman (HashiCorp Vault, AWS Secrets Manager, atau CI/CD Masked Variables):
   - `APP_KEY`: Generate via `php artisan key:generate --show`.
   - Kredensial DB PostgreSQL: User `sistlabum_app` dengan password acak minimal 32 karakter.
   - S3 Storage Credentials: AWS IAM Role dengan policy minimal (hanya bucket SISTLABUM).
   - WhatsApp Gateway Token & Firebase Service Account JSON.
3. Pastikan `APP_DEBUG=false`, `APP_ENV=production`, `HASH_DRIVER=argon2id`, `HASH_VERIFY=false` (kompatibel akun lama).

### Fase B: Pemeriksaan Pra-Rilis (Pre-Flight Checks)
1. **Verifikasi Storage & Direktori**:
   ```bash
   chmod -R 775 storage bootstrap/cache
   chown -R www-data:www-data storage bootstrap/cache
   ```
2. **Konektivitas Dependensi**:
   - PostgreSQL: `pg_isready -h $DB_HOST -p $DB_PORT -U $DB_USERNAME`
   - Redis: `redis-cli -h $REDIS_HOST -p $REDIS_PORT ping`
3. **Backup Penuh Database Sebelum Rilis (Mandatory)**:
   ```bash
   BACKUP_NAME="pre_deploy_backup_$(date +%Y%m%d_%H%M%S).sql"
   pg_dump -U $DB_USERNAME -h $DB_HOST -d $DB_DATABASE > /var/backups/sistlabum/$BACKUP_NAME
   echo "Pre-deploy backup verified at /var/backups/sistlabum/$BACKUP_NAME"
   ```

### Fase C: Langkah Eksekusi Rilis (Atomic Deployment)
1. **Aktifkan Maintenance Mode Graceful**:
   ```bash
   php artisan down --retry=60 --secret="emergency-bypass-token" --render="errors::503"
   ```
2. **Instalasi Dependensi Produksi**:
   ```bash
   composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   ```
3. **Migrasi Database**:
   ```bash
   php artisan migrate --force
   ```
4. **Optimasi Cache Laravel**:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan event:cache
   ```
5. **Restart Daemon Worker & Scheduler**:
   ```bash
   php artisan queue:restart
   sudo supervisorctl restart sistlabum-worker:*
   ```
6. **Nonaktifkan Maintenance Mode**:
   ```bash
   php artisan up
   ```

### Fase D: Post-Deployment Smoke Test Matrix
Verifikasi 7 titik kritis segera setelah sistem online:
1. **Health Check**: `curl -s -i https://api.labschool.sch.id/api/v1/health` (harus `status: healthy`, HTTP 200).
2. **Autentikasi (Argon2id)**: Test login staf admin via `POST /api/v1/auth/login`.
3. **Otorisasi RBAC**: Pastikan user non-admin ditolak (HTTP 403) saat mengakses `/api/v1/audit-logs`.
4. **Alur PPDB**: Pastikan endpoint publik `/api/v1/public/ppdb/waves/active` merespon data gelombang aktif.
5. **CBT Engine**: Pastikan sesi ujian `/api/v1/exams` dapat diinisialisasi oleh proctor.
6. **Presensi Siswa**: Pastikan endpoint presensi hari ini `/api/v1/attendances/today` dapat dibaca guru.
7. **Isolasi Multi-Sekolah / Akses Antar-Sekolah**: Pastikan data siswa sekolah A tidak bocor ke akun guru sekolah B (tenant isolation).

---

## 8. Prosedur Pembatalan & Rollback Darurat (Emergency Rollback Runbook)

Jika post-deployment smoke test gagal atau terjadi error rate > 1%:

### Langkah 1: Kunci Aplikasi ke Maintenance Mode
```bash
php artisan down --message="Sistem sedang mengalami pemeliharaan darurat. Mohon tunggu beberapa saat."
```

### Langkah 2: Matikan Worker Antrean
```bash
sudo supervisorctl stop sistlabum-worker:*
```

### Langkah 3: Rollback Database (Jika Migrasi Bersifat Non-Destruktif)
```bash
php artisan migrate:rollback --step=1
```
*Atau, jika terjadi korupsi skema / inkonsistensi data*:
```bash
dropdb -U $DB_USERNAME -h $DB_HOST $DB_DATABASE
createdb -U $DB_USERNAME -h $DB_HOST $DB_DATABASE
psql -U $DB_USERNAME -h $DB_HOST -d $DB_DATABASE -f /var/backups/sistlabum/$BACKUP_NAME
```

### Langkah 4: Checkout Commit Versi Stabil Sebelumnya
```bash
git checkout <PREVIOUS_STABLE_TAG_OR_COMMIT>
composer install --no-dev --optimize-autoloader
php artisan config:cache && php artisan route:cache
```

### Langkah 5: Nyalakan Kembali Worker & Aplikasi
```bash
sudo supervisorctl start sistlabum-worker:*
php artisan up
```

### Langkah 6: Post-Mortem & Notifikasi Tim
Catat insiden di register risiko dan evaluasi kegagalan sebelum penjadwalan ulang deployment.
