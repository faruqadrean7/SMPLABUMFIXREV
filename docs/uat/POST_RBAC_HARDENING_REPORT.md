# POST-RBAC-HARDENING-01 — Laporan Remediasi Persona UAT, Deteksi Permission Drift, dan Admin Route Guard

**Tanggal Pelaporan:** 15 Oktober 2026  
**Status Eksekutif:** SELESAI (PASS)  
**Dokumen Acuan:** `13_MASTER_MODULES.md` (SSOT), `docs/uat/RBAC_POST_REMEDIATION_AUDIT_REPORT.md`  
**Cakupan Repositori:**
- Backend Laravel: `apps/api`
- Frontend Web/Admin Next.js: `apps/web`

---

## 1. Ringkasan Eksekutif

Audit pascaremediasi sebelumnya (`RBAC_POST_REMEDIATION_AUDIT_REPORT.md`) menyimpulkan bahwa celah keamanan buku tamu telah ditutup, 7 jabatan internal sekolah telah terintegrasi ke dalam Admin System dengan RBAC yang konsisten, dan permission drift antar-seeder telah direkonsiliasi. Namun, audit mencatat 3 catatan residual:
1. **POST-AUDIT-01 (P2):** Persona UAT Kesiswaan (`kesiswaan.staging`) dan Humas (`humas.staging`) belum didaftarkan di `UatStagingSeeder.php`.
2. **POST-AUDIT-02 (P2):** Belum tersedianya sarana audit read-only untuk mendeteksi potensi permission drift pada database existing/staging yang sudah berjalan.
3. **POST-AUDIT-03 (P3):** Ketikan URL manual langsung ke modul terproteksi di frontend Admin System belum memiliki UX penolakan akses yang konsisten (`AccessDenied`) di level route layout.

Melalui penugasan **POST-RBAC-HARDENING-01**, ketiga temuan di atas telah **diimplementasikan, diverifikasi, dan diuji secara menyeluruh** tanpa mengubah skema database, tanpa menambah paket dependensi eksternal, dan tanpa merusak status kelulusan pengujian yang sudah ada.

---

## 2. Matriks Status Temuan

| ID Temuan | Severity | Deskripsi Temuan | Status Remediasi | Bukti Verifikasi |
|---|---|---|---|---|
| **POST-AUDIT-01** | P2 | Persona UAT Kesiswaan & Humas belum tersedia di seeder staging | **FIXED** | Persona 13 & 14 terdaftar di `UatStagingSeeder.php`, 19 test di `UatSeederTest.php` lulus (217 assertions). |
| **POST-AUDIT-02** | P2 | Risiko permission drift pada DB existing tanpa sarana inspeksi | **FIXED** | Command Artisan read-only `php artisan rbac:audit-drift` diimplementasikan dengan mode table & `--json`, 5 test di `RbacAuditDriftTest.php` lulus. |
| **POST-AUDIT-03** | P3 | UX rute admin yang dibuka langsung via URL belum seragam | **FIXED** | Route guard modul via `getAdminRoutePermission()` & `AccessDenied` di `AdminLayout`, 18 test di `admin-route-guard.test.tsx` lulus. |

---

## 3. Detail Implementasi & Perubahan Kode

### A. Persona UAT Kesiswaan dan Humas (POST-AUDIT-01)

1. **`apps/api/database/seeders/UatStagingSeeder.php`**:
   - Menambahkan 2 entitas persona baru (total 14 persona UAT terkelola):
     - **Persona 13 (`kesiswaan.staging`)**: Role `student_affairs`, Nama: *Staf Pembina Kesiswaan*, Unit: *Kesiswaan*, Jabatan: *Staf Pembina Kesiswaan*, Email: `kesiswaan.uat@smp-labum.sch.id`.
     - **Persona 14 (`humas.staging`)**: Role `public_relations`, Nama: *Staf Hubungan Masyarakat*, Unit: *Humas*, Jabatan: *Staf Hubungan Masyarakat*, Email: `humas.uat@smp-labum.sch.id`.
   - Mengikuti prinsip *least privilege*, kedua staf terikat secara valid pada sekolah kanonis `00000000-0000-0000-0000-000000000001` dengan relasi model `Staff` lengkap.
   - Idempoten: Jika akun sudah ada, seeder memperbarui biodata dan profil tanpa menimpa password yang sudah dimodifikasi secara lokal (`preserve existing passwords`).
   - Keamanan: Kredensial tidak di-hardcode, proteksi `ensureSafeEnvironment()` tetap berlaku ketat (menolak jalan di `production`).

