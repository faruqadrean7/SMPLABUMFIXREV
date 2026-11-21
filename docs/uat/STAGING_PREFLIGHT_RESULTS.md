# HASIL VERIFIKASI PREFLIGHT LINGKUNGAN STAGING (STAGING PREFLIGHT RESULTS) SISTLABUM

- **Nama Proyek:** Portal Sekolah Satu Pintu (SISTLABUM)
- **Instansi:** SMP Laboratorium Universitas Negeri Malang
- **Repository:** `/Users/macbookprom12020/Desktop/Project/SISTLABUM`
- **Branch Target:** `develop` | **HEAD Terverifikasi:** `448a1a499d3c3a87aa61c3d28428c616dd8f0405`
- **Tanggal Verifikasi:** 17 Oktober 2026, 10:25 WIB
- **Klasifikasi Dokumen:** Laporan Audit Pra-Penerbangan (*Preflight Verification Report*)
- **Dokumen Acuan:**
  - `AGENTS.md`
  - `08_DATABASE_SCHEMA.md`
  - `09_SECURITY_PRIVACY.md`
  - `10_TESTING_QA.md`
  - `13_MASTER_MODULES.md`
  - `docs/uat/STAGING_ENVIRONMENT_READINESS_REPORT.md`
  - `docs/uat/STAGING_UAT_EXECUTION_CHECKLIST.md`
  - `docs/uat/PORTAL_STAGING_UAT_TEST_PLAN.md`
  - `docs/uat/PORTAL_BUGS_AND_RISKS_REGISTER.md`

---

## Ringkasan Eksekutif

Audit pra-penerbangan (*final preflight verification*) ini dilakukan untuk memverifikasi secara independen bukti aktual dari seluruh prasyarat teknis sebelum User Acceptance Testing (UAT) Staging resmi dibuka untuk pengguna sekolah (dewan guru, siswa, wali murid, panitia PPDB, staf TU, dan pimpinan sekolah).

Audit membedakan secara tegas antara:
1. **Kesiapan Repositori & Spesifikasi Kode Aplikasi (*Codebase Readiness*)**: Kode sumber, migrasi PostgreSQL, proteksi seeder UAT, mekanisme sandbox, dan uji otomatis (1.163 tests backend dan 775 tests frontend) telah 100% tervalidasi dan lulus.
2. **Kesiapan Infrastruktur Server Staging Nyata (*Live Infrastructure Readiness*)**: Server fisik/VM staging, alokasi DNS, sertifikat SSL/HTTPS, dan basis data staging aktif belum dapat diverifikasi secara langsung dari repositori karena ketiadaan akses SSH operasional dan domain staging masih berstatus `NXDOMAIN`.

---

## A. Matriks Verifikasi Komprehensif Preflight UAT

