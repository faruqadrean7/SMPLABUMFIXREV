# LAPORAN FINAL HARDENING & STAGING READINESS PORTAL GURU
**Portal Satu Pintu — Sistem Informasi Sekolah Terpadu (SISTLABUM)**  
**Dokumen Referensi:** `docs/uat/TEACHER_PORTAL_FINAL_HARDENING_AND_STAGING_READINESS_REPORT.md`  
**Tanggal:** 24 Oktober 2026  
**Auditor/Engineer:** Senior Laravel Backend Engineer, Application Security Engineer & QA Release Engineer  
**Branch:** `fix/teacher-gaps` (HEAD: `ca7ed5b`)  
**Status Evaluasi:** **CONDITIONAL GO (Semua Kriteria Teknis Lulus, Menunggu Eksekusi Staging Manual)**

---

## 1. EXECUTIVE SUMMARY

Laporan ini mendokumentasikan tindakan pengerasan teknis (*final hardening*) dan verifikasi kesiapan lingkungan staging untuk Portal Guru SISTLABUM. Pekerjaan berfokus pada penyelesaian celah konkurensi pada pencatatan poin karakter siswa (P2-03) yang sebelumnya berstatus `PARTIAL`, verifikasi penguncian nilai terpusat (P2-01), proteksi race condition izin siswa (P2-02), integrasi UI kanonis pada `apps/web` (P1-01), pembersihan prototipe `apps/portal` (P1-02), serta penetapan prosedur uji staging formal.

### Ringkasan Pencapaian Utama:
1. **Idempotensi & Concurrency Hardening (P2-03):** 
   - Diperkuat dengan pola *Atomic Cache Lock + Double-Checked Locking* (`Cache::lock()->block(3)`) pada `MeTeacherController::storeStudentPoint`.
   - Deteksi manipulasi muatan (*payload mismatch detection*): Request berulang dengan key yang sama tetapi payload berbeda ditolak dengan HTTP 422 `IDEMPOTENCY_CONFLICT`.
   - Replay aman: Request dengan key dan payload identik mengembalikan HTTP 200 replayed tanpa duplikasi database.
   - Isolasi ketat: Key di-scope per authenticated user ID (`idemp:student_point:{user_id}:{key}`).
   - *Fail-closed*: Jika cache service mengalami kegagalan/gangguan koneksi, operasi ditolak dengan HTTP 503 `IDEMPOTENCY_UNAVAILABLE` demi menjaga integritas data.
   - Debounce dinormalisasi menjadi 2 detik khusus untuk mitigasi *accidental double-click* pada UI tanpa idempotency key.
2. **Pengujian Otomatis:**
   - 23/23 tests lulus pada `TeacherGapRemediationFeatureTest.php` (77 assertions).
   - 82/82 tests lulus pada `tests/Feature/Teacher/` (357 assertions).
   - 14/14 tests lulus pada web UI & API client tests (`portal-guru-homeroom-ui.test.tsx` & `portal-guru-gap-remediation.test.ts`).
   - TypeScript Typecheck `apps/web` dan `apps/portal`: **0 errors**.
   - Standar formatting Laravel Pint: **0 violations (PASSED)**.
3. **Preservasi Repositori 100%:** Seluruh modifikasi lokal pengembang (termasuk staging PPDB `(ppdb)/ppdb` dan header publik) tetap utuh tanpa ada file yang tertimpa atau hilang.

---

## 2. KONDISI GIT AWAL DAN AKHIR

### 2.1 Kondisi Awal
- **Branch:** `fix/teacher-gaps`
- **Commit HEAD:** `ca7ed5b` (`merge: integrate portal application updates from origin/develop`)
- **Upstream:** `origin/develop` (synced at `7a6c378`)
- **Status Staged:** Rename rute PPDB `apps/web/src/app/(public)/ppdb/...` -> `apps/web/src/app/(ppdb)/ppdb/...` (terpelihara).