2. **`apps/api/tests/Feature/Auth/UatSeederTest.php`**:
   - Memperbarui daftar persona yang diharapkan menjadi 14 persona.
   - Menambahkan test case komprehensif `test_uat_seed_03_student_affairs_and_public_relations_personas_have_correct_roles_and_least_privilege()`:
     - Memvalidasi role `student_affairs` memiliki `student.point.read`, `student.point.write`, `student.extracurricular.manage`, dan ditolak dari `cbt.bank.write`, `master.teacher.manage`, `complaint.view_reporter`.
     - Memvalidasi role `public_relations` memiliki `guest.book.read`, `guest.book.manage`, `complaint.read`, `complaint.manage`, dan ditolak dari `counseling.case.read`, `academic.grade.write`, `complaint.view_reporter`.
     - Memvalidasi relasi profil `Staff` dan tenant isolation.

---

### B. Tool Inspeksi Permission Drift Read-Only (POST-AUDIT-02)

1. **`apps/api/app/Console/Commands/RbacAuditDriftCommand.php`**:
   - Perintah kanonis: `php artisan rbac:audit-drift {--json} {--strict}`.
   - Sifat kerja: **100% Read-Only**. Tidak ada perintah `syncPermissions`, `attach`, `detach`, atau modifikasi database apa pun.
   - Mekanisme inspeksi:
     1. Membaca matriks kanonis SSOT dari `RolePermissionSeeder` dan `13_MASTER_MODULES.md`.
     2. Memeriksa keberadaan peran kanonis dan mendeteksi peran tak dikenal (*Unknown Roles*).
     3. Mendeteksi izin bawaan yang belum terpasang (*Missing Canonical Permissions*).
     4. Mendeteksi izin berbahaya/kebocoran izin administratif (*Prohibited Administrative Leaks*), seperti siswa/wali/humas memegang izin mutasi kurikulum, soal CBT, atau rahasia pelapor aduan.
     5. Mengkategorikan penambahan izin khusus yang sah (*Custom Extensions* / role delegasi).
     6. Mendeteksi izin tanpa pemilik (*Orphan Permissions*).
   - Format Output:
     - Default: Tabel terminal rapi dengan ringkasan status (`CLEAN`, `DRIFT_DETECTED`, `INCOMPLETE_MIGRATION`).
     - Opsi `--json`: Output JSON murni terstruktur untuk automasi CI/CD atau monitoring berkala.
   - Keamanan: Tidak mengekspos token, password hash, salt, atau data pribadi pengguna.

2. **`apps/api/tests/Feature/Auth/RbacAuditDriftTest.php`**:
   - 5 pengujian otomatis terisolasi:
     - `test_audit_drift_command_reports_clean_on_fresh_seeded_database`: Memverifikasi database baru menghasilkan status `CLEAN` dan exit code 0.
     - `test_audit_drift_command_detects_prohibited_leaks`: Memverifikasi deteksi instan saat peran siswa/wali disuntik izin administratif berbahaya dengan exit code 1.
     - `test_audit_drift_command_differentiates_custom_extensions`: Memverifikasi izin tambahan sah tidak dianggap sebagai kegagalan sistematis melainkan ekstensi terdata.
     - `test_audit_drift_command_detects_unknown_roles`: Memverifikasi deteksi peran yang tidak terdaftar di SSOT.
     - `test_audit_drift_command_json_output_structure_and_safety`: Memverifikasi integritas payload JSON dan tidak adanya data kredensial.

---

### C. Admin Route Guard & Direct URL Access (POST-AUDIT-03)

1. **`apps/web/src/lib/rbac/navigation.ts`**:
   - Menambahkan compiler aturan izin rute `COMPILED_ADMIN_ROUTE_RULES` yang secara otomatis mengekstrak seluruh hierarki menu induk dan sub-item dari `ADMIN_NAV_SECTIONS`.
   - Menambahkan registri rute tambahan `ADDITIONAL_ADMIN_ROUTES` untuk sub-halaman fungsional (seperti `/admin/counseling/early-warnings`, `/admin/facilities/library`, materi ajar, dll.).
   - Aturan diurutkan berdasarkan panjang URL menurun (`href.length` descending) agar sub-halaman yang lebih spesifik (`/admin/settings/roles`) diprioritaskan sebelum pencocokan rute induk (`/admin/settings`).
   - Mengekspor fungsi kanonis `getAdminRoutePermission(pathname: string): string | string[] | undefined`.