| No | Kategori & Pemeriksaan | Hasil Aktual | Status | Bukti & Log Relevan | Risiko Jika Belum Terpenuhi | Penanggung Jawab | Tindakan Berikutnya | Kriteria Penerimaan |
|:--:|---|---|:---:|---|---|:---:|---|---|
| **1** | **Infrastruktur: APP_ENV & APP_DEBUG** | Spesifikasi repositori memuat `APP_ENV=staging`, `APP_DEBUG=false` pada `.env.example`. Nilai aktual pada file `.env` server staging belum dapat dibaca langsung karena ketiadaan akses shell/SSH ke host staging. | **`NOT VERIFIED`** | `apps/api/.env.example:5-6`; Host staging belum terhubung via SSH. | Jika `APP_DEBUG=true` di staging, error sistem akan membocorkan kredensial database dan jejak eksekusi (*stack trace*) ke browser penguji. | DevOps / Sysadmin | Buat file `.env` staging dengan `APP_ENV=staging` dan `APP_DEBUG=false`. | `php artisan env` di server staging menghasilkan `[staging]` dan `config('app.debug') === false`. |
| **2** | **Infrastruktur: PostgreSQL 16 & Migrasi** | 46 migrasi Laravel menggunakan skema kompatibel PostgreSQL 16 (`$table->uuid('id')`, JSONB, timestamptz). Eksekusi `php artisan migrate:status` pada server staging live belum dapat diverifikasi. | **`NOT VERIFIED`** | `apps/api/config/database.php:87-100`; `docker-compose.yml:5-23` (`postgres:16-alpine`). | Jika database staging belum diprovisioning atau skema belum dimigrasikan, seluruh endpoint API akan gagal dengan HTTP 500 (*Table does not exist*). | Database Administrator / DevOps | Siapkan instance PostgreSQL 16 staging, jalankan `php artisan migrate --force`. | Seluruh 46 file migrasi berstatus `Ran` pada server staging. |
| **3** | **Infrastruktur: Redis 7 & Cache/Session** | Konfigurasi `config/database.php` dan `routes/api.php` memverifikasi `Redis::ping()`. Layanan Redis live di server staging belum dapat diverifikasi secara langsung. | **`NOT VERIFIED`** | `apps/api/routes/api.php:122-128`; `docker-compose.yml:32-43` (`redis:7-alpine`). | Jika Redis mati, endpoint `/api/v1/health` berstatus `degraded`, timer CBT dan sinkronisasi autosave ujian siswa gagal berfungsi. | DevOps / Sysadmin | Jalankan container/service Redis 7, pastikan firewall internal membuka port 6379 untuk API. | `redis-cli ping` mengembalikan `PONG` dan respons health check `services.redis: "ok"`. |
| **4** | **Infrastruktur: Storage MinIO/S3 Staging** | `FileStorageService.php` dan `config/filesystems.php` siap menangani presigned URL driver S3/MinIO. Akses live bucket staging `sistlabum-files` belum dapat diverifikasi. | **`NOT VERIFIED`** | `apps/api/config/filesystems.php:50-65`; `docker-compose.yml:48-64`. | Pengunggahan pasfoto profil dan dokumen KK/Akta PPDB akan gagal jika bucket belum dibuat atau kredensial MinIO salah. | DevOps / Sysadmin | Buat bucket `sistlabum-files` di MinIO/S3 staging dan lakukan uji upload presigned URL. | HTTP PUT berkas ke presigned URL menghasilkan HTTP 200 dan berkas dapat diunduh kembali. |
| **5** | **Infrastruktur: DNS & Domain Staging** | Pemeriksaan resolver DNS terhadap host `staging.smp-labum.sch.id` dan `api-staging.smp-labum.sch.id` mengembalikan `3(NXDOMAIN)`. | **`BLOCKED`** | Output resolver lokal: `Host staging.smp-labum.sch.id not found: 3(NXDOMAIN)`. | Penguji tidak dapat mengakses portal web Next.js maupun API melalui peramban internet. | Pemilik Sistem / IT Sekolah | Tambahkan DNS A record `staging.smp-labum.sch.id` dan `api-staging.smp-labum.sch.id` mengarah ke IP publik staging. | Domain staging dapat di-resolve ke IP publik yang sah oleh DNS publik (Google DNS/Cloudflare DNS). |
| **6** | **Infrastruktur: HTTPS & Sertifikat SSL** | Penerbitan SSL belum dapat dilakukan karena dependensi DNS (rekaman A) belum aktif. | **`BLOCKED`** | Dependen pada item No 5 (NXDOMAIN). | Browser modern menolak cookie secure Sanctum (`SESSION_SECURE_COOKIE=true`), menyebabkan sesi login pengguna langsung terputus (*CSRF token mismatch*). | DevOps / Sysadmin | Jalankan `certbot --nginx` setelah DNS aktif untuk menerbitkan sertifikat Let's Encrypt. | URL `https://staging.smp-labum.sch.id` dapat diakses dengan sertifikat TLS valid tanpa peringatan keamanan browser. |
| **7** | **Infrastruktur: Integrasi CORS & Sanctum** | `config/cors.php` dan `config/sanctum.php` mendukung konfigurasi `CORS_ALLOWED_ORIGINS` dan `SANCTUM_STATEFUL_DOMAINS` dengan `supports_credentials=true`. | **`PASS`** (Spesifikasi Kode) | `apps/api/config/cors.php:22-37`; `apps/api/config/sanctum.php:21-26`. | Jika origin staging tidak didaftarkan pada `.env` staging, panggilan API dari web Next.js diblokir oleh kebijakan CORS browser. | DevOps | Masukkan origin web staging ke `CORS_ALLOWED_ORIGINS` dan `SANCTUM_STATEFUL_DOMAINS` di `.env`. | Preflight HTTP OPTIONS pada endpoint API mengembalikan header `Access-Control-Allow-Origin` yang sesuai. |
| **8** | **Seeder: Guard Lingkungan Fail-Closed** | `UatStagingSeeder::checkEnvironmentSafety()` secara mutlak memblokir environment `production` dan mewajibkan opt-in eksplisit `UAT_SEED_ENABLED=true` di `staging`. | **`PASS`** | `apps/api/database/seeders/UatStagingSeeder.php:44-56`; Teruji di `UatSeederTest.php` (19 tests passed). | Data operasional riil sekolah dapat tertimpa atau rusak jika seeder tereksekusi di server produksi. | QA / Backend | Pertahankan proteksi guard; jangan hapus atau bypass logika keamanan lingkungan ini. | Eksekusi di environment production melempar `RuntimeException` mutlak. |
| **9** | **Seeder: Validasi Tenant & Anti-Konflik** | `UatStagingSeeder::resolveTenantSchoolId()` memverifikasi format UUID `school_id` dan mencocokkan dengan `SiteSetting` database. | **`PASS`** | `apps/api/database/seeders/UatStagingSeeder.php:63-98`; Teruji di `UatSeederTest.php`. | Data uji UAT bercampur dengan tenant lain atau menghasilkan integritas referensi yang rusak. | QA / Backend | Sediakan `UAT_SCHOOL_ID` yang valid pada `.env` staging sebelum seeding. | Tenant ID tervalidasi sebagai UUID yang konsisten dengan data sekolah. |
| **10** | **Seeder: Kredensial Uji dari Secret** | `UatStagingSeeder::resolveUatPassword()` mewajibkan secret `UAT_STAGING_PASSWORD` di staging dan melarang penggunaan password default bersama (`SistLabUM2026!UAT`). | **`PASS`** | `apps/api/database/seeders/UatStagingSeeder.php:112-140`; Teruji di `UatSeederTest.php`. | Akun pengujian dibobol pihak luar jika menggunakan kata sandi standar publik. | Lead QA / DevOps | Tentukan kata sandi kuat unik untuk `UAT_STAGING_PASSWORD` dan bagikan hanya kepada 16 penguji resmi. | Seeder gagal jika `UAT_STAGING_PASSWORD` kosong atau memakai kata sandi default publik. |
| **11** | **Seeder: Idempotensi & Proteksi Akun** | Seeder menggunakan transaksi `DB::transaction`, `firstOrCreate`, dan tidak mereset kata sandi akun existing kecuali flag `UAT_FORCE_RESET_PASSWORDS=true`. | **`PASS`** | `apps/api/database/seeders/UatStagingSeeder.php:267-303`; Teruji di `UatSeederTest.php`. | Seeding berulang menghapus progres pengujian atau mengunci akun penguji yang sedang login. | QA / Backend | Jalankan seeder tanpa opsi force-reset setelah inisialisasi awal. | Eksekusi seeder dua kali berturut-turut menghasilkan jumlah baris data yang identik. |
| **12** | **Seeder: Kepatuhan Privasi Data Sintetis** | Seluruh data siswa, orang tua, NIK, NISN, nomor telepon, dan nilai adalah 100% data sintetis fiktif. Tidak ada kredensial atau rahasia yang tercetak di log. | **`PASS`** | `apps/api/database/seeders/UatStagingSeeder.php:391-443`; NIK `3578010101090001`, Telp `081234567890`. | Pelanggaran UU PDP / privasi data anak jika data siswa riil digunakan di server staging. | Tim Pengembang | Jangan pernah memasukkan dump data riil siswa ke database staging. | Seluruh data pengujian dapat dibuktikan berasal dari generator sintetis. |
| **13** | **Backup: Validitas Perintah pg_dump** | Perintah kanonis `pg_dump` dengan format custom binary terkompresi (`-F c -b -v`) telah didefinisikan untuk menggantikan kesalahan dokumentasi lama `mysqldump`. | **`PASS`** (Protokol) / **`NOT VERIFIED`** (Eksekusi Live) | `docs/uat/STAGING_ENVIRONMENT_READINESS_REPORT.md:210-226`. | Kegagalan pencadangan jika menggunakan utilitas `mysqldump` pada database PostgreSQL. | DevOps / DBA | Eksekusi perintah `pg_dump` segera setelah migrasi database selesai di server staging. | Terbentuk berkas `.dump` yang valid dan dapat diinspeksi menggunakan `pg_restore -l`. |
| **14** | **Backup: Arsip Berkas storage/app** | Protokol pencadangan mencakup arsip tarball terkompresi `tar -czvf` untuk seluruh berkas fisik di `storage/app`. | **`PASS`** (Protokol) / **`NOT VERIFIED`** (Eksekusi Live) | `docs/uat/STAGING_ENVIRONMENT_READINESS_REPORT.md:223-226`. | Kehilangan berkas fisik lampiran tugas dan dokumen PPDB saat rollback database. | DevOps / Sysadmin | Eksekusi pencadangan tarball storage bersamaan dengan database dump. | Terbentuk berkas `storage_staging_baseline_[TIMESTAMP].tar.gz` di `/var/backups/sistlabum/`. |
| **15** | **Backup: Hak Akses Direktori Cadangan** | Spesifikasi membatasi izin direktori `/var/backups/sistlabum` ke `700` dan berkas dump ke `600`, kepemilikan user `postgres`. | **`PASS`** (Protokol) / **`NOT VERIFIED`** (Eksekusi Live) | `docs/uat/STAGING_ENVIRONMENT_READINESS_REPORT.md:230-235`. | Berkas cadangan yang memuat data pengujian terbaca oleh pengguna tidak berwenang pada server hosting bersama. | DevOps / Sysadmin | Terapkan perintah `chmod 700 /var/backups/sistlabum` dan `chmod 600 /var/backups/sistlabum/*`. | Hanya root dan user database yang memiliki izin membaca berkas dump. |
| **16** | **Restore: Uji Coba Pemulihan Terkendali** | Prosedur pemulihan cepat (*disaster recovery*) menggunakan `pg_restore --clean --if-exists` dan pengosongan cache Laravel telah didokumentasikan lengkap. | **`PASS`** (Protokol) / **`NOT VERIFIED`** (Eksekusi Live) | `docs/uat/STAGING_ENVIRONMENT_READINESS_REPORT.md:240-284`. | Tim operasional panik dan tidak mampu memulihkan sistem jika terjadi insiden Stop-the-Line saat sesi UAT. | Lead DevOps / DBA | Jalankan simulasi restore pada database staging cadangan terpisah sebelum hari pelaksanaan UAT. | Database berhasil dipulihkan ke status baseline dan health check kembali `status: ok`. |
| **17** | **Integrasi: Endpoint GET /api/v1/health** | Logika endpoint memeriksa koneksi PDO database dan `Redis::ping()`. Status `ok` hanya diberikan jika keduanya aktif. | **`PASS`** (Logika Kode) / **`NOT VERIFIED`** (Akses HTTP Staging) | `apps/api/routes/api.php:113-139`; Teruji via PHPUnit. | Layanan staging tampak berjalan di web tetapi mengalami kegagalan tersembunyi pada antrean atau database. | DevOps / QA | Lakukan pengujian HTTP: `curl -i https://api-staging.smp-labum.sch.id/api/v1/health`. | HTTP 200 dengan payload JSON `status: "ok"`, `services.database: "ok"`, `services.redis: "ok"`. |
| **18** | **Integrasi: Sandbox OTP PPDB** | `PPDBAccountService.php` menyimpan OTP di cache Redis selama 15 menit dan tidak mengeksposnya pada respons HTTP di environment staging. Audit log tidak mencatat angka OTP. | **`PASS`** | `apps/api/app/Services/PPDBAccountService.php:67-114`. | Penguji PPDB terblokir pada verifikasi OTP jika tidak diberikan akses sandbox; atau OTP bocor ke publik jika diekspos di JSON. | Backend / QA | Sediakan panduan akses Mailpit atau instruksi verifikasi sandbox bagi penguji pendaftaran PPDB. | OTP tidak muncul di respons JSON registrasi staging, namun dapat diverifikasi via sandbox. |
| **19** | **Integrasi: Mailpit / Log Driver Email** | Konfigurasi bawaan repositori menggunakan `MAIL_MAILER=log` atau container Mailpit (port 1025/8025). Tidak ada pengiriman email ke server SMTP publik riil. | **`PASS`** | `apps/api/.env.example:57`; `docker-compose.yml:68-76`. | Email notifikasi uji coba terkirim ke alamat email masyarakat sungguhan. | DevOps / QA | Pastikan `.env` staging menyetel `MAIL_MAILER=mailpit` (port 1025) atau `MAIL_MAILER=log`. | Seluruh pesan email masuk ke webmail inspector Mailpit (port 8025) atau `storage/logs/laravel.log`. |
| **20** | **Integrasi: Mock Stub WhatsApp & FCM** | `MessagingService.php` menggunakan adapter simulasi `dispatchToProvider` yang menghasilkan ID referensi acak tanpa memanggil API pihak ketiga berbayar. | **`PASS`** | `apps/api/app/Services/Ntf/MessagingService.php:237-259`. | Pulsa atau saldo gateway WA terpotong, atau pesan notifikasi palsu terkirim ke nomor HP orang tua asli. | Backend | Pertahankan implementasi mock stub selama pengujian staging UAT. | Pengiriman notifikasi WA/Push berstatus `sent` dengan `provider_ref: wa_msg_...` tanpa koneksi eksternal. |
| **21** | **Kebijakan: Scheduler & Pembersihan Berkas** | Perintah pembersihan berkas yatim `CleanupOrphanFilesCommand` tidak didaftarkan di scheduler otomatis `routes/console.php` dan berjalan dalam mode default laporan (*dry-run*). | **`PASS`** | `apps/api/routes/console.php`; `apps/api/app/Console/Commands/CleanupOrphanFilesCommand.php`. | Draf unggahan pendaftar PPDB multi-hari terhapus otomatis di server sebelum formulir difinalisasi. | DevOps / QA | Jangan mendaftarkan cron pembersihan berkas dengan flag `--execute` di server staging. | Eksekusi pembersihan di staging hanya dilakukan secara manual dengan persetujuan QA Lead. |