### 2.2 Kondisi Akhir
- **Working Tree:** Terjaga bersih dari operasi destruktif; tidak ada `git commit`, `git push`, `git reset`, atau `git clean` yang dijalankan.
- **File Terdampak Remediasi & Hardening:**
  - `apps/api/app/Http/Controllers/Api/V1/MeTeacherController.php`: Hardening idempotensi dengan atomic lock, payload hash validation, fail-closed cache error handling, debounce 2s, dan fleksibilitas input tiket sarpras.
  - `apps/api/tests/Feature/Teacher/TeacherGapRemediationFeatureTest.php`: Penambahan uji spesifik konkurensi, payload mismatch, isolasi pengguna, debounce, dan fail-closed cache.
  - File remediasi sebelumnya pada `GradeService.php`, `AcademicAttendanceService.php`, `routes/api.php`, `apps/portal/...`, dan `apps/web/...` tetap stabil dan lulus uji.

---

## 3. DAFTAR PERUBAHAN BESERTA ALASAN

| File | Tipe Perubahan | Alasan & Justifikasi Teknis |
|---|---|---|
| `apps/api/app/Http/Controllers/Api/V1/MeTeacherController.php` | Modifikasi | Menerapkan `Cache::lock()->block(3)`, verifikasi `payload_hash` SHA-256 untuk mendeteksi konflik payload, double-checked locking, penanganan fail-closed HTTP 503 jika cache down, dan debounce 2s. |
| `apps/api/tests/Feature/Teacher/TeacherGapRemediationFeatureTest.php` | Modifikasi | Menambahkan 5 metode uji baru untuk membuktikan payload mismatch (HTTP 422), isolasi user, lock contention simulation, debounce 2s, dan fail-closed saat cache service down. |
| `apps/api/app/Services/GradeService.php` | Terverifikasi | Pengecekan `locked_at` pada `storeGrade` dan `bulkStoreGrades` menolak mutasi nilai terkunci dengan HTTP 422. |
| `apps/api/app/Services/AcademicAttendanceService.php` | Terverifikasi | `DB::transaction` dengan `LeaveRequest::lockForUpdate()` dan re-evaluasi status untuk mencegah race condition perizinan siswa. |
| `apps/web/src/app/(portal)/portal/guru/wali-kelas/page.tsx` | Terverifikasi | Tab perizinan dan form poin/BK terhubung ke API resmi dengan UI loading/error/empty state. |
| `apps/web/src/app/(portal)/portal/guru/fasilitas/page.tsx` | Terverifikasi | Halaman reservasi fasilitas dan tiket pelaporan sarpras mandiri dengan deteksi konflik jadwal HTTP 409. |
| `apps/portal/src/app/guru/perizinan/page.tsx` & `sarpras/page.tsx` | Terverifikasi | Pembersihan mock statis dan penyematan banner peringatan prototipe resmi. |

---

## 4. TEMUAN IDEMPOTENSI SEBELUM DAN SESUDAH

| Parameter Evaluasi | Sebelum Hardening | Sesudah Hardening |
|---|---|---|
| **Eksekusi Concurrency** | Pemeriksaan cache terjadi sebelum transaksi; cache ditulis setelah commit. Request paralel berpotensi lolos bersamaan. | Dilindungi *Atomic Cache Lock* (`lockKey` 10s dengan `block(3)`) dan *Double-Checked Locking* di dalam lock. |
| **Payload Mismatch** | Request dengan key sama tetapi payload berbeda diam-diam mengembalikan hasil pertama. | Dihitung hash SHA-256 dari payload. Jika berbeda, ditolak dengan HTTP 422 `IDEMPOTENCY_CONFLICT`. |
| **Isolasi Lingkup** | Scoped per user di cache, belum diuji lintas user. | Terbukti terisolasi per user (`idemp:student_point:{user_id}:{key}`). Guru A tidak saling memblokir atau membaca key Guru B. |
| **Kegagalan Cache** | Cache down berisiko menelan kegagalan dan meneruskan duplikasi. | *Fail-closed*: Jika cache service error, transaksi dibatalkan dengan HTTP 503 `IDEMPOTENCY_UNAVAILABLE`. |
| **Debounce Fingerprint** | 15 detik (terlalu lama, berpotensi memblokir pencatatan poin sah berikutnya). | Disesuaikan menjadi 2 detik khusus untuk menangkal double-click UI tanpa membatasi alur kerja guru. |
| **Header Standar** | Mendukung `X-Idempotency-Key` dan `Idempotency-Key`. | Tetap didukung penuh; respons replay menyertakan `meta.idempotent_replayed = true` (HTTP 200). |

