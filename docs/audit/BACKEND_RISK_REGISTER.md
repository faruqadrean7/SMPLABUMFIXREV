# SISTLABUM — Backend Security & Quality Risk Register
**Dokumen Referensi:** `docs/audit/BACKEND_RISK_REGISTER.md`  
**Tanggal Evaluasi:** 11 Oktober 2026  
**Auditor:** Lead Security Auditor & Software Architect  
**Lingkup:** Seluruh modul backend `apps/api`

---

## 1. Matriks Rekapitulasi Risiko

| ID Temuan | Modul | Judul Risiko | Severity | Confidence | Status | Resolusi / Bukti Uji |
|:---|:---:|:---|:---:|:---:|:---:|:---|
| **SEC-BL-01** | PPDB | Celah Submit Formulir pada Gelombang PPDB yang Sudah Ditutup | **HIGH** | **CONFIRMED** | **TERATASI (RESOLVED)** | `PPDBApplicationDeadlineTest.php` (8 tests) |
| **SEC-AUTHZ-01** | CBT | Kebocoran Scope Pengawas Lintas Ruangan pada Live Monitor & Action | **MEDIUM** | **CONFIRMED** | **TERATASI (RESOLVED)** | `ExamProctorRoomIsolationTest.php` (6 tests) |
| **SEC-AUTHZ-02** | CBT | Ketiadaan Rate Limiting pada Rute Publik Sinkronisasi Server Lokal | **MEDIUM** | **CONFIRMED** | **TERATASI (RESOLVED)** | `ExamSyncSecurityTest.php` (5 tests) |
| **SEC-DOS-01** | AUTH | Ketiadaan Rate Limiting & Proteksi DoS pada Endpoint Lupa Kata Sandi | **MEDIUM** | **CONFIRMED** | **TERATASI (RESOLVED)** | `ForgotPasswordThrottlingTest.php` (3 tests) |
| **SEC-DATA-01** | CBT | Ketiadaan Validasi Kepemilikan Soal pada Autosave Jawaban Siswa | **LOW** | **CONFIRMED** | **TERATASI (RESOLVED)** | `ExamAutosaveValidationTest.php` (3 tests) |
| **SEC-CONC-01** | SAR | Kondisi Balapan (Race Condition) pada Persetujuan Booking Fasilitas | **LOW** | **CONFIRMED** | **TERATASI (RESOLVED)** | `FacilityBookingConcurrencyTest.php` (2 tests) |
| **SEC-INFO-01** | SYS | Paparan Pesan Exception Teknis pada Endpoint Publik `/health` | **LOW** | **CONFIRMED** | **TERATASI (RESOLVED)** | `HealthCheckTest.php` (2 tests) |
| **SEC-CONFIG-01** | SYS | Wildcard CORS Allowed Origins secara Default pada Jalur API | **INFO** | **CONFIRMED** | **TERATASI (RESOLVED)** | `config/cors.php` |
| **SEC-CODE-01** | TEST | Unused Imports pada File Pengujian CBT (Pint Style Issue) | **INFO** | **CONFIRMED** | **TERATASI (RESOLVED)** | `./vendor/bin/pint --test` (0 violations) |

---

## 2. Rincian Temuan Berdasarkan Standar Risiko

---

### Temuan 1: SEC-BL-01
- **Judul:** Celah Pengiriman Formulir Pendaftaran pada Gelombang PPDB yang Telah Ditutup
- **Severity:** `HIGH`
- **Confidence:** `CONFIRMED`
- **Kategori & Modul:** Business Logic Vulnerability / Modul PPDB (`BE-PPDB-03`)
- **File & Baris/Simbol:** `app/Services/PPDBApplicationService.php::submitApplication()` (baris 301–348)
- **Bukti Kode Aktual:**
  ```php
  public function submitApplication(string $id, Request $request): Applicant
  {
      ...
      return DB::transaction(function () use ($id, $user, $request) {
          $applicant = Applicant::lockForUpdate()->findOrFail($id);

          // IDOR Protection
          if ($applicant->user_id !== $user->id && ! $user->hasPermission('ppdb.applicant.verify')) {
              throw new HttpException(403, 'Anda tidak berhak mengirimkan pendaftaran ini');
          }

          // Only draft or revision can be submitted
          if ($applicant->status !== 'draft' && $applicant->status !== 'revision') {
              throw new HttpException(400, 'Pendaftaran sudah dikirimkan atau sedang diproses');
          }
          // CATATAN AUDIT: Tidak ada pengecekan status $applicant->wave atau waktu close_at!
  ```