---

## B. Audit Kesiapan Seeder dan Basis Data

### 1. Guard Lingkungan & Isolasi Tenant
- **Proteksi Produksi:** Lulus audit (`UatStagingSeeder::checkEnvironmentSafety`). Jika dijalankan pada `APP_ENV=production`, seeder langsung melempar `\RuntimeException` sebelum mengeksekusi instruksi database apa pun.
- **Proteksi Staging Opt-in:** Pada environment selain `testing`, seeder menuntut variabel lingkungan `UAT_SEED_ENABLED=true`. Jika bernilai `false` atau tidak disetel, seeder menolak eksekusi (*fail-closed*).
- **Integritas Tenant (School ID):** Seeder memvalidasi parameter `UAT_SCHOOL_ID` harus berupa string UUID v4/v7 yang valid dan memastikan nilainya tidak berbenturan dengan `school_id` yang tersimpan pada tabel `site_settings`.

### 2. Keamanan Kredensial Akun Pengujian
- **Rahasia Staging Unik:** Di staging, seeder mewajibkan penyediaan variabel lingkungan `UAT_STAGING_PASSWORD`.
- **Penolakan Password Terprediksi:** Seeder secara eksplisit memblokir kata sandi bawaan repositori `SistLabUM2026!UAT` di staging untuk mencegah pengambilalihan akun uji oleh pihak yang membaca repositori publik/internal.
- **Pencegahan Penimpaan Sandi Diam-Diam:** Fungsi helper seeding akun memeriksa keberadaan pengguna di database (`$user->exists`). Kata sandi akun yang sudah ada **TIDAK** ditimpa secara otomatis, sehingga penguji yang telah mengganti kata sandi atau sedang menguji sesi tidak kehilangan aksesnya. Penimpaan sandi hanya terjadi jika diberikan bendera tegas `UAT_FORCE_RESET_PASSWORDS=true`.