---

## 5. STRATEGI ATOMICITY DAN CONCURRENCY YANG DITERAPKAN

1. **Double-Checked Locking Pattern**:
   ```
   Client Request (X-Idempotency-Key)
           │
           ▼
   Check Cache (Fast-Path) ──[Hit?]──► Validasi Payload Hash ──► Return HTTP 200 Replay
           │ Miss
           ▼
   Acquire Atomic Lock (Cache::lock()->block(3))
           │ Acquired
           ▼
   Re-Check Cache (Inside Lock) ──[Hit?]──► Validasi Payload Hash ──► Return HTTP 200 Replay
           │ Miss
           ▼
   DB::transaction(Insert StudentPoint + AuditLog)
           │ Success
           ▼
   Write Cache with Payload Hash (TTL 24h)
           │
           ▼
   Release Atomic Lock (finally block)
           │
           ▼
   Return HTTP 201 Created
   ```
2. **Kompensasi dan Fail-Safety**:
   - Jika lock timeout (permintaan paralel sedang berjalan), return HTTP 409 `CONCURRENT_REQUEST`.
   - Jika cache store error, return HTTP 503 `IDEMPOTENCY_UNAVAILABLE` (*fail-closed*).
   - Lock selalu dilepas pada blok `finally`.

---

## 6. BUKTI PENGUJIAN KONKURENSI & KETERBATASAN TEST ENVIRONMENT

### 6.1 Bukti Pengujian Otomatis
Pada `TeacherGapRemediationFeatureTest.php`:
- `test_student_point_recording_is_idempotent_and_prevents_duplicate_records`: Request ulang dengan key dan payload sama menghasilkan HTTP 200 dengan record count DB tetap 1.
- `test_student_point_idempotency_rejects_same_key_with_different_payload`: Request ulang dengan key sama tetapi data berbeda menghasilkan HTTP 422 `IDEMPOTENCY_CONFLICT`.
- `test_student_point_idempotency_is_isolated_between_different_users`: Dua guru berbeda menggunakan key yang sama tidak saling bertabrakan (masing-masing 201 created).
- `test_student_point_handles_lock_timeout_concurrency_conflict`: Membuktikan mutual exclusion pada atomic lock.
- `test_student_point_idempotency_fails_closed_when_cache_service_fails`: Membuktikan respons HTTP 503 saat cache exception terjadi.

### 6.2 Keterbatasan Test Environment (Catatan Kejujuran QA)
- Test suite berjalan di bawah PHPUnit dengan driver in-memory SQLite dan cache store `array`.
- `ArrayLock` pada Laravel mensimulasikan mekanisme atomic lock secara deterministik dalam satu thread PHP. Pengujian beban konkurensi terdistribusi nyata multi-thread (seperti Apache Bench atau k6 terhadap Redis dan PostgreSQL) wajib dijalankan di server Staging sebelum rilis produksi.

---

## 7. HASIL REGRESI GRADE LOCK, LEAVE DECISION, RBAC & ANTI-IDOR

1. **Grade Ledger Locking (P2-01)**:
   - Terbukti memblokir mutasi pada nilai berkondisi `locked_at !== null` via `POST /api/v1/grades` (single) dan `POST /api/v1/grades/bulk` dengan HTTP 422.
   - Pengecualian resmi hanya berlaku jika `force_overwrite = true` dan pengguna berwenang (`admin`, `super_admin`, atau permission `grade.unlock`).
