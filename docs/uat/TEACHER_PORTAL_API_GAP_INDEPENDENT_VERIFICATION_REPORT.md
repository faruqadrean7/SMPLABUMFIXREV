# LAPORAN AUDIT INDEPENDEN PASCA-REMEDIASI & REGRESI LINTAS PORTAL (SISTLABUM)

**Dokumen**: `docs/uat/TEACHER_PORTAL_API_GAP_INDEPENDENT_VERIFICATION_REPORT.md`  
**Tanggal Audit**: 24 Oktober 2026  
**Auditor**: Independent Principal Engineer & Security Auditor  
**Branch Diperiksa**: `fix/teacher-gaps` (HEAD: `ca7ed5b`)  
**Base Branch Terkait**: `develop` (`7a6c378`), merge commit `ca7ed5b`  
**Dokumen Acuan**: `docs/uat/TEACHER_PORTAL_API_GAP_REMEDIATION_REPORT.md`  
**Status Audit**: ⚠️ **PERLU PENYELARASAN FRONTEND UI SEBELUM RILIS PENUH** (Backend Lulus Penuh, Frontend UI Perlu Integrasi Komponen)  

---

## 1. Branch, HEAD, dan Kondisi Working Tree

### 1.1 Status Git & Relasi Cabang
- **Active Branch**: `fix/teacher-gaps`
- **Current HEAD**: `ca7ed5b` (*merge: integrate portal application updates from origin/develop*)
- **Upstream Merge Parent 1**: `008439c` (*docs(audit): laporan pemenuhan kesenjangan portal guru*)
- **Upstream Merge Parent 2**: `7a6c378` (*origin/develop / develop: feat: add portal application with guardian context, LMS integration, and teacher/parent dashboards*)
- **Kondisi Branch**: Sinkron dengan upstream `origin/develop`, tanpa konflik merge.

### 1.2 Status Working Tree
Semua perubahan lokal pengembang tetap dipertahankan (*uncommitted*), tanpa adanya eksekusi perintah Git destruktif (`git reset --hard`, `git clean -fd` dilarang):

```
Changes to be committed:
  renamed:    apps/web/src/app/(public)/ppdb/daftar/page.tsx -> apps/web/src/app/(ppdb)/ppdb/daftar/page.tsx
  renamed:    apps/web/src/app/(public)/ppdb/dashboard/page.tsx -> apps/web/src/app/(ppdb)/ppdb/dashboard/page.tsx
  renamed:    apps/web/src/app/(public)/ppdb/masuk/page.tsx -> apps/web/src/app/(ppdb)/ppdb/masuk/page.tsx
  renamed:    apps/web/src/app/(public)/ppdb/page.tsx -> apps/web/src/app/(ppdb)/ppdb/page.tsx

Changes not staged for commit:
  modified:   apps/api/app/Http/Controllers/Api/V1/MeTeacherController.php
  modified:   apps/api/routes/api.php
  modified:   apps/portal/src/lib/lms-api.ts
  modified:   apps/web/src/components/shell/public/PublicHeader.tsx
  modified:   apps/web/src/features/portal/guru/guru-api.ts
  modified:   apps/web/src/features/portal/guru/types.ts
  modified:   apps/web/src/test/public-shell.test.tsx

Untracked files:
  apps/api/tests/Feature/Teacher/TeacherGapRemediationFeatureTest.php
  apps/web/src/app/(ppdb)/layout.tsx
  apps/web/src/test/portal-guru-gap-remediation.test.ts
  docs/uat/TEACHER_PORTAL_API_GAP_INDEPENDENT_VERIFICATION_REPORT.md
  docs/uat/TEACHER_PORTAL_API_GAP_REMEDIATION_REPORT.md
```

*Catatan perubahan di luar cakupan guru*: Perubahan pada folder `(ppdb)` dan `PublicHeader.tsx` merupakan perbaikan isolasi header publik PPDB Online yang sah dari sesi sebelumnya dan tetap dipertahankan secara utuh.

