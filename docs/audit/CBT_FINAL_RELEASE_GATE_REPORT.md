# SISTLABUM CBT — FINAL RELEASE GATE REPORT

**Tanggal Rilis:** 14 Oktober 2026  
**Status Release Gate:** **CONDITIONAL GO**  
**Auditor / Tim Pelaksana:** Antigravity AI Pair Programming Agent & Core Engineering Team  
**Cakupan Audit:** Modul Computer-Based Testing (CBT) SISTLABUM — `apps/api` (Laravel) & `apps/web` (Next.js 14)  
**Dokumen Rujukan:** `docs/audit/CBT_HARDENING_AUDIT_REPORT.md`, `08_DATABASE_SCHEMA.md`, `09_SECURITY_PRIVACY.md`, `AGENTS.md`

---

## 1. Executive Summary & Keputusan Akhir

Berdasarkan audit menyeluruh, hardening kode, dan eksekusi pengujian regresi penuh terhadap sistem ujian berbasis komputer (CBT) SISTLABUM, seluruh kriteria teknis pada kode aplikasi, basis data, dan antarmuka pengguna telah terpenuhi 100%.

| Parameter Penilaian | Target Standar | Status Aktual | Hasil |
|---|---|---|---|
| **Otoritas Waktu Server (A)** | Kebal clock skew & tab throttling | Offset absolut server & server-side timeout lock | **LULUS** |
| **Idempotensi & Anti-Stale (B)** | Jawaban out-of-order tidak menimpa | Aturan deterministik 3 lapis & atomic upsert | **LULUS** |
| **Auto-Submit & Grading (C)** | Transaksional, esai tidak dinilai otomatis | `lockForUpdate()`, esai score tetap `null` | **LULUS** |
| **Perangkat Bersama (D)** | Antrean offline tidak bocor antar-siswa | User-scoped key & active owner guard | **LULUS** |
| **Uji Regresi Backend** | 100% lulus, 0 kegagalan | **1.057 tests passed (5.785 assertions)** | **LULUS** |
| **Uji Regresi Web** | 100% lulus, 0 kegagalan | **52 test files passed (617 tests)** | **LULUS** |
| **Kualitas Kode & Build** | Strict TypeScript & Lint bersih | `tsc --noEmit` (0 error), Next.js Build (85 rute) | **LULUS** |
| **Runtime Scheduler Host (E)** | Daemon cron berjalan pada server target | Host produksi tidak diakses dari runner dev | **NOT VERIFIED** |

### Keputusan Rilis: **CONDITIONAL GO**

**Syarat Mutlak Menuju Status GO Penuh:**
1. Tim Operasional/DevOps wajib memasang baris crontab pada sistem operasi server target (`* * * * * cd /var/www/sistlabum/apps/api && /usr/bin/php artisan schedule:run >> /var/log/sistlabum-scheduler.log 2>&1`).
2. Melakukan verifikasi runtime bahwa cron berputar setidaknya satu kali dan mencatat entri log pertama secara sukses.

---

## 2. Analisis & Verifikasi Mendalam per Area

### A. Deadline dan Toleransi Waktu Ujian

1. **Status Toleransi +5 Detik (`cbt.grace_period_seconds`):**
   - **Bukan Kebijakan Pedagogis Resmi:** Toleransi 5 detik **tidak** diumumkan kepada siswa dan **bukan** tambahan waktu ujian resmi.
   - **Detail Implementasi Operasional (*Network Flight Buffer*):** Toleransi ini semata-mata merupakan buffer teknis di server untuk mengakomodasi waktu transmisi paket (*in-flight packets*) pada jaringan seluler/Wi-Fi berlatensi tinggi, agar jawaban yang diklik siswa pada detik-detik terakhir sebelum deadline tidak ditolak secara sepihak saat baru tiba di server 1–3 detik kemudian.
2. **Kekebalan terhadap Manipulasi Jam Klien:**
   - Server backend (`ExamSessionService::ensureSessionActive`) mengevaluasi deadline semata-mata berdasarkan waktu server saat request diterima:
     $$\text{server\_time} > \text{ends\_at} + \text{grace\_period}$$
   - Timestamp yang dikirim klien (`answered_at`) **tidak dapat digunakan** untuk meloloskan jawaban jika waktu server telah melewati deadline. Manipulasi jam klien ke masa lampau tidak memiliki efek apa pun terhadap penegakan batas waktu server.
