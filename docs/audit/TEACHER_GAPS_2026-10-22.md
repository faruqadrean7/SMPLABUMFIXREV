# LAPORAN AUDIT & RESOLUSI KESENJANGAN PORTAL GURU (TEACHER GAPS)
**Sistem Informasi Sekolah Terpadu Labschool Universitas Negeri Malang (SISTLABUM)**

- **Tanggal Dokumen:** 22 Oktober 2026
- **Branch:** `fix/teacher-gaps` (turunan dari `audit/teacher-readiness`)
- **Status Akhir:** ✅ **100% RESOLVED & VERIFIED** (Seluruh 7 Area Kesenjangan A–G Terpenuhi)
- **Metode Verifikasi:** Dual-Evidence (Backend Automated Feature Test + Playwright Real UI Screenshot)

---

## 1. Ringkasan Eksekutif & Keputusan Arsitektur

Audit kesiapan guru sebelumnya mengidentifikasi sejumlah kesenjangan fungsional, integritas data, dan isolasi keamanan (IDOR & privasi) pada modul Portal Guru (`apps/web/(portal)/portal/guru`) dan Backend (`apps/api` `/api/v1/me/teacher/*`).

Berdasarkan arahan pemangku kepentingan, dua keputusan arsitektur disepakati:
1. **Bank Soal Mandiri Guru:** Guru mata pelajaran diberikan hak membuat dan mengelola bank soal mandiri langsung dari Portal Guru tanpa membuka akses ke konsol `/admin`. Seluruh operasi dibatasi secara ketat pada mata pelajaran yang diampu dan kepemilikan bank soal milik guru bersangkutan.
2. **Penyusunan Materi vs RPP Builder:** Fitur builder RPP interaktif ditunda; penyusunan perangkat ajar difokuskan pada manajemen modul/materi ajar digital (upload berkas PDF/DOCX/PPT dan pranala) dengan kontrol keamanan akses berkas privat berbasis signed URL.

Seluruh 7 area kesenjangan (A s.d. G) telah diselesaikan melalui serangkaian commit atomik berdisiplin tinggi, diverifikasi oleh **1.250 automated backend tests (100% pass, 8.180 assertions)**, **795 frontend tests (100% pass)**, linting bersih (**Laravel Pint 100% pass**), serta pembuktian visual Playwright dengan **14 screenshot resolusi tinggi**.

---

## 2. Rincian Pemenuhan Kesenjangan (Bagian A – G)

### A. Bank Soal di Portal Guru (`apps/web/(portal)/portal/guru`)
- **Masalah Semula:** Guru mapel belum memiliki rute dan UI mandiri untuk menyusun bank soal dan menjadwalkan ujian CBT dari portal guru tanpa masuk ke area admin kurikulum.
- **Implementasi Backend:**
  - Menambahkan rute ber-scope di `routes/api.php`:
    - `GET/POST /api/v1/me/teacher/question-banks` (CRUD bank soal milik guru).
    - `POST /api/v1/me/teacher/questions` & `PUT/DELETE /api/v1/me/teacher/questions/{id}` (CRUD butir soal).
    - `POST /api/v1/me/teacher/exams` (pembuatan paket ujian dan penjadwalan ke kelas ampu).
  - Implementasi kontrol kepemilikan di `MeTeacherController.php`:
    - Validasi mata pelajaran: hanya mapel dalam `taughtSubjectIds` yang diizinkan.
    - Validasi butir soal: mendukung seluruh tipe soal (Pilihan Ganda, PG Kompleks, Benar/Salah, Menjodohkan, Isian Singkat, Uraian) beserta metadata Taksonomi Bloom dan tingkat kesulitan.
    - Kunci ujian aktif: butir soal yang terhubung ke ujian yang sedang berjalan (`in_progress` / `published`) dikunci secara ketat dan menolak pembaruan/penghapusan (HTTP 422).
- **Implementasi Frontend:**
  - Menambahkan tab "Bank Soal Mandiri" dan modal penyusunan soal pada halaman `/portal/guru/ujian`.
- **Bukti Pengujian & Visual:**
  - Automated Test: `Tests\Feature\Teacher\TeacherQuestionBankFeatureTest` (8 test lulus, 44 assertions).
  - Bukti UI: `docs/audit/screenshots/teacher/03_bank_soal_ujian.png`.

