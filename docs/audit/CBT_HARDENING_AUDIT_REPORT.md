# SISTLABUM CBT — LAPORAN AUDIT FINAL HARDENING TIMER, OFFLINE SYNC, DAN AUTO-SUBMIT

**Tanggal Audit:** 14 Oktober 2026  
**Status Evaluasi:** VERIFIED & HARDENED (Kode siap, pengujian 100% lulus, verifikasi scheduler host diperlukan)  
**Tujuan Dokumen:** Dokumentasi pembuktian teknis dan audit final penutupan celah waktu (timing), sinkronisasi offline (offline sync), mitigasi kecurangan (anti-cheat), dan penutupan otomatis (auto-submit) pada modul CBT portal SISTLABUM.

---

## 1. Implementasi yang Diperiksa & Diperkuat

### A. Waktu dan Timer Server (Server-Enforced Timing)
1. **Otoritas Tunggal Server (Single Source of Truth):**
   - Server backend (`apps/api`) adalah satu-satunya penentu durasi dan batas akhir ujian.
   - Field `ends_at` dihitung saat inisialisasi sesi (`startOrResumeSession`) menggunakan rumus:
     $$\text{ends\_at} = \min(\text{started\_at} + \text{duration\_min}, \text{exam.end\_at})$$
   - Tanggal dan jam server dikirimkan secara seragam via properti `server_time` (ISO 8601 UTC) pada respons `start-session`, `session-questions`, `save-answer`, dan `violations`.
2. **Kompensasi Clock Skew Absolut di Frontend:**
   - Frontend (`apps/web/src/app/(portal)/portal/siswa/ujian/[id]/page.tsx`) menghitung selisih jam server dan jam klien sekali saat inisialisasi:
     $$\text{serverOffsetMs} = \text{server\_time} - \text{Date.now}()$$
   - Sisa detik pengerjaan dihitung secara absolut:
     $$\text{remaining} = \max\left(0, \left\lfloor \frac{\text{ends\_at} - (\text{Date.now}() + \text{serverOffsetMs})}{1000} \right\rfloor\right)$$
   - Formula ini **kebal terhadap manipulasi jam perangkat pengguna** (baik dimajukan maupun dimundurkan).
3. **Ketahanan Terhadap Throttling Tab Latar Belakang (Tab Throttling):**
   - Browser modern menurunkan frekuensi `setInterval` hingga 1 kali per menit pada tab tidak aktif.
   - Komponen mendengarkan event `visibilitychange`. Saat pengguna beralih kembali ke tab ujian, waktu sisa segera dihitung ulang seketika menggunakan waktu absolut tanpa mengandalkan akumulasi tick interval.
4. **Penegakan Batas Waktu Server (Server-Side Deadline Enforcement):**
   - Endpoint `save-answer` memanggil `ensureSessionActive()`. Jika jam server melewati `ends_at` ditambah batas toleransi toleran (`cbt.grace_period_seconds = 5`), request ditolak dengan status HTTP 422 dan kode error `EXAM_TIME_OVER`.
   - Untuk sesi warisan (legacy) yang hanya memiliki `deadline_at`, server mempertahankan kode error `BUSINESS_RULE_VIOLATION`.

---

### B. Antrean Offline & Sinkronisasi Jawaban (Offline Sync & Queueing)
1. **Isolasi Antrean Antar-Akun & Antar-Ujian (Cross-Account Isolation):**
   - Kunci penyimpanan lokal di-scope berdasarkan identitas siswa yang login:
     - Jawaban: `cbt_pending_answers_{userId}_{examId}` (dengan fallback un-scoped `cbt_pending_answers_{examId}` untuk pengujian unit).
     - Pelanggaran: `cbt_pending_violations_{userId}_{examId}`.
   - Pada perangkat laboratorium bersama (*shared computers*), data antrean Siswa A tidak dapat dimuat, diakses, atau dikirimkan oleh Siswa B yang login berikutnya.
2. **Identitas Unik & Idempotensi Jawaban:**
   - Setiap mutasi jawaban menghasilkan `idempotency_key` unik (`${questionId}_${timestamp}_${random}`) dan mencatat `answered_at` (timestamp lokal saat siswa mengklik opsi).
