# Rencana Pengujian Regresi & Verifikasi Keamanan (`REGRESSION_TEST_PLAN`)

> **Basis Verifikasi:** Branch `develop` · Commit `be6a875ffdce99dd8cf3c070cea750b8f3024750`  
> **Standar:** Mengikuti `10_TESTING_QA.md` & `AGENTS.md §5` (nama uji berawalan ID fitur; mencakup happy path, validasi gagal, 401, 403, & edge-case bisnis; 0 test lama boleh dihapus/dinonaktifkan).

---

## 1. Test Case Wajib untuk 3 Prioritas Keamanan Utama (Ketentuan C)

### 1.1 XXE pada `DocxQuestionParser` (`P0-01`)
- **File Target:** `apps/api/tests/Feature/Cbt/BeCbt13DocxImportSecurityTest.php`
- **`BE-CBT-13-SEC-01` (XXE External File Read):** Buat file `.docx` valid (ZIP) berisi `word/document.xml` dengan `<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/hostname">]>` dan `<w:t>1. &xxe;</w:t>` → `DocxQuestionParser::parse()` wajib menolak / melempar validasi error dan **tidak boleh** memuat isi `/etc/hostname`.
- **`BE-CBT-13-SEC-02` (XInclude Injection):** Buat `.docx` dengan `<xi:include href="file:///etc/hostname" parse="text"/>` → wajib ditolak / tidak mengekspansi konten eksternal.
- **`BE-CBT-13-SEC-03` (Zip Bomb Guard):** Buat `.docx` dengan `word/document.xml` > 5MB → ditolak aman tanpa OOM.
- **`BE-CBT-13-REG-01` (Regresi Fungsional):** Jalankan ulang penuh `BeCbt13DocxImportTest.php` + tambahan test soal `[BS]` tanpa opsi eksplisit dengan `KUNCI: B` (`P1-13`) → 100% lulus.

### 1.2 `OrphanFileService` & Relasi Polymorphic (`P0-02`)
- **File Target:** `apps/api/tests/Feature/Storage/OrphanFilePolymorphicProtectionTest.php`
- **`BE-FILE-ORPHAN-01` (Lampiran Pengaduan Polymorphic):** Buat `Complaint` + `File` (`owner_type = 'complaint'`, `owner_id = $complaint->id`, `created_at = now()->subHours(48)`) → `scanOrphans(24)` wajib menandai `is_referenced = true` dan `purgeOrphans()` **tidak boleh menghapusnya**.
- **`BE-FILE-ORPHAN-02` (4 Kolom Non-FK + 2 FK Baru + `site_settings`):** Buat file berumur 48 jam yang direferensikan oleh `schools.logo_file_id`, `program_materials.file_id`, `program_tasks.file_id`, `program_task_submissions.file_id`, `payments.proof_file_id`, `question_import_jobs.source_file_id`, dan `site_settings.setting_value` → **0 file** dari daftar ini masuk `orphan_candidates`.
- **`BE-FILE-ORPHAN-03` (Orphan Sejati & Dry-Run Default):** Buat file yatim sejati berumur 48 jam + file `complaint_pending` berumur 48 jam tanpa `Complaint` → keduanya terdeteksi di dry-run `artisan('files:cleanup-orphans')` **tanpa dihapus**, dan hanya dihapus bila dipanggil dengan `--execute --force`.
- **`BE-FILE-ORPHAN-04` (Efisiensi Query):** Verifikasi `scanOrphans(24, 100)` menggunakan query batch (< 100 query total, bukan 3.500+ query).