### 3. Integritas Relasi Data & Cakupan 16 Persona
- **Status Saat Ini:** `UatStagingSeeder.php` telah menginisialisasi 14 entitas akun persona (Super Admin, Kepala Sekolah, Kurikulum, Wali Kelas 7-A, Guru MTK, Guru BK, Staf TU, Staf Sarpras, Panitia PPDB, Siswa Aktif 7A, Orang Tua 1, Calon Siswa PPDB, Staf Kesiswaan, dan Staf Humas).
- **Persona Lanjutan untuk Skenario Khusus:** Skenario uji isolasi antarkelas (`TC-LMS-04`) dan uji penetrasi anti-IDOR orang tua (`TC-ORT-05/06`) membutuhkan persona Siswa 8B (`siswa.zahra`) dan Orang Tua 2 (`wali.dewi`). Sebelum skenario tersebut dijalankan, penguji dapat memanfaatkan akun sintetis dinamis atau menambahkan entri rombel 8B pada seeder staging.
- **Kepatuhan Privasi (Zero Real PII):** Seluruh data siswa, nomor induk kependudukan, nomor registrasi, nomor telepon, dan data nilai adalah data sintetis fiktif murni. Tidak ada data nyata anak yang terekspos.

---

## C. Audit Prosedur Pencadangan dan Pemulihan (Backup & Restore)