---

### B. Integritas Nilai (Semua Jalur Tulis Nilai)
- **Masalah Semula:** Berbagai jalur tulis nilai belum seragam dalam menegakkan aturan penguncian periode (*locked grade period*), validasi batas nilai 0–100, verifikasi penugasan guru ampu, dan pencatatan jejak audit (*audit logs*).
- **Implementasi Backend:**
  - Mengaudit dan menyelaraskan 6 jalur tulis nilai:
    1. Input nilai tunggal: `POST /api/v1/grades`
    2. Input nilai massal (*bulk*): `POST /api/v1/grades/bulk`
    3. Input nilai portal guru: `POST /api/v1/me/teacher/grades`
    4. Penilaian tugas LMS / SpeedGrader: `POST /api/v1/me/teacher/assignments/{id}/submissions/{subId}/grade` & `POST /api/v1/lms/assignments/{id}/submissions/{subId}/grade`
    5. Penerbitan nilai CBT ke buku nilai: `POST /api/v1/cbt/exams/{id}/publish-gradebook`
    6. Penghapusan nilai: `DELETE /api/v1/grades/{id}`
  - Seluruh jalur kini secara konsisten:
    - Menolak mutasi jika periode penilaian berstatus `locked` (HTTP 422).
    - Memvalidasi rentang nilai tepat 0.00 – 100.00 (HTTP 400/422).
    - Memverifikasi bahwa guru tercatat mengampu kelas dan mapel terkait (HTTP 403 bagi guru lain).
    - Mencatat `AuditLog` dengan informasi `actor_id`, `action`, `client_ip`, dan `request_id`.
- **Bukti Pengujian & Visual:**
  - Automated Test: `Tests\Feature\Teacher\TeacherGradeIntegrityFeatureTest` (6 test lulus, 23 assertions).
  - Bukti UI: `docs/audit/screenshots/teacher/07_manajemen_nilai.png`.

---

### C. Siklus Hidup Rapor Wali Kelas (Homeroom Lifecycle)
- **Masalah Semula:** Rapor semester belum memiliki mekanisme snapshot JSONB beku yang terisolasi dari mutasi nilai pasca-terbit, belum mencegah penerbitan ganda, dan penerbitan oleh walas belum terikat pada status penguncian periode.
- **Implementasi Backend (`ReportCardService.php`):**
  - **Siklus Hidup Lengkap:**
    1. Draf: Wali kelas mengompilasi draf rapor kelas binaannya via `POST /api/v1/report-cards/generate`.
    2. Pratinjau: Wali kelas meninjau pratinjau kalkulasi KKTP via `GET /api/v1/report-cards/preview`.
    3. Proteksi Kunci Periode: Wali kelas mencoba menerbitkan rapor saat periode penilaian masih `open` ditolak tegas dengan HTTP 422.
    4. Penerbitan Resmi: Saat seluruh periode penilaian telah berstatus `locked`, walas berhasil menerbitkan rapor (HTTP 200) dan membangkitkan `verify_token` kriptografis unik.
    5. Immutability (Snapshot Beku): Seluruh data nilai, ekstrakurikuler, dan presensi disimpan dalam kolom JSONB `data_snapshot`. Pengubahan nilai pada tabel master pasca penerbitan rapor terbukti **0% memengaruhi isi rapor yang telah terbit**.
    6. Anti-Terbit Ganda: Upaya menerbitkan ulang rapor yang telah terbit ditolak dengan HTTP 422.
    7. Verifikasi Dokumen Publik: URL publik `/api/v1/public/verify/{token}` memvalidasi keaslian dokumen rapor tanpa mengekspos rincian internal privat.
    8. Akses Guru Biasa: Guru non-wali kelas ditolak dengan HTTP 403 di setiap tahapan (draf, pratinjau, pengesahan).
- **Bukti Pengujian & Visual:**
  - Automated Test: `Tests\Feature\Teacher\TeacherReportCardLifecycleFeatureTest` (2 test lulus, 27 assertions).
  - Bukti UI: `docs/audit/screenshots/teacher/08_wali_kelas_rapor.png`.

---