### 1.3 Analisis Kebutuhan `apps/portal/src/lib/lms-api.ts`
- **Konteks**: Berkas `apps/portal/src/lib/lms-api.ts` berasal dari upstream `develop` (commit `7a6c378`), yang kemudian dilengkapi dengan method Fitur A - E pada cabang ini.
- **Temuan Auditor**: 
  1. Berkas ini mutlak diperlukan di `apps/portal` agar kompilasi TypeScript (`npx tsc --noEmit`) berhasil tanpa error, karena 6 modul halaman di `apps/portal` (`cbt`, `nilai`, `tugas`, `jurnal`, `materi`, `siswa`) mengimpor `{ lmsApi }` dari `@/lib/api-client`.
  2. Namun secara arsitektur monorepo SISTLABUM (lihat `docs/audit/AUDIT_READINESS_2026-10-22.md`), aplikasi kanonis portal sekolah adalah `apps/web` (Next.js 14). Keberadaan `apps/portal` (Next.js 16) merupakan aplikasi sekunder/eksperimental yang belum masuk ke alur deployment resmi Docker Compose.
  3. Kesimpulan: `lms-api.ts` berfungsi efektif sebagai API client di `apps/portal`, tetapi aplikasi utama pengguna tetaplah `apps/web`.

---

## 2. Perbandingan Klaim Laporan Remediasi vs Fakta Audit Aktual

| Indikator Pengujian | Klaim Laporan Remediasi | Hasil Audit Aktual Independen | Verifikasi & Bukti |
|---|---|---|---|
| **Backend Remediation Tests** | 15 / 15 Passed | **15 / 15 Passed** (47 assertions) | ✅ **Terverifikasi Akurat** (`TeacherGapRemediationFeatureTest.php`, durasi: 1.34s) |
| **Teacher Feature Suite** | 74 / 74 Passed | **74 / 74 Passed** (327 assertions) | ✅ **Terverifikasi Akurat** (`tests/Feature/Teacher`, durasi: 4.85s) |
| **Full Backend Suite** | 1.265 / 1.265 Passed | **1.265 / 1.265 Passed** (8.227 assertions) | ✅ **Terverifikasi Akurat** (`php artisan test`, 0 failure, 0 error, durasi: 39.10s) |
| **Frontend Web Remediation** | 8 / 8 Passed | **8 / 8 Passed** | ✅ **Terverifikasi Akurat** (`portal-guru-gap-remediation.test.ts`, durasi: 0.74s) |
| **Full Frontend Web Suite** | 795 / 795 Passed | **803 / 803 Passed** (68 test files) | ⚠️ **Klaim Laporan Berisi Angka Lama** (Laporan mencatat 795 karena belum memasukkan 8 test baru di file ke-68. Aktualnya adalah **803 passed**). |
| **Typecheck `apps/web`** | 0 Errors | **0 Errors** (Clean) | ✅ **Terverifikasi Akurat** (`tsc --noEmit`) |
| **Typecheck `apps/portal`** | 0 Errors | **0 Errors** (Clean) | ✅ **Terverifikasi Akurat** (`tsc --noEmit`) |
| **Laravel Pint** | Lulus | **Lulus** (Zero style violations) | ✅ **Terverifikasi Akurat** (`./vendor/bin/pint --test`) |
| **Integrasi UI Web E2E** | Diklaim "Terintegrasi" | **Sebagian (API Client Siap, UI Belum Dimount)** | ⚠️ **Temuan P1**: Method API ada di `guru-api.ts`, namun halaman `apps/web/src/app/(portal)/portal/guru/` belum memiliki form/tombol visual untuk Fitur A, B, C. |

---

## 3. Matriks Status Endpoint API & Lima Kelompok Fitur

Status Klasifikasi:
- `VERIFIED_BY_CODE`: Kode route, auth, controller, dan query basis data telah ditelaah dan diverifikasi.
- `TEST_PASS`: Lulus pengujian otomatis feature/unit test.
- `RUNTIME_VERIFIED`: Terverifikasi berjalan pada antarmuka pengguna web riil.
- `NOT_RUN`: Belum dieksekusi pada runtime browser karena ketiadaan komponen visual pemanggil.
- `FAIL`: Gagal validasi atau pengujian.

