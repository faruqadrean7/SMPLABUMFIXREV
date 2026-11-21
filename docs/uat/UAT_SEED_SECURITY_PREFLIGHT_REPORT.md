# Laporan Security Hardening & Preflight Audit Seeder (`UAT-SEED-02`)
**Sistem Informasi Sekolah Terpadu Laboratorium UM (SISTLABUM)**  
*Dokumen Ref: `UAT-SEED-02 / UAT_SEED_SECURITY_PREFLIGHT_REPORT.md`*  
*Tanggal: 15 Oktober 2026*  
*Status Evaluasi: PASS (Preflight Hardening Verified, Staging Infrastructure Prerequisite Pending)*

---

## 1. Ringkasan Eksekutif & Tujuan

Berdasarkan mandat penugasan `UAT-SEED-02`, telah dilakukan audit keamanan operasional dan hardening secara mendalam terhadap `UatStagingSeeder` sebelum seeder dieksekusi pada basis data staging nyata. 

Audit ini meninjau empat domain kritis:
1. **Keamanan Autentikasi & Kata Sandi**: Penghapusan ketergantungan otomatis pada password default bersama di staging nyata, penegakan secret khusus staging, pencegahan reset password diam-diam pada akun yang sudah ada, serta jaminan zero-leakage password pada log dan exception.
2. **Isolasi Tenant & ID Sekolah**: Eliminasi asumsi `school_id` statis tanpa validasi, penegakan resolusi tenant fail-closed, validasi format UUID, dan deteksi konflik konfigurasi antara environment dan basis data.
3. **Safety Guard Lingkungan**: Verifikasi penolakan mutlak di lingkungan `production` (termasuk saat opt-in aktif), persyaratan `UAT_SEED_ENABLED=true` di `staging`, penolakan default pada lingkungan tidak dikenal/lokal, penanganan konflik identifier akun secara eksplisit, dan eksekusi preflight guard sebelum operasi tulis apa pun.
4. **Pembuktian Regresi Otomatis**: Penambahan 10 test case keamanan komprehensif pada `apps/api/tests/Feature/Auth/UatSeederTest.php` (total 18 tests, 173 assertions) dan verifikasi seluruh suite backend (1.119 tests, 6.189 assertions).

---

## 2. Temuan Audit Berdasarkan Kode Aktual & Matriks Risiko

| ID Temuan | Area Audit | Deskripsi Kondisi Sebelum Hardening | Tingkat Risiko | File Terkait | Status Remediasi |
|---|---|---|:---:|---|:---:|
| **SEC-SEED-01** | Autentikasi Staging | Password staging secara diam-diam dapat menggunakan fallback terprediksi `SistLabUM2026!UAT` jika environment variable tidak diisi di staging nyata. | **HIGH** | `UatStagingSeeder.php` | ✅ REMEDIATED |
| **SEC-SEED-02** | Integritas Kredensial | Eksekusi seeder berulang pada akun yang sudah ada me-reset password secara otomatis jika tidak cocok dengan password seeder, menghapus kustomisasi tester. | **MEDIUM** | `UatStagingSeeder.php` | ✅ REMEDIATED |
| **SEC-SEED-03** | Tenant Scoping | `school_id` di-hardcode ke `00000000-0000-0000-0000-000000000001` tanpa memvalidasi apakah staging menggunakan tenant UUID yang berbeda atau telah memiliki `SiteSetting`. | **HIGH** | `UatStagingSeeder.php` | ✅ REMEDIATED |
| **SEC-SEED-04** | Identifier Collision | Jika email persona UAT telah diambil oleh akun non-UAT lain di database, seeder memicu PDOException crash yang tidak terkendali alih-alih penolakan aman fail-closed. | **MEDIUM** | `UatStagingSeeder.php` | ✅ REMEDIATED |
| **SEC-SEED-05** | Preflight Timing | Pengecekan environment dan pemanggilan DatabaseSeeder dilakukan di dalam callback transaksi tanpa validasi awal secret dan tenant sebelum DB touch. | **LOW** | `UatStagingSeeder.php` | ✅ REMEDIATED |