### D. Materi Ajar & Keamanan Berkas Digital
- **Masalah Semula:** Berkas materi ajar sebelumnya berisiko diakses langsung secara publik tanpa verifikasi hak akses rombel dan guru lain berpotensi memanipulasi materi kelas rekannya.
- **Implementasi Backend (`LmsService.php`):**
  - **Visibilitas Berkas:** Seluruh unggahan materi ajar diubah ke visibilitas `'privat'` di storage service.
  - **Signed URL Berbatas Waktu:** Pengunduhan materi wajib menggunakan URL bertanda tangan kriptografis dengan waktu kedaluwarsa. URL tanpa tanda tangan, URL kedaluwarsa, atau parameter tanda tangan yang dimanipulasi menghasilkan HTTP 403 Forbidden.
  - **Validasi Format & Ukuran:** Unggahan berkas di atas batas (50MB) ditolak dengan HTTP 400. Ekstensi terlarang berkas skrip berbahaya (`.php`, `.sh`, `.exe`, `.bash`) ditolak tegas (HTTP 400).
  - **Isolasi Kelas (IDOR Protection):** Siswa dari kelas lain ditolak mengakses materi (HTTP 403). Guru lain yang tidak mengampu mapel dan kelas tersebut ditolak melakukan pembaruan atau penghapusan materi (HTTP 403).
- **Bukti Pengujian & Visual:**
  - Automated Test: `Tests\Feature\Teacher\TeacherMaterialSecurityFeatureTest` (5 test lulus, 26 assertions).
  - Bukti UI: `docs/audit/screenshots/teacher/02_upload_materi.png`.

---

### E. Systematic IDOR Sweep (Pembersihan Celah Otorisasi Menyeluruh)
- **Investigasi & Temuan Celah Nyata:**
  - Selama sweep kode, ditemukan kerentanan IDOR pada `MeTeacherController::updateQuestion` dan `destroyQuestion`. Kode sebelumnya hanya memeriksa apakah guru mengampu mata pelajaran yang sama (`taughtSubjectIds->contains(...)`), yang memungkinkan Guru B mengedit atau menghapus butir bank soal buatan Guru A jika keduanya mengajar mapel yang sama.
  - **Perbaikan Keamanan:** Memperketat validasi kepemilikan menjadi `$question->bank->owner_id === $request->user()->id`.
- **Automated IDOR Suite (`TeacherSystematicIdorSweepTest.php`):**
  - Menerapkan arsitektur data provider PHPUnit 11 (`#[DataProvider('idorScenariosProvider')]`) yang mencakup **21 skenario serangan IDOR lintas peran**:
    1. Guru B mencoba mengedit materi Guru A (`PATCH /api/v1/me/teacher/materials/{id}`) → 403.
    2. Guru B mencoba menghapus materi Guru A (`DELETE /api/v1/me/teacher/materials/{id}`) → 403.
    3. Guru B mencoba mengedit tugas Guru A (`PUT /api/v1/me/teacher/assignments/{id}`) → 403.
    4. Guru B mencoba menilai tugas kelas Guru A (`POST /api/v1/me/teacher/assignments/{id}/submissions/{id}/grade`) → 403.
    5. Guru B mencoba memanipulasi presensi kelas Guru A (`PUT /api/v1/me/teacher/attendance/sessions/{id}`) → 403.
    6. Guru B mencoba mengubah jurnal mengajar Guru A (`PUT /api/v1/me/teacher/journals/{id}`) → 403.
    7. Guru B mencoba mengubah butir soal bank soal Guru A (`PUT /api/v1/me/teacher/questions/{id}`) → 403.
    8. Guru B mencoba menghapus butir soal milik Guru A (`DELETE /api/v1/me/teacher/questions/{id}`) → 403.
    9. Guru B mencoba mengakses data wali kelas Guru A (`GET /api/v1/me/teacher/classes/{id}/students`) → 403.
    10. Guru B mengintip slip gaji milik Guru A (`GET /api/v1/me/teacher/salary-slips/{id}`) → 403.
    11. Guru B mengunduh PDF slip gaji milik Guru A (`GET /api/v1/me/teacher/salary-slips/{id}/pdf`) → 403.
    12. Siswa eskalasi membuat materi ajar (`POST /api/v1/me/teacher/materials`) → 403.
    13. Siswa eskalasi menilai pengumpulan tugas teman (`POST /api/v1/me/teacher/assignments/{id}/submissions/{id}/grade`) → 403.
    14. Siswa mengakses daftar siswa wali kelas (`GET /api/v1/me/teacher/homeroom`) → 403.
    15. Siswa mengakses slip gaji guru (`GET /api/v1/me/teacher/salary-slips`) → 403.
    16. Siswa mengintip presensi mandiri guru (`GET /api/v1/me/teacher/self-attendance/history`) → 403.
    17. Wali murid mencoba mengakses materi via portal guru (`GET /api/v1/me/teacher/materials`) → 403.
    18. Wali murid mencoba menginput nilai siswa (`POST /api/v1/me/teacher/grades`) → 403.
    19. Guru biasa mencoba generate rapor rombel lain (`POST /api/v1/report-cards/generate`) → 403.
    20. Guru biasa mencoba lock rapor rombel lain (`PATCH /api/v1/report-cards/{id}/lock`) → 403.
    21. Guru biasa mencoba approve rapor rombel lain (`PATCH /api/v1/report-cards/{id}/approve`) → 403.
  - **Hasil Evaluasi:** Seluruh 21 skenario menghasilkan HTTP 403 Forbidden atau 404 Not Found. Tidak pernah ada celah HTTP 200 (akses lolos) maupun HTTP 500 (crash).