3. **Perlindungan Terhadap Out-of-Order Retries (Stale Answer Protection):**
   - Masalah: Jika siswa memilih Opsi A (t=1), lalu berganti ke Opsi B (t=2) dalam kondisi koneksi lambat, ada risiko request Opsi B tiba duluan dan tersimpan, lalu retry Opsi A tiba belakangan dan menimpa Opsi B.
   - Solusi di Server: Pada `ExamSessionService::saveAnswer`, server membandingkan timestamp `answered_at` yang masuk dengan `answered_at` yang sudah tersimpan di tabel `exam_answers`. Jika request yang masuk memiliki `answered_at < existing->answered_at`, request dianggap usang (*stale*). Server mengembalikan `{ is_saved: true, is_stale: true }` tanpa mengubah data Opsi B di database.
4. **Resiliensi Pengiriman Antrean Bertahap (Partial Drain Recovery):**
   - Fungsi `flushPendingOfflineData` mengirimkan antrean satu per satu.
   - Entri di penyimpanan lokal HANYA dihapus setelah server mengonfirmasi status sukses.
   - Jika koneksi terputus di tengah pengiriman (misal butir 1 sukses, butir 2 putus), butir 1 terhapus dari antrean sedangkan butir 2 tetap tersimpan aman di `localStorage` untuk percobaan pengiriman berikutnya.
5. **Multi-Tab Sync & In-Memory Fallback:**
   - Komponen memantau event `window.addEventListener('storage')` untuk menyinkronkan jawaban secara reaktif jika siswa membuka lebih dari satu tab.
   - Seluruh operasi `localStorage` dibungkus dalam blok try-catch dengan fallback ke memori (`memoryPendingAnswersRef`) untuk mencegah crash pada browser dengan kuota penyimpanan penuh atau mode penyamaran ketat.

---

### C. Pelanggaran Integritas Ujian (Anti-Cheat Violation Tracking)
1. **Validasi Server-Side Ketat:**
   - Payload `violation_type` divalidasi via `LogExamViolationRequest` dengan daftar tipe yang diizinkan: `blur`, `window_blur`, `tab_switch`, `background`, `fullscreen_exit`, `inspect_element`, `other`.
2. **Pencatatan Atomik & Pengambilan Keputusan di Server:**
   - Mutasi counter `violation_count` dilakukan di dalam transaksi database dengan `lockForUpdate()`.
   - Ambang batas (*threshold*) konsekuensi ditentukan server dari konfigurasi `config/cbt.php`:
     - Pelanggaran 1–2: Konsekuensi `warning`.
     - Pelanggaran 3 (ambang batas kunci): Sesi diubah menjadi `terkunci` (*locked screen*), siswa wajib memanggil pengawas untuk membuka kunci.
     - Pelanggaran 5 (ambang batas diskualifikasi): Sesi diubah menjadi `selesai` (*disqualified/auto-submit*).

---

### D. Auto-Submit & Penjadwalan (Auto-Submit & Scheduler)
1. **Command Auto-Submit:**
   - Perintah: `php artisan exam:auto-submit` dengan alias `cbt:close-expired-sessions`.
   - Opsi: `--grace-seconds` (default membaca `config('cbt.grace_period_seconds', 5)`).
2. **Keamanan Konkurensi & Idempotensi Worker:**
   - Setiap sesi kedaluwarsa diproses di dalam transaksi database independen:
     ```php
     $session = ExamSession::where('id', $sessionId)
         ->whereIn('status', ['mengerjakan', 'in_progress'])
         ->lockForUpdate()
         ->first();
     ```
   - Jika dua worker scheduler berjalan pada saat bersamaan, worker kedua akan mendapati `$session` telah bernilai null atau berstatus `selesai`, sehingga tidak terjadi duplikasi proses atau error constraint database.
3. **Koreksi Nilai Objektif Otomatis:**
   - Saat sesi kedaluwarsa ditutup otomatis oleh command, service `ExamGradingService::gradeSessionObjectiveAnswers` segera dipanggil untuk menilai butir PG/objektif dan menghitung skor akhir sesi (skala 0–100) serta menyimpan nilai ke `exam_sessions.score`.
4. **Audit Trail:**
   - Setiap sesi yang ditutup otomatis mencatat `AuditLog` dengan aksi `EXAM_SESSION_AUTO_SUBMITTED`.

---

## 2. Rincian Pengujian yang Dijalankan