| ID | Fitur & Endpoint | Method | Otorisasi & Scoping | Status Verifikasi | Catatan Auditor |
|---|---|---|---|---|---|
| **A1** | `me/teacher/homeroom/leave-requests` | `GET` | `auth:sanctum`, Scope: Wali Kelas (`homeroom_teacher_id`) | `VERIFIED_BY_CODE`<br>`TEST_PASS` | Filter kelas di luar binaan ditolak HTTP 403. Paginasi aman. |
| **A2** | `me/teacher/homeroom/leave-requests/{id}/decision` | `PATCH` | `auth:sanctum`, Scope: Wali Kelas siswa terkait | `VERIFIED_BY_CODE`<br>`TEST_PASS` | Keputusan final tidak bisa diubah (HTTP 422). Otomatis menyinkronkan presensi harian siswa (`source = 'gerbang'`). |
| **B1** | `me/teacher/student-points` | `POST` | `auth:sanctum`, Scope: Guru pengampu atau walas | `VERIFIED_BY_CODE`<br>`TEST_PASS` | `verifyStudentAccess` menolak siswa kelas lain (HTTP 403). Mencatat `AuditLog`. |
| **B2** | `me/teacher/counseling-referrals` | `POST` | `auth:sanctum`, Scope: Guru pengampu atau walas | `VERIFIED_BY_CODE`<br>`TEST_PASS` | Mencegah rujukan ganda pending (HTTP 422). Terhubung ke tabel `counseling_referrals`. |
| **C1** | `me/teacher/facility-bookings` | `GET` | `auth:sanctum`, Scope: Peminjam (`requested_by`) | `VERIFIED_BY_CODE`<br>`TEST_PASS` | Guru hanya melihat daftar booking miliknya sendiri. |
| **C2** | `me/teacher/facility-bookings` | `POST` | `auth:sanctum`, Validasi overlap rentang waktu | `VERIFIED_BY_CODE`<br>`TEST_PASS` | Bentrok jadwal menghasilkan HTTP 409 CONFLICT. `requested_by` dijamin dari token. |
| **C3** | `me/teacher/tickets` | `POST` | `auth:sanctum`, `reporter_id` dari sesi guru | `VERIFIED_BY_CODE`<br>`TEST_PASS` | Nomor tiket otomatis `TKT-YYYYMMDD-XXXX`. Mencatat audit log. |
| **D1** | `me/teacher/questions` | `GET` | `auth:sanctum`, Scope: Mapel `TeachingAssignment` / Bank milik guru | `VERIFIED_BY_CODE`<br>`TEST_PASS` | Mengakses mapel di luar penugasan menghasilkan HTTP 403. |
| **D2** | `me/teacher/questions` | `POST` | `auth:sanctum`, Scope: Mapel pengampu | `VERIFIED_BY_CODE`<br>`TEST_PASS` | Validasi tipe soal, opsi, pembobotan, dan relasi bank soal. |
| **E1** | `me/teacher/grades/lock` | `POST` | `auth:sanctum`, Scope: Mapel & kelas yang diampu guru | `VERIFIED_BY_CODE`<br>`TEST_PASS` | Mengunci ledger (`locked_at = now()`). Menolak pengubahan nilai massal via `updateBulkGrades` (HTTP 422). |

*Catatan untuk kolom `RUNTIME_VERIFIED`*: Seluruh endpoint di atas saat ini berstatus **`NOT_RUN` pada browser UI `apps/web`** karena halaman UI penampil (`apps/web/src/app/(portal)/portal/guru/`) belum memasang form/tombol pemanggil method API client tersebut.

---

## 4. Hasil Pengujian Keamanan Khusus & Anti-IDOR