1. **Perbaikan Transkripsi Engine Database:**
   Dokumen `STAGING_UAT_EXECUTION_CHECKLIST.md` yang sebelumnya menyebutkan `mysqldump` telah dikoreksi menjadi protokol kanonis **PostgreSQL 16** menggunakan utilitas `pg_dump` dan `pg_restore`.
2. **Kesesuaian Format Pencadangan:**
   Format `-F c` (*custom format*) menghasilkan berkas dump binary yang mendukung fleksibilitas pemulihan per-tabel, kompresi efisien, dan restorasi multithread via `pg_restore`.
3. **Pencadangan Berkas Fisik:**
   Direktori berkas `storage/app` (mencakup dokumen privat dan publik) dicadangkan secara paralel ke dalam arsip `.tar.gz`.
4. **Isolasi Database Latihan Restore:**
   Prosedur pemulihan mewajibkan pengujian pemulihan dilakukan pada database pengujian cadangan (*scratch database*) sebelum diterapkan pada basis data staging aktif.

---

## D. Audit Endpoint Kesehatan dan Batas Perilaku Integrasi

1. **Health Check (`GET /api/v1/health`):**
   - Menguji konektivitas PDO database dan Redis ping.
   - Status respons HTTP:
     - `HTTP 200` dengan JSON `{"status": "ok", "services": {"database": "ok", "redis": "ok"}}` jika kedua dependensi aktif.
     - `HTTP 200` dengan JSON `{"status": "degraded", ...}` jika salah satu dependensi mati.