---

## 3. Rincian Perubahan & Hardening yang Dilakukan

### A. Hardening Autentikasi dan Resolusi Kata Sandi (`resolveUatPassword`)
1. **Penolakan Fallback Terprediksi di Staging**:
   - Di lingkungan `staging`, `UAT_STAGING_PASSWORD` atau `UAT_DEFAULT_PASSWORD` **wajib disediakan secara eksplisit**.
   - Jika bernilai kosong, seeder langsung melempar `RuntimeException` sebelum operasi tulis dimulai.
   - Jika bernilai default terprediksi (`SistLabUM2026!UAT`), seeder menolak eksekusi kecuali variabel `ALLOW_PREDICTABLE_UAT_PASSWORD=true` diberikan secara sadar oleh teknisi.
2. **Dukungan Testing Terisolasi**:
   - Khusus pada lingkungan `testing`, seeder diizinkan menggunakan fallback terstandarisasi agar seluruh test suite CI/CD dapat berjalan mandiri tanpa konfigurasi eksternal.
3. **Pencegahan Reset Password Diam-Diam**:
   - Jika akun persona sudah ada di database (`$user->exists`), seeder **mempertahankan password yang sudah ada** tanpa perubahan hash.
   - Reset password massal hanya dilakukan jika flag eksplisit `UAT_FORCE_RESET_PASSWORDS=true` diaktifkan di environment.
4. **Pencegahan Kebocoran Rahasia (Zero-Leakage)**:
   - Tidak ada kata sandi mentah yang dicetak pada output konsol, pesan exception, trace log, maupun laporan.

### B. Validasi Tenant & Identitas Sekolah (`resolveTenantSchoolId`)
1. **Resolusi Bertingkat**:
   - Seeder memeriksa `UAT_SCHOOL_ID` atau `DEFAULT_SCHOOL_ID` di environment.
   - Seeder memeriksa apakah tabel `site_settings` (key `general_info`) telah memiliki `school_id` tersimpan.
2. **Deteksi Konflik Tenant**:
   - Jika konfigurasi environment berbeda dengan `school_id` yang tersimpan pada `site_settings` basis data staging, seeder melempar exception fail-closed untuk mencegah salah tenant atau kebocoran data lintas sekolah.
3. **Validasi Format UUID**:
   - `school_id` divalidasi ketat menggunakan `Str::isUuid($schoolId)`. Nilai non-UUID ditolak seketika sebelum transaksi DB dimulai.
4. **Konsistensi Scoping**:
   - Seluruh 12 user, model profil (`Teacher`, `Staff`, `Student`, `Guardian`), rombel `7-A`, mapel `MTK-7`, gelombang PPDB, fasilitas, booking, dan `SiteSetting` dipastikan terikat pada UUID tenant yang sama.

### C. Penanganan Konflik Identifier Akun
Pada fungsi `$seedUser`:
- Mengecek apakah `username` dan `email` terikat pada dua baris akun berbeda di tabel `users`.
- Jika email UAT telah digunakan oleh akun non-UAT sebelumnya, seeder melempar exception:
  `Konflik identifier akun UAT: email '...' telah digunakan oleh akun lain (username: '...')`
- Akun non-UAT (seperti `superadmin` bawaan `DatabaseSeeder` atau akun guru asli) dijamin tidak dimutasi atau terhapus.

---

## 4. Bukti Pengujian Regresi Otomatis

Pengujian regresi dijalankan pada basis data SQLite in-memory terisolasi (`apps/api/tests/Feature/Auth/UatSeederTest.php`).

