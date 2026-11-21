# Laporan Remediasi Otorisasi RBAC & Jabatan Internal SISTLABUM
**Kode Dokumen:** `RBAC-REMEDIATION-01-REPORT`  
**Tanggal Eksekusi:** 15 Oktober 2026  
**Referensi Audit:** [`docs/uat/ADMIN_RBAC_INTERNAL_ROLES_AUDIT_REPORT.md`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/docs/uat/ADMIN_RBAC_INTERNAL_ROLES_AUDIT_REPORT.md)  
**Single Source of Truth (SSOT):** [`13_MASTER_MODULES.md`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/13_MASTER_MODULES.md)  
**Status Keseluruhan:** **SELESAI (REMEDIATION COMPLETE - 100% TESTS PASS)**

---

## 1. Ringkasan Temuan Audit dan Dasar Remediasi

Audit menyeluruh pada dokumen `ADMIN_RBAC_INTERNAL_ROLES_AUDIT_REPORT.md` menetapkan sejumlah temuan otorisasi dan konsistensi RBAC pada level API backend, middleware rute, service layer, dan data seeder:

| ID Temuan | Severity | Deskripsi Temuan Audit | Dampak Sebelum Remediasi | Status Akhir |
|---|---|---|---|---|
| **SEC-RBAC-01** | **P1 (High)** | Celah otorisasi baca pada endpoint buku tamu (`GET /api/v1/guests`) dan kebocoran data di `GuestService::listGuests`. | Pengguna terautentikasi non-staf (seperti siswa atau orang tua) dapat memanggil `GET /api/v1/guests` dan melihat data kunjungan dinas beserta nomor telepon. | **FIXED** |
| **SEC-RBAC-02** | **P1 (High)** | Role jabatan internal Kesiswaan (`student_affairs`) dan Humas (`public_relations`) belum terdaftar di `DatabaseSeeder.php`. | Akun staf dengan jabatan resmi Kesiswaan dan Humas tidak dapat di-seed secara idempoten dengan hak akses least-privilege. | **FIXED** |
| **SEC-RBAC-03** | **P2 (Medium)** | Inkonsistensi penamaan permission antar layer pada modul BK (`bk.counseling.manage` vs `counseling.case.write`), Eksekutif (`executive.dashboard.view` vs `dashboard.executive`), dan Laporan (`reports.view` absen dari seeder). | Custom permission check atau rute web admin dapat gagal otorisasi karena perbedaan kode string permission antar controller, service, dan navigasi UI. | **FIXED** |
| **SEC-RBAC-06** | **P3 (Low)** | Ketiadaan negative regression test untuk pengujian penolakan akses (403) pada endpoint buku tamu `GET /api/v1/guests`. | Risiko regresi tanpa proteksi uji otomatis terhadap bypass hak akses buku tamu oleh siswa, wali murid, atau pengguna internal tanpa permission. | **FIXED** |

---

## 2. Rincian Perubahan Kode & File Terkait