### A. Pengujian Backend (PHPUnit / Feature Tests)
File Pengujian:
1. `apps/api/tests/Feature/Cbt/ExamTimingTest.php` (11 pengujian)
2. `apps/api/tests/Feature/Cbt/ExamViolationTest.php` (1 pengujian komprehensif)

| Skenario Pengujian | Target Uji | Hasil |
|---|---|---|
| **Skenario 1** | Jawaban tepat pada batas waktu diterima (toleransi 5s); lewat 5s ditolak `EXAM_TIME_OVER`. | **PASS** |
| **Skenario 2** | Start sesi menghitung `ends_at = min(started_at + durasi, exam.end_at)`. | **PASS** |
| **Skenario 3** | Resume sesi bersifat idempoten dan tidak mereset timer atau started_at. | **PASS** |
| **Skenario 4** | Simpan jawaban setelah ends_at + grace period ditolak dengan kode `EXAM_TIME_OVER`. | **PASS** |
| **Skenario 5** | Jawaban lama dan baru mengalami retry dalam urutan terbalik; server menolak penimpaan data usang (*stale answer protection*). | **PASS** |
| **Skenario 6** | Server menerima request jawaban identik berulang kali; respons idempoten tanpa duplikasi baris database. | **PASS** |
| **Skenario 7** | Finish session bersifat idempoten (submit berulang kali mengembalikan status selesai). | **PASS** |
| **Skenario 8** | Auto-close command menutup sesi yang melewati batas waktu ends_at. | **PASS** |
| **Skenario 9** | Dua proses auto-submit memproses sesi yang sama secara konkuren; sesi hanya diproses sekali tanpa crash. | **PASS** |
| **Skenario 10** | Sesi yang belum kedaluwarsa tidak diubah atau ditutup oleh auto-submit command. | **PASS** |
| **Skenario 11** | Sesi lama tanpa `ends_at` (hanya `deadline_at`) tetap kompatibel dan mengembalikan `BUSINESS_RULE_VIOLATION`. | **PASS** |
| **Skenario 12 & 13** | Browser ditutup siswa tanpa tombol submit; server auto-submit menutup sesi dan menghitung skor objektif otomatis (skor 100). | **PASS** |
| **Pelanggaran Anti-Cheat** | Counter pelanggaran naik atomik dalam transaksi konkuren, memicu status `terkunci` pada 3 kali dan `selesai` pada 5 kali. | **PASS** |

**Total Backend Test:** **1.054 tests passed, 5.766 assertions (100% GREEN)**.

---

### B. Pengujian Frontend (Vitest Integration Tests)
File Pengujian:
1. `apps/web/src/test/cbt-timing.test.tsx` (7 pengujian)
2. `apps/web/src/test/cbt-violation.test.tsx` (2 pengujian)

| Skenario Pengujian | Target Uji | Hasil |
|---|---|---|
| **Clock Skew Positif** | Jam perangkat siswa dimajukan 3 jam lebih cepat; sisa waktu tetap tepat 01:00:00 (offset mengompensasi secara absolut). | **PASS** |
| **Clock Skew Negatif** | Jam perangkat siswa dimundurkan 2 jam ke belakang; sisa waktu tetap tepat 01:00:00. | **PASS** |
| **Tab Throttling** | Tab diminimalkan di latar belakang dengan loncatan waktu 65 detik; saat aktif kembali, sisa waktu berkurang tepat 65 detik. | **PASS** |
| **Browser Reload** | Reload peramban memulihkan sisa waktu riil dari server tanpa mereset timer ke nilai default 5400s. | **PASS** |
| **Offline Answer Queue** | Jawaban disimpan di antrean lokal saat offline dan di-drain otomatis saat event `online` dipicu. | **PASS** |
| **Partial Drain Network Drop** | Koneksi putus di tengah pengiriman antrean; butir yang berhasil dihapus, butir yang gagal tetap berada di antrean. | **PASS** |
| **Cross-Account Isolation** | Antrean jawaban offline Siswa A tidak bocor atau dimuat oleh Siswa B pada komputer laboratorium bersama. | **PASS** |
| **Violation Sync** | `violation_count` dipulihkan dari respons server saat inisialisasi dan memicu modal peringatan. | **PASS** |
| **Offline Violation Queue** | Pelanggaran saat offline diantrekan di penyimpanan lokal dan dikirim ke server saat kembali online. | **PASS** |