3. **Uji Batas Waktu & Penolakan Mutasi:**
   - Tepat pada deadline $\rightarrow$ Diterima (status HTTP 200).
   - Setelah deadline dalam toleransi ($< 5\text{ detik}$) $\rightarrow$ Diterima.
   - Setelah toleransi deadline ($> 5\text{ detik}$) $\rightarrow$ Ditolak HTTP 422 dengan kode error `EXAM_TIME_OVER` (atau `BUSINESS_RULE_VIOLATION` untuk sesi warisan/legacy).
   - Retry lambat setelah deadline $\rightarrow$ Server mengunci sesi menjadi `selesai` (*timeout lock*) dan memicu penilaian otomatis untuk jawaban objektif yang telah tersimpan.
   - Seluruh mutasi lanjutan pada sesi yang telah berstatus `selesai` ditolak secara mutlak dengan HTTP 422 ("Sesi ujian telah selesai dan tidak dapat diubah").

---

### B. Konflik, Idempotensi, dan Perlindungan Jawaban

1. **Pembatasan Waktu Masuk (*Timestamp Clamping*):**
   - Timestamp masukan `answered_at` dari klien dibatasi (*clamped*) dua arah:
     - Batas Atas: Jika `answered_at > now()`, dipotong menjadi `now()` (mencegah timestamp masa depan).
     - Batas Bawah: Jika `answered_at < session.started_at`, dipotong menjadi `session.started_at` (mencegah timestamp purba sebelum ujian dimulai).
2. **Aturan Konflik Deterministik (3 Aturan Baku):**
   - **Aturan 1 (Idempotent Match):** Jika jawaban yang masuk memiliki nilai dan status ragu-ragu yang persis sama dengan record di basis data, server langsung mengembalikan `{ is_saved: true, is_stale: false }` tanpa melakukan penulisan berulang.
   - **Aturan 2 (Stale Out-of-Order Rejection):** Jika jawaban yang masuk memiliki timestamp lebih lama daripada data di database (`incoming.answered_at < existing.answered_at`), request ditandai sebagai data usang. Server mengembalikan `{ is_saved: true, is_stale: true }` tanpa menimpa jawaban terbaru di database.
   - **Aturan 3 (Tie-Breaker & Pembaruan):** Jika `incoming.answered_at >= existing.answered_at` dan nilai berbeda, pembaruan dieksekusi secara atomik menggunakan UPSERT berbasis basis data (`ON CONFLICT (session_id, question_id) DO UPDATE`), mempertahankan UUID awal baris untuk mencegah bloat id.
3. **Hasil Pengujian Konflik:**
   - Uji dua tab konkuren, timestamp identik, jam perangkat dimajukan (+3 jam) dan dimundurkan (-2 jam), serta paket out-of-order terbukti **lulus 100% tanpa inkonsistensi data**.

---

### C. Auto-Submit dan Penilaian (Grading)

1. **Transaksi & Row Locking:**
   - Perintah `exam:auto-submit` (`cbt:close-expired-sessions`) memproses setiap sesi kedaluwarsa di dalam transaksi database tersendiri yang dilindungi `lockForUpdate()`.
   - Jika dua worker scheduler berjalan pada saat bersamaan, worker kedua mendapati sesi telah bernilai `null` atau berstatus `selesai`, sehingga terhindar dari eksekusi ganda (*race condition*).
2. **Resiliensi Parsial & Audit Log:**
   - Kesalahan pada satu sesi dibungkus dalam blok try-catch dengan logging mandiri, menjamin bahwa kegagalan satu sesi tidak membatalkan pemrosesan sesi-sesi lainnya dalam antrean.
   - Setiap sesi yang ditutup otomatis menghasilkan entri audit resmi pada tabel `audit_logs` dengan aksi `EXAM_SESSION_AUTO_SUBMITTED`.
3. **Integritas Penilaian Soal Esai/Uraian:**
   - Pada ujian campuran (*mixed exams*) yang memuat butir Pilihan Ganda dan Uraian/Esai:
     - Soal objektif (PG, PG Kompleks, BS, Menjodohkan, Isian) dinilai secara otomatis oleh algoritma koreksi.
     - **Butir Uraian / Esai WAJIB TETAP BERSTATUS `score = null`!** Sistem **tidak menganggap jawaban esai telah dinilai secara otomatis**.
     - Skor sesi sementara dihitung proporsional terhadap total bobot ujian (misal butir PG benar bobot 10 dari total bobot 20 menghasilkan skor sementara 50,0). Skor final akan diperbarui saat guru menilai butir esai via modul penilaian manual.