2. **Leave Decision (P2-02)**:
   - Menggunakan `LeaveRequest::lockForUpdate()->findOrFail()` dalam `DB::transaction`.
   - Menguji bahwa keputusan izin kedua terhadap pengajuan yang sudah diproses secara konsisten ditolak dengan HTTP 422.
   - Sinkronisasi presensi via `Attendance::updateOrCreate` menjamin catatan harian unik dan tidak menimpa record presensi gerbang secara tidak sah.
3. **RBAC & Anti-IDOR**:
   - Guru mapel hanya dapat melihat dan menilai kelas/mapel binaannya (diuji via `test_teacher_cannot_create_questions_in_unauthorized_subject` dan `test_teacher_cannot_lock_grades_for_unassigned_class_or_subject` -> HTTP 403).
   - Wali kelas hanya dapat memutuskan izin siswa pada kelas perwaliannya (HTTP 403 untuk guru lain).

---

## 8. HASIL QUALITY GATES

| Test Suite | Working Directory | Perintah | Exit Code | Hasil |
|---|---|---|---|---|
| **Remediation & Hardening Backend** | `apps/api` | `php artisan test tests/Feature/Teacher/TeacherGapRemediationFeatureTest.php` | `0` | **23 passed, 0 failed, 77 assertions** |
| **All Teacher Feature Backend** | `apps/api` | `php artisan test tests/Feature/Teacher/` | `0` | **82 passed, 0 failed, 357 assertions** |
| **Laravel Pint Formatter** | `apps/api` | `./vendor/bin/pint --test` | `0` | **PASSED (0 style violations)** |
| **Web UI Integration** | `apps/web` | `npx vitest run src/test/portal-guru-homeroom-ui.test.tsx` | `0` | **6 passed, 0 failed** |
| **Web API Client Remediation** | `apps/web` | `npx vitest run src/test/portal-guru-gap-remediation.test.ts` | `0` | **8 passed, 0 failed** |
| **Typecheck Web** | `apps/web` | `npm run typecheck` (`tsc --noEmit`) | `0` | **0 errors** |
| **Typecheck Portal** | `apps/portal` | `npx tsc --noEmit` | `0` | **0 errors** |

---

## 9. STATUS BROWSER/E2E DAN STAGING

- **Status E2E Browser:** **`NOT_RUN`**
  - Justifikasi: Repositori `apps/web` tidak memiliki framework Playwright/Cypress yang dikonfigurasi. Aturan keselamatan ketat melarang instalasi dependensi luar atau mutasi database langsung pada sesi audit ini.
  - Pengujian interaksi komponen UI telah diverifikasi secara mendalam menggunakan DOM Integration Test Vitest + Testing Library (14/14 skenario lulus).
- **Status Akun Staging:** **`BLOCKED / PENDING STAGING DEPLOYMENT`**
  - Akun uji seperti `guru.mtk.staging` dan `walas.7a.staging` didefinisikan dalam seeder `TeacherDemoSeeder` dengan guard staging/local.
  - Verifikasi langsung pada browser harus dijalankan oleh engineer staging setelah kode di-deploy ke server staging dengan database terisolasi.

---

## 10. RISIKO TERSISA DAN MITIGASI

1. **Risiko Cache Eviction pada Beban Ekstrem**:
   - Jika Redis mengalami OOM (Out Of Memory) dan mengeksekusi eviksi LRU sebelum 24 jam, key idempotensi bisa hilang.
   - *Mitigasi:* Konfigurasikan Redis dengan `maxmemory-policy volatile-lru` dan pertimbangkan pembuatan tabel database `idempotency_keys` bila audit transaksi finansial/poin formal diwajibkan oleh sekolah.
