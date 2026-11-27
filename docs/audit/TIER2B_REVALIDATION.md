# Re-Validasi Audit Mendalam — Persiapan Tier 2B (`TIER2B_REVALIDATION`)

> **Mode:** Read-Only Verification & Planning (0 baris kode aplikasi diubah)  
> **Tanggal Re-Validasi:** 27 November 2026  
> **Basis Verifikasi:** Branch `develop` · `HEAD = origin/develop = 69b3832fa7eb4bd9035573764c15871388703e1a`  
> **Baseline Sebelumnya:** Tier 0 + `P0-16` (`showAssignment`) + Tier 1A + Tier 1B + Tier 2A (`31bfc29..d0f7888`) + Integrasi Complaint (`69b3832`)

---

## 1. Ringkasan Eksekutif & Matriks Pengelompokan

Total **30 temuan** diaudit ulang langsung terhadap kode aktual pada `HEAD = 69b3832` (27 ID dari audit sebelumnya yang masuk klaster Tier 2B + 3 temuan baru terkait permanenisasi concurrency, `Role::firstOrCreate`, dan `GradeRecorder` locking).

### Distribusi Status Verifikasi di `HEAD = 69b3832`

| Status Aktual | Jumlah | Daftar ID |
|---|---:|---|
| **`CONFIRMED`** (penuh) | **23** | `P0-16` *(sisa 5 sub-item)*, `P1-10`, `P1-13`, `P1-14`, `P1-16` *(teknis non-kebijakan)*, `P1-18`, `P1-19`, `P1-20`, `P1-21`, `P1-23`, `P1-25`, `P1-27`, `P1-28`, `P1-32`, `P1-39`, `P1-40`, `P1-41`, `P1-42`, `P1-43`, `P1-44`, `T2B-CONC-01`, `T2B-CONC-02`, `T2B-CONC-03` |
| **`PARTIALLY_FIXED`** (sebagian selesai di Tier 0–2A / `69b3832`) | **7** | `P0-30`, `P0-31`, `P1-11`, `P1-12`, `P1-22`, `P1-24`, `P1-26` |
| **`ALREADY_FIXED`** (penuh) | **0** | *(Sub-item yang sudah selesai di Tier 0–2A dicatat eksplisit agar tidak dikerjakan ulang: XSS Berita Acara `P1-11`, `start_at` check `P1-12`, `OrphanFileService::scanOrphans` batch `P0-31`, `CounselingSession.follow_up` `P1-24`, CSV formula `FinanceReportController` `P1-26`.)* |

### Pengelompokan Berdasarkan Tingkat Risiko, Dampak Pengguna, Dependensi, & Kompleksitas

| ID | Klaster | Status di `69b3832` | Risiko | Dampak Pengguna | Kompleksitas | Dependensi Utama |
|---|---|---|---|---|---|---|
| **P1-14** | Penilaian / IDOR | `CONFIRMED` | **KRITIS** | Guru / Siswa (manipulasi nilai lintas kelas + bulk save 422) | Sedang | Independen (`GradeRecorder` tetap `max(manual, auto)`) |
| **P1-11** | CBT AuthZ | `PARTIALLY_FIXED` | **KRITIS** | Guru / Pengawas / Siswa (intip soal/kunci ujian guru lain) | Sedang | Independen |
| **P1-28** | Program Khusus | `CONFIRMED` | **KRITIS** | Siswa / Guru Program Khusus (IDOR submit/file + kehilangan histori) | Sedang | Relasi `Student::activeClasses()` |
| **P1-23** | Komunikasi & Privasi | `CONFIRMED` | **KRITIS** *(sub `/me/counseling-requests`)* | Ortu / Siswa / BK / Admin PR (bypass P0-18 + lampiran bullying tak tampil) | Sedang | Harmonis dgn `69b3832` (`admin/public-relations`) |
| **P1-25** | PPDB Pasca-Enroll | `CONFIRMED` | **KRITIS** | Ortu Siswa Baru / Panitia (cross-family IDOR + akun tak bisa aktivasi) | Sedang–Tinggi | Pasca Tier 2A `P0-23` |
| **T2B-CONC-02** | DB Concurrency | `CONFIRMED` | **TINGGI** | Pendaftar PPDB / Admin (crash `25P02` saat DB tanpa seeder / race) | Rendah | Fondasi sebelum `T2B-CONC-01` |
| **T2B-CONC-03** | Penilaian Concurrency | `CONFIRMED` | **TINGGI** | Guru / CBT (lost update `max(manual, auto)` / crash `23505`) | Rendah | Independen (rumus `max` **tidak diubah**) |
| **T2B-CONC-01** | CI / Concurrency QA | `CONFIRMED` | **TINGGI** | Tim Engineering (uji concurrency PG selalu `skipped` di CI Linux) | Sedang | Bergantung `T2B-CONC-02` & `T2B-CONC-03` |
| **P0-30** | Skema DB & Indeks | `PARTIALLY_FIXED` | **TINGGI** | Seluruh pengguna (seq scan + ganda T.A./gelombang aktif saat `school_id NULL`) | Sedang | Pre-flight duplicate check DB |
| **P0-31** | Performa / N+1 / Queue | `PARTIALLY_FIXED` | **TINGGI** | Admin / Guru / Bendahara (timeout 504 saat batch rapor/tagihan/EW) | Sedang–Tinggi | Setelah `P0-30` & `P1-39` |
| **P1-12** | CBT Session | `PARTIALLY_FIXED` | **TINGGI** | Siswa / Pengawas (skor `null` saat force-submit/disqualify; race autosave) | Sedang | Sebelum `P1-11` selesai sepenuhnya / paralel |
| **P1-13** | CBT DOCX Import | `CONFIRMED` | **TINGGI** | Guru CBT (soal `[BS] KUNCI: B` selalu gagal; double-commit duplikasi soal) | Sedang | Cek polling FE sebelum ubah `dispatchSync` |
| **P1-16** *(teknis)* | E-Rapor Lock & Ekskul | `CONFIRMED` | **TINGGI** | Siswa / Ortu / Walas (`bulkLock` hilangkan rapor `terbit` dari siswa/ortu) | Rendah–Sedang | **Role penerbitan tidak diubah** (pending sekolah) |
| **P1-21** | Pengumuman & Scope | `CONFIRMED` | **TINGGI** | Ortu / Guru (Ortu blind kelas; guru bebas broadcast `'semua'`) | Sedang | Relasi `Student::activeClasses()` |
| **P1-24** *(sisa)* | Notif Throttle & Device | `PARTIALLY_FIXED` | **TINGGI** | Admin / WebPush (tanpa throttle + `DELETE /devices/{token}` 404 + `fcm_token` 500) | Rendah | Migrasi aditif `devices.fcm_token` → `text` |
| **P1-27** | Cron Presensi & EW | `CONFIRMED` | **TINGGI** | BK / Walas / Siswa (alert EW duplikat/terlewat; izin isi Sabtu/Minggu) | Rendah–Sedang | Memakai `AttendanceAggregator` (Tier 2A `P0-26`) |
| **P1-40** | Paginasi API (DoS) | `CONFIRMED` | **TINGGI** | Seluruh API (`?per_page=999999` memicu OOM di 11 endpoint) | Rendah | Independen |
| **P0-16** *(sisa)* | LMS Guru & Siswa | `CONFIRMED` | **TINGGI** | Guru / Siswa / Walas (viewer mock, fake success siswa, lock nilai 422/403) | Sedang–Tinggi | Setelah `P1-14` (`updateBulkGrades`) |
| **P1-10** | Materi Guru | `CONFIRMED` | **TINGGI** | Guru / Siswa (file materi hilang → `default-modul.pdf`, `Invalid Date`) | Sedang | Selaraskan dgn `P0-16` & `P0-09` (Tier 1A) |
| **P1-18** | Preferensi & Bell Notif | `CONFIRMED` | **SEDANG–TINGGI** | Seluruh portal (preferensi selalu gagal 422 diam-diam; bell tak polling) | Sedang | Setelah `P1-24` (`devices`) |
| **P1-20** | Deep Link Notifikasi | `CONFIRMED` | **SEDANG–TINGGI** | Seluruh portal (100% link notifikasi BE tak terbaca FE + 4 route 404) | Rendah–Sedang | Harmonis dgn `69b3832` (`ComplaintService`) |
| **P1-22** | Event Notif & SLA Cron | `PARTIALLY_FIXED` | **SEDANG–TINGGI** | Guru / Siswa / Ortu / Sarpras (event bisu + spam SLA tiap jam) | Sedang | Setelah `P1-20` (standar `target_url`) |
| **P1-32** | Dashboard & CSV Report | `CONFIRMED` | **SEDANG–TINGGI** | Kepala Sekolah / Admin (4 KPI salah + CSV formula injection) | Rendah–Sedang | Independen |
| **P1-39** | Queue `after_commit` | `CONFIRMED` | **SEDANG–TINGGI** | Seluruh job async (worker baca transaksi belum commit) | Rendah | Sebelum offload queue di `P0-31` |
| **P1-41** | Lebar Kolom `nik_enc` | `CONFIRMED` | **SEDANG** | PPDB / Master Siswa (headroom enkripsi tinggal ~31 char di `varchar(255)`) | Rendah | Metadata-only di PG |
| **P1-42** | Soft-Delete `UNIQUE` | `CONFIRMED` | **SEDANG** | Admin Master (tak bisa buat ulang kode/NIS/username pasca soft-delete) | Sedang | Pre-flight index; FK `schools` ditunda ke multi-tenant |
| **P1-43** | `LOWER() LIKE` vs Trigram | `CONFIRMED` | **SEDANG** | Seluruh pencarian (78 lokasi abaikan 4 indeks `gin_trgm_ops` eksisting) | Sedang | Setelah `P0-30` |
| **P1-44** | Konsistensi Skema | `CONFIRMED` | **SEDANG** | CBT Sync / Program Khusus (`ends_at` NULL + 0 FK `program_*` + CHECK bias) | Sedang | Pre-flight orphan check; normalisasi CHECK bertahap |
| **P1-26** *(sisa)* | Operasional Keuangan | `PARTIALLY_FIXED` | **SEDANG** | Bendahara / Ortu (cron SPP tak terjadwal, rekening hardcoded, void tanpa notif) | Rendah | Harmonis dgn `routes/console.php` (`69b3832`) |
| **P1-19** | Provider WA/Email & UI | `CONFIRMED` | **SEDANG** | Admin (log palsu `'sent'` tanpa kirim nyata + `/admin/notifications` tanpa tab) | Tinggi | Setelah `P1-24` (throttle) & `P1-21` |