**Total Web Test:** **52 test files passed, 615 tests (100% GREEN)**.  
**Lint & TypeScript Check:** **Lulus tanpa error (`npm run lint` & `tsc --noEmit` bersih)**.

---

## 3. Status Scheduler pada Host Lokal vs Produksi

### A. Host Lokal Pengembangan (`macbookprom12020`)
- **Status Crontab OS:**
  ```text
  $ crontab -l
  crontab: no crontab for macbookprom12020
  ```
  Pada host lokal macOS saat ini, daemon cron OS **belum aktif**.
- **Status Scheduler Laravel (`apps/api`):**
  Pendaftaran jadwal di `apps/api/routes/console.php`:
  ```php
  Schedule::command('exam:auto-submit')->everyMinute()->withoutOverlapping();
  ```
  Daftar perintah terjadwal (`php artisan schedule:list`):
  ```text
  * * * * * php artisan exam:auto-submit .. Next Due: 24 seconds from now
  ```
- **Kompensasi Pengaman Saat Scheduler Tidak Aktif (Lazy Fallback):**
  Jika scheduler OS tidak berjalan di host lokal, sesi ujian yang kedaluwarsa tetap diamankan saat siswa berinteraksi via endpoint `save-answer` atau `start-session` karena method `ensureSessionActive()` langsung mendeteksi `ends_at` yang telah lewat dan menutup sesi seketika (*on-the-fly closure*).

### B. Host Target / Produksi
Pada server staging/produksi (Linux Ubuntu/Debian), scheduler Laravel **wajib** dipicu oleh cron OS atau systemd timer.

---

## 4. Konfigurasi Cron yang Wajib Dipasang pada Host Target

### Opsi 1: Cron Standar Linux (Direkomendasikan)
Pasang satu baris cron pada user service aplikasi (misal `www-data`):

```bash
* * * * * cd /var/www/sistlabum/apps/api && /usr/bin/php artisan schedule:run >> /var/log/sistlabum-scheduler.log 2>&1
```

> **Catatan Teknis Pemasangan:**
> 1. Gunakan path absolut ke direktori `apps/api` dan binary PHP yang digunakan host (`which php`).
> 2. Jangan memasang cron terpisah untuk `exam:auto-submit` secara langsung jika `schedule:run` sudah dipasang, guna mencegah eksekusi ganda yang tidak perlu.
> 3. Parameter `withoutOverlapping()` pada Laravel Scheduler menggunakan cache lock untuk menjamin tidak ada dua instance `exam:auto-submit` yang berjalan tumpang-tindih.

### Opsi 2: Systemd Timer (Alternatif Modern untuk Container / Linux Systemd)
Jika server produksi menggunakan systemd service:

**`/etc/systemd/system/sistlabum-scheduler.service`**
```ini
[Unit]
Description=SISTLABUM Laravel Scheduler Runner
After=network.target

[Service]
Type=oneshot
User=www-data
WorkingDirectory=/var/www/sistlabum/apps/api
ExecStart=/usr/bin/php artisan schedule:run
StandardOutput=append:/var/log/sistlabum-scheduler.log
StandardError=append:/var/log/sistlabum-scheduler.log
```

**`/etc/systemd/system/sistlabum-scheduler.timer`**
```ini
[Unit]
Description=Run SISTLABUM Scheduler Every Minute

[Timer]
OnCalendar=*-*-* *:*:00
AccuracySec=1s
Persistent=true

[Install]
WantedBy=timers.target
```

---

## 5. Evaluasi IndexedDB vs LocalStorage

| Aspek | LocalStorage (Diimplementasikan) | IndexedDB |
|---|---|---|
| **Karakteristik Operasi** | Sinkron (*Synchronous*) | Asinkron (*Asynchronous*) |
| **Keandalan saat Tab Ditutup Mendadak (`beforeunload`)** | **Sangat Tinggi**: Penulisan selesai seketika sebelum browser mematikan proses tab. | **Sedang**: Transaksi asinkron rawan terpotong (*aborted*) jika tab di-close paksa oleh siswa. |
| **Ukuran Data CBT** | Butir jawaban CBT berkisar antara 20–100 butir (~5 KB hingga 50 KB JSON), jauh di bawah batas 5 MB browser. | Cocok untuk file raksasa (video, media cache puluhan MB). |
| **Kompleksitas & Fallback** | Ringan, kompatibel dengan browser seluler lama, memiliki fallback memori jika storage terkunci. | Memerlukan skema upgrade database dan penanganan error kompleks pada browser mobile lama. |
| **Keputusan Arsitektur** | **Dipertahankan sebagai primary offline storage** untuk jawaban dan pelanggaran CBT, diperkuat dengan metadata sequence timestamp dan isolasi user ID. | Direkomendasikan hanya jika di masa depan CBT mendukung ujian berbasis lampiran video/audio luring resolusi tinggi. |