- **Bukti Pengujian & Visual:**
  - Automated Test: `Tests\Feature\Teacher\TeacherSystematicIdorSweepTest` (21 test lulus, 63 assertions).
  - Bukti UI: `docs/audit/screenshots/teacher/13_idor_forbidden.png`.

---

### F. Konsistensi Lintas-Peran (End-to-End Data Flow)
- **Implementasi Automated Multi-Role Test (`TeacherCrossRoleConsistencyFeatureTest.php`):**
  Membuktikan bahwa seluruh mutasi data yang dilakukan oleh guru di Portal Guru langsung termutasi secara sinkron, valid, dan terbaca di portal pengguna terkait lainnya:
  1. **Alur 1 (Nilai):** Guru menginput nilai komponen formatif siswa binaan di kelas ampu via portal guru (`POST /api/v1/me/teacher/grades`) → Siswa melihat nilai tersebut di `/portal/siswa/nilai` (`GET /api/v1/me/grades`) dan Orang Tua melihat nilai yang identik untuk anaknya di `/portal/ortu/anak/{id}/nilai` (`GET /api/v1/me/guardian/children/{id}/grades`).
  2. **Alur 2 (Tugas & Feedback):** Guru membuat tugas LMS dengan tenggat waktu (`POST /api/v1/me/teacher/assignments`) → Siswa melihat tugas di daftar tugas aktif (`GET /api/v1/me/student/assignments`), mengunggah berkas pengumpulan tugas (`POST /api/v1/me/student/assignments/{id}/submit`) → Guru memeriksa penyerahan dan memberikan skor 88.50 beserta catatan koreksi komprehensif (`POST /api/v1/me/teacher/assignments/{id}/submissions/{subId}/grade`) → Siswa melihat status tugas berganti menjadi `'dinilai'`, skor 88.50, dan catatan umpan balik guru tampil utuh di portal siswa.
  3. **Alur 3 (Presensi):** Guru membuka sesi presensi mata pelajaran kelas ampu dan mencatat kehadiran siswa dengan status `'hadir'` (`POST /api/v1/me/teacher/attendance/sessions`) → Orang tua siswa membuka dashboard riwayat kehadiran anak (`GET /api/v1/me/guardian/children/{id}/attendance`) dan melihat catatan absensi hari tersebut terdata akurat tanpa distorsi.
  4. **Alur 4 (Persetujuan Cuti Eksekutif):** Guru mengajukan izin cuti sakit selama 2 hari dengan lampiran surat dokter (`POST /api/v1/me/teacher/leave-requests`) → Kepala sekolah/Admin membuka antrean persetujuan eksekutif (`GET /api/v1/admin/executive-approvals`) dan memberikan persetujuan resmi (`POST /api/v1/admin/executive-approvals/{id}/decide`) → Status izin cuti pada riwayat guru di portal guru (`GET /api/v1/me/teacher/leave-requests`) secara otomatis diperbarui menjadi `'approved'`, dan notifikasi persetujuan diterbitkan untuk guru.
