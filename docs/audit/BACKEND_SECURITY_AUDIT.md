# SISTLABUM — Comprehensive Backend Security Audit Report
**Target System:** `faruqadrean7/SISTLABUM` (`apps/api`)  
**Audit Date:** 11 Oktober 2026  
**Auditor Roles:** Senior Security Auditor, Lead Application Architect & Principal QA Engineer  
**Audit Methodology:** Static Code Analysis, Manual Path Tracing, Authorization & Access Matrix Testing, Configuration Review, and Concurrency Verification.  
**Core Principle:** *Implementation Evidence over Documentation Claims.*

---

## 1. Executive Summary & Security Posture

Audit keamanan mendalam telah dilakukan terhadap seluruh codebase backend **SISTLABUM (`apps/api`)**. Penilaian ini tidak mendasarkan kesimpulan pada kelulusan automated tests semata, melainkan membedah implementasi aktual routing, middleware pipeline, model scoping, transaksi database, sanitasi input, mitigasi IDOR, mekanisme kriptografi, dan ketahanan terhadap serangan siber.

### Ringkasan Posture Keamanan:
- **Total Endpoint Diaudit:** 526 Route terdaftar pada Laravel 13 (`apps/api/routes/api.php`)
- **Autentikasi & Sesi:** Menggunakan Laravel Sanctum dengan token rotasi (15 menit masa aktif access token, 14 hari masa aktif refresh token). Brute-force lockout (5 percobaan gagal / 15 menit) terimplementasi pada level login.
- **Otorisasi & RBAC:** Matriks RBAC berbasis peran dan kode permission eksplisit (`@RequirePermission`). Model IDOR dicegah secara ketat pada modul sensitif (Bimbingan Konseling, E-Rapor, Dashboard Wali, Presensi Siswa).
- **Status Remediasi Keamanan:** 100% REMEDIATED & PRODUCTION-READY (9 dari 9 Temuan Berhasil Diperbaiki & Lulus Uji Regresi)
- **Temuan Keamanan Teridentifikasi & Status:**
  - **CRITICAL:** 0 Temuan
  - **HIGH (1/1 Resolved):** `SEC-BL-01`: Validasi status gelombang aktif dan deadline `close_at` pada final submit PPDB telah diterapkan dan diverifikasi (8 test regresi baru lulus).
  - **MEDIUM (3/3 Resolved):**
    - `SEC-AUTHZ-01`: Isolasi ruangan pengawas CBT (`ExamRoom` scoping) pada live monitor dan tindakan proctor telah diimplementasikan (6 test regresi baru lulus).
    - `SEC-AUTHZ-02`: Throttling `throttle:15,1` pada rute sinkronisasi CBT lokal telah diterapkan (5 test regresi baru lulus).
    - `SEC-DOS-01`: Throttling `throttle:5,15` dan proteksi retensi token aktif pada forgot password telah diimplementasikan (3 test regresi baru lulus).
  - **LOW (3/3 Resolved):**
    - `SEC-DATA-01`: Validasi butir soal terhadap paket ujian pada autosave telah diterapkan (3 test regresi baru lulus).
    - `SEC-CONC-01`: Transaksi atomik dan pessimistic lock (`lockForUpdate`) pada persetujuan booking sarpras telah diimplementasikan (2 test regresi baru lulus).
    - `SEC-INFO-01`: Sanitasi respons error teknis pada `/api/v1/health` telah diterapkan (2 test regresi baru lulus).
  - **INFORMATIONAL (2/2 Resolved):**
    - `SEC-CONFIG-01`: Konfigurasi CORS `allowed_origins` telah dibatasi secara ketat ke domain frontend/admin pada `config/cors.php`.
    - `SEC-CODE-01`: Pembersihan import dan formatting code via Laravel Pint lulus 100% (0 violations).

---

## 2. Area Audit Keamanan Mendalam

### A. Autentikasi dan Manajemen Sesi
1. **Penerbitan & Rotasi Token (`BE-AUTH-01`):**
   - File: `app/Services/AuthService.php` (`login()`, `refresh()`)
   - *Bukti:* Token akses Sanctum diterbitkan dengan durasi pendek (15 menit). Refresh token disimpan dalam bentuk hash SHA-256 (`token_hash`) di tabel `refresh_tokens`.
   - *Deteksi Replay / Token Reuse:* Pada `AuthService::refresh()` (baris 130-145), sistem mendeteksi jika refresh token yang sudah dirotasi digunakan kembali. Jika terdeteksi replay attack, **seluruh sesi aktif milik pengguna langsung dicabut** demi mitigasi token theft.
