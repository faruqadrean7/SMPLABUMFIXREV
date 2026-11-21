# LAPORAN VERIFIKASI PASCA-PROVISIONING & GERBANG UAT STAGING (STG-READY-03)

- **Nama Proyek:** Portal Sekolah Satu Pintu (SISTLABUM)
- **Instansi:** SMP Laboratorium Universitas Negeri Malang
- **Repository:** `/Users/macbookprom12020/Desktop/Project/SISTLABUM`
- **Branch Target:** `develop` | **HEAD Baseline:** `448a1a499d3c3a87aa61c3d28428c616dd8f0405`
- **Waktu Verifikasi:** 17 Oktober 2026, 10:28 WIB
- **Klasifikasi Dokumen:** Laporan Audit Verifikasi Pasca-Provisioning (*Post-Provisioning Verification & UAT Gate*)
- **Dokumen Acuan:**
  - `AGENTS.md`
  - `08_DATABASE_SCHEMA.md`
  - `09_SECURITY_PRIVACY.md`
  - `10_TESTING_QA.md`
  - `13_MASTER_MODULES.md`
  - `docs/uat/STAGING_ENVIRONMENT_READINESS_REPORT.md`
  - `docs/uat/STAGING_PREFLIGHT_RESULTS.md`
  - `docs/uat/PORTAL_STAGING_UAT_TEST_PLAN.md`
  - `docs/uat/PORTAL_BUGS_AND_RISKS_REGISTER.md`

---

## 1. Ringkasan Eksekutif

Laporan audit **STG-READY-03** ini disusun untuk mengevaluasi status aktual kesiapan lingkungan staging setelah tahap serah terima provisioning oleh tim DevOps. Audit ini menentukan secara independen apakah seluruh prasyarat wajib untuk pelaksanaan User Acceptance Testing (UAT) resmi bersama tim sekolah telah terpenuhi berdasarkan **bukti nyata eksekusi (*ground truth*)**, bukan asumsi konfigurasi lokal atau klaim yang belum diverifikasi.