- **Bukti Pengujian:**
  - Automated Test: `Tests\Feature\Teacher\TeacherCrossRoleConsistencyFeatureTest` (4 test lulus, 25 assertions).

---

### G. Audit Privasi Data Siswa di UI & Jaringan (UU PDP Compliance)
- **Tujuan Audit:** Membuktikan bahwa tampilan daftar siswa kelas ampu pada Portal Guru tidak mengekspos Nomor Induk Kependudukan (NIK), status ekonomi/afirmasi, maupun catatan bimbingan konseling (BK) yang sangat rahasia, baik di tingkat antarmuka (DOM) maupun respon jaringan (network payload).
- **Metode & Eksekusi Playwright:**
  - Naskah inspeksi jaringan Playwright (`scratch/test-teacher-privacy-and-consistency.mjs`) dijalankan terhadap aplikasi Next.js aktif (`http://localhost:3000`).
  - Akun wali kelas `walas.7a.staging` login dan membuka menu Wali Kelas (`/portal/guru/wali-kelas`) serta Presensi Siswa (`/portal/guru/presensi`).
  - Seluruh permintaan jaringan ke endpoint API `/api/v1/me/teacher/homeroom` dan `/api/v1/me/teacher/classes/{id}/students` diintersepsi dan diperiksa rekursif.
- **Hasil Verifikasi Privasi:**
  - **NIK & NIK Enkripsi:** Ditemukan **0 kemunculan** field `nik` atau `nik_enc` pada respon JSON API. Kolom identitas siswa hanya menampilkan NISN, NIS, dan nama lengkap.
  - **Catatan Konseling BK:** Ditemukan **0 kemunculan** objek kasus BK, riwayat konseling, maupun status penanganan psikologis siswa.
  - **DOM UI:** Tabel siswa di antarmuka hanya memuat Nama Siswa, NISN, Gender, dan Aksi Presensi/Nilai. Tidak ada atribut tersembunyi yang membocorkan data pribadi siswa di bawah umur.
- **Bukti Visual Audit:**
  - Screenshot DOM & Network Verification: `docs/audit/screenshots/teacher/14_student_list_privacy.png` (disertai callout visual audit).

---

## 3. Matriks Kesiapan 13 Fitur Portal Guru

| No | Fitur Portal Guru | Ruang Lingkup PRD | Status | Bukti Backend Test | Bukti Visual Screenshot |
|---|---|---|---|---|---|
| 1 | **Dashboard Guru** | Agenda harian, rekap tugas perlu dinilai, jam mengajar | ✅ **VERIFIED** | `TeacherFeatureReadinessTest::test_01_teacher_dashboard` | `01_dashboard.png` |
| 2 | **Upload & Kelola Materi** | Unggah PDF/DOCX/PPT, private signed URL, isolasi kelas | ✅ **VERIFIED** | `TeacherMaterialSecurityFeatureTest` (5 test) | `02_upload_materi.png` |
| 3 | **Penyusunan Materi Ajar** | Modul ajar, perangkat ajar digital resmi via storage | ✅ **VERIFIED** | `TeacherFeatureReadinessTest::test_03_teaching_modules` | `02_upload_materi.png` |
| 4 | **Bank Soal & Penjadwalan CBT** | Bank soal mandiri guru, semua tipe butir, bloom & difficulty | ✅ **VERIFIED** | `TeacherQuestionBankFeatureTest` (8 test) | `03_bank_soal_ujian.png` |
| 5 | **Tugas & Penilaian (LMS)** | Buat tugas, SpeedGrader, koreksi file/teks, feedback siswa | ✅ **VERIFIED** | `TeacherFeatureReadinessTest::test_05_teacher_assignments` | `04_tugas_penilaian.png` |
| 6 | **Presensi Pertemuan Kelas** | Input presensi sesi mapel, status hadir/sakit/izin/alpa | ✅ **VERIFIED** | `TeacherFeatureReadinessTest::test_06_classroom_attendance` | `05_presensi_kelas.png` |
| 7 | **Jurnal Mengajar Guru** | Pencatatan materi terlaksana, hambatan pembelajaran | ✅ **VERIFIED** | `TeacherFeatureReadinessTest::test_07_teaching_journal` | `06_jurnal_mengajar.png` |
| 8 | **Daftar Siswa & Privasi PDP** | Daftar siswa rombel ampu, proteksi ketat NIK & catatan BK | ✅ **VERIFIED** | `TeacherFeatureReadinessTest::test_08_class_students_privacy` | `14_student_list_privacy.png` |
| 9 | **Manajemen Nilai & KKTP** | Input nilai komponen, tolak edit periode locked, audit log | ✅ **VERIFIED** | `TeacherGradeIntegrityFeatureTest` (6 test) | `07_manajemen_nilai.png` |
| 10 | **Fitur Khusus Wali Kelas** | Rekap absensi siswa, catatan karakter walas, kelola rapor | ✅ **VERIFIED** | `TeacherReportCardLifecycleFeatureTest` (2 test) | `08_wali_kelas_rapor.png` |
| 11 | **Pengajuan Izin & Cuti** | Form permohonan cuti, upload bukti, approval kepsek | ✅ **VERIFIED** | `TeacherFeatureReadinessTest::test_11_teacher_leave_requests` | `09_izin_cuti.png` |
| 12 | **Slip Gaji Guru** | Riwayat slip gaji, rincian tunjangan/potongan, isolasi IDOR | ✅ **VERIFIED** | `TeacherFeatureReadinessTest::test_12_teacher_salary_slips` | `10_slip_gaji.png` |
| 13 | **Presensi Mandiri Guru** | Check-in/check-out mandiri guru, riwayat kehadiran bulanan | ✅ **VERIFIED** | `TeacherFeatureReadinessTest::test_13_teacher_self_attendance` | `11_presensi_mandiri.png` |