### 4.1 Perizinan Siswa dan Presensi (Fitur A)
- **Otorisasi Wali Kelas**: Terverifikasi ketat. Jika guru A mengajar siswa di kelas 7-A tetapi bukan wali kelasnya, permintaan `PATCH /homeroom/leave-requests/{id}/decision` ditolak dengan HTTP 403.
- **Imutabilitas**: Setelah status perizinan menjadi `disetujui` atau `ditolak`, panggilan ulang dengan payload berbeda ditolak dengan HTTP 422 (`Permohonan izin ini sudah diputuskan sebelumnya...`).
- **Efek Samping Presensi**: Panggilan ke `AcademicAttendanceService::decideLeaveRequest` terbukti membuat rekaman `Attendance` dengan `status` yang sesuai (`sakit` atau `izin`) dan `source = 'gerbang'`.
- **Catatan Auditor (P2)**: Belum terdapat klausa `lockForUpdate()` pada entitas `LeaveRequest`. Pada skenario konkurensi ekstrem di mana wali kelas mengklik dua kali secara simultan dari dua tab, ada celah *race condition* sebelum transaksi mengunci baris.

### 4.2 Poin Karakter dan BK (Fitur B)
- **Verifikasi Cakupan Siswa**: Method `verifyStudentAccess` menolak guru yang mencoba mencatat poin atau merujuk siswa di luar kelas yang diajar atau kelas binaannya (lulus uji IDOR dengan HTTP 403).
- **Integritas Rujukan BK**: Mencegah spam rujukan identik yang berstatus `pending` untuk siswa yang sama dengan pesan HTTP 422.
- **Catatan Auditor (P2)**: Endpoint `storeStudentPoint` belum menerapkan *idempotency window*. Jika guru melakukan *double-click* cepat pada tombol submit, sistem akan membuat dua baris poin yang sama dalam hitungan detik.

### 4.3 Fasilitas dan Tiket Sarpras (Fitur C)
- **Deteksi Bentrok Jadwal**: Logika `Booking::where(...)->where('start_at', '<', $endAt)->where('end_at', '>', $startAt)` terbukti menggagalkan reservasi yang bertabrakan waktu dengan HTTP 409 CONFLICT.
- **Anti-Spoofing**: Kolom `requested_by` dan `reporter_id` diambil langsung dari `$request->user()->id`, sehingga pengguna tidak dapat memalsukan identitas pemohon.
- **Catatan Auditor (P3)**: Nomor tiket dihasilkan dengan `TKT-YYYYMMDD-` ditambah 4 karakter acak (`Str::random(4)`). Dalam skala ratusan tiket harian, terdapat risiko kolisi *unique constraint* jika tidak disertai mekanisme retry.

### 4.4 Bank Soal Guru (Fitur D)
- **Isolasi Mata Pelajaran**: Guru Matematika tidak dapat melihat maupun membuat soal untuk mata pelajaran IPA jika tidak memiliki `TeachingAssignment` (terverifikasi menghasilkan HTTP 403).
- **Kebocoran Kunci Jawaban**: Endpoint `GET /api/v1/me/teacher/questions` memang mengembalikan `is_correct` untuk kebutuhan authoring guru. Namun, endpoint ini berada di bawah prefix `me/teacher` yang terlindungi oleh pengecekan profil guru (`getTeacher`). Siswa yang mengakses endpoint ini langsung ditolak dengan HTTP 403, sehingga tidak ada kebocoran kunci jawaban ke siswa.

### 4.5 Penguncian Ledger Nilai Guru (Fitur E)
- **Proteksi pada Portal Guru**: Terverifikasi bahwa `PUT /api/v1/me/teacher/grades/bulk` memindai apakah terdapat nilai dengan `locked_at !== null` pada siswa dan komponen terkait. Jika ditemukan, operasi ditolak dengan HTTP 422.
- **Temuan Celah Bypass (P2)**: 
  Pada jalur umum/admin yang dikelola oleh `GradeService.php` (`storeGrade` dan `storeBulkGrades` pada endpoint `/api/v1/grades`), validasi yang dilakukan **hanya memeriksa `$period->status === 'locked'`**, dan **TIDAK memeriksa `Grade.locked_at` individual**. Akibatnya, jika ada pengguna dengan permission `academic.grade.input` (seperti kurikulum/admin) mengakses endpoint `/api/v1/grades`, penguncian mandiri yang dilakukan guru dapat tertimpa tanpa peringatan.

---

## 5. Hasil Pengujian Regresi Lintas Portal

Berdasarkan pengujian suite terintegrasi pada SQLite in-memory test database:

| No | Skenario Regresi Lintas Portal | Hasil Aktual | Status |
|---|---|---|---|
| 1 | **Admin vs Guru Scope**: Admin mengelola data master; guru hanya melihat data kelas binaan & penugasan. | Kueri guru terfilter via `TeachingAssignment` dan `SchoolClass::homeroom_teacher_id`. | ✅ **LULUS** |
| 2 | **Wali Kelas vs Presensi**: Wali kelas menyetujui izin; presensi siswa otomatis tercatat pada tanggal izin. | Status presensi tersinkronisasi menjadi `sakit`/`izin` dengan `source = 'gerbang'`. | ✅ **LULUS** |
| 3 | **Guru vs Kesiswaan Poin**: Guru mencatat poin siswa; data tersimpan di `student_points` dan tercatat di audit log. | Poin terakumulasi di tabel kesiswaan, terisolasi dari siswa luar kelas. | ✅ **LULUS** |
| 4 | **Guru vs Konselor BK**: Guru merujuk siswa ke BK; rujukan tersimpan dengan status `pending`. | Rujukan masuk ke antrean BK, duplikasi pending ditolak HTTP 422. | ✅ **LULUS** |
| 5 | **Guru vs Staf Sarpras Booking**: Guru membuat peminjaman; status awal `menunggu` untuk ditinjau sarpras. | Booking terdaftar dengan `requested_by` sah, bentrok ditolak HTTP 409. | ✅ **LULUS** |
| 6 | **Guru vs Helpdesk Tiket**: Guru melaporkan kendala alat; tiket dibuat dengan identitas pelapor tepercaya. | Tiket berstatus `pending` dengan nomor tiket unik dan keterkaitan aset. | ✅ **LULUS** |
| 7 | **Guru vs CBT Siswa**: Guru membuat soal di bank soal; siswa tidak dapat mengakses kunci jawaban via API guru. | Siswa mendapat HTTP 403 pada endpoint guru; player ujian siswa memakai whitelist. | ✅ **LULUS** |
| 8 | **Penguncian Ledger vs Modifikasi Nilai**: Guru mengunci ledger; rute `me/teacher/grades/bulk` menolak modifikasi. | HTTP 422 dikembalikan saat mencoba mengedit nilai pada ledger yang dikunci. | ✅ **LULUS** |
| 9 | **Kompatibilitas Endpoint Lama**: Endpoint portal siswa, ortu, dan admin lama tetap berfungsi normal. | 1.265 backend tests dan 803 frontend tests lulus tanpa regresi. | ✅ **LULUS** |
| 10 | **Isolasi Siswa & Ortu**: Siswa dan orang tua tidak dapat mengakses endpoint guru atau data siswa lain. | Akses silang menghasilkan HTTP 403 / 404 sesuai aturan kepemilikan. | ✅ **LULUS** |

---

## 6. Bukti Eksekusi Test Aktual

### Test 1: Backend Gap Remediation Feature Test
- **Perintah**: `cd apps/api && php artisan test tests/Feature/Teacher/TeacherGapRemediationFeatureTest.php`
- **Lingkungan**: Local PHP 8.5.11, SQLite In-Memory, Laravel 11
- **Exit Code**: `0`
- **Output**:
  ```json
  {"tool":"phpunit","result":"passed","tests":15,"passed":15,"assertions":47,"duration_ms":1340}
  ```

### Test 2: Seluruh Feature Test Guru (`tests/Feature/Teacher`)
- **Perintah**: `cd apps/api && php artisan test tests/Feature/Teacher`
- **Exit Code**: `0`
- **Output**:
  ```json
  {"tool":"phpunit","result":"passed","tests":74,"passed":74,"assertions":327,"duration_ms":4847}
  ```

### Test 3: Seluruh Backend Test Suite (14 Modul)
- **Perintah**: `cd apps/api && php artisan test`
- **Exit Code**: `0`
- **Output**:
  ```json
  {"tool":"phpunit","result":"passed","tests":1265,"passed":1265,"assertions":8227,"duration_ms":39102}
  ```