*(Catatan: `P0-17` PDF E-Rapor, `P0-27..29` unifikasi shell/navigasi, `P1-15/17/29/30/31/33..38/45` tetap berada pada Tier 3/4 sesuai roadmap induk agar Tier 2B fokus pada integritas data, performa, LMS/CBT, komunikasi, dan concurrency.)*

---

## 2. Detail Audit Per Temuan (Bukti Kode Aktual di `HEAD = 69b3832`)

### Klaster A — Concurrency, Transaksi, & Fondasi Queue

#### A.1 `T2B-CONC-01` — Permanenisasi Pengujian Concurrency PostgreSQL
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** [PostgreSqlConcurrencyTest.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/tests/Feature/Ntf/PostgreSqlConcurrencyTest.php#L28-L72), [phpunit.xml](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/phpunit.xml#L7-L35)
- **Bukti Kode:** Baris 33–57 men-hardcode `'host' => 'localhost'`, `'port' => 5432`, `'database' => 'sistlabum_staging_test'`, `'username' => 'macbookprom12020'`.
- **Akar Masalah:** Koneksi concurrency terpaku pada laptop macOS lama dan belum ada `<testsuite name="Concurrency">` terpisah untuk menguji 6 skenario multi-proses Tier 2A secara permanen di CI PostgreSQL.
- **Dampak:** Di CI Linux/staging, seluruh 6 uji `PostgreSqlConcurrencyTest` selalu `skipped` dan perlindungan `lockForUpdate`/`pg_advisory_xact_lock` Tier 2A tidak terproteksi regresi otomatis.
- **Solusi Rekomendasi:** Ubah `PostgreSqlConcurrencyTest` agar membaca env `PGSQL_CONCURRENCY_*` dengan fallback `config('database.connections.pgsql')` (membuat 2 koneksi terpisah dengan `pg_backend_pid()` berbeda) dan pindahkan/tambahkan suite `tests/Concurrency/` (NTF + 6 skenario multi-proses Tier 2A + `GradeRecorder` + `Role::ensureSystemRole`) sebagai testsuite terpisah di `phpunit.xml` agar tidak bentrok dengan `RefreshDatabase`.
- **Risiko Regresi:** Rendah jika dijalankan terhadap database terpisah (`sistlabum_concurrency_test`) atau dengan fixture UUID unik + pembersihan ketat di `tearDown()`.
- **Rencana Uji:** `php artisan test --testsuite=Concurrency` menjalankan ≥ 15 skenario multi-koneksi/multi-proses dengan 0 skipped & 0 failed.

#### A.2 `T2B-CONC-02` — Race Condition `Role::firstOrCreate` (dan `firstOrCreate`/`updateOrCreate`) pada DB Tanpa Seeder
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** 8 titik `Role::firstOrCreate` di dalam `DB::transaction()`: [PPDBEnrollmentService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/PPDBEnrollmentService.php#L154-L155), [PPDBAccountService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/PPDBAccountService.php#L42-L48), [GuardianService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/GuardianService.php#L115), [TeacherStaffService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/TeacherStaffService.php#L111), [ProvisionsLoginAccount.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Concerns/ProvisionsLoginAccount.php#L30); plus `PPDBReRegistration::updateOrCreate` ([PPDBEnrollmentService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/PPDBEnrollmentService.php#L76)).
- **Bukti Kode:** `Role::firstOrCreate(['code' => 'student'], ['name' => 'Siswa', 'is_system' => true])` dipanggil di dalam `DB::transaction()`.
- **Akar Masalah:** Di PostgreSQL, bila dua transaksi paralel memanggil `firstOrCreate()` saat tabel `roles` belum ter-seed, transaksi kedua memicu `SQLSTATE[23505]` yang langsung mengubah transaksi PG menjadi *aborted* (`SQLSTATE[25P02]`) sehingga retry `SELECT` Laravel pun gagal total.
- **Dampak:** Crash transaksi enrollment PPDB, pembuatan akun wali/siswa/guru pada environment baru/tanpa seeder atau migrasi bertahap.
- **Solusi Rekomendasi:** Tambahkan helper atomik `Role::ensureSystemRole(string $code, string $name): Role` berbasis `DB::table('roles')->insertOrIgnore([...])` (`ON CONFLICT DO NOTHING` di PG, tidak pernah memicu `23505`/`25P02`) lalu `Role::where('code', $code)->firstOrFail()`; ganti seluruh 8 pemanggilan `Role::firstOrCreate`. Kunci `Applicant` dengan `lockForUpdate()` sebelum `PPDBReRegistration::updateOrCreate` di `confirmReRegistration()`.
- **Risiko Regresi:** Sangat rendah (semantik identik untuk DB yang sudah ter-seed, jauh lebih aman untuk DB kosong).
- **Rencana Uji:** Uji multi-proses 4 worker PG memanggil `Role::ensureSystemRole('student', 'Siswa')` secara simultan di dalam `DB::transaction()` pada tabel `roles` kosong → 4/4 sukses, 1 baris `roles`, 0 error `25P02`.

#### A.3 `T2B-CONC-03` — Concurrency Hardening `GradeRecorder` (Mempertahankan `max(manual, auto)`)
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** [GradeRecorder.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/GradeRecorder.php#L76-L147)
- **Bukti Kode:** `$existing = Grade::where('student_id', ...)->where('component_id', ...)->where('period_id', ...)->first();` tanpa `DB::transaction()` dan tanpa `lockForUpdate()`.
- **Akar Masalah:** Penulisan paralel (mis. guru input nilai manual bersamaan dengan sinkronisasi otomatis CBT/tugas) pada sel nilai yang sama menyebabkan *lost update* (menimpa `manual_value`/`auto_value`) atau `SQLSTATE[23505]` pada insert sel baru.
- **Dampak:** Pelanggaran tak disengaja atas kebijakan `max(manual, auto)` atau rollback seluruh batch `publishToGradebook()`.
- **Solusi Rekomendasi:** Bungkus `record()` dalam `DB::transaction()`, pasang `pg_advisory_xact_lock(hashtextextended(...))` / `pg_advisory_xact_lock(crc32(...))` pada driver `pgsql` untuk kunci `(student_id, component_id, period_id)` + `->lockForUpdate()->first()`. **Rumus `resolveEffective()` (`max(manual, auto)`) tidak disentuh sama sekali.**
- **Risiko Regresi:** Sangat rendah (`pg_advisory_xact_lock` re-entrant dalam sesi transaksi yang sama).
- **Rencana Uji:** Uji concurrency 2 proses PG paralel (`recordManual(85)` + `recordAuto(80)` dan `recordManual(70)` + `recordAuto(90)`) pada sel baru & eksisting → 0 error, `value === max(manual, auto)`, `grade_histories` tepat 2 entri; seluruh `GradebookProtectionTest` tetap 100% hijau.

#### A.4 `P1-39` — Konfigurasi Queue (`after_commit`) & Cache
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** [queue.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/config/queue.php#L16-L73), [cache.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/config/cache.php#L18), [.env.example](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/.env.example#L38-L50)
- **Bukti Kode:** `'after_commit' => false` pada seluruh driver (`database`, `beanstalkd`, `sqs`, `redis`); `.env.example` masih men-set `database` untuk cache/queue/session.
- **Akar Masalah:** Job yang di-dispatch dari dalam `DB::transaction()` dapat dikonsumsi worker sebelum transaksi commit.
- **Dampak:** Race `ModelNotFoundException` di worker async & beban I/O berlebih ke PostgreSQL.
- **Solusi Rekomendasi:** Ubah `'after_commit' => (bool) env('QUEUE_AFTER_COMMIT', true)` pada semua driver di `config/queue.php` dan selaraskan panduan Redis di `apps/api/.env.example`.
- **Risiko Regresi:** Rendah; wajib jalankan full PHPUnit untuk memastikan 0 test bergantung pada dispatch sebelum rollback.
- **Rencana Uji:** Assert `config('queue.connections.redis.after_commit') === true` + test `Queue::fake()` membuktikan job dalam transaksi rollback tidak pernah dikirim.

---

### Klaster B — Skema Database, Indeks, Query Lambat, & Paginasi

#### B.1 `P0-30` — Indeks Komposit Hilang & Partial Unique `school_id IS NULL`
- **Status:** `PARTIALLY_FIXED` *(indeks CBT `2026_11_24_000001` dan complaints `2026_11_27_000050` sudah ada; 9 tabel target & 2 partial unique index masih bermasalah)*
- **Lokasi File & Baris:** [2026_10_29_000031_add_native_db_constraints.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/database/migrations/2026_10_29_000031_add_native_db_constraints.php#L45-L47), [000002](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/database/migrations/2026_10_07_000002_create_identity_and_access_tables.php#L103-L119), [000004](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/database/migrations/2026_10_07_000004_create_ppdb_tables.php#L49-L73), [000005](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/database/migrations/2026_10_07_000005_create_academic_schedule_attendance_lms_tables.php#L62-L79), [000006](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/database/migrations/2026_10_07_000006_create_cbt_tables.php#L127-L162), [000007](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/database/migrations/2026_10_07_000007_create_academic_grading_and_reports_tables.php#L44-L64), [000030](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/database/migrations/2026_10_26_000030_create_finance_tables.php#L46-L104)
- **Bukti Kode:** `CREATE UNIQUE INDEX ... ON academic_years (school_id) WHERE is_active = true AND deleted_at IS NULL` & `ON ppdb_waves (school_id) WHERE status = 'aktif'` — di PG standar `NULL != NULL`.
- **Akar Masalah:** Partial unique index gagal menegakkan keunikan saat `school_id IS NULL`; indeks komposit query panas (`grades(period_id, component_id)`, `grades(student_id, created_at)`, `grades(exam_id)`, `exam_answers(question_id, score)`, `exam_sessions(status, ends_at)`, `exam_sessions(status, deadline_at)`, `exam_sessions(student_id, status)`, `audit_logs(created_at DESC)`, `audit_logs(action, created_at)`, `attendances(att_date, status)`, `attendances(student_id, att_date, status)`, `invoices(status, due_date)`, `payments(status, payment_date)`, `applicants(wave_id, track_id, status)`, `applicants(status, re_registration_deadline_at)`) belum dibuat.
- **Dampak:** Sequential scan berulang (terutama `ExamAutoSubmitCommand` tiap menit, `AuditLogService`, laporan keuangan, & `EarlyWarningService`) dan potensi duplikasi tahun ajaran / gelombang PPDB aktif.
- **Solusi Rekomendasi:** Migrasi baru non-destruktif menambah indeks komposit + partial unique `WHERE school_id IS NULL AND ...` (dengan perintah pre-flight SQL sebelum diterapkan di staging/prod).
- **Risiko Regresi:** Sedang pada partial unique index jika data produksi sudah punya >1 baris aktif `school_id IS NULL` (mitigasi: pre-flight check wajib).
- **Rencana Uji:** Test PostgreSQL memverifikasi insert 2 `academic_years` aktif (`school_id=NULL`) dan 2 `ppdb_waves` aktif (`school_id=NULL`) ditolak `23505` + `migrate:rollback` bersih.

#### B.2 `P0-31` — 7 Jalur N+1 & Proses Sinkron Berat
- **Status:** `PARTIALLY_FIXED` *(`OrphanFileService::scanOrphans` sudah batch di `018230e`; `ExamGradingService::autoGradeExam`/`recalculateItemAnalysis` sebagian sudah preload; 6 jalur + `purgeOrphans` + `getEssaySubmissions` masih N+1/sinkron)*
- **Lokasi File & Baris:**
  1. [EarlyWarningService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Bk/EarlyWarningService.php#L203-L456) (3.200–4.800 query untuk 800 siswa dalam 1 transaksi)
  2. [ReportCardService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/ReportCardService.php#L164-L347) (`$student->classes()->first()` meski sudah di-eager-load; `ReportDescriptionTemplate::where` di loop mapel = 432 query/kelas; `Grade`/`Attendance`/`Extracurricular`/`CharacterNote` per siswa)
  3. [InvoiceGeneratorService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Finance/InvoiceGeneratorService.php#L112-L222) (>5.000 query sinkron saat `target_type='all'`)
  4. [ExamGradingService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Cbt/ExamGradingService.php#L60-L164) (N+1 `$ans->grader?->name` di `getEssaySubmissions:161`; query ulang `ExamAnswer` di `refreshSessionTotalScore:708`; `recalculateItemAnalysis` sinkron)
  5. [OrphanFileService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Storage/OrphanFileService.php#L415-L493) (`purgeOrphans:455` memanggil `checkFileReferences` 38+ query per file + `buildBatchReferenceMap:415-422` LIKE loop per file publik)
  6. [routes/console.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/routes/console.php#L15-L45) (11 jadwal tanpa `->onOneServer()` / `->runInBackground()`)
  7. [QuestionImportService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Cbt/QuestionImportService.php#L64) (`ParseQuestionImportJob::dispatchSync` sinkron meski job `implements ShouldQueue`)
- **Dampak:** HTTP 504 timeout saat generate rapor/tagihan massal, lock DB panjang, lonjakan CPU/RAM, dan eksekusi ganda cron di multi-server.
- **Solusi Rekomendasi:** Preload batch (`whereIn` + `groupBy`) & cache peta `ReportDescriptionTemplate` pada `EarlyWarningService`/`ReportCardService`; prefetch existing invoices & batch `Notification::insert()` pada `InvoiceGeneratorService`; tambah eager-load `'grader'` & hindari re-query di `ExamGradingService`; ubah `purgeOrphans` memakai `buildBatchReferenceMap` per chunk; tambah `->onOneServer()` di `console.php`; koordinasikan migrasi `dispatchSync` → `dispatch` dengan polling FE (atau feature flag env).
- **Risiko Regresi:** Tinggi bila `QuestionImportService:64` langsung diubah ke async tanpa memastikan FE sudah polling status `queued`/`parsing`; rendah untuk optimasi preload query.
- **Rencana Uji:** Assertion `DB::enableQueryLog()` (batas atas jumlah query tidak tumbuh linear/kuadratik terhadap N siswa/soal) + semua feature test modul terkait hijau.

#### B.3 `P1-40` — Paginasi Tanpa Batas Atas (`?per_page=999999`)
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** 11 Service+Controller tanpa batas atas (`CharacterNoteService:65`, `GradeService:232`, `LmsService:77,313`, `QuestionBankService:40`, `ReportCardService:46`, `ExtracurricularService:146`, `CounselingService:54,295`, `AssetAuditService:50`, `FacilityService:52`), 1 FormRequest terlalu longgar (`GenerateReportRequest:21` `max:500` + `ReportService` 6 titik), serta 11 Service master tanpa `max(1, ...)`.
- **Akar Masalah:** Input `per_page` langsung di-cast ke `(int)` dan diteruskan ke `paginate()` tanpa clamp.
- **Dampak:** Risiko OOM PHP-FPM / DoS database dengan 1 request `?per_page=999999` atau error `per_page=-5`.
- **Solusi Rekomendasi:** Helper terpusat `PaginationHelper::clamp($value, default: 15, max: 100)` pada semua Service + validasi FormRequest.
- **Risiko Regresi:** Sangat rendah.
- **Rencana Uji:** Feature test mengirim `?per_page=99999` dan `?per_page=-5` ke ke-11 endpoint → `meta.per_page` ter-clamp ke `1..100`.

#### B.4 `P1-41` — Pelebaran Kolom `students.nik_enc` & `applicants.nik_enc`
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** [000003](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/database/migrations/2026_10_07_000003_create_master_data_tables.php#L125), [000004](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/database/migrations/2026_10_07_000004_create_ppdb_tables.php#L56)
- **Bukti Kode:** `$table->string('nik_enc', 255);` — ciphertext Laravel AES-256-GCM/CBC untuk 16 digit sudah ~224 karakter (margin ~31 karakter).
- **Akar Masalah:** Kapasitas `varchar(255)` tidak memadai untuk overhead enkripsi.
- **Dampak:** Risiko `SQLSTATE[22001]: value too long` bila payload sedikit lebih panjang atau format cipher berubah.
- **Solusi Rekomendasi:** Migrasi non-destruktif `ALTER COLUMN nik_enc TYPE TEXT` (metadata-only di PG).
- **Risiko Regresi:** Nol.
- **Rencana Uji:** Uji simpan & dekripsi `nik_enc` dengan ciphertext >255 karakter di PostgreSQL.

#### B.5 `P1-42` — `UNIQUE` pada 13 Tabel `softDeletes()` & Kesiapan FK `schools(id)`
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** 13 tabel `softDeletes()` (`users`, `academic_years`, `semesters`, `rooms`, `subjects`, `teachers`, `staff`, `guardians`, `students`, `classes`, `teacher_salary_slips`, `schools`, `program_task_submissions`); 0 dari ~56 tabel punya FK ke `schools(id)`.
- **Akar Masalah:** Constraint `UNIQUE` standar masih memblokir baris yang sudah memiliki `deleted_at IS NOT NULL`.
- **Dampak:** Tidak bisa membuat ulang kode/username/NIS/NIP/kelas yang pernah di-soft-delete.
- **Solusi Rekomendasi:** Konversi constraint `UNIQUE` pada 13 tabel `softDeletes()` menjadi partial unique index `WHERE deleted_at IS NULL` di PostgreSQL. **FK `schools(id)` sengaja ditunda** ke fase multi-tenant terpisah (memasangnya sekarang akan memecah 100+ test yang memakai UUID acak `school_id`).
- **Risiko Regresi:** Rendah untuk partial unique index (pastikan `restore()` menangani konflik aktif dengan pesan 422).
- **Rencana Uji:** Create → soft-delete → create ulang dengan `nis`/`username`/`code` sama sukses; duplikat aktif ditolak `23505`.

#### B.6 `P1-43` — 78 Lokasi `LOWER(...) LIKE` yang Mengabaikan Indeks `gin_trgm_ops`
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** 78 pemanggilan di 20 file (termasuk `CounselingService:49`, `EarlyWarningService:83`, `ExamTokenRoomService:219`, `AlumniService:49`, `ExtracurricularService:141`, `StudentPointService:195`, `LibraryService:42`, `FinanceReportController:113-125`), sementara 4 indeks `gin_trgm_ops` (`students.full_name`, `teachers.full_name`, `posts.title`, `library_books.title`) di [000031](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/database/migrations/2026_10_29_000031_add_native_db_constraints.php#L60-L63) tidak pernah terpakai oleh `LOWER(col) LIKE`.
- **Akar Masalah:** Ekspresi `LOWER(col)` menghalangi planner PostgreSQL memanfaatkan index `gin_trgm_ops` pada kolom mentah.
- **Dampak:** Sequential scan penuh pada seluruh fitur pencarian teks.
- **Solusi Rekomendasi:** Helper/macro driver-aware `whereIlike` (`ILIKE` di `pgsql`, `LOWER() LIKE` di `sqlite`) pada 20 file + tambah indeks `gin_trgm_ops` selektif (`students.nis`, `applicants.full_name`, `exams.title`, `tickets.title`, `assets.name`, `announcements.title`).
- **Risiko Regresi:** Sangat rendah.
- **Rencana Uji:** `EXPLAIN` PostgreSQL membuktikan `Bitmap Index Scan` pada `idx_trgm_students_name` + uji pencarian case-insensitive hijau.

#### B.7 `P1-44` — `exam_sessions.deadline_at` vs `ends_at`, Missing FK, & CHECK Status Campuran
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** [ExamSyncService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Cbt/ExamSyncService.php#L167-L175) (mengisi `deadline_at`, membiarkan `ends_at = NULL`); [2026_11_03_000043](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/database/migrations/2026_11_03_000043_create_program_khusus_tables.php#L13-L88) (0 FK di `program_materials`/`program_tasks`); [2026_11_05_000045](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/database/migrations/2026_11_05_000045_add_manual_auto_values_to_grades.php#L46-L64) (`grade_histories`); [2026_10_29_000029](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/database/migrations/2026_10_29_000029_create_schools_table.php#L24) (`schools.logo_file_id`); [2026_10_26_000034](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/database/migrations/2026_10_26_000034_widen_cbt_status_check_constraints.php#L12-L15) & [2026_10_29_000032](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/database/migrations/2026_10_29_000032_add_status_enum_check_constraints.php#L24-L28).
- **Akar Masalah:** Drift penulisan kolom ganda, migrasi baru lupa memasang FK, dan CHECK dwibahasa (`approved` vs `disetujui`, `active` vs `aktif`) yang dapat membypass partial unique index (`bookings`, `ppdb_waves`).
- **Dampak:** Error code tak konsisten pada sesi CBT hasil sync, potensi data yatim referensial, & celah bypass double-booking/single-active-wave bila nilai alias EN masuk.
- **Solusi Rekomendasi:** Isi `ends_at = deadline_at` di `ExamSyncService::pushResults`, tambah FK `ON DELETE SET NULL` (`NOT VALID` pasca pre-flight orphan check) pada `program_*`, `grade_histories`, `schools.logo_file_id`; normalisasi CHECK status dilakukan terpisah setelah audit data lama & fixture test.
- **Risiko Regresi:** Rendah untuk sync `ends_at` & FK `NOT VALID`; sedang bila CHECK langsung dipersempit (karena itu ditunda ke sub-tahap terkendali).
- **Rencana Uji:** Test `ExamSyncService::pushResults` menghasilkan `ends_at === deadline_at` + uji FK `ON DELETE SET NULL` saat file/entity terkait dihapus.

---

### Klaster C — Konsistensi LMS, Penilaian, CBT, Program Khusus, & Operasional Akademik

#### C.1 `P0-16` *(Sisa 5 Sub-Item di Luar `showAssignment`)* — LMS, SpeedGrader, Wali Kelas, & Portal Siswa
- **Status:** `CONFIRMED` *(`showAssignment` & `storeAssignment` sudah selesai di `66ea9bc`; 5 sub-item berikut masih bermasalah)*
- **Lokasi File & Baris:**
  1. [TeacherLmsController.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Http/Controllers/Api/V1/Teacher/TeacherLmsController.php#L271-L296) (`submissions()`: tak eager-load `file`, tak kembalikan `notes`/`file`, `$sub->score ? ... : null` mengubah skor `0` jadi `null`, `is_late` hardcoded `false`).
  2. [TeacherHomeroomController.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Http/Controllers/Api/V1/Teacher/TeacherHomeroomController.php#L52-L84) (`attendanceRate=98.0`, `averageScore=86.5`, `semester='Ganjil'`, `attendanceRecap`, `isReportCardLocked=false` semuanya hardcoded).
  3. [portal/guru/tugas/[id]/penilaian/page.tsx](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/web/src/app/(portal)/portal/guru/tugas/[id]/penilaian/page.tsx#L126-L364) (viewer hardcoded `"LAPORAN PRAKTIKUM INFORMATIKA"`, tombol unduh dummy toast, `href="/guru/tugas"`, hardcoded `Kelas IX-A`).
  4. [portal/guru/nilai/page.tsx](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/web/src/app/(portal)/portal/guru/nilai/page.tsx#L158-L168) & [lms-api.ts](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/web/src/lib/lms-api.ts#L312-L315) (memanggil endpoint admin `PATCH /grade-periods/{id}/lock` dengan payload salah `{ is_locked }` → 422/403, bukan `POST /me/teacher/grades/lock`).
  5. [portal/siswa/tugas/page.tsx](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/web/src/app/(portal)/portal/siswa/tugas/page.tsx#L38-L116) (blok `catch` memalsukan status `'dikumpulkan'` & sukses; `<input type="file">` hanya menyimpan `file.name`, tidak mengirim `FormData`).
- **Dampak:** Guru menilai dokumen fiktif, statistik wali kelas palsu, tombol kunci nilai guru selalu gagal, dan siswa mengira pengumpulan gagal sudah terkirim padahal file biner tidak pernah terunggah.
- **Solusi Rekomendasi:** Sambungkan seluruh 5 titik ke data & endpoint nyata (`AttendanceAggregator` untuk walas, `FileStorageService` signed URL untuk viewer/unduh, `lockTeacherGrades` untuk kunci guru, `FormData` upload nyata + error state jujur di portal siswa).
- **Risiko Regresi:** Sedang pada `useSubmitTask` (wajib tetap mendukung submit berbasis `drive_link`/`notes` tanpa file).
- **Rencana Uji:** Feature test `submissions()` (skor `0`, `is_late=true`, `file`), `homeroom()` (agregat nyata), upload tugas multipart; Vitest `siswa/tugas` error state tidak menampilkan sukses.

#### C.2 `P1-10` — Materi Guru (`TeacherLmsController` & `portal/guru/materi`)
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** [TeacherLmsController.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Http/Controllers/Api/V1/Teacher/TeacherLmsController.php#L35-L125), [portal/guru/materi/page.tsx](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/web/src/app/(portal)/portal/guru/materi/page.tsx#L80-L284)
- **Bukti Kode:** `storeMaterial()` tidak memeriksa `$request->hasFile('file')` dan menautkan dummy `File::firstOrCreate(['object_key' => 'materials/default-modul.pdf'])`; mengembalikan `createdAt` sementara FE membaca `created_at` (`Invalid Date`); tombol unduh hanya `toast.success`.
- **Dampak:** File modul ajar guru hilang dan tidak bisa diunduh siswa/guru.
- **Solusi Rekomendasi:** Delegasikan penyimpanan file nyata via `TeachingMaterialService`/`FileStorageService` (validasi magic bytes/ukuran), sinkronkan DTO (`created_at` + `createdAt`, `file.id`, `category`), sambungkan tombol unduh ke `/files/{id}/url`.
- **Risiko Regresi:** Pastikan konsistensi tabel `teaching_materials` agar materi guru tetap muncul di `GET /teaching-materials` portal siswa.
- **Rencana Uji:** Feature test `POST /me/teacher/materials` dengan PDF asli memverifikasi berkas tersimpan di storage (bukan `default-modul.pdf`) dan dapat diunduh siswa.

#### C.3 `P1-11` — CBT AuthZ (Sisa Celah Bypass `cbt.exam.manage` & Filter Draft)
- **Status:** `PARTIALLY_FIXED` *(XSS Berita Acara di `ExamReportService` dan strip kunci di `ExamSyncService::pullPackage` **ALREADY_FIXED**)*
- **Lokasi File & Baris:** [ExamController.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Http/Controllers/Api/V1/ExamController.php#L86-L266), [ExamSessionService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Cbt/ExamSessionService.php#L690-L1210), [ExamReportService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Cbt/ExamReportService.php#L36-L395), [ExamTokenRoomService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Cbt/ExamTokenRoomService.php#L245-L273), [ExamSyncService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Cbt/ExamSyncService.php#L156-L280), [ExamService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/ExamService.php#L40-L44)
- **Bukti Kode:** `cbt.exam.manage` (dimiliki juga oleh role `teacher`) masih diperlakukan sebagai global admin; `getQuestions/getRooms/getParticipants` tidak memanggil `assertCanManage`; `ExamService:40-44` memakai `elseif` sehingga `GET /exams?status=draft` oleh siswa membypass `exclude_draft_archived`.
- **Dampak:** Guru A bisa mengintip kunci soal, token ruang, & memanipulasi ujian Guru B; siswa dapat mengenumerasi ujian `draft`.
- **Solusi Rekomendasi:** Satukan guard ownership di semua controller/service CBT (pisahkan role admin/operator CBT dari `cbt.exam.manage`), ubah `elseif` → `if` terpisah di `ExamService::listExams`, dan validasi `student_id`/`question_id` + panggil grading pada `ExamSyncService::pushResults`.
- **Risiko Regresi:** Verifikasi role `cbt_operator` pada seeder & test CBT agar tidak terkena 403 tak terduga.
- **Rencana Uji:** Feature test lintas-guru (403), proctor lintas-ruang (403), dan siswa `GET /exams?status=draft` (hasil kosong).

#### C.4 `P1-12` — CBT Session Lifecycle (`saveAnswer` Lock, 3 Jalur Terminal Tanpa Grading, & Counter Pelanggaran)
- **Status:** `PARTIALLY_FIXED` *(`start_at`/draft check & timeout grading di `ensureSessionActive`/`ExamAutoSubmitCommand` **ALREADY_FIXED**)*
- **Lokasi File & Baris:** [ExamSessionService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Cbt/ExamSessionService.php#L192-L199), [L434-L533](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Cbt/ExamSessionService.php#L434-L533), [L571-L593](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Cbt/ExamSessionService.php#L571-L593), [L767-L773](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Cbt/ExamSessionService.php#L767-L773), [L1001-L1166](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Cbt/ExamSessionService.php#L1001-L1166)
- **Akar Masalah:** `saveAnswer` tanpa transaksi/`lockForUpdate()` (rawan stale overwrite & race vs `finishSession`); 3 jalur terminal (`startOrResumeSession` timeout, `logViolation` auto-disqualify, `force_submit`) mengubah status `'selesai'` tanpa memanggil `gradeSessionObjectiveAnswers()`; `blur_count` vs `violation_count` tak sinkron antara `logViolation`, `recordHeartbeat`, dan `getLiveMonitoring`.
- **Dampak:** Skor siswa tetap `null` saat `force_submit`/diskualifikasi/timeout resume; jawaban terbaru tertimpa request lama.
- **Solusi Rekomendasi:** Transaksi singkat + `lockForUpdate()` di `saveAnswer()`, helper terminal tunggal `finalizeSession()` yang selalu memanggil `gradeSessionObjectiveAnswers()`, serta penyelarasan counter pelanggaran.
- **Risiko Regresi:** Pastikan transaksi `saveAnswer()` sangat ringan agar tidak menimbulkan lock contention pada rate 300 req/menit.
- **Rencana Uji:** Feature test `force_submit`, auto-disqualify, & timeout resume menghasilkan `score !== null`; race `saveAnswer` stale timestamp ditolak; konsistensi `blur_count`/`violation_count`.

#### C.5 `P1-13` — CBT DOCX Parser (`[BS] KUNCI: B`) & Import Integrity
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** [DocxQuestionParser.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Cbt/DocxQuestionParser.php#L442-L448), [QuestionImportService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Cbt/QuestionImportService.php#L64-L179)
- **Bukti Kode:** Pada auto-opsi `[BS]`, `'B'` dievaluasi `true` di opsi A (`Benar`) **dan** opsi B (`Salah`) sekaligus; `commitImport()` tanpa `DB::transaction()`/`lockForUpdate()`; `updateDraft()` hanya memvalidasi `pg`/`bs`.
- **Dampak:** Impor soal `[BS]` dengan `KUNCI: B` selalu gagal; double-commit menggandakan seluruh bank soal; soal `pg_kompleks`/`menjodohkan`/`isian` cacat lolos edit draf.
- **Solusi Rekomendasi:** Perbaiki logika `[BS]` agar tepat 1 opsi benar, bungkus `commitImport()` dengan transaksi + `lockForUpdate()`, ekstrak validator bersama untuk ke-6 tipe soal; pisahkan perubahan `dispatchSync` → `dispatch` setelah konfirmasi polling FE.
- **Risiko Regresi:** Sedang pada perubahan `dispatchSync` (wajib sinkron dengan FE & `BeCbt13DocxImportTest`).
- **Rencana Uji:** Unit test semua variasi kunci `[BS]`, test double-commit paralel (idempoten 1×), test `updateDraft` menolak tipe non-PG cacat.

#### C.6 `P1-14` — Grading IDOR & Konsistensi Buku Nilai
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** [GradeService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/GradeService.php#L240-L709), [TeacherGradingController.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Http/Controllers/Api/V1/Teacher/TeacherGradingController.php#L60-L348), [ExamGradingService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Cbt/ExamGradingService.php#L894-L957), [ProgressReportService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/ProgressReportService.php#L556)
- **Bukti Kode:** `authorizeGradeEntry()` loloskan semua guru saat `$component->class_id === null`; `storeGrade`/`bulkStoreGrades`/`deleteGrade`/`updateBulkGrades` tidak memverifikasi `student_id` ∈ kelas guru; `updateBulkGrades:293` melempar 422 pada sel kosong `null`; `grades()` & `gradeComponents()` mengabaikan komponen global (`class_id IS NULL`); `ProgressReportService:556` membaca `$period->is_locked` (kolom non-eksisten → selalu `false`).
- **Dampak:** IDOR tulis/hapus nilai siswa kelas/mapel lain; simpan nilai massal portal guru error 422 jika ada sel kosong; komponen global hilang di portal guru.
- **Solusi Rekomendasi:** Wajibkan cek penugasan mapel (`class_id IS NULL`) & keanggotaan aktif siswa pada semua jalur tulis nilai + `publishToGradebook()`; izinkan skip sel `null` & whitelist siswa/komponen di `updateBulkGrades()`; sertakan komponen global di `TeacherGradingController`; ganti `$period->is_locked` → `$period->status === 'locked'`.
- **Risiko Regresi:** Cek fixture test lama yang membuat guru + komponen global tanpa `TeachingAssignment`.
- **Rencana Uji:** Feature test IDOR lintas kelas/mapel → 403; `updateBulkGrades` dengan sel `null` → 200; komponen global muncul; `is_locked` akurat.

#### C.7 `P1-16` *(Khusus Teknis Non-Kebijakan)* — Proteksi Status `terbit` Rapor & Filter Ekskul
> **Kebijakan Sekolah Tetap Dihormati:** Peran/permission penerbitan E-Rapor (`canApprove`/`canGenerate`) **TIDAK DIUBAH** hingga pihak sekolah mengonfirmasi alur Guru Mapel → Wali Kelas → Kepala Sekolah.
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** [ReportCardService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/ReportCardService.php#L317-L327), [L428-L436](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/ReportCardService.php#L428-L436), [L655-L663](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/ReportCardService.php#L655-L663)
- **Bukti Kode:** `lockReportCard()` & `bulkLock()` menimpa `status = 'terkunci'` tanpa memeriksa apakah status sudah `'disetujui'`/`'terbit'`, padahal siswa/ortu hanya boleh membaca `['disetujui', 'terbit']`; `ExtracurricularMember` ditarik tanpa filter `status = 'aktif'` maupun batas tanggal semester.
- **Dampak:** Mengunci satu kelas menghilangkan E-Rapor yang sudah terbit dari akun siswa/ortu; ekskul nonaktif/tahun lalu ikut tercetak.
- **Solusi Rekomendasi:** Batasi `lockReportCard`/`bulkLock` hanya pada transisi `draft <-> terkunci` (tolak/skip `disetujui`/`terbit` dengan statistik `skipped_published_count`); filter `ExtracurricularMember` ke `status = 'aktif'` & `joined_at <= semester.end_date` (+ pertimbangkan kolom nullable `semester_id`).
- **Risiko Regresi:** Rendah — justru selaras penuh dengan state machine `ApprovalService`.
- **Rencana Uji:** Feature test `bulkLock` tidak menurunkan status rapor `'terbit'`, siswa/ortu tetap bisa akses detail/PDF; ekskul nonaktif tidak muncul di snapshot.

#### C.8 `P1-28` — Program Khusus (IDOR `submitTask`, `activeClasses()`, & Histori Mood/Mutabaah)
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** [ProgramKhususService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/ProgramKhususService.php#L34-L277), [SubmitProgramTaskRequest.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Http/Requests/ProgramKhusus/SubmitProgramTaskRequest.php#L12), [Student.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Models/Student.php#L72-L77), [MeStudentController.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Http/Controllers/Api/V1/MeStudentController.php#L176-L233)
- **Akar Masalah:** `submitTask` tidak cek `school_id`, `target_scope` (`student`/`class`), `due_at`, maupun kepemilikan `file_id`; `Student::classes()` tidak memfilter `wherePivotNull('left_at')`; `daily_mood` & `mutabaah_today` menimpa 1 slot JSON.
- **Dampak:** IDOR submit tugas & pencurian referensi file privat; siswa naik kelas menerima materi kelas lama; hilangnya histori harian Mood/Mutabaah.
- **Solusi Rekomendasi:** Tambah relasi `Student::activeClasses()`, validasi penuh `target_scope`/`school_id`/`file_id`/`due_at` di `submitTask`, simpan riwayat per tanggal `Y-m-d` (`daily_mood_history`, `mutabaah_history`) sambil mempertahankan field eksisting demi kompatibilitas FE.
- **Risiko Regresi:** Rendah jika relasi baru `activeClasses()` digunakan khusus di modul aktif (tanpa merusak caller historis `classes()`).
- **Rencana Uji:** Feature test IDOR tugas personal/kelas/file lain → 403; siswa pindah kelas hanya dapat materi kelas aktif; submit 2 hari menyimpan 2 catatan histori.

#### C.9 `P1-25` — PPDB Pasca-Enroll (Cross-Family IDOR, `escalateWaitlist`, Aktivasi & QR)
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** [PPDBEnrollmentService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/PPDBEnrollmentService.php#L190-L332), [PPDBSelectionService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/PPDBSelectionService.php#L335-L367), [PPDBReceiptAndStatsService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/PPDBReceiptAndStatsService.php#L41-L70)
- **Bukti Kode:** `Guardian::where('phone', $parent->phone)->first()` tanpa `school_id`/verifikasi keluarga; `escalateWaitlist()` mengabaikan `$data['track_id']` dan tidak mengecualikan `re_registration_deadline_at > now()`; URL `/auth/activate-guardian?token=...` & `/verifikasi/{token}` 404 (belum ada endpoint/halaman).
- **Dampak:** Cross-family IDOR data anak; promosi cadangan lintas jalur & gugur dini cadangan aktif; akun ortu baru mustahil diaktifkan; QR bukti PPDB 404.
- **Solusi Rekomendasi:** Hentikan auto-attach guardian hanya berdasarkan nomor HP mentah (wajib verifikasi/isolasi sekolah); filter `track_id` & lindungi `re_registration_deadline_at > now()` di `escalateWaitlist`; tambahkan endpoint throttled aktivasi token sekali pakai + verifikasi publik QR PPDB + halaman FE terkait (pertahankan path `/auth/activate-guardian?token=` sesuai `PPDBEnrollmentTest:523`).
- **Risiko Regresi:** Sedang pada perubahan logika deduplikasi guardian (pastikan tes saudara kandung tidak pecah).
- **Rencana Uji:** Feature test dua keluarga nomor HP sama tidak tertaut otomatis; `escalateWaitlist` hanya memproses `track_id` target dan melindungi cadangan aktif; aktivasi token valid/replay; verifikasi QR valid/invalid.

#### C.10 `P1-26` *(Sisa)* — Operasional Keuangan
- **Status:** `PARTIALLY_FIXED` *(CSV formula injection di `FinanceReportController` **ALREADY_FIXED** di `31bfc29`)*
- **Lokasi File & Baris:** [routes/console.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/routes/console.php#L1-L45), [ManualGiroPaymentChannel.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Finance/Channels/ManualGiroPaymentChannel.php#L32-L43), [FinanceService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Finance/FinanceService.php#L470-L505)
- **Akar Masalah:** `finance:send-due-reminders` & `finance:reconcile` tidak terjadwal; nomor rekening hardcoded `'015-100-2026'`; `voidPayment()` tidak mengirim notifikasi ke pembayar/wali.
- **Solusi Rekomendasi:** Jadwalkan kedua command di `routes/console.php` (tanpa menyentuh jadwal `complaints:escalate-overdue` dari `69b3832`); baca rekening dari `SiteSetting`/config; kirim `Notification` di `voidPayment()`.
- **Risiko Regresi:** Rendah (verifikasi argumen default `finance:reconcile` bersifat aman/read-only atau rekonsiliasi aman).
- **Rencana Uji:** Test jadwal `Schedule`, test `ManualGiroPaymentChannel` baca `SiteSetting`, test `voidPayment` membuat notifikasi.

#### C.11 `P1-27` — Cron Presensi, Early Warning Duplikat, & Auto-Fill Akhir Pekan
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** [DailyAttendanceSummaryCommand.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Console/Commands/DailyAttendanceSummaryCommand.php#L37-L67), [EarlyWarningService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Bk/EarlyWarningService.php#L178-L455), [AcademicAttendanceService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/AcademicAttendanceService.php#L351-L364), [ApprovalService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Rpt/ApprovalService.php#L227-L240)
- **Akar Masalah:** `DailyAttendanceSummaryCommand` memfilter `status = 'active'` (skip siswa `'aktif'`), menghitung raw row tanpa `AttendanceAggregator`, dan membuat `rule_code='ATT_LOW'`/`status='aktif'` yang bentrok dengan `EarlyWarningService` (`ATTENDANCE_LOW`/`open`) 30 menit kemudian; loop auto-fill izin mengisi Sabtu & Minggu.
- **Dampak:** Alert Early Warning terlewat/ganda; hari libur tercatat sakit/izin sehingga menggelembungkan rekap rapor.
- **Solusi Rekomendasi:** Pusatkan evaluasi Early Warning di `EarlyWarningService` (status `IN ('aktif','active')` + `AttendanceAggregator`), hapus duplikasi `ATT_LOW` di `DailyAttendanceSummaryCommand`; skip `$date->isWeekend()` (dan tabel `holidays` bila tersedia) pada loop izin disetujui di `AcademicAttendanceService` & `ApprovalService`.
- **Risiko Regresi:** Rendah — cek test yang membuat izin pada akhir pekan agar memakai tanggal hari kerja.
- **Rencana Uji:** Uji 2 cron malam menghasilkan tepat 1 EW per siswa `'aktif'`; izin Jumat–Senin tidak membuat record presensi pada Sabtu & Minggu.

#### C.12 `P1-32` — Dashboard KPI & Keamanan/Limit CSV Laporan
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** [DashboardService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Rpt/DashboardService.php#L160-L243), [ReportService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Rpt/ReportService.php#L138-L519)
- **Bukti Kode:** `DashboardService` query `Booking::where('status', 'menunggu')` (DB memakai `'diajukan'` → selalu `0`), `Ticket::where('priority', 'darurat')` tanpa filter status belum selesai, `gradesByLevel` join `class_students` tanpa `left_at IS NULL` (double-count siswa naik kelas), `total_students` tanpa filter siswa aktif; `ReportService::buildCsv:487-519` memanggil `fputcsv` mentah tanpa sanitasi formula `=,+,-,@` dan memotong diam-diam pada `limit(5000)`.
- **Dampak:** KPI pimpinan tidak akurat; risiko CSV Formula Injection di Excel; data terpotong tanpa peringatan.
- **Solusi Rekomendasi:** Perbaiki ke-4 query di `DashboardService`; tambahkan sanitasi CSV formula + UTF-8 BOM + header/catatan bila data terpotong (atau streaming `cursor()` untuk ekspor besar) pada `ReportService::buildCsv`.
- **Risiko Regresi:** Rendah (verifikasi `DashboardTest` & `ReportTest` eksisting).
- **Rencana Uji:** Feature test booking `'diajukan'` terhitung, tiket darurat `'selesai'` tidak terhitung, siswa naik kelas tidak double-count, dan sel CSV diawali `=CMD` ter-prefix `'`.

---

### Klaster D — Komunikasi, Notifikasi, BK, & Harmoni dengan Commit `69b3832`

#### D.1 `P1-18` — `NotificationBell`, `sw.js`, & Preferensi Notifikasi (422 Permanen)
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** [notification-bell.tsx](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/web/src/components/notifications/notification-bell.tsx#L59-L61), [sw.js](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/web/public/sw.js#L67-L83), [web-push.ts](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/web/src/features/notifications/web-push.ts#L453-L466), [UpdateNotificationPreferenceRequest.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Http/Requests/Ntf/UpdateNotificationPreferenceRequest.php#L18-L33), [NotificationPreference.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Models/NotificationPreference.php#L29-L78)
- **Akar Masalah:** `web-push.ts` mengirim 13 topic ID (`announcement`, `attendance`, dll.) sementara BE hanya mengizinkan 8 tipe (`academic_announcement`, `attendance_alert`, dll.) → **zero-overlap (422)** lalu ditelan `catch`; `NotificationBell` tanpa polling; ikon `sw.js` 404.
- **Dampak:** Preferensi notifikasi tidak pernah tersimpan di DB; badge notifikasi tak pernah update real-time.
- **Solusi Rekomendasi:** Mapping kanonis 2-arah + lengkapi `allowed_roles` (`principal`, `counselor`, `admin_tu`, `humas`, `kesiswaan`); hapus silent-swallow di FE; polling 60s (pause saat `document.hidden`) di `NotificationBell`; perbaiki ikon `sw.js` ke `/images/logo.png`.
- **Risiko Regresi:** Perlu sinkronisasi mock di `apps/web/src/test/web-push.test.ts:314-319`.
- **Rencana Uji:** Feature test BE simpan preferensi untuk seluruh role → 200; Vitest polling & pause `NotificationBell`.

#### D.2 `P1-19` — `MessagingService::dispatchToProvider()` & UI Admin Notifikasi
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** [MessagingService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Ntf/MessagingService.php#L275-L297), [admin/notifications/page.tsx](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/web/src/app/(admin)/admin/notifications/page.tsx#L1-L8)
- **Akar Masalah:** `dispatchToProvider()` 100% simulasi (selalu menandai `'sent'` meski tidak mengirim Email/WA); `/admin/notifications` hanya render inbox pribadi.
- **Dampak:** False positive log pengiriman di produksi; admin tidak punya UI Broadcast/Template/Log/Retry.
- **Solusi Rekomendasi:** Adapter driver nyata (`Mail` untuk email, HTTP gateway env-driven untuk WA, mode jujur `provider_unconfigured` di produksi jika belum disetel); perluas `/admin/notifications` menjadi 4 tab (Kotak Masuk, Broadcast, Template, Log & Retry) dengan `PermissionGate`.
- **Risiko Regresi:** Jaga kompatibilitas `MessagingTest.php` pada `APP_ENV=testing` dengan `Mail::fake()` / `Http::fake()`.
- **Rencana Uji:** Feature test Email/WA adapter + Vitest 4 tab `/admin/notifications`.

#### D.3 `P1-20` — Deep Link Notifikasi (Key Mismatch & 4 Dead Routes)
- **Status:** `CONFIRMED` *(termasuk 2 notifikasi baru dari `69b3832` yang memakai `action_url`)*
- **Lokasi File & Baris:** [notification-bell.tsx](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/web/src/components/notifications/notification-bell.tsx#L124-L126), [notification-inbox-view.tsx](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/web/src/components/notifications/notification-inbox-view.tsx#L121-L123), [ProgressReportReminderCommand.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Console/Commands/ProgressReportReminderCommand.php#L31) (`/grades`), [TicketSlaReminderCommand.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Console/Commands/TicketSlaReminderCommand.php#L62) (`/facility/tickets`), [MouExpiryReminderCommand.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Console/Commands/MouExpiryReminderCommand.php#L52) (`/partnerships`), [ReminderService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Ntf/ReminderService.php#L345) (`/portal/wali/presensi`), [ComplaintService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/ComplaintService.php#L572-L659) (`action_url`).
- **Akar Masalah:** FE hanya membaca `target_url`/`url`, sedangkan 100% BE menulis `deep_link`/`action_url`; 4 rute BE tidak ada di Next.js App Router.
- **Dampak:** Klik notifikasi selalu gagal menuju halaman tujuan.
- **Solusi Rekomendasi:** FE fallback `target_url ?? action_url ?? deep_link ?? url` + `getSafeDeepLink()`; kanonisasi BE ke `target_url` (dual-key `target_url` + `action_url` di `ComplaintService` agar nol regresi dengan `69b3832`), perbaiki 4 dead routes, dan perbaiki isolasi `school_id` pada `ComplaintService::escalateOverdue:630-665`.
- **Risiko Regresi:** Perlu update assertion path lama di `cross-role-matrix.test.ts:190-198` dan jaga 100% test `ComplaintTriageTest.php`.
- **Rencana Uji:** Unit test FE ekstraktor + anti-XSS URL; feature test BE seluruh command menghasilkan `target_url` valid.

#### D.4 `P1-21` — Segmentasi Pengumuman Ortu & Pembatasan Cakupan Guru
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:** [AnnouncementService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Ntf/AnnouncementService.php#L24-L383), [NotificationService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Ntf/NotificationService.php#L139-L146)
- **Akar Masalah:** `AnnouncementService` hanya me-resolve kelas `Student`, mengabaikan `Guardian -> students -> activeClasses`; role `teacher` dimasukkan ke `isStaffOrAdmin()` sehingga bebas membuat pengumuman `'semua'`/kelas lain dan membaca semua draft sekolah; `NotificationService::broadcast('class')` tidak filter `left_at IS NULL` & tak sertakan guardian.
- **Dampak:** Ortu tak menerima pengumuman kelas anaknya; guru bisa broadcast seluruh sekolah tanpa batas cakupan.
- **Solusi Rekomendasi:** Pisahkan pengelola pengumuman tingkat sekolah dari `teacher`; batasi `teacher` ke kelas ajar/walas sendiri; resolve kelas & tingkat anak bagi `Guardian`; filter `left_at IS NULL` di broadcast kelas.
- **Risiko Regresi:** Verifikasi `AnnouncementTest.php` agar test peran teacher pada pengumuman `'role'` tetap hijau.
- **Rencana Uji:** Feature test Ortu melihat pengumuman kelas/tingkat anaknya (dan 403 kelas lain); Guru ditolak 403 saat membuat pengumuman `'semua'` atau kelas guru lain.

#### D.5 `P1-22` — Event Notifikasi Hilang & Spam SLA Tiket Per Jam
- **Status:** `PARTIALLY_FIXED` *(3 event sudah ada: `decideLeaveRequest`, `reviewApproval(LeaveRequest)`, `gradeSubmission`; 6 layanan lain + deduplikasi SLA tiket masih hilang)*
- **Lokasi File & Baris:** [AcademicAttendanceService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/AcademicAttendanceService.php#L181-L253), [ApprovalService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Rpt/ApprovalService.php#L291-L425), [LmsService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/LmsService.php#L85-L662), [CounselingRequestService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Bk/CounselingRequestService.php#L103-L350), [TicketService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/Sar/TicketService.php#L111-L275), [TeacherStaffService.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Services/TeacherStaffService.php#L717-L812), [TicketSlaReminderCommand.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Console/Commands/TicketSlaReminderCommand.php#L29-L81)
- **Akar Masalah:** 6 layanan operasional tidak memicu notifikasi; `TicketSlaReminderCommand` berjalan tiap jam tanpa deduplikasi, melewatkan status `'diproses'`, dan tidak memfilter `school_id`.
- **Dampak:** Operasional harian tanpa pemberitahuan; spam notifikasi SLA berulang setiap jam; tiket `'diproses'` lepas pantauan SLA.
- **Solusi Rekomendasi:** Tambahkan notifikasi **steril** (0 teks sensitif BK/nilai/nominal gaji sesuai `09_SECURITY_PRIVACY.md`) + `target_url` kanonis di ke-6 layanan; perbaiki `TicketSlaReminderCommand` (status `'diproses'`, filter `school_id`, deduplikasi 24 jam via `ReminderDispatch`).
- **Risiko Regresi:** Pastikan `BkAndComplaintPrivacyTest` & `LmsNotificationPrivacyTest` tetap 100% steril.
- **Rencana Uji:** Feature test tiap event + assertion anti-kebocoran konten sensitif + uji idempotensi 2× run `sarpras:ticket-sla-reminder`.

#### D.6 `P1-23` — UI Komunikasi & Celah Privasi `/me/counseling-requests`
- **Status:** `CONFIRMED`
- **Lokasi File & Baris:**
  - **Celah Privasi Kritis:** [MeStudentController.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/app/Http/Controllers/Api/V1/MeStudentController.php#L508-L610) — `GET /api/v1/me/counseling-requests` untuk guardian memfilter `where('student_id', ...)` **tanpa `where('requested_by', $user->id)`**, menembus proteksi P0-18 sehingga ortu bisa membaca curhat mandiri siswa! Selain itu `preferred_schedule` & `counselor_name` dibuang diam-diam.
  - [portal/ortu/konseling/page.tsx](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/web/src/app/(portal)/portal/ortu/konseling/page.tsx#L52-L199): hardcode `"Ibu Ratna Dewi, S.Pd., Kons."` & `wa.me/6281334567895`; status `'ditolak'` jatuh ke `'menunggu'`.
  - [portal/guru/perizinan/page.tsx](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/web/src/app/(portal)/portal/guru/perizinan/page.tsx#L66-L586): `'dispen'` jadi `'izin'`, tombol WA stub toast, tanpa input `decision_notes`, semester hardcoded.
  - [admin/public-relations/page.tsx](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/web/src/app/(admin)/admin/public-relations/page.tsx#L1442-L1601) & [types.ts](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/web/src/features/public-relations/types.ts#L68-L97): **`selectedComplaint.attachments` masih belum dirender di Modal 3** (pasca `69b3832`); dropdown status Modal 3 belum selaras dengan `ALLOWED_TRANSITIONS` baru dari `69b3832`.
- **Solusi Rekomendasi:** Kunci `where('requested_by', $user->id)` di `MeStudentController::counselingRequests()` untuk guardian; simpan `preferred_schedule`/tanggal & hapus kontak fiktif; render galeri `selectedComplaint.attachments` via `signed_url` + harmonisasi `ALLOWED_TRANSITIONS` di Modal 3 tanpa mengubah elemen/teks yang diuji `adm-guest-02-bullying-review.test.tsx`; perbaiki `'dispen'`, `decision_notes`, dan WA nyata di `guru/perizinan`.
- **Risiko Regresi:** Jaga kompatibilitas `fix-guru-08.test.tsx` & `adm-guest-02-bullying-review.test.tsx`.
- **Rencana Uji:** Feature test BE guardian tidak bisa membaca curhat siswa lewat `/me/counseling-requests` (konsisten P0-18); Vitest render lampiran pengaduan + seluruh test `69b3832` hijau.

#### D.7 `P1-24` *(Sisa)* — Throttle Notifikasi & `DELETE /devices/{token}` + Panjang Kolom `fcm_token`
- **Status:** `PARTIALLY_FIXED` *(`CounselingSession.follow_up` **ALREADY_FIXED** di Tier 1A `9d9dcd3`)*
- **Lokasi File & Baris:** [routes/api/notifications.php](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/routes/api/notifications.php#L9-L83), [000002](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/api/database/migrations/2026_10_07_000002_create_identity_and_access_tables.php#L71) (`fcm_token` `string(500)` vs FormRequest `max:1000`), [notifications-api.ts](file:///home/j1nkisaragi/projects/SMPLABUMFIXREV/apps/web/src/features/notifications/notifications-api.ts#L61)
- **Akar Masalah:** Endpoint broadcast/send/retry/announcement/devices tanpa `throttle`; token WebPush JSON mengandung karakter `/` sehingga path `DELETE /devices/{token}` selalu 404; panjang kolom `fcm_token(500)` lebih sempit dari validasi `max:1000` sehingga token WebPush >500 char gagal 500 di PG.
- **Solusi Rekomendasi:** Tambah `throttle` terarah di `routes/api/notifications.php`; tambah `DELETE /api/v1/devices` (body JSON) sambil mempertahankan `DELETE /devices/{token}` dengan `->where('token', '.*')` demi kompatibilitas Android; migrasi aditif melebarkan `devices.fcm_token` → `text`.
- **Risiko Regresi:** Nol bagi klien Android selama rute lama dipertahankan.
- **Rencana Uji:** Feature test `DELETE /devices` dengan JSON WebPush ber-slash → 200; `POST /devices` 700 char → 201; `DELETE /devices/{simpleToken}` → 200; rate limit broadcast → 429.