2. **Isolasi Notifikasi Eksternal:**
   - **WhatsApp & SMS:** Modul `MessagingService.php` 100% menggunakan stub penyedia internal (`wa_msg_...`). Tidak ada pesan keluar ke jaringan seluler eksternal.
   - **Email:** Menggunakan `MAIL_MAILER=log` atau Mailpit (`localhost:1025`). Seluruh pesan tertampung di webmail inspector port 8025.
   - **OTP PPDB:** Pada staging, OTP 6-digit tersimpan aman di Redis cache (`ppdb_otp:...`) dan tidak dikembalikan pada respons JSON publik registrasi.

---

## E. Lima Keputusan Gerbang Kesiapan UAT Staging (Gate Decisions)

```
================================================================================
                    KEPUTUSAN GERBANG KESIAPAN PREFLIGHT UAT
================================================================================
1. Kesiapan Infrastruktur Staging : NOT VERIFIED / BLOCKED (Memerlukan DNS & SSL)
2. Kesiapan Akun dan Data         : PASS WITH CONDITIONS (Siap Dieksekusi via Seeder)
3. Kesiapan Integrasi Layanan     : PASS (Mock & Sandbox Siap)
4. Kesiapan Backup & Pemulihan    : PASS (Prosedur & Perintah PostgreSQL Tervalidasi)
--------------------------------------------------------------------------------
5. KEPUTUSAN FINAL IZIN UAT       : HOLD (BELUM BOLEH DIMULAI)
================================================================================
```