### Test 4: Frontend Web Gap Remediation Test
- **Perintah**: `cd apps/web && npx vitest run src/test/portal-guru-gap-remediation.test.ts`
- **Exit Code**: `0`
- **Output**:
  ```
   ✓ src/test/portal-guru-gap-remediation.test.ts (8 tests) passed (737ms)
  ```

### Test 5: Seluruh Frontend Web Test Suite (`apps/web`)
- **Perintah**: `cd apps/web && npm test`
- **Exit Code**: `0`
- **Output**:
  ```
   Test Files  68 passed (68)
        Tests  803 passed (803)
     Duration  10.06s
  ```

### Test 6: Static Typecheck & Formatting
- `cd apps/web && npm run typecheck` ➔ Exit code `0` (0 errors)
- `cd apps/portal && npx tsc --noEmit` ➔ Exit code `0` (0 errors)
- `cd apps/api && ./vendor/bin/pint --test app/Http/Controllers/Api/V1/MeTeacherController.php routes/api.php tests/Feature/Teacher/TeacherGapRemediationFeatureTest.php` ➔ Passed

---

## 7. Daftar Temuan Berdasarkan Tingkat Keparahan (Severity)

### 🔴 P1 (Tinggi / Perlu Tindakan Sebelum Rilis)
1. **[P1-01] Frontend UI `apps/web` Belum Memasang Komponen Penampil Fitur A, B, C**:
   - **Lokasi**: `apps/web/src/app/(portal)/portal/guru/wali-kelas/page.tsx`
   - **Deskripsi**: Method API client telah siap di `guru-api.ts`, namun antarmuka pengguna pada portal guru belum merender tabel daftar perizinan siswa, modal persetujuan izin, form pencatatan poin karakter, maupun form pengajuan booking sarpras. Fitur baru saat ini hanya bisa dipanggil via API atau test otomatis.
   - **Rekomendasi**: Pasang komponen UI (atau salin dan adaptasikan komponen dari `apps/portal/src/app/guru/perizinan` dan `siswa`) ke dalam sub-tab di halaman `apps/web/(portal)/portal/guru/wali-kelas/` atau menu terkait.

2. **[P1-02] Halaman pada `apps/portal` Masih Hardcoded ke Mock Data**:
   - **Lokasi**: `apps/portal/src/app/guru/perizinan/page.tsx`, `apps/portal/src/app/guru/siswa/page.tsx`, `apps/portal/src/app/guru/sarpras/page.tsx`
   - **Deskripsi**: Meskipun `apps/portal/src/lib/lms-api.ts` telah dibuat, ketiga halaman di atas tidak mengimpor atau memanggil method tersebut. Halaman-halaman tersebut masih memutasi data `useState` dummy lokal.

### 🟡 P2 (Sedang / Hardening Keamanan)
3. **[P2-01] Celah Bypass Penguncian Nilai Mandiri Guru pada Jalur `GradeService` Admin**:
   - **Lokasi**: `apps/api/app/Services/GradeService.php:247` dan `apps/api/app/Services/GradeService.php:304`
   - **Deskripsi**: Penguncian `Grade.locked_at` oleh guru dihormati di `MeTeacherController::updateBulkGrades`, tetapi belum divalidasi pada `GradeService::storeGrade` dan `storeBulkGrades`. Pengguna dengan permission `academic.grade.input` dapat menimpa nilai yang telah dikunci guru melalui endpoint `/api/v1/grades`.
   - **Rekomendasi**: Tambahkan pemeriksaan `if ($existing && $existing->locked_at !== null) { throw new HttpException(422, 'Nilai telah dikunci oleh guru.'); }` pada `GradeService`.

4. **[P2-02] Ketiadaan Row Locking pada Keputusan Izin Siswa**:
   - **Lokasi**: `apps/api/app/Http/Controllers/Api/V1/MeTeacherController.php:1887`
   - **Deskripsi**: `LeaveRequest::find($id)` tidak menggunakan `lockForUpdate()`, membuka celah *race condition* jika dua permintaan masuk serentak.