### Temuan Utama Status Lapangan:
1. **Status DNS & Domain Staging:** Masih **`BLOCKED`**. Pengecekan resolver DNS terhadap hostname `staging.smp-labum.sch.id` dan `api-staging.smp-labum.sch.id` secara konsisten mengembalikan respons `3(NXDOMAIN)`. Domain staging belum dipetakan ke alamat IP publik server VPS oleh pengelola domain sekolah.
2. **Status HTTPS & SSL/TLS:** Masih **`BLOCKED`**. Penerbitan sertifikat SSL (Let's Encrypt / Certbot) belum dapat dilakukan karena prasyarat propagasi rekaman DNS A belum aktif. Sesi autentikasi aman peramban modern berbasis cookie secure Sanctum belum dapat berfungsi tanpa HTTPS.
3. **Status Akses Host Server Staging:** Masih **`NOT VERIFIED`**. Repositori dan lingkungan audit saat ini tidak memiliki akses SSH operasional ke host VM staging, sehingga kondisi file `.env` staging, status kontainer, dan basis data PostgreSQL staging live belum dapat dibuktikan secara langsung.
4. **Status Kode, Seeder, dan Batas Integrasi:** Berstatus **`PASS`**. Seluruh komponen logika seeder (`UatStagingSeeder`), isolasi tenant, enkripsi kata sandi via secret, mock stub WhatsApp/FCM, sandbox email Mailpit, dan proteksi OTP PPDB telah tervalidasi 100% melalui 19 pengujian otomatis terpadu.

---

## 2. Matriks Verifikasi Pasca-Provisioning 10 Komponen Wajib

| No | Komponen Wajib | Waktu Pemeriksaan | Hasil Aktual & Bukti Terlacak | Status Verifikasi | Dampak Risiko | Tindakan Tersisa | Penanggung Jawab |
|:--:|---|:---:|---|:---:|---|---|:---:|
| **1** | **DNS Kedua Domain Staging** | 17 Okt 2026, 10:24 WIB | Perintah: `host staging.smp-labum.sch.id && host api-staging.smp-labum.sch.id`.<br>Bukti Output: `Host staging.smp-labum.sch.id not found: 3(NXDOMAIN)`. Host `api-staging` juga mengembalikan `NXDOMAIN`. | **`BLOCKED`** | Penguji tidak dapat membuka URL portal web maupun memanggil API staging dari peramban internet. | Buat 2 rekaman DNS `A Record` di Name Server sekolah mengarah ke IP publik server staging. | Tim IT Sekolah / DevOps |
| **2** | **HTTPS & Validitas Sertifikat SSL** | 17 Okt 2026, 10:24 WIB | Perintah: `curl -I --connect-timeout 5 https://staging.smp-labum.sch.id`.<br>Bukti Output: `Could not resolve host: staging.smp-labum.sch.id` (akibat DNS belum aktif). | **`BLOCKED`** | Tanpa HTTPS, browser modern menolak cookie autentikasi Sanctum (`SESSION_SECURE_COOKIE=true`), memicu error 419 CSRF. | Jalankan `certbot --nginx` pada reverse proxy setelah propagasi DNS selesai. | DevOps / Sysadmin |
| **3** | **Health Check (`GET /api/v1/health`)** | 17 Okt 2026, 10:24 WIB | Panggilan live ke API staging terblokir (NXDOMAIN).<br>Uji verifikasi kode di workstation lokal (`127.0.0.1:8000/api/v1/health`):<br>`{"status":"degraded","services":{"database":"ok","redis":"unreachable"},"version":"1.1.0"}`. | **`NOT VERIFIED / BLOCKED`** | Jika Redis mati di server staging, health check melaporkan degraded dan timer CBT serta antrean asinkron terhenti. | Pastikan container/service Redis 7 berjalan di host staging dan lulus tes `GET /api/v1/health` dengan `status: ok`. | DevOps |
| **4** | **PostgreSQL Database Staging** | 17 Okt 2026, 10:25 WIB | Database engine pada spesifikasi kanonis adalah PostgreSQL 16 (`apps/api/config/database.php:87-100`). Eksekusi `php artisan migrate:status` pada server staging nyata belum dapat diakses secara live. | **`NOT VERIFIED`** | Endpoint API mengembalikan HTTP 500 (*Relation does not exist*) jika migrasi skema belum dieksekusi di database staging. | Buat database `sistlabum_staging` di PostgreSQL 16 staging dan jalankan `php artisan migrate --force`. | DBA / DevOps |
| **5** | **Konfigurasi Environment (`.env` Staging)** | 17 Okt 2026, 10:25 WIB | Repositori menyediakan template kanonis `apps/api/.env.example`. Nilai aktual pada file `/var/www/sistlabum/apps/api/.env` server staging belum dapat dibaca dari lingkungan audit lokal. | **`NOT VERIFIED`** | Jika `APP_DEBUG=true` aktif di staging, stack trace dan kredensial sensitif terekspos ke layar pengguna saat terjadi error. | Pasang `.env` staging dengan `APP_ENV=staging`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `CORS_ALLOWED_ORIGINS`, dan `SANCTUM_STATEFUL_DOMAINS`. | DevOps |
| **6** | **Keamanan Seeder & Tenant UUID** | 17 Okt 2026, 10:21 WIB | Diuji via PHPUnit: `php artisan test tests/Feature/Auth/UatSeederTest.php`.<br>Bukti Output: **19 passed** (217 assertions). Seeder menolak production, mewajibkan opt-in, menolak password default, dan memvalidasi UUID tenant `UAT_SCHOOL_ID`. | **`PASS WITH CONDITIONS`** | Korupsi basis data akibat penimpaan data produksi dicegah oleh fail-closed guard. | Setel `UAT_SEED_ENABLED=true` dan `UAT_STAGING_PASSWORD` di `.env` staging, lalu jalankan `php artisan db:seed --class=UatStagingSeeder`. | QA / DevOps |
| **7** | **Pencadangan Fisik Database & Storage** | 17 Okt 2026, 10:26 WIB | Protokol pencadangan kanonis PostgreSQL `pg_dump -F c` dan tarball `storage/app` telah disahkan di dokumen kesiapan. Eksekusi pencadangan baseline nyata di `/var/backups/sistlabum` belum dapat diverifikasi secara live. | **`NOT VERIFIED`** | Jika terjadi kerusakan data saat uji skenario destruktif, sistem tidak dapat dikembalikan ke titik awal sebelum pengujian. | Eksekusi perintah `pg_dump` dan `tar -czvf` ke direktori berizin `700` sebelum akses login dibagikan. | DevOps / DBA |
| **8** | **Uji Coba Pemulihan (*Restore Drill*)** | 17 Okt 2026, 10:26 WIB | Prosedur pemulihan `pg_restore --clean --if-exists` ke database terpisah telah didokumentasikan di SOP. Bukti log eksekusi restore drill di staging belum diserahkan. | **`NOT VERIFIED`** | Prosedur pemulihan darurat (*disaster recovery*) berisiko macet jika tidak pernah disimulasikan sebelumnya. | Jalankan simulasi restore pada database uji coba kosong (*scratch database*) sebelum sesi UAT resmi dimulai. | DevOps / DBA |
| **9** | **Isolasi Notifikasi (OTP, Email, WA, FCM)** | 17 Okt 2026, 10:22 WIB | Kode `PPDBAccountService.php:110` memastikan OTP tidak dikembalikan di respons JSON staging. `MessagingService.php:237` 100% menggunakan provider mock stub. `mail.php` diarahkan ke log/Mailpit. | **`PASS`** | Tidak ada risiko transmisi pesan ke nomor telepon atau email riil masyarakat luas; tidak ada kebocoran OTP ke respons publik. | Siapkan akses inspeksi sandbox (Mailpit port 8025 / Redis cache) bagi penguji alur registrasi PPDB. | QA / Backend |
| **10** | **Integritas Akun UAT & Kerahasiaan Kredensial** | 17 Okt 2026, 10:21 WIB | 14 persona pengujian siap diinisialisasi secara idempoten oleh seeder dengan kata sandi ter-hash bcrypt dari `UAT_STAGING_PASSWORD`. Tidak ada kredensial atau data pribadi yang dicetak ke log. | **`PASS`** | Keamanan akun pengujian terjamin tanpa risiko kebocoran kata sandi bersama publik. | Distribusikan kredensial melalui lembar kerja rahasia tertutup hanya kepada 16 penguji yang ditunjuk. | Lead QA / Project Owner |

---

## 3. Evaluasi Rinci Kesiapan Komponen

### 3.1 Evaluasi Jaringan, DNS, dan Aksesibilitas Web (Komponen 1 & 2)
Pemeriksaan aktif membuktikan bahwa infrastruktur jaringan publik untuk staging **BELUM AKTIF**:
```
$ host staging.smp-labum.sch.id
Host staging.smp-labum.sch.id not found: 3(NXDOMAIN)

$ host api-staging.smp-labum.sch.id
Host api-staging.smp-labum.sch.id not found: 3(NXDOMAIN)
```
- **Kondisi:** DNS domain belum terdaftar pada Name Server sekolah.
- **Konsekuensi:** Akses via browser (Next.js web portal) dan komunikasi HTTP API terputus secara mutlak. Penerbitan sertifikat SSL melalui protokol ACME Let's Encrypt tidak dapat diverifikasi oleh otoritas sertifikat (*Challenge validation failed*).
- **Status Komponen:** **`BLOCKED (Pemblokir Kritis P0)`**.

### 3.2 Evaluasi Dependensi Runtime & Health Check (Komponen 3 & 4)
- **Logika Endpoint:** Rute `GET /api/v1/health` di `apps/api/routes/api.php` menguji ketersediaan koneksi database via `DB::connection()->getPdo()` dan in-memory cache via `Redis::ping()`. Status agregat `ok` hanya dikeluarkan apabila kedua layanan beroperasi normal.
- **Hasil Pemeriksaan Workstation:**
  Workstation pengujian lokal saat ini menjalankan PostgreSQL 17 lokal dan backend Laravel, menghasilkan respons:
  ```json
  {"status":"degraded","services":{"database":"ok","redis":"unreachable"},"version":"1.1.0"}
  ```
- **Evaluasi Staging:** Mengingat Redis adalah dependensi mutlak untuk sesi ujian CBT dan queue worker, server staging nyata wajib menjalankan kontainer Redis 7 dan membuktikan respons `services.redis: "ok"` sebelum akses pengujian dibuka.
- **Status Komponen:** **`NOT VERIFIED (Membutuhkan Pengecekan Server Live)`**.

### 3.3 Evaluasi Keamanan Seeder & Integritas Tenant (Komponen 6 & 10)
- **Hasil Uji Otomatis:** File `apps/api/tests/Feature/Auth/UatSeederTest.php` dieksekusi dengan hasil **19 tes lulus sempurna (217 assertions)**.
- **Karakteristik Keamanan Terverifikasi:**
  1. *Fail-closed environment safety:* Pemanggilan di lingkungan `production` langsung melempar `\RuntimeException`.
  2. *Opt-in requirement:* Pemanggilan di lingkungan staging tanpa `UAT_SEED_ENABLED=true` langsung ditolak.
  3. *Credential protection:* Pemanggilan di staging tanpa rahasia `UAT_STAGING_PASSWORD` atau menggunakan sandi default publik `SistLabUM2026!UAT` langsung digagalkan.
  4. *Tenant integrity:* Memvalidasi UUID tenant sekolah dan menolak konflik identitas dengan tabel `site_settings`.
  5. *Idempotency:* Eksekusi berulang tidak menduplikasi pengguna atau mereset kata sandi akun yang sudah berjalan.
  6. *Privacy compliance:* Menggunakan 100% data sintetis fiktif tanpa PII riil siswa.
- **Status Komponen:** **`PASS WITH CONDITIONS (Tervalidasi & Siap Eksekusi di Staging)`**.

### 3.4 Evaluasi Pencadangan dan Pemulihan (Komponen 7 & 8)
- **Koreksi Arsitektur:** Dokumentasi operasional telah dibersihkan dari referensi usang `mysqldump` dan diselaraskan penuh dengan utilitas kanonis **PostgreSQL 16** (`pg_dump` dan `pg_restore`).
- **Status Lapangan:** Bukti fisik file dump `.dump` dan arsip `.tar.gz` di `/var/backups/sistlabum/` pada server staging nyata belum diserahkan oleh tim DevOps, serta uji pemulihan (*restore drill*) belum dilaporkan.
- **Status Komponen:** **`NOT VERIFIED (Prosedur Siap, Eksekusi Live Tertunda)`**.

### 3.5 Evaluasi Batas Perilaku Integrasi Eksternal (Komponen 9)
- **WhatsApp & FCM:** Tidak ada sambungan ke gateway pihak ketiga berbayar. Seluruh transmisi disimulasikan melalui provider mock stub internal di `apps/api/app/Services/Ntf/MessagingService.php`.
- **Email:** Menggunakan driver `log` atau Mailpit lokal pada port SMTP 1025.
- **OTP PPDB:** Kode 6-digit disimpan di cache Redis selama 15 menit dan tidak diekspos di HTTP respons publik pada mode staging.
- **Status Komponen:** **`PASS (Aman & Terisolasi Penuh)`**.

---

## 4. Lima Keputusan Gerbang Kesiapan UAT Staging

Sesuai pedoman tata kelola kualitas `AGENTS.md` (Bagian 2 Aturan Emas No. 9: *Jujur soal status. Jangan klaim selesai tanpa bukti.*) dan Bagian 10 (*Definition of Done*), ditetapkan **5 Keputusan Gerbang Terpisah**:

```
================================================================================
                    LIMA KEPUTUSAN GERBANG KESIAPAN UAT STAGING
================================================================================
1. Infrastructure Readiness           : BLOCKED (DNS NXDOMAIN & SSL Belum Aktif)
2. Database, Accounts & Test Data     : APPROVED WITH CONDITIONS (Seeder Siap)
3. Integration Readiness              : APPROVED (Mock & Sandbox Tervalidasi)
4. Backup & Restore Readiness         : NOT VERIFIED (Eksekusi Live Belum Dilaporkan)
--------------------------------------------------------------------------------
5. FINAL AUTHORIZATION TO START UAT   : BLOCKED (UAT BELUM BOLEH DIMULAI)
================================================================================
```

### Rincian Pertimbangan Keputusan:

1. **Keputusan 1 — Infrastructure Readiness:** **`BLOCKED`**
   - *Pertimbangan:* Domain `staging.smp-labum.sch.id` dan `api-staging.smp-labum.sch.id` belum dapat di-resolve (NXDOMAIN), sertifikat SSL belum diterbitkan, dan konektivitas live ke instance PostgreSQL 16 serta Redis 7 di server hosting staging belum dapat dibuktikan.

2. **Keputusan 2 — Database, Accounts, and Test Data Readiness:** **`APPROVED WITH CONDITIONS`**
   - *Pertimbangan:* Logika kode `UatStagingSeeder.php` telah teruji 100% lulus pada 19 skenario keamanan pra-penerbangan, memiliki proteksi fail-closed, dan idempoten.
   - *Syarat Eksekusi:* Eksekusi migrasi skema dan seeding data di server staging nyata wajib dilakukan setelah database PostgreSQL 16 diprovisioning dan variabel `UAT_STAGING_PASSWORD` diisi.

3. **Keputusan 3 — Integration Readiness:** **`APPROVED`**
   - *Pertimbangan:* Isolasi integrasi eksternal berstatus aman. Tidak ada risiko transmisi pesan ke nomor telepon atau email riil masyarakat, kode OTP PPDB terlindungi dari kebocoran publik, dan lonceng notifikasi internal beroperasi via database.

4. **Keputusan 4 — Backup and Restore Readiness:** **`NOT VERIFIED`**
   - *Pertimbangan:* Prosedur pencadangan kanonis berbasis `pg_dump -F c` dan pemulihan `pg_restore` telah disahkan secara prosedural. Namun, bukti fisik pembuatan dump baseline pre-UAT dan laporan uji coba pemulihan terkendali di server staging nyata belum tersedia.

5. **Keputusan 5 — Final Authorization to Start UAT:** **`BLOCKED (UAT BELUM BOLEH DIMULAI)`**
   - *Pertimbangan Wajib:* Sesuai instruksi baku: **Keputusan akhir hanya boleh dinyatakan APPROVED jika seluruh gerbang wajib telah memiliki bukti aktual yang memadai. Jangan menyatakan UAT siap jika ada syarat wajib yang masih FAIL, NOT VERIFIED, atau BLOCKED**. UAT ditangguhkan sampai seluruh tindakan blocker di bawah ini terselesaikan.

---

## 5. Ringkasan Blocker yang Masih Ada (Outstanding Blockers)

| ID Blocker | Tingkat Keparahan | Komponen | Deskripsi Hambatan |
|:---:|:---:|---|---|
| **BLK-01** | **P0 (Kritis)** | DNS Server Sekolah | Domain `staging.smp-labum.sch.id` dan `api-staging.smp-labum.sch.id` masih berstatus `NXDOMAIN`. |
| **BLK-02** | **P0 (Kritis)** | SSL / HTTPS | Sertifikat TLS belum diterbitkan; peramban memblokir autentikasi cookie secure Sanctum. |
| **BLK-03** | **P0 (Kritis)** | Host Staging Environment | File `.env` staging, service PostgreSQL 16, dan Redis 7 belum terverifikasi aktif secara live via health check. |
| **BLK-04** | **P1 (Penting)** | Baseline Backup & Restore Drill | Belum ada bukti fisik file dump cadangan awal dan log simulasi pemulihan pada database terpisah di host staging. |

---

## 6. Daftar Tindakan Manusia yang Dibutuhkan (Action Items for DevOps & School IT)

Untuk mengalihkan status gerbang menjadi **`APPROVED`**, langkah-langkah manual berikut wajib dieksekusi oleh pihak penanggung jawab manusia:

1. **Pendaftaran DNS Staging (IT Sekolah / Sysadmin DNS):**
   - Tambahkan 2 rekaman `A Record` pada server DNS sekolah:
     - `staging.smp-labum.sch.id` -> `[IP Publik VPS Staging]`
     - `api-staging.smp-labum.sch.id` -> `[IP Publik VPS Staging]`
   - *Kriteria Selesai:* Perintah `host staging.smp-labum.sch.id` mengembalikan IP yang sesuai.

2. **Penerbitan Sertifikat TLS/HTTPS (DevOps):**
   - Setelah DNS aktif, jalankan Certbot pada reverse proxy NGINX:
     ```bash
     sudo certbot --nginx -d staging.smp-labum.sch.id -d api-staging.smp-labum.sch.id
     ```
   - *Kriteria Selesai:* Browser membuka `https://staging.smp-labum.sch.id` dengan gembok hijau aman (*Valid SSL Certificate*).

3. **Penyiapan Konfigurasi Server Staging (DevOps):**
   - Buat file `.env` di `/var/www/sistlabum/apps/api/.env`:
     - `APP_ENV=staging`, `APP_DEBUG=false`, `APP_KEY=[kunci unik staging]`.
     - `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_DATABASE=sistlabum_staging`.
     - `REDIS_CLIENT=phpredis`, `REDIS_HOST=127.0.0.1`, `REDIS_PORT=6379`.
     - `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`.
     - `CORS_ALLOWED_ORIGINS=https://staging.smp-labum.sch.id`.
     - `SANCTUM_STATEFUL_DOMAINS=staging.smp-labum.sch.id`.
     - `UAT_SEED_ENABLED=true`, `UAT_STAGING_PASSWORD=[secret kuat unik]`.
     - `UAT_SCHOOL_ID=00000000-0000-0000-0000-000000000001`.
     - `MAIL_MAILER=log` (atau mailpit).

4. **Eksekusi Migrasi & Seeding Terkontrol (DevOps / QA):**
   - Jalankan perintah berikut pada host staging:
     ```bash
     cd /var/www/sistlabum/apps/api
     php artisan migrate --force
     php artisan db:seed --class=UatStagingSeeder --force
     ```
   - *Kriteria Selesai:* 14 akun persona terdaftar dan siap diautentikasi.

5. **Pencadangan Baseline Pre-UAT & Uji Pemulihan (DevOps / DBA):**
   - Eksekusi dump baseline sebelum pengujian dibuka:
     ```bash
     mkdir -p /var/backups/sistlabum && chmod 700 /var/backups/sistlabum
     pg_dump -h 127.0.0.1 -p 5432 -U postgres -d sistlabum_staging -F c -b -v -f /var/backups/sistlabum/db_staging_baseline_pre_uat.dump
     tar -czvf /var/backups/sistlabum/storage_staging_baseline_pre_uat.tar.gz -C /var/www/sistlabum/apps/api/storage/app .
     chmod 600 /var/backups/sistlabum/*
     ```
   - Jalankan uji restore terkendali pada database pengujian kosong (*scratch test*).

6. **Verifikasi Akhir Liveness & Pembukaan Akses UAT (Lead QA & Tim Sekolah):**
   - Panggil `curl -i https://api-staging.smp-labum.sch.id/api/v1/health` dan pastikan respons mengembalikan HTTP 200 dengan status `ok` untuk database dan redis.
   - Distribusikan lembar kredensial tertutup kepada 16 penguji resmi dan buka sesi pengujian UAT.