### 1.3 Horizontal IDOR & Upload Hardening `FileStorageService` (`P0-03`, `P0-04`)
- **File Target:** `apps/api/tests/Feature/Storage/FileStorageIdorAndUploadSecurityTest.php`
- **`BE-FILE-IDOR-01` (Slip Gaji Guru):** Guru B, Pustakawan (`librarian`), dan Staf Sarpras (`facility_staff`) memanggil `GET /api/v1/files/{slipGuruA}/url` → **403 Forbidden**. Guru A sendiri & Bendahara/Super Admin → **200 OK**.
- **`BE-FILE-IDOR-02` (Dokumen PPDB & Bukti Bayar SPP):** Guru & Sarpras akses dokumen `applicant_documents` → **403**; Panitia PPDB → **200**. Guru & Panitia PPDB akses `payments.proof_file_id` → **403**; Bendahara & Ortu pemilik invoice → **200**.
- **`BE-FILE-IDOR-03` (Clamp TTL):** `GET /api/v1/files/{id}/url?ttl=315360000` → respons `ttl_seconds` maksimal `300` dan `expires_at <= now()->addSeconds(305)`.
- **`BE-FILE-SEC-01` (Upload Signature):** `POST /api/v1/files/upload-signed/{token}` tanpa query signature valid / signature dimanipulasi → **403 Forbidden**.
- **`BE-FILE-SEC-02` (Block SVG & Magic Bytes):** Presign `.svg` / `image/svg+xml` → **422**; upload file PHP/HTML berekstensi `.jpg` (`mime_type: image/jpeg`) → gagal cek `finfo` magic bytes (**422**).
- **`BE-FILE-SEC-03` (Anti-Spoofing `owner_type`):** User biasa kirim `owner_type: 'complaint', owner_id: $complaint->id` ke `POST /api/v1/files/confirm` → ditolak / diabaikan (file tidak pernah terlampir ke `Complaint::attachments()`).
- **`BE-FILE-SEC-04` (Security Headers Download):** `downloadSigned()` menyertakan `X-Content-Type-Options: nosniff`.

---

## 2. Test Case Wajib untuk Proteksi Spesifikasi Bisnis (Ketentuan D)

- **`BE-GRD-SPEC-01` (`GradebookProtectionTest.php` — JANGAN UBAH KEBIJAKAN):**
  - Pastikan seluruh test eksisting di `apps/api/tests/Feature/Academic/GradebookProtectionTest.php` tetap 100% hijau:
    - Manual `90` + auto `87` → `value = 90` (`source = manual`).
    - Manual `80` + auto `95` → `value = 95` (`source = assignment`/`cbt`).
    - Koreksi manual turun `75` saat `auto = 87` → `value = 87` (aturan `max(manual, auto)` terjaga).
    - Daftar tugas `GET /me/teacher/assignments` memuat `grade_component_id` & `grade_component_name` (`7e4bb8c`).
    - Nilai tugas masuk ke periode `period_id` pilihan / bulan tenggat (`6fd4551`, `118d6a3`).
- **`BE-LMS-COMP-02` (Tambahan Regresi `showAssignment`):**
  - `GET /api/v1/me/teacher/assignments/{id}` mengembalikan `grade_component_id`, `grade_component_name`, dan `max_score` konsisten dengan `GET /me/teacher/assignments`.

---

## 3. Test Case Wajib untuk 8 Bug Fungsional (Ketentuan E)