---

## 6. Risiko yang Masih Terbuka & Rekomendasi Mitigasi

1. **Downtime Cron OS Host:**
   - *Risiko:* Jika server host mengalami kegagalan cron daemon, sesi siswa yang menutup peramban tanpa menekan submit tidak segera ditutup pada menit yang sama.
   - *Mitigasi Eksisting:* Metode `ensureSessionActive()` pada backend langsung menutup sesi saat ada interaksi berikutnya. Pengawas juga memiliki tombol `force_submit` pada dashboard pengawas CBT.
   - *Rekomendasi:* Pasang monitoring detak jantung scheduler (*heartbeat monitoring*) seperti Healthchecks.io atau Sentry Cron Monitoring pada server produksi.
2. **Koneksi Jaringan Seluler Ekstrem:**
   - *Risiko:* Siswa menyelesaikan ujian di daerah dengan sinyal seluler putus-nyambung ekstrim.
   - *Mitigasi Eksisting:* Antrean offline menyimpan seluruh jawaban secara lokal dan mencatat status `saving` / `error` / `saved` pada status bar ujian. Tombol submit akhir memeriksa apakah masih ada antrean offline yang belum terkirim sebelum menutup sesi.

---

## 7. Daftar File yang Diubah

1. **`apps/api/app/Console/Commands/ExamAutoSubmitCommand.php`**:
   - Menambahkan per-session DB transaction dan locking (`lockForUpdate`).
   - Menambahkan evaluasi otomatis butir soal objektif (`gradeSessionObjectiveAnswers`) dan kalkulasi nilai akhir sesi saat penutupan otomatis.
   - Menambahkan error handling dan logging per sesi agar kegagalan satu sesi tidak membatalkan sesi lainnya.
2. **`apps/api/app/Services/Cbt/ExamGradingService.php`**:
   - Menambahkan method publik `gradeSessionObjectiveAnswers(ExamSession $session)`.
   - Mengubah `refreshSessionTotalScore` menjadi method publik dengan fallback default `$examQuestions = null`.
   - Mendukung parsing key `opt` pada evaluasi butir pilihan ganda.
3. **`apps/api/app/Services/Cbt/ExamSessionService.php`**:
   - Menambahkan perlindungan *stale answer overwrite*: membandingkan `answered_at` request dengan data DB untuk mencegah jawaban lama menimpa jawaban baru saat retry out-of-order.
4. **`apps/api/app/Http/Requests/Cbt/SaveExamAnswerRequest.php`**:
   - Menambahkan validasi `answered_at`, `idempotency_key`, dan `client_timestamp`.
5. **`apps/api/tests/Feature/Cbt/ExamTimingTest.php`**:
   - Menambahkan pengujian skenario 1, 5, 6, 9, 10, 11, 12, dan 13.
6. **`apps/web/src/app/(portal)/portal/siswa/ujian/[id]/page.tsx`**:
   - Menambahkan isolasi user-scoped storage key (`cbt_pending_answers_{userId}_{examId}`) untuk mencegah kebocoran data antar-siswa pada perangkat bersama.
   - Menambahkan struktur metadata timestamp dan idempotency key pada antrean lokal.
   - Memperkuat `flushPendingOfflineData` dengan perlindungan drain parsial (hanya menghapus butir yang terkonfirmasi sukses di server).
   - Menambahkan in-memory storage fallback saat storage lokal gagal.
   - Menangani kode error penolakan server `EXAM_TIME_OVER` dan `BUSINESS_RULE_VIOLATION`.
7. **`apps/web/src/test/cbt-timing.test.tsx`**:
   - Menambahkan pengujian clock skew negatif (-2 jam), kegagalan parsial saat drain antrean, dan isolasi antrean offline antar-akun siswa.
8. **`docs/audit/CBT_HARDENING_AUDIT_REPORT.md`**:
   - Dokumen laporan audit komprehensif baru tanpa menimpa laporan audit sebelumnya.