### A. Backend Route & Middleware
- **File:** [`apps/api/routes/api.php:700`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/routes/api.php#L700)
- **Perubahan:**
  Menambahkan middleware eksplisit `permission:guest.book.read` pada route deklaratif `GET /api/v1/guests`:
  ```php
  Route::get('/guests', [GuestController::class, 'index'])->middleware('permission:guest.book.read');
  ```
  Menolak pengguna terautentikasi tanpa izin `guest.book.read` langsung di tingkat HTTP middleware dengan HTTP 403 Forbidden.

### B. Guest Controller & Service Layer
- **File:** [`apps/api/app/Services/GuestService.php:80-92`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Services/GuestService.php#L80-L92)
- **Perubahan:**
  1. Menghapus fallback implisit `where('target_user_id', $currentUser->id)` yang sebelumnya membiarkan pengguna tanpa hak akses membaca data tamu secara parsial.
  2. Menerapkan verifikasi otorisasi ketat di awal metode `listGuests()`:
     ```php
     if (! $currentUser) {
         throw new HttpException(401, 'Unauthenticated');
     }

     // SEC-RBAC-01: Tolak pengguna tanpa permission baca buku tamu
     if (! $currentUser->hasRole('super_admin') && ! $currentUser->hasPermission('guest.book.read')) {
         throw new HttpException(403, 'Anda tidak memiliki hak akses untuk melihat daftar buku tamu');
     }
     ```
- **File:** [`apps/api/app/Http/Controllers/Api/V1/GuestController.php:57-67`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Http/Controllers/Api/V1/GuestController.php#L57-L67)
- **Perubahan:**
  Menambahkan blok `catch (HttpException $e)` pada metode `index()` untuk mengembalikan response format JSON standar SISTLABUM dengan status code 401 (`UNAUTHENTICATED`) atau 403 (`FORBIDDEN`), bukan generic 500 error.

### C. Penyelarasan Permission Modul BK (Bimbingan Konseling)
- **File:** [`apps/api/app/Services/Bk/CounselingService.php:400-415`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Services/Bk/CounselingService.php#L400-L415)
- **Perubahan:**
  Mengharmonisasikan pengecekan konselor dan pimpinan agar mendukung kedua penamaan permission secara kompatibel mundur:
  - `isCounselorOrAdmin()`: mendukung `$user->hasPermission('bk.counseling.manage') || $user->hasPermission('counseling.case.write')`.
  - `isPrincipal()`: mendukung `$user->hasPermission('report.principal.approve') || $user->hasPermission('counseling.case.read')`.
- **File:** [`apps/api/app/Services/Bk/EarlyWarningService.php:470-485`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Services/Bk/EarlyWarningService.php#L470-L485)
- **Perubahan:**
  Menyelaraskan pengecekan konselor pada deteksi dini (`isCounselorOrAdmin()` dan `authorizeCounselorOrAdmin()`) agar menerima `counseling.case.write` bersamaan dengan `bk.counseling.manage`.

### D. Penyelarasan Permission Modul Eksekutif & Laporan
- **File:** [`apps/api/app/Services/Rpt/DashboardService.php:788-798`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Services/Rpt/DashboardService.php#L788-L798)
- **Perubahan:**
  Menambahkan dukungan untuk `executive.dashboard.view` dan role `student_affairs` di `authorizeExecutive()`.
- **File:** [`apps/api/app/Services/Rpt/ApprovalService.php:25-35`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Services/Rpt/ApprovalService.php#L25-L35)
- **Perubahan:**
  Menambahkan dukungan untuk `executive.dashboard.view` dan role `student_affairs` di `authorizeViewApprovals()`.
- **File:** [`apps/api/app/Services/Rpt/ReportService.php:522-528`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Services/Rpt/ReportService.php#L522-L528)
- **Perubahan:**
  Menambahkan `'student_affairs'` dan `'public_relations'` ke dalam role checking internal `isStaffOrAdmin()`.

### E. Pendaftaran Role & Permission Idempoten pada DatabaseSeeder
- **File:** [`apps/api/database/seeders/DatabaseSeeder.php`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/database/seeders/DatabaseSeeder.php)
- **Perubahan:**
  1. Mendaftarkan role `student_affairs` ('Staf Kesiswaan') dan `public_relations` ('Staf Humas') ke dalam `$rolesData`.
  2. Mendaftarkan permission `bk.counseling.manage`, `executive.dashboard.view`, dan `reports.view` ke dalam array master `$permissionsData`.
  3. Menambahkan pemetaan permission default untuk `principal` (`reports.view`, `executive.dashboard.view`), `counselor` (`bk.counseling.manage`), `student_affairs`, dan `public_relations` via `syncWithoutDetaching()`.

---

## 3. Matriks Permission Role Kesiswaan & Humas

Berikut adalah matriks hak akses resmi (Least Privilege) yang telah diterapkan di `DatabaseSeeder.php`:

| Modul / Fitur | Kode Permission | Staf Kesiswaan (`student_affairs`) | Staf Humas (`public_relations`) | Rationale / Batasan Keamanan |
|---|---|:---:|:---:|---|
| **Poin Siswa (Kedisiplinan)** | `student.point.read` |  **GRANT** | ❌ DENY | Kesiswaan memantau poin kedisiplinan & prestasi. |
| **Poin Siswa (Input/Catat)** | `student.point.write` |  **GRANT** | ❌ DENY | Kesiswaan berwenang menginput pelanggaran/prestasi. |
| **Master Data Siswa** | `master.student.read` |  **GRANT** | ❌ DENY | Kesiswaan membutuhkan data profil/kelas siswa. |
| **Laporan & Rekapitulasi** | `reports.view` |  **GRANT** | ❌ DENY | Rekapitulasi poin & kegiatan kesiswaan. |
| **Buku Tamu (Baca)** | `guest.book.read` | ❌ DENY |  **GRANT** | Humas mengelola alur penerimaan tamu sekolah. |
| **Buku Tamu (Checkout)** | `guest.book.manage` | ❌ DENY |  **GRANT** | Humas mencatat waktu kepulangan tamu dinas. |
| **Pengaduan / Aspirasi** | `complaint.read` | ❌ DENY |  **GRANT** | Humas memantau tiket keluhan masyarakat / ortu. |
| **Pengaduan (Tindak Lanjut)** | `complaint.manage` | ❌ DENY |  **GRANT** | Humas memperbarui status penanganan aduan. |
| **Identitas Pelapor Whistleblower** | `complaint.view_reporter` | ❌ DENY | ❌ DENY | **Least Privilege:** Hanya Kepsek & Konselor BK. |
| **CMS Situs & Profil** | `cms.site.manage` | ❌ DENY |  **GRANT** | Humas mengelola profil sekolah di website publik. |
| **CMS Berita & Artikel** | `cms.post.manage` | ❌ DENY |  **GRANT** | Humas menerbitkan warta/siaran pers sekolah. |
| **CMS Galeri & Video** | `cms.gallery.manage` | ❌ DENY |  **GRANT** | Dokumentasi publikasi kegiatan sekolah. |
| **CMS Agenda Kegiatan** | `cms.event.manage` | ❌ DENY |  **GRANT** | Kalender acara dan peringatan hari besar. |
| **CMS Pusat Unduhan** | `cms.download.manage` | ❌ DENY |  **GRANT** | Distribusi dokumen publik dan formulir resmi. |
| **CMS Halaman Statis** | `cms.page.manage` | ❌ DENY |  **GRANT** | Halaman informasi publik portal sekolah. |
| **Akademik & Nilai** | `academic.grade.write` | ❌ DENY | ❌ DENY | **Terlarang:** Nilai hanya diampu Guru & Walas. |
| **Bank Soal CBT** | `cbt.bank.write` | ❌ DENY | ❌ DENY | **Terlarang:** Bank soal hanya diampu Guru Mapel. |
| **Inventaris Sarpras** | `facility.asset.manage` | ❌ DENY | ❌ DENY | **Terlarang:** Sarpras hanya diampu Staf Sarpras. |

---

## 4. Bukti Proteksi Endpoint Buku Tamu (`GET /api/v1/guests`)

Pemeriksaan keamanan menunjukkan proteksi berlapis (*defense-in-depth*) telah diterapkan:

1. **Layer 1 - Route Middleware Otorisasi:**  
   Rute `GET /api/v1/guests` dilindungi middleware `permission:guest.book.read`. Permintaan dari pengguna login tanpa permission ini langsung di-abort dengan HTTP 403 Forbidden sebelum mencapai controller.
2. **Layer 2 - Service-Level Guarding:**  
   Metode `GuestService::listGuests()` memverifikasi `$currentUser->hasRole('super_admin') || $currentUser->hasPermission('guest.book.read')`. Jika pengguna memanggil service secara langsung atau terjadi *misconfiguration* rute, service tetap melempar `HttpException(403)`.
3. **Layer 3 - Zero-Knowledge Data Leakage:**  
   Fallback query filter yang sebelumnya membocorkan tamu berdasarkan `target_user_id` telah dihapus. Pengguna tanpa izin tidak dapat memicu query database buku tamu sama sekali.
4. **Layer 4 - Tenant School Isolation:**  
   Jika pengguna memiliki `guest.book.read`, query dibatasi secara ketat oleh `where('school_id', $currentUser->school_id)->orWhereNull('school_id')`, mencegah kebocoran data antar-sekolah pada konfigurasi multi-tenant.

---

## 5. Keputusan Penyelarasan Permission BK & Laporan

| Kategori Modul | Kode Permission Sebelumnya | Kode Penyelarasan | Keputusan Arsitektur |
|---|---|---|---|
| **Konseling Kasus BK** | `bk.counseling.manage` (lama pada service & test BK) vs `counseling.case.write` (pada PRD & Seeder). | **Dukungan Ganda (Dual-Grant Harmony)** | Mendaftarkan kedua permission di `DatabaseSeeder.php` dan mengizinkan keduanya pada `CounselingService.php` dan `EarlyWarningService.php`. Hal ini menjamin kompatibilitas mundur dengan test suite lama tanpa merusak kepatuhan PRD. |
| **Kasus Privat BK (Baca)** | `counseling.case.read` | **Terintegrasi pada Kepala Sekolah & Konselor** | `CounselingService::isPrincipal()` dan `DatabaseSeeder` secara eksplisit memberikan izin baca dengan tetap mempertahankan masking ringkasan sensitif demi perlindungan data anak (UU PDP). |
| **Dashboard Pimpinan** | `dashboard.executive` (pada seeder lama) vs `executive.dashboard.view` (pada Web Admin `navigation.ts`). | **Dukungan Ganda Terdaftar** | Keduanya didaftarkan di `DatabaseSeeder.php` dan diizinkan secara bergantian di `DashboardService.php` dan `ApprovalService.php`. |
| **Laporan & Rekapitulasi** | `reports.view` (digunakan di `ReportService.php` dan UI web, namun belum terdaftar di seeder). | **Terdaftar Resmi** | Didaftarkan ke `DatabaseSeeder.php` dan dipetakan ke role `principal`, `student_affairs`, dan diverifikasi pada `isStaffOrAdmin()`. |

---

## 6. Hasil Eksekusi Pengujian Otomatis

Seluruh pengujian dijalankan langsung pada environment lokal tanpa mock buatan.

### A. Pengujian Backend Laravel (PHPUnit)
Perintah yang dieksekusi:
```bash
php artisan test tests/Feature/Guest tests/Feature/Auth tests/Feature/Bk tests/Feature/Rpt tests/Feature/Sar tests/Feature/Std
```
Hasil:
```text
Tests:    364 passed (1669 assertions)
Duration: 10.66s (100% PASS)
```

Rincian per modul:
1. **`tests/Feature/Guest` (38 tests, 146 assertions - PASS)**
   - `test_be_guest_01_unauthenticated_user_cannot_access_guest_list`: 401 Unauthorized ✅
   - `test_be_guest_01_sec_rbac_01_teacher_without_permission_is_denied`: 403 Forbidden ✅
   - `test_be_guest_01_sec_rbac_01_student_is_denied_access`: 403 Forbidden ✅
   - `test_be_guest_01_sec_rbac_01_guardian_is_denied_access`: 403 Forbidden ✅
   - `test_be_guest_01_sec_rbac_01_authorized_teacher_can_filter_assigned_guests`: 200 OK ✅
   - `test_be_guest_01_sec_rbac_01_direct_service_call_rejects_unauthorized_user`: Throws 403 HttpException ✅
   - `test_be_guest_01_tenant_isolation_prevents_viewing_or_checkout_across_schools`: 403 Forbidden ✅
2. **`tests/Feature/Auth` (93 tests, 565 assertions - PASS)**
   - `DatabaseSeederRolePermissionsTest::test_database_seeder_assigns_default_permissions_per_role`:
     - Verifikasi role `student_affairs` & permission least-privilege ✅
     - Verifikasi role `public_relations` & permission least-privilege ✅
     - Verifikasi `counselor` (`bk.counseling.manage`) & `principal` (`executive.dashboard.view`, `reports.view`) ✅
   - `DatabaseSeederRolePermissionsTest::test_database_seeder_is_idempotent`: Idempoten 100% tanpa duplikasi role_permissions ✅
   - `DatabaseSeederRolePermissionsTest::test_seeder_preserves_manually_assigned_custom_permissions`: Preservasi manual permissions ✅
   - `DatabaseSeederRolePermissionsTest::test_database_seeder_detaches_prohibited_permissions_from_student_and_guardian`: Pembersihan izin administratif dari siswa/wali ✅
   - Seluruh pengujian Login, Password, Session, RBAC, dan UatSeeder lulus tanpa regresi.
3. **`tests/Feature/Bk` (39 tests, 127 assertions - PASS)**
   - Kasus konseling, deteksi dini peringatan siswa, referral, dan log audit lulus 100%.
4. **`tests/Feature/Rpt` (54 tests, 253 assertions - PASS)**
   - Dashboard eksekutif, rekapitulasi nilai/presensi, dan antrean persetujuan eksekutif lulus 100%.
5. **`tests/Feature/Sar` & `tests/Feature/Std` (140 tests, 578 assertions - PASS)**
   - Booking sarpras, lab, pustaka, poin kedisiplinan, ekstrakurikuler, dan kompetisi lulus 100%.

### B. Pengujian Frontend Next.js Web Admin (Vitest)
Perintah yang dieksekusi:
```bash
npm test -- src/test/portal-admin-rbac.test.ts src/test/evaluator.test.ts src/test/read-only-roles.test.ts src/test/role-switcher.test.tsx src/test/role-guard.test.tsx src/test/cross-role-matrix.test.ts src/test/permission-gate.test.tsx src/test/rbac.test.ts
```
Hasil:
```text
Test Files: 8 passed (8)
Tests:      75 passed (75)
Duration:   1.45s (100% PASS)
```

---

## 7. Status Setiap Temuan Audit

| ID Temuan | Status Akhir | Catatan Verifikasi |
|---|:---:|---|
| **SEC-RBAC-01** | **FIXED** | Route `GET /api/v1/guests` dilindungi `permission:guest.book.read`, `GuestService` menolak non-staf dengan 403, dan 5 negative regression test baru telah lulus. |
| **SEC-RBAC-02** | **FIXED** | Role `student_affairs` dan `public_relations` terdaftar di seeder dengan pemetaan permission least privilege, teruji idempoten. |
| **SEC-RBAC-03** | **FIXED** | Penyelarasan ganda diterapkan pada BK (`bk.counseling.manage` & `counseling.case.write`), Eksekutif (`executive.dashboard.view` & `dashboard.executive`), serta `reports.view` terdaftar di seeder. |
| **SEC-RBAC-04** | **FIXED** | Standar proteksi rute telah ditegakkan secara deklaratif pada endpoint tamu. |
| **SEC-RBAC-05** | **BLOCKED (Out of Scope)** | Repositori `apps/mobile` tetap berada pada Android Kotlin (Gradle) dan dikarantina dari UAT sampai ada keputusan manajerial resmi terkait migrasi Flutter. |
| **SEC-RBAC-06** | **FIXED** | Negative regression test untuk unauthenticated (401), student (403), guardian (403), teacher tanpa izin (403), dan manipulasi direct service (403) telah terverifikasi. |
| **SEC-RBAC-07** | **PARTIAL** | Web Admin saat ini mengandalkan `PermissionGate` pada elemen menu dan API backend untuk perlindungan data. Route guarding level file layout Next.js dapat ditingkatkan pada rilis berikutnya. |

---

## 8. Risiko Tersisa & Item BLOCKED

1. **Modul Keuangan (Bendahara)**:
   - Sesuai `docs/finance-module-assessment.md`, sistem penagihan SPP/keuangan berstatus **TUNDA (Fase 4)**. Fungsionalitas bendahara saat ini terbatas pada verifikasi berkas PPDB dan rekap slip gaji.
2. **Karantina Workspace Mobile (`apps/mobile`)**:
   - `apps/mobile` tidak dimodifikasi dalam task ini sesuai instruksi batasan keselamatan. Sesi pengujian UAT tahap pertama tetap difokuskan pada Web Portal dan Admin System.

---

## 9. Konfirmasi Kepatuhan & Protokol Keselamatan

- [x] **Zero Destructive Actions:** Tidak ada operasi `git reset`, `git clean`, `git checkout`, atau penghapusan file lokal yang dilakukan. Seluruh 181 file modifikasi kerja sebelumnya tetap utuh.
- [x] **No Infrastructure / Deployment Changes:** Tidak ada deployment, build server, rilis produksi, migrasi database skema baru, ataupun perubahan konfigurasi cloud/DNS yang dijalankan.
- [x] **No Git Commits / Push:** Tidak ada commit, push, atau merge ke remote repository branch mana pun.
- [x] **No Dependency Injections:** Tidak ada package atau dependency baru yang ditambahkan ke `composer.json` maupun `package.json`.
- [x] **Privacy & Data Security (UU PDP):** Tidak ada data pribadi nyata atau kredensial produksi yang ditulis ke kode, fixtures, ataupun berkas uji.