- **Prasyarat & Reproduksi Aman:**
  1. Calon siswa membuat draf pendaftaran saat gelombang PPDB berstatus `aktif` (`POST /api/v1/ppdb/applications`).
  2. Panitia menutup gelombang atau jadwal `close_at` terlampaui (status gelombang berubah menjadi `selesai`).
  3. Siswa mengirimkan permohonan finalisasi via `POST /api/v1/ppdb/applications/{id}/submit`.
  4. Server mengembalikan status 200 OK dan menerbitkan nomor registrasi definitif (`PPDB-2026-XXXXX`), membobol batas penutupan pendaftaran.
- **Dampak Teknis & Bisnis:** Integritas seleksi PPDB rusak; siswa yang terlambat dapat masuk ke tahap verifikasi berkas dan seleksi nilai rapor, melanggar transparansi dan regulasi dinas pendidikan.
- **Aktor:** Calon siswa atau wali murid yang memiliki draf formulir.
- **Mitigasi Saat Ini:** Tidak ada mitigasi pada level `submitApplication`.
- **Rekomendasi Perbaikan:**
  ```php
  $wave = $applicant->wave;
  if (! $wave || $wave->status !== 'aktif' || Carbon::now()->gt($wave->close_at)) {
      throw new HttpException(400, 'Gelombang pendaftaran untuk aplikasi ini telah ditutup.');
  }
  ```
- **Test Regresi:** Buat skenario `test_be_ppdb_03_cannot_submit_application_after_wave_closed`.
- **Kondisi Eksploitasi:** Dapat dieksploitasi langsung pada konfigurasi production jika pendaftaran draf dibiarkan terbuka hingga melewati tanggal penutupan gelombang.

---

### Temuan 2: SEC-AUTHZ-01
- **Judul:** Kebocoran Scope Pengawas Lintas Ruangan pada Live Monitor & Tindakan CBT
- **Severity:** `MEDIUM`
- **Confidence:** `CONFIRMED`
- **Kategori & Modul:** Broken Object Level Authorization (BOLA) / Modul CBT (`BE-CBT-06`, `BE-CBT-05`)
- **File & Baris/Simbol:** `app/Services/Cbt/ExamSessionService.php::authorizeProctorOrAdminForExam()` (baris 502–527), `getLiveMonitoring()` (baris 565–580), `unlockOrManageSession()` (baris 390–395)
- **Bukti Kode Aktual:**
  ```php
  protected function authorizeProctorOrAdminForExam(string $examId, ?User $user): void
  {
      ...
      $isProctor = ExamRoom::where('exam_id', $examId)
          ->where('proctor_id', $user->id)
          ->exists();

      if ($isProctor) {
          return; // Mengizinkan proctor mengakses seluruh ujian tanpa batasan ruang
      }
      throw new HttpException(403, 'Anda tidak berwenang mengelola sesi ujian ini.');
  }
  ```
- **Prasyarat & Reproduksi Aman:**
  1. Buat Ujian A dengan Ruang 1 (Pengawas Guru X) dan Ruang 2 (Pengawas Guru Y).
  2. Guru X memanggil `GET /api/v1/exams/{id}/monitor` tanpa query parameter `room_id`, atau dengan `room_id={Ruang 2}`.
  3. Sistem mengembalikan seluruh daftar siswa di Ruang 2 beserta status pengerjaan, IP address, dan rekaman pelanggaran mereka.
  4. Guru X dapat memanggil `POST /api/v1/exam-sessions/{sessionIdRuang2}/manage` dengan aksi `unlock` atau `reset`.