5. **[P2-03] Ketiadaan Jendela Idempotensi pada Pencatatan Poin Siswa**:
   - **Lokasi**: `apps/api/app/Http/Controllers/Api/V1/MeTeacherController.php:1993`
   - **Deskripsi**: Klik ganda cepat oleh guru dapat membuat baris `StudentPoint` duplikat dalam hitungan detik.

### 🟢 P3 (Rendah / Peningkatan Arsitektur)
6. **[P3-01] Entropi Nomor Tiket Sarpras (`Str::random(4)`)**:
   - **Lokasi**: `apps/api/app/Http/Controllers/Api/V1/MeTeacherController.php:2236`
   - **Deskripsi**: Format `TKT-YYYYMMDD-XXXX` memiliki ruang 4 karakter alfanumerik. Disarankan ditingkatkan menjadi 6 karakter alfanumerik (`Str::upper(Str::random(6))`) untuk menjamin keunikan tanpa kolisi.

---

## 8. Daftar Pengujian yang Belum Dilakukan

1. **Uji Runtime Browser E2E (Playwright) untuk 5 Fitur Baru di `apps/web`**:
   - Karena form dan tombol UI belum dimount di `apps/web`, pengujian interaksi pengguna berbasis browser (klik tombol persetujuan izin, pengisian form rujukan BK) belum dapat dijalankan via Playwright.
2. **Uji Beban Konkurensi Mutasi Nilai Terkunci**:
   - Pengujian stres konkurensi (misal 50 request serentak mencoba mengubah nilai pada detik yang sama dengan eksekusi `lockGrades`).
3. **Uji Integrasi Webhook / Notifikasi Eksternal**:
   - Pengiriman notifikasi WhatsApp / Push ke orang tua saat izin disetujui wali kelas belum diuji dengan gateway pihak ketiga nyata (masih menggunakan mock).

---

## 9. Rekomendasi Perbaikan & Kriteria Penerimaan (Acceptance Criteria)

### Rekomendasi Langkah Kerja:
1. **Frontend `apps/web`**: Mount tab "Perizinan Siswa" dan "Poin & Rujukan Siswa" pada `apps/web/src/app/(portal)/portal/guru/wali-kelas/page.tsx` dengan menghubungkan komponen form ke `guruApi.getHomeroomLeaveRequests()`, `guruApi.decideHomeroomLeaveRequest()`, dan `guruApi.recordStudentPoint()`.
2. **Backend Hardening**: Tambahkan guard `locked_at` pada `GradeService.php` agar penguncian nilai guru kebal dari jalur admin `/api/v1/grades`.
3. **Frontend `apps/portal`**: Ambil keputusan konsolidasi — jika `apps/web` adalah portal resmi, beri peringatan deprecation pada `apps/portal` atau hapus dari monorepo untuk menghindari ambiguitas tim.

---

## 10. Keputusan Akhir Auditor

| Kategori | Status | Keterangan |
|---|---|---|
| **Kesiapan Backend API** | 🟢 **SIAP (READY)** | 10 endpoint ber-scope guru aktif, validasi lengkap, anti-IDOR terverifikasi, 15/15 test remediasi dan 1.265/1.265 test regresi lulus 100%. |
| **Kesiapan Frontend API Client** | 🟢 **SIAP (READY)** | 10 client method terpasang di `guru-api.ts`, 100% type-safe, 8/8 test client lulus. |
| **Kesiapan Antarmuka Pengguna (UI)** | 🟡 **MEMERLUKAN PERBAIKAN** | Komponen UI visual penampil pada `apps/web` belum terpasang (Temuan P1-01). |
| **Kepatuhan Proteksi Git** | 🟢 **100% PATUH** | Tidak ada `git commit`, `git push`, maupun operasi destruktif yang dijalankan. |

**Keputusan Ringkas**:  
Backend dan API Client telah **SELESAI dan LULUS AUDIT**. Untuk mencapai kesiapan produksi penuh (*Full Production Readiness*), tim pengembang disarankan memasang komponen visual UI di `apps/web` (Temuan P1-01) dan menambahkan guard `locked_at` pada `GradeService.php` (Temuan P2-01) sebelum menggabungkan branch `fix/teacher-gaps` ke `develop`.