---

### D. Keamanan Perangkat Bersama (*Shared Devices / Lab Computers*)

1. **Isolasi Antrean Lokal Antar-Akun:**
   - Di laboratorium sekolah, komputer digunakan bergantian oleh berbagai siswa antar-sesi.
   - Antrean jawaban offline dan metadata disimpan dengan pemisahan kunci berbasis ID pengguna:
     - `cbt_pending_answers_{userId}_{examId}`
     - `cbt_pending_answers_meta_{userId}_{examId}`
     - `cbt_pending_violations_{userId}_{examId}`
2. **Perlindungan Kunci Aktif (*Active Owner Guard*):**
   - Kunci global `cbt_pending_answers_{examId}` dipasangi penanda kepemilikan `cbt_active_owner_{examId} = userId`.
   - Jika Siswa A logout dan Siswa B membuka ujian di peramban yang sama, pembaca antrean mendeteksi `owner !== currentUserId` dan **menolak memuat data milik Siswa A**, mencegah kebocoran jawaban antar-siswa secara mutlak.
3. **Pembersihan Bersih (*Clean Queue Lifecycle*):**
   - Saat ujian dikonfirmasi selesai (`handleConfirmFinish`), waktu server habis (`handleForceSubmitTimeout`), atau sesi kedaluwarsa terdeteksi pada inisialisasi, fungsi `clearOfflineQueues()` membersihkan seluruh entri di `localStorage` baik yang berskope maupun global.
4. **Resiliensi Penyimpanan (In-Memory Fallback):**
   - Seluruh akses penyimpanan lokal dibungkus penanganan error yang mengalihkan data ke memori RAM (`memoryPendingAnswersRef`), menjamin ketiadaan crash pada peramban dengan mode *Incognito* ketat atau kuota penyimpanan penuh.

---

### E. Verifikasi Operasional Scheduler Host

1. **Pemeriksaan Daftar Tugas Terjadwal (`php artisan schedule:list`):**
   ```text
   * * * * * php artisan ppdb:close-wave .. Next Due: 8 seconds from now
   * * * * * php artisan exam:auto-submit .. Next Due: 8 seconds from now
   0 23 * * * php artisan attendance:daily-summary Next Due: 6 hours from now
   ...
   ```
   Perintah `exam:auto-submit` terdaftar aktif berjalan setiap menit (`* * * * *`) dengan proteksi `withoutOverlapping()`.
2. **Konfigurasi Crontab Sistem Linux Produksi:**
   ```bash
   # Tambahkan pada crontab user www-data (crontab -u www-data -e)
   * * * * * cd /var/www/sistlabum/apps/api && /usr/bin/php artisan schedule:run >> /var/log/sistlabum-scheduler.log 2>&1
   ```
3. **Rekomendasi Monitoring Keterlambatan Scheduler:**
   - Pasang health check berkala (misal via Healthchecks.io atau Laravel Schedule Monitor) untuk memberi peringatan jika `schedule:run` tidak dieksekusi lebih dari 3 menit.
4. **Deklarasi Kejujuran Status Runtime Produksi (AGENTS.md Bagian 2 & 9):**
   - Lingkungan eksekusi saat ini adalah runner pengujian/pengembangan macOS lokal (`crontab: no crontab for macbookprom12020`).
   - Runner ini **tidak memiliki akses SSH atau kontrol crontab langsung ke server produksi/staging**.
   - Sesuai aturan kerja integritas teknis, status operasional daemon cron pada server produksi dicatat secara jujur sebagai:  
     $$\mathbf{STATUS:\;NOT\;VERIFIED\;(Host\;SSH\;Unreachable)}$$

---

## 3. Bukti Pengujian Regresi Lengkap

### A. Pengujian Backend (`apps/api`)
```text
$ php artisan test
{"tool":"phpunit","result":"passed","tests":1057,"passed":1057,"assertions":5785,"duration_ms":36600}
```
- **Total Uji:** 1.057 pengujian fitur dan unit.
- **Hasil:** 1.057 lulus (0 gagal, 0 error, 0 dilewati).
- **Asersi:** 5.785 asersi verifikasi.
- **Uji Khusus CBT:** 167 pengujian CBT (termasuk 14 pengujian timing, race condition, mixed exam grading, dan idempotensi) lulus 100%.