| # | Test ID | File Target | Skenario & Ekspektasi |
|---|---|---|---|
| **1** | `BE-FIN-RPT-01` | `apps/api/tests/Feature/Finance/FinanceReportRegressionTest.php` | `GET /api/v1/finance/reports/income` (tanpa filter, dengan `?channel=Transfer+Bank`, dengan legacy `?payment_method=Transfer+Bank`, dan `?format=csv`) → **200 OK** (0 SQL 500 error), total pemasukan & breakdown akurat, field CSV diawali `=,+,-,@` ter-sanitasi `'`. |
| **2** | `BE-GRD-PDF-01` | `apps/api/tests/Feature/Academic/ReportCardPdfRegressionTest.php` | `GET /api/v1/report-cards/{id}/pdf` mengembalikan `application/pdf` valid (`%PDF`), memuat nama sekolah dari `SiteSetting`, nama mapel panjang >25 karakter tidak terpotong, karakter UTF-8 valid, dan token/QR verifikasi valid. |
| **3** | `BE-GRD-WGT-01` | `apps/api/tests/Feature/Academic/ReportCardWeightingTest.php` | Siswa memiliki komponen Tugas (bobot 40) di 5 bulan (nilai 80 semua) + SAS (bobot 60) di 1 bulan (nilai 100) + nilai di semester lain → `compileSemesterSnapshot()` menghasilkan **`final_score = 92.00`** (bukan `84.62`) dan tidak membocorkan nilai semester lain. |
| **4** | `BE-ATT-AGG-01` | `apps/api/tests/Feature/Academic/AttendanceAggregationTest.php` | Tanggal `2026-10-12`: 1 baris `source='gerbang'` (`alpa`) + 6 baris `source='kelas'` (`alpa`); plus 1 baris `alpa` pada `2025-10-12` (tahun lalu) + 1 baris `terlambat` pada `2026-10-13` → E-Rapor, E-Progress bulan 10/2026, & Early Warning menghitung tepat **1 hari alpa** (bukan 7 atau 8) dan **1 hari terlambat**. |
| **5** | `BE-PPDB-STEP-01` | `apps/api/tests/Feature/Ppdb/PpdbStepAutosaveRegressionTest.php` | `PUT /api/v1/ppdb/applications/{id}/step/personal`, `/parent`, `/school` dengan payload identik `ppdb/dashboard/page.tsx` (`nisn`, `father_name`/`mother_name`, `origin_school`, `average_score`, `rapor_details`) → 200 OK; `GET /ppdb/me` mengembalikan seluruh field utuh (tidak `[]`); 2 pendaftar baru berturut-turut tidak bentrok NIK hardcoded. |
| **6** | `BE-CBT-PKG-01` | `apps/api/tests/Feature/Cbt/ExamMultiPackageGradingTest.php` | Buat ujian dengan 4 soal Paket `A` (bobot 1) dan 4 soal Paket `B` (bobot 1). Peserta Paket `A` menjawab benar 4/4 soal Paket `A` → `finishSession()` menghasilkan **`score = 100.00`** (bukan `50.00`), dengan `DB::enableQueryLog()` membuktikan tidak ada N+1 per soal. |
| **7** | `FE-CBT-AUTOSAVE-01` | `apps/web/src/test/fe-cbt-autosave-debounce.test.tsx` | (a) Simulasi pengetikan 50 karakter beruntun di `<Textarea>` uraian dengan `vi.useFakeTimers()` → hanya **1×** `saveStudentAnswer` setelah 800ms; (b) `window.blur` + `visibilitychange` dalam 100ms → hanya **1×** `logViolation`; (c) klik Selesai saat antrean offline berisi jawaban → `flushPendingOfflineData()` dipanggil & selesai **sebelum** `finishStudentSession()` dan `clearOfflineQueues()`. |
| **8** | `BE-FIN-TX-01` | `apps/api/tests/Feature/Finance/FinanceTransactionIntegrityTest.php` | (a) Batalkan invoice (`dibatalkan`), coba `verifyPayment()` pada slip lama → **422** & tidak terbit kuitansi; (b) 2 cicilan melebihi `total_amount` ditolak overpayment; (c) `submitPayment()` memperhitungkan nominal `menunggu_verifikasi`; (d) `generateBulk()` 50 siswa menghasilkan 50 `invoice_number` unik tanpa duplikat. |

---

## 4. Test Case Regresi Keamanan Lintas Modul & Frontend