```text
Suite: Tests\Feature\Auth\UatSeederTest
──────────────────────────────────────────────────────────────────────────────────
 ✓ uat seed 01 seeder executes successfully and creates all 12 personas
 ✓ uat seed 01 academic and class assignments are properly linked
 ✓ uat seed 01 student and guardian relations are properly linked
 ✓ uat seed 01 ppdb applicant data is properly initialized
 ✓ uat seed 01 initial approval queue records have canonical statuses
 ✓ uat seed 01 seeder is fully idempotent
 ✓ uat seed 01 safety guard blocks production environment
 ✓ uat seed 01 safety guard requires opt in outside testing
 ✓ uat seed 02 staging password must be provided via approved config
 ✓ uat seed 02 missing or invalid tenant config fails safely in staging
 ✓ uat seed 02 production is strictly rejected even with opt in
 ✓ uat seed 02 disallowed environment is rejected by default
 ✓ uat seed 02 rejection leaves no database changes
 ✓ uat seed 02 non uat accounts outside seeder scope are not mutated
 ✓ uat seed 02 repeated seeding does not silently reset existing account passwords
 ✓ uat seed 02 account identifier conflict fails safely
 ✓ uat seed 02 tenant relations are valid and consistent across all uat data
 ✓ uat seed 02 exceptions and outputs never contain plaintext passwords
──────────────────────────────────────────────────────────────────────────────────
Tests: 18 passed (173 assertions) | Duration: 1.08s
```

### Hasil Uji Menyeluruh Backend & Frontend
1. **Backend PHPUnit Full Suite**:
   - Perintah: `php artisan test`
   - Hasil: **1.119 passed (6.189 assertions)**, Duration: 38.15s (100% GREEN, Zero Regressions).
2. **Frontend Vitest Suite**:
   - Perintah: `npm test` di `apps/web`
   - Hasil: **61 test files passed, 723 tests passed** (100% GREEN).

---

## 5. Prasyarat Konfigurasi Lingkungan Staging Nyata

Sebelum menjalankan `php artisan db:seed --class=UatStagingSeeder` pada server staging Linux, tim DevOps/Sysadmin **wajib** memastikan konfigurasi environment berikut telah terisi pada `.env` staging:

```bash
# 1. Konfigurasi Lingkungan & Safety Opt-In (Wajib)
APP_ENV=staging
UAT_SEED_ENABLED=true

# 2. Secret Kata Sandi Staging Khusus (Wajib, jangan gunakan password default repo)
# Password ini akan di-hash secara aman ke seluruh 12 akun penguji saat akun pertama kali dibuat
UAT_STAGING_PASSWORD="GantiDenganRahasiaKuatUatStaging2026!#"

# 3. Identitas Tenant Sekolah (Wajib)
# Masukkan UUID tenant sekolah SMP Laboratorium UM di staging
UAT_SCHOOL_ID="00000000-0000-0000-0000-000000000001"

# 4. Kebijakan Reset Password pada Pemanggilan Berulang (Opsional, Default: false)
# Biarkan false agar password akun yang sudah dimodifikasi tester tidak ter-reset
UAT_FORCE_RESET_PASSWORDS=false
```

---

## 6. Temuan yang Belum Dapat Diverifikasi (Unverified Staging Dependencies)

Sesuai laporan `docs/uat/UAT_STAGING_RECHECK_REPORT.md`:
1. **Server Staging Linux & DNS**: Belum dapat diverifikasi pada host remote nyata karena VM dan domain staging (`staging.smp-labum.sch.id`) belum online (NXDOMAIN).
2. **PostgreSQL Staging Database**: Eksekusi nyata seeder pada engine PostgreSQL staging belum dapat dilakukan hingga server dan database staging diprovisioning sesuai `docs/uat/UAT_STAGING_REMEDIATION_RUNBOOK.md`.

---

## 7. Keputusan Akhir Preflight

Berdasarkan seluruh pengujian kode, pembuktian isolasi tenant, penegakan secret staging, dan safeguard fail-closed:

### **Status: PASS (PREFLIGHT AUDIT & HARDENING COMPLIANT)**

> [!IMPORTANT]
> **Catatan Kepatuhan Operasional**:
> Status `PASS` ini menyatakan bahwa **kode dan logika seeder telah memenuhi standar keamanan tertinggi dan siap dijalankan**. Keputusan ini **TIDAK** menyatakan bahwa lingkungan staging sudah siap untuk UAT, karena kesiapan staging tetap mensyaratkan penyelesaian remediasi infrastruktur, DNS, SSL, database provisioning, dan worker scheduler sesuai dokumen runbook operasional.