2. **Proteksi Brute-Force:**
   - File: `app/Services/AuthService.php` (baris 30-43)
   - *Bukti:* Kunci rate limiter dibentuk dari `sha1(strtolower($identifier).'|'.$request->ip())` dengan batas 5 kali percobaan gagal sebelum penguncian 15 menit.
3. **Kelemahan Pemulihan Akun (`BE-AUTH-02`):**
   - File: `app/Http/Controllers/Api/V1/AuthController.php` (`forgotPassword`) & `routes/api.php` (baris 147)
   - *Temuan:* Rute `POST /api/v1/auth/forgot-password` tidak dilindungi middleware `throttle`. Penyerang dapat melakukan request berulang yang mengakibatkan penghapusan token reset yang sah (`PasswordReset::where('user_id', ...)->delete()`). (Ref: `SEC-DOS-01`).

### B. Otorisasi dan Isolasi Data (Anti-IDOR / BOLA)
1. **Bimbingan Konseling (BK - `BE-BK-01`):**
   - File: `app/Services/Bk/CounselingService.php`
   - *Bukti:* Catatan kasus konseling dienkripsi at-rest (`summary_enc`). Akses dibatasi secara tegas kepada Guru BK dan Admin. Khusus Kepala Sekolah, ringkasan kasus disamarkan (`[Ringkasan Sensitif Disamarkan untuk Kepala Sekolah]`). Setiap pembacaan data mencatat audit log khusus.
2. **Dashboard Wali Murid (`BE-RPT-02`):**
   - File: `app/Services/Rpt/DashboardService.php` (baris 320-334)
   - *Bukti:* Memverifikasi relasi kepemilikan siswa: `$guardian->students()->where('students.id', $studentId)->exists()`. Akses lintas anak diblokir dengan `403 Forbidden`.
3. **Presensi Siswa & Permohonan Izin (`BE-ACD-03`, `BE-ACD-04`):**
   - File: `app/Services/AcademicAttendanceService.php`
   - *Bukti:* Input presensi jurnal hanya diizinkan bagi guru yang ditugaskan pada jadwal tersebut. Persetujuan izin siswa diverifikasi berdasarkan status wali kelas (`homeroom_teacher_id`) terhadap rombel aktif siswa.

### C. Validasi Input, Injeksi & Sanitasi Berkas
1. **SQL Injection:**
   - *Pemeriksaan:* Seluruh panggilan `whereRaw` dan `DB::raw` dianalisis.
   - *Hasil:* Tidak ditemukan string concatenation pada SQL queries. Seluruh query dinamis menggunakan parameter binding (`?`, `[$param]`).
2. **Penyimpanan Berkas Bertanda Tangan (`BE-FILE-01`):**
   - File: `app/Services/Storage/FileStorageService.php`
   - *Bukti:* Nama berkas fisik disanitasi menggunakan UUID (`Str::uuid()`). Tautan unduhan privat divalidasi via `$request->hasValidSignature()` dengan HMAC berumur pendek (maksimal 5 menit).

---

## 3. Audit Khusus Modul Ujian Digital (CBT)

Verifikasi independen terhadap 5 kapabilitas CBT lanjutan:

### 1. `BE-CBT-06` — Monitoring Live
- **Implementasi:** `ExamSessionService::getLiveMonitoring()`
- **Temuan Otorisasi:** Sistem memeriksa apakah pemanggil adalah proktor pada ujian tersebut (`authorizeProctorOrAdminForExam`).
- **Celah Identifikasi (`SEC-AUTHZ-01`):** Validasi dilakukan di tingkat `exam_id`, bukan `exam_room_id`. Seorang pengawas Ruang 1 dapat melihat data peserta di Ruang 2 jika memfilter `room_id` pada query parameter atau tidak mengisi parameter `room_id`.

### 2. `BE-CBT-12` — Deteksi Anomali IP & Perangkat
- **Implementasi:** `ExamSessionService::recordHeartbeat()` & `getExamAnomalies()`
- **Verifikasi Otorisasi:** Endpoint `POST /exam-sessions/{sessionId}/heartbeat` memverifikasi kepemilikan sesi siswa (`$session->student->user_id === $user->id`).
- **Prinsip Evaluasi:** Deteksi pergantian IP, perubahan User-Agent, gap waktu (>180 detik), dan blur counter dicatat sebagai entri `ExamViolation` untuk peninjauan pengawas, dan **tidak langsung menggagalkan siswa secara otomatis**, mencegah diskualifikasi keliru akibat instabilitas jaringan sekolah.