2. **Perubahan PPDB Staged**:
   - Terdapat perubahan penataan rute PPDB pada `apps/web` yang saat ini berstatus staged.
   - *Mitigasi:* Pisahkan commit fitur PPDB dan fitur Guru Remediasi dalam branch/commit berbeda sebelum merge ke `develop`.

---

## 11. LANGKAH MANUAL UNTUK PEMILIK STAGING / DEVOPS

Sebelum melakukan promosi kode ke produksi, tim Staging/DevOps wajib menjalankan prosedur berikut:
1. **Review Git & Commit Terpisah**:
   - Commit 1 (PPDB): Perubahan rute PPDB pada `apps/web/(ppdb)/...`.
   - Commit 2 (Portal Guru): File remediasi dan hardening backend/frontend portal guru.
2. **Deploy ke Lingkungan Staging Terisolasi**:
   - Jalankan migrasi dan seeder staging:
     ```bash
     php artisan migrate --force
     TEACHER_DEMO_SEEDER=true php artisan db:seed --class=TeacherDemoSeeder
     ```
3. **Manual Browser Smoke Test**:
   - Login menggunakan akun `walas.7a.staging`:
     - Buka menu Wali Kelas -> Tab Perizinan Siswa -> Setujui 1 izin siswa -> Verifikasi perubahan status presensi.
     - Tab Poin Siswa & BK -> Input 1 poin prestasi -> Pastikan tersimpan dan tidak double-submit.
   - Login menggunakan akun guru mapel `guru.mtk.staging`:
     - Buka menu Fasilitas & Sarpras -> Buat 1 reservasi laboratorium -> Buat 1 reservasi pada jam yang sama (pastikan muncul dialog konflik 409).
     - Buka Penilaian -> Kunci Buku Nilai -> Verifikasi bahwa input nilai berikutnya terkunci.

---

## 12. KEPUTUSAN AKHIR PER DOMAIN

| Domain | Status | Evaluasi & Dasar Pertimbangan |
|---|:---:|---|
| **1. Backend Correctness** | `PASS` | Seluruh endpoint dan controller mematuhi kontrak API, validasi DTO, dan kode status baku. |
| **2. Authorization / RBAC / Anti-IDOR** | `PASS` | Hak akses guru mapel dan wali kelas terisolasi ketat; akses lintas kelas menghasilkan HTTP 403. |
| **3. UI & API Integration** | `PASS` | Komponen `apps/web` terintegrasi penuh ke API resmi dengan penanganan state lengkap. |
| **4. Concurrency & Idempotency** | `PASS` | Double-checked locking via `Cache::lock()->block(3)`, deteksi payload mismatch (422), dan fail-closed (503) telah teruji. |
| **5. Automated Tests** | `PASS` | 23/23 remediasi tests lulus, 82/82 teacher feature tests lulus, typechecks bersih, Pint lulus. |
| **6. Browser Runtime / E2E** | `NOT_RUN` | Playwright tidak terpasang di repo; DOM integration tests lulus; live E2E dialihkan ke staging smoke test. |
| **7. Git Integrity** | `PASS` | Seluruh perubahan staged PPDB dan modifikasi lokal terjaga utuh tanpa konflik. |
| **8. Staging Verification** | `BLOCKED` | Memerlukan deployment ke server staging bersama dan eksekusi skenario akun demo. |

---

# **KEPUTUSAN AKHIR: CONDITIONAL GO**

### Syarat Pemenuhan (Release Conditions):
1. **Pemisahan Commit:** Tim engineering melakukan commit terpisah antara modifikasi PPDB yang sudah staged dengan pekerjaan remediasi Portal Guru.
2. **Staging Smoke Test:** Melaksanakan verifikasi browser manual di server staging menggunakan akun `walas.7a.staging` dan `guru.mtk.staging` sesuai panduan pada Bagian 11.
3. **Verifikasi Redis Staging:** Memastikan ekstensi `php-redis` aktif di server staging untuk mendukung atomic locking terdistribusi secara penuh.