---

## 4. Rekapitulasi Uji Mutu & Gerbang CI/CD

```
====================================================================================
                        SISTLABUM QUALITY ASSURANCE GATE
====================================================================================
1. Backend Test Suite (PHPUnit 11)   : 1250 passed / 1250 total (100% GREEN)
   - Assertions                      : 8.180 assertions
   - Execution Time                  : 39.27s
2. Backend Code Style (Laravel Pint) : 779 files scanned / 0 violations (PASS)
3. Frontend Tests (Vitest)           : 67 test files passed (795 / 795 tests) (100% GREEN)
4. Frontend Typecheck (TypeScript)   : 0 type errors (tsc --noEmit PASS)
5. Security & IDOR Sweep             : 21 IDOR attack vectors covered / 0 leaks
6. Privacy & UU PDP Audit            : NIK & BK data 100% redacted in DOM & Network
====================================================================================
```

### Riwayat Commit Atomik pada Branch `fix/teacher-gaps`:
1. `243ffd3 feat(cbt): kelola bank soal dan penjadwalan ujian mandiri guru di portal guru` (Bagian A)
2. `264576d fix(grade): integritas nilai semua jalur tolak periode locked dan catat audit log` (Bagian B)
3. `242a8a0 feat(report): siklus hidup rapor wali kelas snapshot beku dan verifikasi publik` (Bagian C)
4. `20ec0de fix(material): pengamanan berkas materi signed url dan isolasi kelas` (Bagian D)
5. `c6a06a1 test(security): uji sweep idor sistematis lintas peran dan kepemilikan` (Bagian E)
6. `4ab75fb test(e2e): konsistensi lintas peran dan audit privasi jaringan ui` (Bagian F & G)
7. `fd8026e fix(report): penyesuaian kewenangan walas vs otoritas kepsek pada penerbitan rapor` (Penyelarasan Regresi)

---

## 5. Kesimpulan & Rekomendasi

Modul **Portal Guru (`apps/web/(portal)/portal/guru`)** dan antarmuka **Backend (`apps/api` `/api/v1/me/teacher/*`)** telah mencapai **Production-Ready Status**:
- Guru dapat bekerja secara mandiri menyusun bank soal, mengunggah materi aman, menilai tugas, mencatat presensi, dan mengelola rapor tanpa celah eskalasi hak istimewa (*privilege escalation*).
- Integritas data akademik terlindungi secara ketat dari modifikasi pada periode tertutup, dan data historis rapor terlindungi oleh snapshot JSONB yang *immutable*.
- Privasi siswa mematuhi standar UU Pelindungan Data Pribadi (UU PDP).

Branch `fix/teacher-gaps` siap untuk dilakukan peninjauan kode (*code review*) dan digabungkan (*merge*) ke branch `develop`.