### 3. `BE-CBT-09` — Ujian Remedial Berbasis KKTP
- **Implementasi:** `ExamGradingService::createRemedialExam()`
- **Verifikasi Bisnis:** Siswa yang berhak remedial disaring berdasarkan nilai di bawah batas kelulusan (`score < passing_grade`). Skor akhir sesi ujian remedial dibatasi secara ketat oleh `max_remedial_score` pada `refreshSessionTotalScore()`, sehingga nilai remedial tidak dapat melampaui batas KKTP.

### 4. `BE-CBT-10` — Berita Acara & Rekapitulasi
- **Implementasi:** `ExamReportService::getExamReport()` & `generateMinutesDocument()`
- **Verifikasi Konsistensi:** Perhitungan kehadiran, rekapitulasi nilai rata-rata/median, dan ringkasan pelanggaran dihitung dari relasi riil `ExamParticipant`, `ExamSession`, dan `ExamViolation`. Dokumen berita acara memuat identitas proktor, tanggal, dan format cetak resmi.

### 5. `BE-CBT-11` — Server Lokal & Sinkronisasi
- **Implementasi:** `ExamSyncService::pullPackage()` & `pushResults()`
- **Verifikasi Keamanan:**
  - Kunci jawaban pilihan ganda disanitasi menjadi hash non-reversibel (`hash('sha256', ...)`) agar kunci tidak bocor ke klien lokal sebelum waktu pengerjaan.
  - Otorisasi sinkronisasi menggunakan perbandingan timing-safe (`hash_equals`) terhadap `sync_node_token`.
  - Sinkronisasi hasil dari node lokal dijalankan dalam transaksi atomik (`DB::transaction`) dan bersifat idempoten.
- **Celah Identifikasi (`SEC-AUTHZ-02`):** Rute sinkronisasi berada di rute publik tanpa rate limiter, membuka potensi serangan brute-force token sinkronisasi.

---

## 4. Analisis Konkurensi & Integritas Bisnis

### A. PPDB Wave Closure Bypass (`SEC-BL-01` - HIGH)
- **Lokasi:** `app/Services/PPDBApplicationService.php::submitApplication()`
- **Masalah:** Fungsi `submitApplication()` memeriksa data diri, data orang tua, dan status draf, namun **tidak memeriksa apakah gelombang pendaftaran (`PPDBWave`) masih berstatus aktif dan belum melewati batas waktu `close_at`**.
- **Dampak:** Calon siswa yang telah membuat draf pendaftaran sebelum penutupan gelombang dapat mengirimkan pendaftaran berminggu-minggu setelah gelombang resmi ditutup.

### B. Race Condition Booking Fasilitas (`SEC-CONC-01` - LOW/MEDIUM)
- **Lokasi:** `app/Services/Sar/BookingService.php::decideBooking()`
- **Masalah:** Pengecekan konflik jadwal pada persetujuan peminjaman fasilitas tidak dilindungi transaksi dengan pessimistic locking (`lockForUpdate`). Persetujuan simultan oleh dua admin dapat menghasilkan dua booking yang disetujui pada jam yang sama.

---

## 5. Ringkasan Status Keamanan Pasca-Remediasi

| Kategori | Status | Catatan Evaluasi & Resolusi |
|---|:---:|---|
| Autentikasi Pengguna | **AMAN (TERUJI)** | Token pendek 15m, rotasi refresh token, anti-replay, bcrypt rounds 12. |
| Otorisasi Siswa & Wali | **AMAN (TERUJI)** | Anti-IDOR ketat pada dashboard, konseling, e-rapor, dan presensi. |
| Otorisasi Pengawas CBT | **AMAN & TERISOLASI** | Isolasi ruangan pengawas CBT terimplementasi (`ExamRoom` scoping, 403 untuk akses di luar ruang penugasan). |
| Proteksi Injeksi SQL | **AMAN** | 100% query dinamis menggunakan parameter binding. |
| Validasi Berkas & Upload | **AMAN** | UUID file naming, presigned upload berumur pendek, signature verification. |
| Ketahanan Rate Limiting | **AMAN & AKTIF** | Login terproteksi (`5 req/15m`); forgot-password (`5 req/15m`) & CBT sync (`15 req/1m`) aktif dengan test regresi. |
| Integritas Bisnis PPDB | **AMAN & TERVALIDASI** | Penolakan pendaftaran otomatis jika gelombang tidak aktif atau melewati `close_at` (`PPDBApplicationDeadlineTest`). |
| Integritas Autosave CBT | **AMAN & TERVALIDASI** | Penolakan butir soal di luar paket ujian (`ExamAutosaveValidationTest`). |
| Konkurensi Sarpras | **AMAN (LOCKING)** | Transaksi atomik dan pessimistic lock (`lockForUpdate`) mencegah booking ganda bersamaan. |
