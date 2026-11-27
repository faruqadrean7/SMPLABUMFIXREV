# Daftar Perbaikan yang Sudah Ada di Kode Aktif (`ALREADY_FIXED`)

> **Basis Verifikasi:** Branch `develop` · Commit `be6a875ffdce99dd8cf3c070cea750b8f3024750`  
> **Tujuan:** Memastikan perbaikan terbaru yang sudah dikerjakan tidak diulang atau tertimpa, sekaligus menandai sisa celah yang masih perlu dibereskan secara bedah (*surgical fix*).

---

## Ringkasan

Tidak ada satu pun dari 76 ID temuan besar yang 100% `ALREADY_FIXED` secara utuh, **tetapi terdapat 6 sub-perbaikan signifikan yang sudah selesai di commit terbaru** (`6fd4551`, `118d6a3`, `7e4bb8c`, `8fdb1d9`, `fa49cb4`, dan konfigurasi repo) dan wajib dipertahankan.

---

## Tabel Verifikasi Item yang Sudah Diperbaiki

| ID | Severity | Modul | File | Baris | Status Verifikasi | Bukti | Dampak | Rekomendasi | Test Case |
|---|---|---|---|---|---|---|---|---|---|
| **AF-01** *(sub `P0-16`)* | P0 *(sebagian)* | LMS Guru (`ACD`/`GRD`) | `apps/api/app/Http/Controllers/Api/V1/Teacher/TeacherLmsController.php` | `135, 158-159` | `ALREADY_FIXED` *(parsial)* | Commit `7e4bb8c` telah eager-load `gradeComponent` dan menyertakan `'grade_component_id'` serta `'grade_component_name'` pada `GET /api/v1/me/teacher/assignments`, dengan test di `GradebookProtectionTest.php`. | Halaman daftar tugas guru (`/portal/guru/tugas`) kini akurat membedakan tugas yang tertaut vs belum tertaut komponen nilai. **Catatan sisa:** `showAssignment()` (`:387-412`) & `storeAssignment()` (`:229-245`) masih luput → tetap ditangani di `P0-16`. | **Jangan ubah `assignments()`.** Cukup tambahkan 3 baris `gradeComponent` / `grade_component_id` / `grade_component_name` / `max_score` di `showAssignment()` dan `storeAssignment()`. | `BE-LMS-COMP-01`: Pertahankan test `GradebookProtectionTest` untuk daftar tugas + tambah test untuk `GET /me/teacher/assignments/{id}`. |
| **AF-02** *(sub `P0-16`)* | P0 *(sebagian)* | Penilaian Tugas (`GRD`) | `apps/api/app/Services/LmsService.php`; `apps/api/app/Http/Requests/Academic/GradeSubmissionRequest.php` | `725-765`; `14-18` | `ALREADY_FIXED` | Commit `6fd4551` memperbaiki urutan resolusi periode nilai tugas: (1) `period_id` eksplisit → (2) bulan tenggat tugas (`due_at`) → (3) periode terbuka paling awal → (4) periode bulan berjalan. | Nilai tugas tidak lagi selalu jatuh ke periode bulan pertama semester. | **Pertahankan logika `LmsService::resolveTargetGradePeriod()`**; tidak perlu ditulis ulang. | `BE-GRD-PER-01`: Pertahankan test `GradebookProtectionTest` untuk penentuan periode berdasarkan `period_id` dan bulan `due_at`. |
| **AF-03** *(sub `P0-16`)* | P0 *(sebagian)* | Portal Guru LMS | `apps/api/app/Http/Controllers/Api/V1/Teacher/TeacherLmsController.php`; `apps/api/routes/api/portal.php`; `apps/web/src/app/(portal)/portal/guru/tugas/page.tsx` | `336-370`; `100`; `100-210` | `ALREADY_FIXED` | Commit `118d6a3` telah menyediakan `GET /me/teacher/periods`, banner tugas lama belum tertaut, tombol tautkan ke `POST /assignments/{id}/link-grade-component`, dan dropdown pemilih periode di halaman penilaian tugas. | Data tugas lama dapat ditautkan ke komponen buku nilai dari UI tanpa migrasi destruktif. | **Pertahankan seluruh fitur ini.** Hanya tambahkan dukungan komponen global (`class_id IS NULL`) pada `AssignmentController::linkGradeComponent()` (`P1-14`) dan perbaiki `showAssignment()` (`P0-16`). | `FE-GURU-LINK-01`: Pastikan alur tautkan tugas lama & pilih periode tetap hijau di Vitest & PHPUnit. |
| **AF-04** *(sub `P0-27`)* | P1 *(UX mobile)* | Navigasi Mobile Web | `apps/web/src/app/(portal)/portal/guru/layout.tsx`; `siswa/layout.tsx`; `ortu/layout.tsx` | Drawer & label menu | `ALREADY_FIXED` *(parsial)* | Commit `8fdb1d9` & `fa49cb4` mengubah sidebar portal menjadi drawer yang bisa ditutup di layar HP dan mengizinkan label menu wrap 2 baris agar tidak terpotong. | Tampilan drawer HP tidak lagi menutupi konten atau memotong label menu. **Catatan sisa:** href lama `/guru/*`, `/siswa/*`, `/ortu/*` & `components/role-guard.tsx` belum diperbaiki (`P0-27`). | **Pertahankan implementasi drawer mobile & styling wrap 2-baris** saat memperbaiki href `/portal/*` dan konsolidasi `RoleGuard`. | `FE-MOB-DRAWER-01`: E2E viewport mobile 375px dapat buka/tutup drawer tanpa teks terpotong. |
| **AF-05** *(sub `P1-39`)* | P1 *(sebagian)* | Konfigurasi Infra | `.env.example` (root repo) | `29-31` | `ALREADY_FIXED` *(hanya root Docker)* | Root `.env.example` sudah mengonfigurasi `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, dan `SESSION_DRIVER=redis`. | Deployment berbasis Docker Compose root sudah mengarah ke Redis. **Catatan sisa:** `apps/api/.env.example:48,50` dan default `config/cache.php` & `config/queue.php` masih `database`, serta `'after_commit' => false` pada semua driver termasuk `redis`. | Selaraskan `apps/api/.env.example` dan ubah `'after_commit' => true` di `config/queue.php`. | `BE-CFG-QUEUE-01`: Verifikasi `config('queue.connections.redis.after_commit') === true`. |
| **AF-06** *(sub `P0-02`)* | P0 *(mitigasi operasional)* | Storage / CLI | `apps/api/app/Console/Commands/CleanupOrphanFilesCommand.php`; `apps/api/routes/console.php` | `10-14, 28, 82-89`; `14-41` | `ALREADY_FIXED` *(mitigasi dry-run)* | `files:cleanup-orphans` sudah dirancang **dry-run secara default** (tidak menghapus apa pun tanpa flag `--execute` + konfirmasi interaktif) dan **belum dijadwalkan** otomatis di `routes/console.php`. | Lampiran pengaduan & berkas Program Khusus belum terhapus secara otomatis hari ini selama admin tidak menjalankan `--execute`. | Pertahankan mode dry-run default & larangan penjadwalan cron hingga perbaikan `P0-02` (polimorfik `complaint` + 4 kolom non-FK + `site_settings`) selesai dan diverifikasi via dry-run staging. | `BE-FILE-CLI-01`: Menjalankan `artisan files:cleanup-orphans` tanpa `--execute` tidak mengubah jumlah baris `files` maupun berkas fisik. |

---

## Item yang Telah Diperbaiki pada Gelombang Tier 0 + Surgical Fix `P0-16` (27 Nov 2026)

| ID | Commit | Ringkasan Perbaikan | Status |
|---|---|---|---|
| **P0-01** | `7c5903e` | Blokir XXE (`DOCTYPE`/`ENTITY`), XInclude, & XML > 5MB di `DocxQuestionParser` + `LIBXML_NONET`. | `FIXED` |
| **P0-02** | `018230e` | Perlindungan polymorphic `complaint`, 4 kolom non-FK, 2 FK baru, `site_settings` URL, & batch `WHERE IN` di `OrphanFileService` (dry-run tetap default). | `FIXED` |
| **P0-03** | `b2c49a5` | Hapus blanket role bypass & terapkan otorisasi eksplisit per domain + clamp TTL `30..300` detik di `FileStorageService` & `FileController`. | `FIXED` |
| **P0-04** | `6e15268` | Validasi `hasValidSignature()`, `finfo` magic bytes, block SVG & reserved buckets, kunci `owner_id`/`owner_type` server-side di `confirmUpload()`, & header `nosniff`. | `FIXED` |
| **P0-05** | `b9f00ef` | Tambah `$hidden = ['nik_enc', 'nik_hash']` pada model `Student` & `Applicant`. | `FIXED` |
| **P0-06** | `279cfc3` | Terapkan otorisasi deny-by-default pada `GuardianService::listGuardians()`. | `FIXED` |
| **P0-07** | `813400a` | Wajibkan `auth.role.manage` & blokir eskalasi `super_admin` + `flushPermissionCache()` di `UserService`. | `FIXED` |
| **P0-16 (showAssignment)** | `66ea9bc` | Kembalikan `grade_component_id`, `grade_component_name`, dan `max_score` pada `TeacherLmsController::showAssignment()` & `storeAssignment()` (tetap menjaga `max(manual, auto)`). | `FIXED` |

---

## Item yang Telah Diperbaiki pada Gelombang Tier 1A (27 Nov 2026)

| ID | Commit | Ringkasan Perbaikan | Status |
|---|---|---|---|
| **P0-18** | `9d9dcd3` | `SafeEncryptedCast` (backward-compatible), backfill CLI `security:encrypt-legacy-sensitive-data` (default dry-run), pelebaran non-destruktif `counseling_requests.topic` ke `text`, akses BK eksklusif untuk `CounselingRequestService`, masking `follow_up` kepala sekolah, redaksi isi mentah pada pelacakan pengaduan publik + entropi kode tiket 10 hex + `throttle:10,5`, serta penghapusan teks sensitif dari `audit_logs`. | `FIXED` |
| **P0-19** | `eea5450` | Hilangkan angka skor siswa (`score`) dan teks feedback privat dari `body` & `data` notifikasi `assignment.graded` di `LmsService::gradeSubmission()` (tetap menjaga sinkronisasi `GradeRecorder` `max(manual, auto)`). | `FIXED` |
| **P0-22** | `9a488db` | Fail-closed pada `PPDBResultService` saat `result_publish_at` null, hentikan reset rate limiter pada kecocokan valid, lockout 5 kegagalan + `hash_equals()` di `PPDBAccountService::verifyOtp()`, migrasi validasi duplikat NIK ke blind index `nik_hash` (O(1)) tanpa membocorkan `existing_registration_no`/`status` secara publik, hapus penyimpanan NIK plaintext di `step_data` & `audit_logs`, dan perbaiki kolom audit `ip`. | `FIXED` |
| **P0-09** | `fc48d99` | Otorisasi guru/admin pada upload & revisi `TeachingMaterialService`, verifikasi kepemilikan pada `createVersion()` & `file_id`, dan konsistensi penyimpanan disk `'local'` dengan `visibility = 'privat'` (siswa tetap dapat membaca daftar & detail modul ajar). | `FIXED` |
| **P0-10** | `36c3bb6` | Wajibkan koordinat GPS (`lat`/`lng`) pada check-in & check-out `StaffAttendanceService` saat geofencing aktif, blokir peran non-staf (`student`/`guardian`/`guest`) dengan 403 di seluruh endpoint presensi pegawai, dan verifikasi kepemilikan `photo_file_id`. | `FIXED` |
| **P0-11** | `6ec1be3` | Blokir self-return & manipulasi denda peminjam pada `LibraryService::returnLoan()` dan `LabService::returnLoan()`, sembunyikan riwayat `loans.borrower` pada detail buku/alat lab untuk non-petugas, serta batasi akses baca endpoint internal SAR & HUM (`assets*`, `asset-audits*`, `library/summary`, `lab-equipment/summary`, `partnerships*`, `exchange-programs/{id}/participants`, redaksi `tracer-surveys/{id}` & anti-IDOR `submitResponse`). | `FIXED` |