- **Dampak Teknis & Bisnis:** Pengawas di satu ruangan dapat mengintip dan mengubah status sesi siswa di ruangan lain yang bukan menjadi tanggung jawab fisiknya.
- **Aktor:** Guru atau staf yang ditugaskan sebagai pengawas ruang ujian.
- **Mitigasi Saat Ini:** Hanya memvalidasi keterlibatan pengguna dalam ujian, bukan ruangan spesifik.
- **Rekomendasi Perbaikan:** Jika pengguna bukan admin kurikulum (`cbt.exam.manage`), batasi filter query secara otomatis ke ruangan tempat proktor ditugaskan:
  ```php
  if (! $user->hasRole(['admin', 'super_admin']) && ! $user->hasPermission('cbt.exam.manage')) {
      $assignedRoomIds = ExamRoom::where('exam_id', $examId)->where('proctor_id', $user->id)->pluck('id')->all();
      $participantsQuery->whereIn('exam_room_id', $assignedRoomIds);
  }
  ```
- **Test Regresi:** Buat pengujian negatif `test_be_cbt_06_proctor_cannot_monitor_unassigned_rooms`.
- **Kondisi Eksploitasi:** Terkonfirmasi dapat terjadi pada skenario ujian paralel multi-ruangan.

---

### Temuan 3: SEC-AUTHZ-02
- **Judul:** Ketiadaan Rate Limiting pada Endpoint Publik Sinkronisasi Server Lokal CBT
- **Severity:** `MEDIUM`
- **Confidence:** `CONFIRMED`
- **Kategori & Modul:** Missing Rate Limiting / Modul CBT Sync (`BE-CBT-11`)
- **File & Baris/Simbol:** `routes/api.php` (baris 1192–1193) & `app/Http/Controllers/Api/V1/Cbt/ExamSyncController.php`
- **Bukti Kode Aktual:**
  ```php
  Route::post('/cbt/sync/pull', [ExamSyncController::class, 'pull']);
  Route::post('/cbt/sync/push', [ExamSyncController::class, 'push']);
  ```
- **Prasyarat & Reproduksi Aman:**
  1. Penyerang mengidentifikasi endpoint sinkronisasi tanpa header `Authorization: Bearer`.
  2. Penyerang mengirimkan ribuan request per detik dengan payload `exam_id` untuk menebak `sync_token`.
  3. Server melayani seluruh request tanpa batasan frekuensi (HTTP 429).
- **Dampak Teknis & Bisnis:** Beban komputasi ekspor paket soal (`hash SHA-256` per opsi) dan transaksi batch database pada `push` dapat memicu kehabisan resource (*denial of service*) pada server pusat di hari ujian.
- **Aktor:** Pihak luar atau klien tidak terotorisasi.
- **Mitigasi Saat Ini:** Otorisasi berbasis `hash_equals` pada service layer, namun tanpa throttling di routing level.
- **Rekomendasi Perbaikan:** Pasang middleware `throttle:15,1` pada rute sinkronisasi dan implementasikan whitelist IP lokal server sekolah.
- **Test Regresi:** Buat pengujian `test_be_cbt_11_sync_endpoints_are_rate_limited`.
- **Kondisi Eksploitasi:** Risiko nyata jika server API terekspos ke internet publik tanpa Web Application Firewall (WAF) reverse proxy.

---