2. **`apps/web/src/app/(admin)/admin/layout.tsx`**:
   - Menggunakan `usePathname()` untuk membaca URL aktif.
   - Melakukan evaluasi izin modul:
     ```tsx
     const requiredPermission = getAdminRoutePermission(pathname || '/admin');
     const isModuleAuthorized = !requiredPermission || hasPermission(user, requiredPermission);
     ```
   - Jika modul diizinkan, `{children}` dirender secara normal.
   - Jika modul tidak diizinkan:
     - Area konten utama menampilkan komponen `<AccessDenied />` dengan pesan informatif: *"Akses Modul Terbatas: Akun Anda tidak memiliki izin untuk mengakses modul ini. Silakan gunakan menu navigasi di samping untuk menuju modul yang diizinkan."*
     - Sidebar (`AdminSidebar`) dan topbar (`AdminTopbar`) **tetap aktif dan dirender secara utuh**, memungkinkan pengguna berpindah ke modul yang sah tanpa tersesat.
     - Tidak ada pengalihan buta (*blind redirect*) yang membingungkan.
     - Shell guard tetap memblokir akun Siswa/Ortu secara menyeluruh sebelum masuk ke shell layout.

3. **`apps/web/src/test/admin-route-guard.test.tsx`**:
   - 18 pengujian Vitest otomatis yang mencakup:
     - Resolusi izin kanonis untuk setiap modul utama.
     - Akses Super Admin ke seluruh modul terproteksi (lulus tanpa halangan).
     - Akses Guru BK ke `/admin/counseling` (lulus) vs pengetikan langsung ke `/admin/settings/roles` (ditolak dengan AccessDenied di konten + sidebar tetap tampil).
     - Akses Staf Kesiswaan ke `/admin/student-affairs/points` (lulus) vs `/admin/cbt/questions` (ditolak).
     - Akses Staf Humas ke `/admin/public-relations` (lulus) vs `/admin/settings/roles` (ditolak).
     - Akses Pustakawan ke `/admin/library` (lulus).
     - Akses Staf Sarpras ke `/admin/facilities` (lulus) vs `/admin/counseling` (ditolak).
     - Akses peran personal Siswa ke `/admin/master` (ditolak di level shell dengan layar Akses Dibatasi tanpa sidebar).

---

## 4. Bukti Eksekusi Pengujian

### A. Backend PHPUnit Suites (Laravel)
```bash
php artisan test tests/Feature/Auth tests/Feature/Guest tests/Feature/Bk tests/Feature/Rpt tests/Feature/Sar tests/Feature/Std
```
**Hasil:**
- **Status:** PASS (Exit code 0)
- **Total Tests:** 370 tests
- **Total Assertions:** 1.731 assertions
- **Errors/Failures:** 0
- **Duration:** ~10,6 detik

Termasuk di dalamnya:
- `Tests\Feature\Auth\UatSeederTest`: 19 passed, 217 assertions.
- `Tests\Feature\Auth\RbacAuditDriftTest`: 5 passed, 18 assertions.
- `Tests\Feature\Guest\GuestTest`: 27 passed, 114 assertions.

### B. Frontend Vitest Suites (Next.js)
```bash
npm test -- src/test/admin-route-guard.test.tsx src/test/portal-admin-rbac.test.ts src/test/evaluator.test.ts src/test/read-only-roles.test.ts src/test/role-guard.test.tsx src/test/role-switcher.test.tsx src/test/rbac.test.ts
```
**Hasil:**
- **Status:** PASS (Exit code 0)
- **Total Test Files:** 7 passed (7 files)
- **Total Tests:** 84 passed (84 tests)
- **Duration:** ~1,35 detik

### C. TypeScript Typecheck
```bash
npm run typecheck
```
**Hasil:**
- **Status:** PASS (Exit code 0, no compilation errors)

---

## 5. Contoh Output CLI Drift Inspection