### B. Pengujian Frontend (`apps/web`)
```text
$ npx vitest run
Test Files  52 passed (52)
     Tests  617 passed (617)
  Duration  7.80s
```
- **Total Uji:** 52 berkas pengujian (617 pengujian).
- **Hasil:** 617 lulus (100% GREEN).
- **Uji Khusus CBT:** `cbt-timing.test.tsx` (9 pengujian), `cbt-violation.test.tsx` (2 pengujian), `cbt.test.ts` (16 pengujian) lulus 100%.

### C. Pemeriksaan Tipe & Linting
- **TypeScript:** `npx tsc --noEmit` $\rightarrow$ **0 Error (Strict TypeScript Clean)**.
- **ESLint:** `npm run lint` $\rightarrow$ **0 Error, berkas CBT bersih dari warning hook dependencies**.
- **Next.js Production Build:** `npm run build` $\rightarrow$ **85 rute halaman (statis & dinamis) berhasil dikompilasi**.

---

## 4. Matriks Celah dan Status Penutupan

| ID Celah | Deskripsi Masalah | Solusi Implementasi | Status |
|---|---|---|---|
| **GAP-02** | Waktu ujian hardcode 5400s & rentan throttling browser | Offset absolut server (`server_time - Date.now()`), `visibilitychange` recompute, server deadline lock. | **FIXED** |
| **GAP-07** | Pelanggaran dihitung di klien tanpa otoritas server | Validasi tipe pelanggaran di server, increment atomik dengan `lockForUpdate()`, penegakan ambang kunci/diskualifikasi server. | **FIXED** |
| **RG-SEC-A** | Manipulasi jam klien membocorkan submit setelah deadline | Validasi server strictly menggunakan `$now > ends_at + grace`, penolakan mutlak 422 `EXAM_TIME_OVER`. | **FIXED** |
| **RG-SEC-B** | Out-of-order retry menimpa jawaban baru dengan jawaban usang | Aturan deterministik anti-stale (`incoming < existing` ditolak stale), atomic DB upsert. | **FIXED** |
| **RG-SEC-C** | Auto-submit berpotensi menganggap butir esai otomatis dinilai | Modul penilaian objektif memfilter ketat hanya tipe objektif; butir `uraian` (esai) dipertahankan `score = null`. | **FIXED** |
| **RG-SEC-D** | Antrean offline bocor antar-siswa pada komputer lab bersama | Kunci lokal diskope per user ID (`cbt_pending_answers_{userId}_{examId}`) dan diproteksi `cbt_active_owner`. | **FIXED** |
| **RG-OPS-E** | Kegagalan scheduler cron OS host produksi | Pendaftaran `exam:auto-submit` setiap menit di `routes/console.php` + dokumentasi instalasi crontab. | **CONDITIONAL** |

---

## 5. Panduan Instalasi & Checklist Go-Live untuk Tim DevOps

Sebelum membuka portal CBT untuk asesmen siswa secara langsung, tim DevOps wajib menyelesaikan checklist berikut:

- [ ] **1. Verifikasi Environment PHP & Composer:**
  Pastikan ekstensi `pdo_pgsql`, `bcmath`, `redis`, dan `pcntl` aktif pada runtime PHP server.
- [ ] **2. Pemasangan Crontab OS:**
  Jalankan perintah berikut pada server produksi:
  ```bash
  (crontab -u www-data -l 2>/dev/null; echo "* * * * * cd /var/www/sistlabum/apps/api && /usr/bin/php artisan schedule:run >> /var/log/sistlabum-scheduler.log 2>&1") | crontab -u www-data -
  ```
- [ ] **3. Verifikasi Log Pertama:**
  Pantau berkas log selama 2 menit:
  ```bash
  tail -f /var/log/sistlabum-scheduler.log
  ```
  Pastikan tidak terdapat pesan kesalahan permission atau path not found.
- [ ] **4. Monitoring Scheduler Eksternal:**
  Hubungkan command ke sistem pemantau heartbeat (misal Healthchecks.io) via flag `->thenPing(...)` pada `routes/console.php`.

---

**Kesimpulan Akhir:**  
Aplikasi **SISTLABUM CBT** telah diverifikasi aman, tangguh, dan memenuhi standar integritas tinggi. Status rilis resmi ditetapkan sebagai **CONDITIONAL GO**, menunggu konfirmasi pemasangan crontab pada host produksi oleh tim operasional.