- **PII & RBAC Backend (`apps/api/tests/Feature/Security/AuditRevalidationSecurityTest.php`):**
  - `BE-PII-NIK-01`: `GET /exams/{id}`, `/guardians`, `/alumni`, `/partnerships/{id}` tidak mengandung key `nik_enc`/`nik_hash`.
  - `BE-MST-GRD-01`: Siswa panggil `GET /guardians` → **403**.
  - `BE-AUTH-PRIV-01`: `admin_tu` kirim `roles: ['super_admin']` ke `POST/PUT /users` → **403**.
  - `BE-RBAC-CACHE-01`: Cabut permission role → akses langsung **403** tanpa tunggu 5 menit.
  - `BE-AUTH-PW-01`: `changePassword()` mencabut token perangkat lain & refresh token lama.
  - `BE-BK-PRIV-01`: `counseling_request` milik siswa sendiri → ditolak **403** bagi Wali Kelas/Ortu/Staf non-BK; teks terenkripsi di DB.
  - `BE-GUEST-SEC-01`: `complaints.content` terenkripsi di DB; `GET /public/complaints/{code}` tidak menampilkan `content` mentah & menerapkan rate limit 429.
  - `BE-ATT-STAF-01`: Check-in staf tanpa `lat`/`lng` → **422**; siswa panggil check-in staf → **403**.
  - `BE-SAR-LIB-01`: Siswa panggil `POST /library/loans/{id}/return` & `POST /lab-loans/{id}/return` → **403**.
  - `BE-PPDB-SEC-01`: `PPDBResultService` saat `result_publish_at = null` → tolak (fail-closed); OTP salah 5× → terkunci.
- **Frontend & E2E (`apps/web/src/test/` & `apps/web/e2e/`):**
  - `FE-AUTH-GW-01`: Middleware menolak header palsu `x-admin-gateway: 1` & cookie palsu.
  - `FE-AUTH-TOK-01`: `localStorage` bebas token pasca login (`refreshToken` di HttpOnly cookie).
  - `FE-NAV-01`: Seluruh menu Guru/Siswa/Ortu memakai `/portal/*`, active highlight tepat, 0 redirect 307, `super_admin` lolos masuk portal civitas.
  - `FE-ADM-LAND-01`: Ke-10 role admin masuk `/admin` tanpa 403.

---

## 5. Perintah Verifikasi & Kriteria Lulus (Definition of Done)

Setiap fase perbaikan WAJIB lulus seluruh gerbang verifikasi berikut sebelum commit ke `develop`:

```bash
# 1. Backend Laravel: Lint + Full PHPUnit Regression Suite
cd apps/api && ./vendor/bin/pint --test && php artisan test

# 2. Dry-Run Wajib Khusus Storage (TIDAK BOLEH pakai --execute sebelum audit manual)
cd apps/api && php artisan files:cleanup-orphans --hours=24

# 3. Frontend Next.js: Typecheck + Lint + Unit Test + Build
cd apps/web && npm run typecheck && npm run lint && npm run test && npm run build

# 4. E2E Playwright (pada environment staging / stack dev)
cd apps/web && npm run test:e2e -- e2e/05-idor-security.spec.ts e2e/10-idor-comprehensive.spec.ts e2e/13-ppdb-full-lifecycle.spec.ts e2e/14-cbt-student-exam-lifecycle.spec.ts

# 5. Android Kotlin (saat ada perubahan kontrak API terkait mobile)
cd apps/mobile && ./gradlew testDebugUnitTest
```

### Checklist Gerbang Rilis (DoD)
- [ ] **0 kegagalan** pada seluruh test eksisting (`702+` PHPUnit tests & `1007+` Vitest tests) — termasuk `GradebookProtectionTest` (`max(manual, auto)` + `grade_component_id`) dan `TeacherReportCardLifecycleFeatureTest`.
- [ ] Seluruh test baru berawalan ID fitur lulus 100%.
- [ ] Uji pre-flight data lama (enkripsi legacy `SafeEncryptedCast`, cek duplikat `academic_years`/`ppdb_waves`, dry-run `OrphanFileService`) lulus tanpa kehilangan data.
- [ ] Kontrak API Web & Android terverifikasi kompatibel mundur (khususnya `refreshToken` dual-mode web cookie vs mobile JSON).
