# Rencana Pengujian Regresi & Permanenisasi Concurrency — Persiapan Tier 2B (`TIER2B_REGRESSION_PLAN`)

> **Basis Verifikasi:** Branch `develop` · `HEAD = origin/develop = 69b3832`  
> **Standar:** Mengikuti `10_TESTING_QA.md` & `AGENTS.md §5` (nama uji berawalan ID temuan; mencakup happy path, validasi gagal, 401, 403, boundary bisnis, & query-count guard; 0 test lama dihapus/dinonaktifkan).

---

## 1. Arsitektur Permanenisasi Uji Concurrency PostgreSQL (`T2B-CONC-01`)

### Masalah Saat Ini
1. [PostgreSqlConcurrencyTest.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/tests/Feature/Ntf/PostgreSqlConcurrencyTest.php#L33-L57) men-hardcode kredensial macOS lama (`macbookprom12020`, `sistlabum_staging_test`, port `5432`) sehingga **selalu `skipped`** di Linux/CI.
2. Enam skenario multi-proses Tier 2A yang baru saja lulus **15/15 PASS** masih berada di skrip scratch sementara, belum menjadi suite PHPUnit permanen di repositori.
3. Suite `Feature` standar memakai `RefreshDatabase` (membungkus test dalam transaksi tunggal), sehingga koneksi PostgreSQL ke-2 **tidak bisa melihat** data fixture yang belum di-commit.

### Desain Permanenisasi (Tanpa Mengganggu `php artisan test` Standar)
1. **Testsuite Terdedikasi di [phpunit.xml](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/phpunit.xml):**
   - Tambahkan `<testsuite name="Concurrency"><directory>tests/Concurrency</directory></testsuite>` (dijalankan eksplisit di CI/lokal dengan `php artisan test --testsuite=Concurrency` pada database testing terpisah `sistlabum_concurrency_test` agar tidak pernah deadlock dengan suite `Feature`).
   - Mutakhirkan [PostgreSqlConcurrencyTest.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/tests/Feature/Ntf/PostgreSqlConcurrencyTest.php) agar membaca `env('PG_CONCURRENCY_HOST', config('database.connections.pgsql.host'))`, `PG_CONCURRENCY_PORT`, `PG_CONCURRENCY_DATABASE`, `PG_CONCURRENCY_USERNAME`, `PG_CONCURRENCY_PASSWORD` — dan hanya `markTestSkipped()` bila driver bukan `pgsql` atau koneksi kedua gagal dengan `pg_backend_pid()` berbeda.
2. **Skenario Wajib di `tests/Concurrency/Tier2PostgreSqlConcurrencyTest.php`** *(menggunakan worker proses OS paralel `proc_open` + barrier timestamp + verifikasi `pg_backend_pid()` unik per worker)*:
   - `T2B-CONC-FIN-01`: 4 worker paralel `verifyPayment()` pada payment sama → tepat 1 sukses, 3 ditolak `ValidationException`.
   - `T2B-CONC-FIN-02`: 2 worker paralel `verifyPayment()` (2×600rb pada invoice 1jt) → tepat 1 sukses, 1 diblokir overpayment; dan 2×500rb → 2 sukses, `paid_amount=1.000.000`, status `lunas`, 2 kuitansi unik.
   - `T2B-CONC-FIN-03`: 4 worker paralel `submitPayment()` (4×600rb pada invoice 1jt) → tepat 1 sukses, 3 diblokir, `pendingSum=600.000`.
   - `T2B-CONC-FIN-04`: 6 ronde paralel `cancelInvoice()` vs `verifyPayment()` → *mutual exclusion* ketat (0 kondisi `dibatalkan` + `terverifikasi`).
   - `T2B-CONC-FIN-05`: 8 worker paralel `createInvoice()` & 8 worker paralel `verifyPayment()` → 8/8 nomor `INV/...` & `KWT/...` unik berurutan, 0 `SQLSTATE[23505]`.
   - `T2B-CONC-PPDB-01`: 4 worker paralel `enrollApplicant()` pada pendaftar sama (1 create + 3 idempoten) & 6 worker pada 6 pendaftar berbeda (6/6 NIS unik berurutan).
   - `T2B-CONC-ROLE-01`: 4 worker paralel memanggil `Role::ensureSystemRole('student', 'Siswa')` di dalam `DB::transaction()` pada tabel `roles` kosong → 4/4 sukses, 1 baris `roles`, 0 error `23505`/`25P02`.
   - `T2B-CONC-GRD-01`: 2 worker paralel memanggil `GradeRecorder::recordManual(85)` dan `GradeRecorder::recordAuto(80)` (serta kasus `70` vs `90`) pada sel baru & eksisting → 0 error, `value === max(manual, auto)` terjaga, `grade_histories` tepat 2 baris.

---

## 2. Matriks Test Case Wajib per Batch Tier 2B

### Batch 2B-1 (Concurrency & Queue)
| Test ID | Target File Uji | Skenario & Ekspektasi |
|---|---|---|
| `T2B-CONC-01..03` | `tests/Concurrency/Tier2PostgreSqlConcurrencyTest.php` & `tests/Feature/Ntf/PostgreSqlConcurrencyTest.php` | 6 uji NTF + 8 uji multi-proses Finance/PPDB/Role/GradeRecorder lulus 100% di PostgreSQL terpisah (`0 skipped`). |
| `P1-39-01` | `tests/Feature/Sys/QueueAfterCommitTest.php` | `config('queue.connections.redis.after_commit') === true`; job yang di-dispatch dalam transaksi gagal (rollback) tidak dieksekusi. |
| `REG-GRD-MAX` | `tests/Feature/Academic/GradebookProtectionTest.php` | Seluruh test kebijakan `max(manual, auto)` tetap 100% hijau pasca penambahan `lockForUpdate` di `GradeRecorder`. |

### Batch 2B-2 (Security Guards, IDOR, & Paginasi)
| Test ID | Target File Uji | Skenario & Ekspektasi |
|---|---|---|
| `P1-40-01` | `tests/Feature/Sys/PaginationBoundsHardeningTest.php` | Request `?per_page=99999` & `?per_page=-5` pada ke-11 endpoint unbounded + `ReportService` menghasilkan `1 <= meta.per_page <= 100`. |
| `P1-14-01..03` | `tests/Feature/Academic/GradingAuthorizationHardeningTest.php` | Guru A tulis/hapus nilai siswa kelas lain lewat `/grades`, `/grades/bulk`, `/me/teacher/grades/bulk`, & `publish-gradebook` → **403**; `updateBulkGrades` dengan sel `null` → **200**; komponen global (`class_id IS NULL`) tampil di portal guru; `getClassProgressStatus` mengembalikan `is_locked: true` saat `period->status === 'locked'`. |
| `P1-11-01..03` | `tests/Feature/Cbt/CbtOwnershipAndScopeHardeningTest.php` | Guru B (memiliki `cbt.exam.manage`) akses `getQuestions`/`getRooms`/`getParticipants`/`monitor`/`report`/`token`/`sync` ujian Guru A → **403**; proctor ruang 1 akses ruang 2 → **403**; siswa `GET /exams?status=draft` → hasil kosong; `ExamSyncService::pushResults` menolak `student_id` non-peserta. |
| `P1-28-01..03` | `tests/Feature/ProgramKhusus/ProgramKhususHardeningTest.php` | Siswa A submit tugas `target_scope=student` milik Siswa B atau kelas lain → **403**; `file_id` milik user lain → **403**; siswa naik kelas (`left_at` terisi) hanya mendapat materi kelas aktif; submit Mood/Mutabaah 2 hari berbeda mempertahankan kedua riwayat tanggal. |
| `P1-23-PRIV-01` | `tests/Feature/Bk/BkAndComplaintPrivacyTest.php` | Guardian memanggil `GET /api/v1/me/counseling-requests` **tidak** menerima curhat mandiri yang dibuat siswa sendiri (konsisten P0-18). |

### Batch 2B-3 (Skema PostgreSQL, Indeks, & Trigram)
| Test ID | Target File Uji | Skenario & Ekspektasi |
|---|---|---|
| `P0-30-01` | `tests/Feature/Sys/DatabaseIndexesAndConstraintsTest.php` | Insert 2 `academic_years` (`school_id=null, is_active=true`) & 2 `ppdb_waves` (`school_id=null, status='aktif'`) di PG ditolak `SQLSTATE[23505]`; `migrate:rollback` & re-apply bersih. |
| `P1-41-01` | `tests/Feature/Sys/DatabaseIndexesAndConstraintsTest.php` | Simpan & dekripsi `Student`/`Applicant` dengan `nik_enc` ciphertext >255 karakter sukses di PG. |
| `P1-42-01` | `tests/Feature/Sys/DatabaseIndexesAndConstraintsTest.php` | Soft-delete `Student` (NIS), `User` (username/email), `Subject` (code) → pembuatan ulang dengan nilai identik sukses; 2 baris aktif identik tetap ditolak `23505`. |
| `P1-43-01` | `tests/Feature/Sys/DatabaseIndexesAndConstraintsTest.php` | Pencarian case-insensitive lulus di PG; `EXPLAIN` pada `students.full_name ILIKE '%budi%'` memanfaatkan `idx_trgm_students_name` (bukan `LOWER()`). |
| `P1-44-01` | `tests/Feature/Cbt/ExamSyncConsistencyTest.php` | `ExamSyncService::pushResults` mengisi `ends_at === deadline_at`; hapus file pada `program_materials` / `schools` men-set FK `NULL` (tidak orphan/crash). |

### Batch 2B-4 (CBT Lifecycle/DOCX, N+1, & Cron)
| Test ID | Target File Uji | Skenario & Ekspektasi |
|---|---|---|
| `P1-12-01..03` | `tests/Feature/Cbt/CbtSessionLifecycleTier2BTest.php` | `force_submit`, auto-disqualify (5× pelanggaran), & timeout resume semuanya menghasilkan `score !== null`; `saveAnswer` timestamp basi ditolak & pasca-finish ditolak 422; `blur_count` & `violation_count` konsisten di semua endpoint. |
| `P1-13-01..03` | `tests/Feature/Cbt/BeCbt13DocxImportTest.php` | Soal `[BS]` tanpa opsi eksplisit dengan `KUNCI: B` / `S` / `BENAR` / `SALAH` menghasilkan tepat 1 opsi benar; double-commit paralel hanya mengimpor 1×; `updateDraft` menolak soal `pg_kompleks`/`menjodohkan`/`isian` tanpa kunci. |
| `P0-31-01..03` | `tests/Feature/Sys/BatchPerformanceQueryBudgetTest.php` | Menggunakan `DB::enableQueryLog()` untuk 30 siswa: `EarlyWarningService::evaluateRules`, `ReportCardService::generateReportCards`, `ExamGradingService::getEssaySubmissions`, & `OrphanFileService::purgeOrphans` memenuhi anggaran query $O(1)$–$O(\text{tables})$ tanpa N+1. |
| `P1-27-01..02` | `tests/Feature/Academic/AttendanceCronAndLeaveWeekendTest.php` | Menjalankan `attendance:daily-summary` + `bk:evaluate-early-warnings` menghasilkan tepat 1 alert per siswa `'aktif'` (0 duplikat `ATT_LOW`); izin disetujui Jumat–Senin tidak membuat baris `attendances` untuk Sabtu & Minggu. |

### Batch 2B-5 (E-Rapor Lock Teknis, PPDB Pasca-Enroll, Keuangan Ops, Dashboard/CSV)
| Test ID | Target File Uji | Skenario & Ekspektasi |
|---|---|---|
| `P1-16-TECH-01` | `tests/Feature/Academic/ReportCardLockProtectionTest.php` | `lockReportCard` & `bulkLock` tidak menurunkan rapor berstatus `'disetujui'`/`'terbit'` menjadi `'terkunci'`; siswa/ortu tetap 200 saat akses detail & PDF; `compileSemesterSnapshot` hanya memuat ekskul `'aktif'` dalam rentang semester. |
| `P1-25-01..03` | `tests/Feature/Ppdb/PpdbPostEnrollmentTier2BTest.php` | Dua pendaftar berbeda keluarga dengan nomor HP sama tidak tertaut ke 1 `Guardian`; `escalateWaitlist` dengan `track_id` hanya memproses jalur target dan tidak menggugurkan cadangan dengan `re_registration_deadline_at > now()`; aktivasi token guardian valid (sekali pakai) & verifikasi QR PPDB valid/invalid. |
| `P1-26-01..02` | `tests/Feature/Finance/FinanceOperationsTier2BTest.php` | `Schedule` memuat `finance:send-due-reminders` & `finance:reconcile`; `ManualGiroPaymentChannel` membaca `SiteSetting`; `voidPayment` membuat `Notification` ke pembayar/wali. |
| `P1-32-01..02` | `tests/Feature/Rpt/DashboardAndReportIntegrityTest.php` | Booking `'diajukan'` terhitung di pending; tiket darurat `'selesai'` tidak terhitung; siswa naik kelas (`left_at` terisi) tidak double-count di `gradesByLevel`; `ReportService::buildCsv` men-sanitasi awalan `=,+,-,@`. |

### Batch 2B-6 (Konsistensi LMS Portal Guru & Siswa)
| Test ID | Target File Uji | Skenario & Ekspektasi |
|---|---|---|
| `P0-16-REM-01..02` | `tests/Feature/Teacher/TeacherLmsAndHomeroomTier2BTest.php` & `apps/web/src/test/lms-tier2b.test.tsx` | `submissions()` mengembalikan `score: 0` (bukan `null`), `is_late: true`, `notes`, & `file`; `homeroom()` mengembalikan agregat nyata (bukan 98.0/86.5); `POST /me/tasks/{id}/submit` menyimpan file multipart nyata; Vitest membuktikan error submit siswa menampilkan pesan error (bukan sukses palsu) dan tombol kunci guru memanggil `/me/teacher/grades/lock`. |
| `P1-10-01` | `tests/Feature/Teacher/TeacherLmsAndHomeroomTier2BTest.php` | `POST /me/teacher/materials` dengan PDF asli menyimpan file nyata di disk (bukan `default-modul.pdf`), mengembalikan `created_at` valid, dan dapat diunduh siswa. |

### Batch 2B-7 (Komunikasi, Notifikasi, Deep-Link, & Harmoni `69b3832`)
| Test ID | Target File Uji | Skenario & Ekspektasi |
|---|---|---|
| `P1-24-01..02` | `tests/Feature/Ntf/NotificationDeviceAndThrottleTest.php` | `DELETE /api/v1/devices` dengan token WebPush JSON ber-`/` → 200; `POST /devices` token 700 char → 201; `DELETE /devices/{simpleToken}` tetap 200; rate limit `POST /notifications/broadcast` → 429. |
| `P1-18-01` & `P1-20-01` | `tests/Feature/Ntf/NotificationPreferenceAndDeepLinkTest.php` & `apps/web/src/test/notifications-tier2b.test.tsx` | Simpan preferensi dari semua role → 200 persisten; seluruh command/service mengisi `target_url` valid (0 route 404); `ComplaintService` mengirim dual-key (`target_url` + `action_url`) & isolasi `school_id` di `escalateOverdue()`; `NotificationBell` polling & pause saat `document.hidden`, klik notifikasi membuka rute aman. |
| `P1-21-01` & `P1-22-01` | `tests/Feature/Ntf/AnnouncementScopeAndEventsTest.php` | Ortu melihat pengumuman kelas/tingkat anaknya (403 kelas lain); Guru ditolak 403 untuk `'semua'`/kelas lain; 6 event domain memicu notifikasi steril (assert tidak mengandung topik BK/nominal gaji/skor); `sarpras:ticket-sla-reminder` 2× beruntun hanya buat 1 notifikasi & mencakup status `'diproses'`. |
| `P1-23-01` & `P1-19-01` | `tests/Feature/Ntf/MessagingProviderTest.php` & `apps/web/src/test/adm-guest-02-bullying-review.test.tsx` | Modal 3 `admin/public-relations` merender `selectedComplaint.attachments` (`signed_url`) + **100% test `ComplaintTriageTest.php` & `adm-guest-02-bullying-review.test.tsx` tetap hijau**; `ortu/konseling` tanpa kontak hardcoded & simpan jadwal usulan; `guru/perizinan` dukung `'dispen'` & `decision_notes`; `MessagingService` uji `Mail::fake()` & `Http::fake()`. |

---

## 3. Pre-Flight Checks Wajib Sebelum Migrasi Staging/Produksi (Read-Only SQL)

Sebelum migrasi Batch 2B-3 diterapkan di staging/produksi (dijalankan oleh admin/operator secara terencana — **bukan otomatis**):

```sql
-- 1. Pre-flight P0-30: Pastikan tidak ada >1 tahun ajaran aktif dengan school_id IS NULL
SELECT count(*) AS active_null_school_years
FROM academic_years
WHERE school_id IS NULL AND is_active = true AND deleted_at IS NULL;

-- 2. Pre-flight P0-30: Pastikan tidak ada >1 gelombang PPDB aktif dengan school_id IS NULL
SELECT count(*) AS active_null_school_waves
FROM ppdb_waves
WHERE school_id IS NULL AND status = 'aktif';

-- 3. Pre-flight P1-44: Cek referensi yatim sebelum menambah FK NOT VALID pada program_* & schools
SELECT count(*) FROM schools s LEFT JOIN files f ON f.id = s.logo_file_id WHERE s.logo_file_id IS NOT NULL AND f.id IS NULL;
SELECT count(*) FROM program_materials pm LEFT JOIN files f ON f.id = pm.file_id WHERE pm.file_id IS NOT NULL AND f.id IS NULL;
SELECT count(*) FROM program_tasks pt LEFT JOIN files f ON f.id = pt.file_id WHERE pt.file_id IS NOT NULL AND f.id IS NULL;
SELECT count(*) FROM program_task_submissions pts LEFT JOIN files f ON f.id = pts.file_id WHERE pts.file_id IS NOT NULL AND f.id IS NULL;
```

---

## 4. Gerbang Verifikasi (Definition of Done) per Batch

Setiap batch **wajib** lulus seluruh gerbang berikut sebelum commit:
1. **Backend Lint:** `cd apps/api && ./vendor/bin/pint --test` (0 error).
2. **Backend PostgreSQL Feature Tests:** `php artisan test` pada seluruh suite terdampak + **gerbang wajib `ComplaintTriageTest.php`, `ComplaintTest.php`, `GradebookProtectionTest.php`, `BkAndComplaintPrivacyTest.php`, `LmsNotificationPrivacyTest.php`, & `MobileClientAuthCompatibilityTest.php`**.
3. **Backend PostgreSQL Concurrency Suite:** `php artisan test --testsuite=Concurrency` pada database terpisah `sistlabum_concurrency_test` (0 skipped, 0 failed).
4. **Migrasi Aditif (khusus Batch 2B-3):** Uji `migrate` + `migrate:rollback --path=...` + re-`migrate` di `sistlabum_testing` (0 error).
5. **Frontend Typecheck & Vitest (khusus batch menyentuh `apps/web`):** `cd apps/web && npx tsc --noEmit && npx vitest run` termasuk `adm-guest-02-bullying-review.test.tsx`, `fix-guru-08.test.tsx`, `web-push.test.ts`, `cross-role-matrix.test.ts`, & suite baru.
6. **Secret Scan & Integritas Git:** Zero secret pada `git diff`, zero perubahan tak sengaja pada commit lama atau branch `main`, dan working tree bersih setelah commit batch.