### Rincian Keputusan:

1. **Keputusan 1 — Kesiapan Infrastruktur Staging:** **`NOT VERIFIED / BLOCKED`**
   - *Alasan:* Server staging belum dapat diakses melalui jaringan publik karena rekaman DNS `staging.smp-labum.sch.id` dan `api-staging.smp-labum.sch.id` masih berstatus `NXDOMAIN`. Sertifikat SSL/HTTPS belum diterbitkan, dan instans live PostgreSQL 16 serta Redis 7 di server staging belum dapat diverifikasi status konektivitasnya.

2. **Keputusan 2 — Kesiapan Akun dan Data:** **`PASS WITH CONDITIONS`**
   - *Alasan:* Skrip `UatStagingSeeder.php` telah lolos seluruh 19 uji keamanan preflight (`UatSeederTest.php`), memiliki guard lingkungan fail-closed, menolak kata sandi default publik di staging, dan idempoten.
   - *Syarat:* Eksekusi seeder di server staging wajib menunggu provisioning database PostgreSQL selesai dan secret `UAT_STAGING_PASSWORD` diisi pada `.env` staging.

3. **Keputusan 3 — Kesiapan Integrasi Layanan:** **`PASS`**
   - *Alasan:* Sistem terlindungi dari risiko kebocoran data keluar karena WhatsApp dan FCM menggunakan mock stub terisolasi, email menggunakan sandbox Mailpit/log, dan kode OTP PPDB tidak bocor pada respons publik API di staging.

4. **Keputusan 4 — Kesiapan Backup dan Pemulihan:** **`PASS (Prosedur)` / `NOT VERIFIED (Eksekusi Live)`**
   - *Alasan:* Seluruh perintah pencadangan berbasis `pg_dump` dan pemulihan `pg_restore` telah diselaraskan secara akurat dengan arsitektur PostgreSQL 16.
   - *Syarat:* Tim DevOps wajib mengeksekusi pencadangan baseline nyata segera setelah migrasi database selesai di server staging.

5. **Keputusan 5 — Izin Memulai UAT:** **`HOLD (UAT BELUM BOLEH DIMULAI)`**
   - *Alasan Kepatuhan AGENTS.md Bagian 10 & 14:* Sesuai aturan wajib, **UAT tidak boleh dinyatakan siap jika masih terdapat pemeriksaan wajib yang berstatus FAIL, NOT VERIFIED, atau BLOCKED**. UAT baru dapat dimulai setelah seluruh tindakan manusia pada Bagian F diselesaikan.

---

## F. Tindakan Manusia Wajib yang Tersisa (Pending Human Actions)

Sebelum gerbang UAT dapat dialihkan statusnya menjadi **`GO (DAPAT DIMULAI)`**, tim DevOps, administrator sistem, dan pemilik proyek sekolah wajib menyelesaikan 6 tindakan manusia berikut:

1. **Aksi 1: Konfigurasi DNS Publik Sekolah (P0 — Blocker)**
   - Daftarkan A record pada DNS server otoritatif sekolah:
     - `staging.smp-labum.sch.id` -> `[IP Publik Server Staging]`
     - `api-staging.smp-labum.sch.id` -> `[IP Publik Server Staging]`
   - *Verifikasi:* Panggilan `host staging.smp-labum.sch.id` mengembalikan IP server yang sah.

2. **Aksi 2: Penerbitan Sertifikat SSL/TLS HTTPS (P0 — Blocker)**
   - Jalankan Certbot pada reverse proxy NGINX staging:
     ```bash
     sudo certbot --nginx -d staging.smp-labum.sch.id -d api-staging.smp-labum.sch.id
     ```
   - *Verifikasi:* URL `https://staging.smp-labum.sch.id` memuat sertifikat valid tanpa error di peramban.

3. **Aksi 3: Konfigurasi File Lingkungan `.env` Server Staging (P0 — Blocker)**
   - Buat file `.env` pada direktori backend staging (`/var/www/sistlabum/apps/api/.env`):
     - `APP_ENV=staging`
     - `APP_DEBUG=false`
     - `APP_KEY=[kunci unik staging]`
     - `DB_CONNECTION=pgsql`
     - `DB_HOST=127.0.0.1` (atau nama host container postgres)
     - `DB_PORT=5432`
     - `DB_DATABASE=sistlabum_staging`
     - `REDIS_CLIENT=phpredis`
     - `REDIS_HOST=127.0.0.1`
     - `REDIS_PORT=6379`
     - `SESSION_SECURE_COOKIE=true`
     - `SESSION_SAME_SITE=lax`
     - `CORS_ALLOWED_ORIGINS=https://staging.smp-labum.sch.id`
     - `SANCTUM_STATEFUL_DOMAINS=staging.smp-labum.sch.id`
     - `UAT_SEED_ENABLED=true`
     - `UAT_STAGING_PASSWORD=[secret unik aman yang disetujui]`
     - `UAT_SCHOOL_ID=00000000-0000-0000-0000-000000000001`
     - `MAIL_MAILER=log` (atau mailpit)

4. **Aksi 4: Eksekusi Migrasi Database & Seeding Terkontrol (P0 — Blocker)**
   - Jalankan migrasi dan seeding di server staging:
     ```bash
     cd /var/www/sistlabum/apps/api
     php artisan migrate --force
     php artisan db:seed --class=UatStagingSeeder --force
     ```
   - *Verifikasi:* 14 akun persona terdaftar di database staging dan dapat diautentikasi dengan sandi `UAT_STAGING_PASSWORD`.

5. **Aksi 5: Eksekusi Pencadangan Baseline Pre-UAT (P0 — Blocker)**
   - Eksekusi dump baseline sebelum penguji masuk ke sistem:
     ```bash
     mkdir -p /var/backups/sistlabum && chmod 700 /var/backups/sistlabum
     pg_dump -h 127.0.0.1 -p 5432 -U postgres -d sistlabum_staging -F c -b -v -f /var/backups/sistlabum/db_staging_baseline_pre_uat.dump
     tar -czvf /var/backups/sistlabum/storage_staging_baseline_pre_uat.tar.gz -C /var/www/sistlabum/apps/api/storage/app .
     chmod 600 /var/backups/sistlabum/*
     ```
   - *Verifikasi:* Berkas dump dan tarball tersimpan aman di direktori cadangan.

6. **Aksi 6: Verifikasi Akhir Health Check & Distribusi Akses (P1)**
   - Panggil endpoint kesehatan:
     ```bash
     curl -i https://api-staging.smp-labum.sch.id/api/v1/health
     ```
     Pastikan mengembalikan HTTP 200 dengan `status: "ok"`.
   - Distribusikan kredensial akun uji kepada 16 penguji yang ditunjuk disertai SOP pengujian kasus uji UAT.