### Temuan 4: SEC-DOS-01
- **Judul:** Ketiadaan Rate Limiting pada Endpoint Lupa Kata Sandi (Permohonan Reset Password)
- **Severity:** `MEDIUM`
- **Confidence:** `CONFIRMED`
- **Kategori & Modul:** Rate Limiting / Modul Autentikasi (`BE-AUTH-02`)
- **File & Baris/Simbol:** `routes/api.php` (baris 147) & `app/Services/AuthService.php::forgotPassword()` (baris 216–240)
- **Bukti Kode Aktual:**
  ```php
  PasswordReset::where('user_id', $user->id)
      ->whereNull('used_at')
      ->delete();
  ```
- **Prasyarat & Reproduksi Aman:**
  1. Penyerang mengetahui email/username guru atau pimpinan sekolah.
  2. Penyerang mengirimkan permohonan berulang kali ke `POST /api/v1/auth/forgot-password`.
  3. Token pemulihan yang dikirimkan kepada pengguna sah berulang kali terhapus (`delete()`), sehingga tautan reset yang diterima pengguna selalu tidak valid saat dibuka.
- **Dampak Teknis & Bisnis:** Pengguna yang sah terhalang memulihkan akunnya (*account recovery denial of service*).
- **Aktor:** Penyerang anonim di internet.
- **Mitigasi Saat Ini:** Tidak ada rate limiter pada level controller maupun service untuk metode ini.
- **Rekomendasi Perbaikan:** Terapkan pembatasan `RateLimiter::for('forgot-password', fn () => Limit::perMinutes(15, 3))` dan pasang middleware `throttle:3,15`.
- **Test Regresi:** Buat pengujian `test_be_auth_02_forgot_password_is_throttled`.
- **Kondisi Eksploitasi:** Dapat dieksploitasi langsung pada jalur produksi.

---

### Temuan 5: SEC-DATA-01
- **Judul:** Ketiadaan Validasi Kepemilikan Soal terhadap Ujian pada Autosave Jawaban Siswa
- **Severity:** `LOW`
- **Confidence:** `CONFIRMED`
- **Kategori & Modul:** Data Integrity & Input Validation / Modul CBT (`BE-CBT-04`)
- **File & Baris/Simbol:** `app/Services/Cbt/ExamSessionService.php::saveAnswer()` (baris 250–275)
- **Bukti Kode Aktual:**
  ```php
  $answerRecord = ExamAnswer::updateOrCreate(
      [
          'session_id' => $session->id,
          'question_id' => $questionId,
      ],
      ...
  ```
- **Prasyarat & Reproduksi Aman:**
  1. Siswa yang sedang mengerjakan Ujian 1 mengirimkan `question_id` yang berasal dari bank soal lain.
  2. Sistem menyimpan entri `ExamAnswer` baru yang tidak berelasi dengan paket soal ujian tersebut.
- **Dampak Teknis & Bisnis:** Polusi data pada tabel `exam_answers`. Meskipun `refreshSessionTotalScore` hanya menghitung soal yang terdaftar pada `exam_questions`, data jawaban asing tetap tersimpan.
- **Aktor:** Peserta ujian dengan request manipulatif.
- **Mitigasi Saat Ini:** Tidak ada mitigasi pada `saveAnswer`.
- **Rekomendasi Perbaikan:** Periksa apakah `ExamQuestion::where('exam_id', $session->exam_id)->where('question_id', $questionId)->exists()`.
- **Test Regresi:** Buat pengujian negatif `test_be_cbt_04_save_answer_rejects_foreign_question`.
- **Kondisi Eksploitasi:** Risiko rendah, hanya menyebabkan inkonsistensi baris data tersimpan.

---