### A. Tampilan Terminal Interaktif (Human-Readable)
```text
$ php artisan rbac:audit-drift

  SISTLABUM - Laporan Audit Permission Drift (Read-Only)
  Database: testing (:memory:)
  Waktu: 2026-10-15 11:33:10 UTC

+-------------------+-----------------+----------------+--------------+-------------------+
| Role Code         | Status          | Canonical Miss | Leaks Found  | Custom Extensions |
+-------------------+-----------------+----------------+--------------+-------------------+
| super_admin       | CANONICAL_OK    | 0              | 0            | 0                 |
| principal         | CANONICAL_OK    | 0              | 0            | 0                 |
| counselor         | CANONICAL_OK    | 0              | 0            | 0                 |
| librarian         | CANONICAL_OK    | 0              | 0            | 0                 |
| facility_staff    | CANONICAL_OK    | 0              | 0            | 0                 |
| treasurer         | CANONICAL_OK    | 0              | 0            | 0                 |
| student_affairs   | CANONICAL_OK    | 0              | 0            | 0                 |
| public_relations  | CANONICAL_OK    | 0              | 0            | 0                 |
| admin_tu          | CANONICAL_OK    | 0              | 0            | 0                 |
| teacher           | CANONICAL_OK    | 0              | 0            | 0                 |
| homeroom          | CANONICAL_OK    | 0              | 0            | 0                 |
| student           | CANONICAL_OK    | 0              | 0            | 0                 |
| guardian          | CANONICAL_OK    | 0              | 0            | 0                 |
| ppdb_committee    | CANONICAL_OK    | 0              | 0            | 0                 |
+-------------------+-----------------+----------------+--------------+-------------------+

[PASS] Integritas RBAC kanonis terpelihara dengan baik. Tidak ditemukan kebocoran permission.
```

### B. Tampilan Structured Output (`--json`)
```json
{
  "timestamp": "2026-10-15T11:33:11+00:00",
  "status": "CLEAN",
  "summary": {
    "total_roles_audited": 16,
    "unknown_roles": 0,
    "roles_with_missing_permissions": 0,
    "roles_with_leaks": 0,
    "roles_with_custom_extensions": 0,
    "orphan_permissions": 0
  },
  "roles": [
    {
      "code": "student_affairs",
      "exists": true,
      "status": "CANONICAL_OK",
      "missing_canonical_permissions": [],
      "prohibited_leaks": [],
      "custom_extensions": []
    },
    {
      "code": "public_relations",
      "exists": true,
      "status": "CANONICAL_OK",
      "missing_canonical_permissions": [],
      "prohibited_leaks": [],
      "custom_extensions": []
    }
  ]
}
```

---

## 6. Analisis Risiko Residual & Batasan Lingkungan

1. **Status Database Staging Aktual (UNVERIFIED):**
   - Pengujian otomasi dan seeder berjalan pada lingkungan tes lokal (`:memory:` dan database SQLite pengujian).
   - Ketika deployment ke server staging Linux dilakukan, administrator staging perlu menjalankan satu kali perintah:
     ```bash
     php artisan rbac:audit-drift
     ```
     Jika server staging menggunakan database lama yang dibuat sebelum remediasi RBAC, jalankan `php artisan db:seed --class=RolePermissionSeeder` untuk menyelaraskan permission kanonis.
2. **Karantina Modul Non-UAT:**
   - Modul Keuangan (`treasurer`) tetap berstatus `PARTIAL` (pembayaran SPP online ditunda hingga Fase 4).
   - Aplikasi mobile Android (`apps/mobile`) tetap berada dalam karantina dan tidak terdampak perubahan ini.
3. **Integritas Working Tree:**
   - Seluruh perubahan lokal yang ada sebelumnya (181 file) tetap terpelihara utuh tanpa ada perintah pembersihan destruktif.

---

## 7. Kesimpulan & Rekomendasi

Semua target pada penugasan **POST-RBAC-HARDENING-01** telah tercapai dengan status **SELESAI (PASS)**:
- Persona Kesiswaan dan Humas siap digunakan untuk UAT staging.
- Command deteksi drift siap diintegrasikan pada health check atau cron staging.
- UX pengetikan langsung URL admin telah terlindungi secara konsisten dengan komponen `AccessDenied` tanpa mengorbankan navigasi shell.