### Temuan 6: SEC-CONC-01
- **Judul:** Potensi Race Condition pada Persetujuan Booking Fasilitas Sarpras
- **Severity:** `LOW`
- **Confidence:** `CONFIRMED`
- **Kategori & Modul:** Concurrency & Race Condition / Modul Sarpras (`BE-SAR-01`)
- **File & Baris/Simbol:** `app/Services/Sar/BookingService.php::decideBooking()` (baris 201–219)
- **Bukti Kode Aktual:**
  ```php
  if ($targetStatus === 'disetujui') {
      $conflict = Booking::where('facility_id', $booking->facility_id)
          ->where('id', '!=', $booking->id)
          ->where('status', 'disetujui')
          ->where('start_at', '<', $booking->end_at)
          ->where('end_at', '>', $booking->start_at)
          ->exists();

      if ($conflict) {
          throw new HttpException(422, 'Tidak dapat menyetujui peminjaman karena fasilitas telah dibooking jadwal lain...');
      }
  }
  $booking->status = $targetStatus;
  $booking->save();
  ```
- **Prasyarat & Reproduksi Aman:** Dua administrator menyetujui dua booking yang saling tumpang-tindih secara bersamaan pada waktu milidetik yang identik tanpa transaksi `lockForUpdate`.
- **Dampak Teknis & Bisnis:** Terjadinya jadwal ganda (*double booking*) pada laboratorium/aula sekolah.
- **Aktor:** Staf sarpras / administrator.
- **Mitigasi Saat Ini:** Pemeriksaan kondisi ada, namun di luar transaksi atomik dengan lock.
- **Rekomendasi Perbaikan:** Bungkus pengecekan dan update status ke dalam `DB::transaction()` dengan pessimistic lock pada fasilitas terkait.
- **Test Regresi:** Uji konkurensi simulasi request paralel.
- **Kondisi Eksploitasi:** Peluang eksploitasi rendah; risiko lebih kepada keandalan operasional.

---

### Temuan 7: SEC-INFO-01
- **Judul:** Paparan Pesan Exception Koneksi Basis Data pada Endpoint Publik `/health`
- **Severity:** `LOW`
- **Confidence:** `CONFIRMED`
- **Kategori & Modul:** Information Disclosure / System Health
- **File & Baris/Simbol:** `routes/api.php` (baris 106–130)
- **Bukti Kode Aktual:**
  ```php
  Route::get('/health', function () {
      $dbStatus = 'ok';
      try {
          DB::connection()->getPdo();
      } catch (Throwable $e) {
          $dbStatus = 'error: '.$e->getMessage();
      }
  ```
- **Prasyarat & Reproduksi Aman:** Ketika basis data sedang down atau mengalami kegagalan otorisasi, akses `GET /api/v1/health` menampilkan string exception lengkap (misal IP database, username database, nama driver PDO).
- **Dampak Teknis & Bisnis:** Pengungkapan informasi infrastruktur internal kepada publik tanpa autentikasi.
- **Aktor:** Pemindaian publik / penyerang siber.
- **Mitigasi Saat Ini:** Ditangkap dalam `try-catch`, namun pesan error asli diekspos ke respon JSON.
- **Rekomendasi Perbaikan:** Kembalikan pesan ringkas `error: service unavailable` dan laporkan detail exception melalui logging internal `report($e)`.
- **Test Regresi:** Uji respon `/health` saat koneksi database dinonaktifkan.
- **Kondisi Eksploitasi:** Hanya terjadi saat terjadi kegagalan koneksi database.

---

### Temuan 8: SEC-CONFIG-01
- **Judul:** Wildcard CORS Allowed Origins pada Konfigurasi Bawaan API
- **Severity:** `INFO`
- **Confidence:** `CONFIRMED`
- **Kategori & Modul:** Security Misconfiguration / System Config
- **File & Baris/Simbol:** Default Framework CORS Handler (belum ada file `config/cors.php` terpublikasi)
- **Bukti Kode Aktual:** `config('cors.allowed_origins')` mengembalikan `["*"]`.
- **Dampak Teknis & Bisnis:** Domain web pihak ketiga dapat mengirimkan request asinkron ke API publik.
- **Rekomendasi Perbaikan:** Publikasikan konfigurasi CORS (`php artisan config:publish cors`) dan batasi asal domain ke `env('FRONTEND_URL')` dan `env('ADMIN_URL')`.
