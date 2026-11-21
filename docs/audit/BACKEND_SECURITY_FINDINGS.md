# Laporan Audit Keamanan & Privasi Backend SISTLABUM (Security & Privacy Audit)
**Dokumen Referensi:** `docs/audit/BACKEND_SECURITY_FINDINGS.md`  
**Tanggal Audit:** 11 Oktober 2026  
**Auditor:** Senior Security Auditor & Application Security Architect  
**Prinsip Audit:** *Implementation Evidence over Documentation Claims*

---

## 1. Ringkasan Eksekutif Keamanan (Security Executive Summary)

Audit keamanan dan privasi data dilakukan secara mendalam terhadap seluruh arsitektur backend SISTLABUM (`apps/api`) dengan mengacu pada panduan keamanan data anak dan institusi pendidikan (**`09_SECURITY_PRIVACY.md`**) serta standar OWASP API Security Top 10 (2023).

Backend mengelola data sensitif anak (NIK, identitas wali, catatan akademik), data psikologis privat (Bimbingan Konseling), pengaduan pelanggaran anonim (Whistleblowing System), dan integritas ujian digital (CBT).

### Matriks Temuan Keamanan Faktual:
| Tingkat Keparahan (Severity) | Jumlah Temuan | Status Tindak Lanjut |
|---|:---:|:---|
| 🔴 **CRITICAL** | **0** | Tidak ada celah kritis (RCE, SQLi, Auth Bypass) |
| 🟠 **HIGH** | **0** | Tidak ada celah level tinggi |
| 🟡 **MEDIUM** | **2** | Perlu penguatan konfigurasi Gateway & Key Rotation |
| 🟢 **LOW** | **3** | Penguatan konfigurasi CORS, Upload Buffer, dan TTL Cache |
| 🔵 **INFORMATIONAL** | **2** | Best practice retensi log audit dan pembersihan token |

> **Postur Keamanan Keseluruhan:** **KUAT & MEMENUHI STANDAR PRIVASI TINGGI (STRONG POSTURE)**  
> Implementasi pertahanan berlapis (defense-in-depth), otorisasi deny-by-default, enkripsi at-rest NIK siswa, isolasi data BK/Wali, dan pembersihan kunci jawaban CBT telah diverifikasi berjalan dengan sangat baik pada kode sumber.

---

## 2. Verifikasi Kontrol Keamanan Wajib (Mandatory Security Controls)

| No | Kontrol Keamanan | Status Implementasi | Bukti Kode Sumber & Lokasi |
|:--:|:---|:---:|:---|
| 1 | **Password Hashing** | ✅ **VERIFIED** | Menggunakan algoritma Bcrypt bawaan Laravel (`config/hashing.php`) dengan rounds terstandarisasi. |
| 2 | **Autentikasi Token** | ✅ **VERIFIED** | Laravel Sanctum personal access token dengan revocable tokens pada `LogoutController` & `RefreshTokenController`. |
| 3 | **Otorisasi Deny-by-Default** | ✅ **VERIFIED** | Seluruh rute non-publik di-guard via `auth:sanctum` dan middleware `permission:...` (`RequirePermission::class`). |
| 4 | **Pencegahan SQL Injection** | ✅ **VERIFIED** | 100% query memanfaatkan Eloquent ORM & Query Builder bertipe parameter binding; ekspresi `DB::raw` hanya berupa fungsi agregat statis. |
| 5 | **Perlindungan Data Pribadi (PII)**| ✅ **VERIFIED** | NIK siswa dan calon pendaftar dienkripsi *at-rest* via cast `'nik_enc' => 'encrypted'` (`Student.php:43`, `Applicant.php:39`) dan disamarkan saat dibaca (`StudentService.php:653`). |
| 6 | **Kerahasiaan Catatan BK** | ✅ **VERIFIED** | Ringkasan kasus BK dienkripsi at-rest (`summary_enc`). Setiap pembacaan kasus mencatat jejak audit (`CounselingService.php:104`). Khusus peran Kepala Sekolah, ringkasan otomatis disamarkan. |
| 7 | **Whistleblowing Anonim** | ✅ **VERIFIED** | Model `Complaint.php` mendukung flag `is_anonymous` murni tanpa menyimpan identitas pelapor ke log atau response publik. Pelacakan menggunakan token acak `ticket_code`. |
| 8 | **Pencegahan Kebocoran Kunci CBT** | ✅ **VERIFIED** | `ExamSessionService.php:207` secara eksplisit membuang atribut `is_correct` dari opsi jawaban sebelum dikirimkan ke layar siswa. |
| 9 | **Penyimpanan Berkas Bertanda Tangan** | ✅ **VERIFIED** | `FileStorageService.php` menggunakan UUID random untuk nama berkas fisik, whitelist ekstensi, dan URL unduhan privat bertanda tangan berumur pendek (`<= 5 menit`). |
| 10 | **Audit Trail Menyeluruh** | ✅ **VERIFIED** | Tabel `audit_logs` aktif mencatat aktor, aksi, IP, User-Agent, dan delta perubahan pada seluruh modul administratif. |

---

## 3. Rincian Temuan & Rekomendasi (Detailed Findings)

### 3.1. Temuan Tingkat Medium (Medium Severity)

#### SEC-MED-01: Penerapan Rate Limiting pada API Gateway / Reverse Proxy
- **Kategori:** OWASP API4:2023 — Unrestricted Resource Consumption
- **Lokasi Terkait:** `apps/api/bootstrap/app.php`, `apps/api/routes/api.php`
- **Kondisi Faktual:**
  - Exception renderer pada `bootstrap/app.php:71` telah siap menangani `ThrottleRequestsException` dan memetakan kode error `RATE_LIMITED` (429).
  - Namun, pada `routes/api.php`, middleware `throttle:...` belum di-attach secara eksplisit ke grup route publik yang sensitif (seperti `POST /api/v1/auth/login`, `POST /api/v1/whistleblowing`, dan `POST /api/v1/storage/upload/presign`).
- **Potensi Risiko:**
  - Jika backend diekspos langsung ke internet tanpa API Gateway (Nginx / Cloudflare WAF), endpoint autentikasi berpotensi menjadi target *brute force* dan *credential stuffing*.
- **Rekomendasi Remediasi:**
  - Tambahkan konfigurasi `RateLimiter::for('api', ...)` dan `RateLimiter::for('login', ...)` di `bootstrap/app.php` atau `AppServiceProvider.php` (misal 5 request/menit untuk login, 60 request/menit untuk rute API umum).
  - Pastikan reverse proxy production (Nginx / Traefik / Cloudflare) memiliki rule `limit_req_zone` aktif.

#### SEC-MED-02: Manajemen Kunci Enkripsi At-Rest (Key Management SOP)
- **Kategori:** Cryptographic Storage & Key Lifecycle
- **Lokasi Terkait:** `apps/api/app/Models/Student.php`, `apps/api/app/Models/Applicant.php`
- **Kondisi Faktual:**
  - Enkripsi kolom `nik_enc` bergantung sepenuhnya pada nilai `APP_KEY` environment runtime Laravel.
- **Potensi Risiko:**
  - Apabila `APP_KEY` diganti tanpa prosedur *re-encryption*, seluruh data NIK siswa dan pendaftar yang tersimpan di database tidak akan dapat didekripsi kembali (terjadi *unrecoverable data corruption*).
- **Rekomendasi Remediasi:**
  - Buat Standar Operasional Prosedur (SOP) rotasi kunci enkripsi.
  - Sediakan console command pemeliharaan khusus (`app:re-encrypt-nik`) jika sewaktu-waktu institusi sekolah mewajibkan rotasi kunci aplikasi tahunan.

---

### 3.2. Temuan Tingkat Rendah (Low Severity)

#### SEC-LOW-01: Toleransi Ukuran Berkas pada Direct Stream Upload
- **Kategori:** Input Validation & Storage Safety
- **Lokasi Terkait:** `apps/api/app/Services/Storage/FileStorageService.php:109`
- **Kondisi Faktual:**
  - Logika pengecekan ukuran berkas memberikan toleransi 10% + 1KB:
    ```php
    if ($size > ($cacheData['size_bytes'] * 1.1) + 1024) { ... }
    ```
  - Hal ini ditujukan untuk mengakomodasi boundary header HTTP jika berkas dikirim dalam bentuk multipart.
- **Potensi Risiko:**
  - Ukuran berkas fisik sedikit lebih besar daripada kuota presign yang dialokasikan di awal.
- **Rekomendasi Remediasi:**
  - Pisahkan penanganan multipart form-data (yang diukur dari `$request->file('file')->getSize()`) dengan raw body stream agar pengecekan binary stream dapat dilakukan secara eksak tanpa toleransi tambahan.

#### SEC-LOW-02: Pengaturan CORS Explicit Origin untuk Production
- **Kategori:** Cross-Origin Resource Sharing
- **Lokasi Terkait:** `apps/api/config/`
- **Kondisi Faktual:**
  - File `config/cors.php` belum dipublikasikan secara eksplisit, sehingga masih menggunakan default internal framework.
- **Potensi Risiko:**
  - Potensi request cross-origin dari domain yang tidak berwenang jika environment production tidak mengunci origin.
- **Rekomendasi Remediasi:**
  - Publikasikan file konfigurasi CORS dan definisikan domain resmi:
    ```env
    CORS_ALLOWED_ORIGINS=https://portal.sekolah.sch.id,https://admin.sekolah.sch.id
    ```

#### SEC-LOW-03: Masa Berlaku (TTL) Presigned Download Berkas Sensitif
- **Kategori:** Temporary Credential Exposure
- **Lokasi Terkait:** `apps/api/app/Services/Storage/FileStorageService.php:189`
- **Kondisi Faktual:**
  - Default TTL untuk presigned download URL adalah 300 detik (5 menit).
- **Potensi Risiko:**
  - Jika link unduhan berkas konseling BK dibagikan oleh staf yang berhak ke pihak luar, tautan masih dapat dibuka selama 5 menit.
- **Rekomendasi Remediasi:**
  - Kurangi TTL khusus untuk berkas BK dan investigasi sensitif menjadi 60-120 detik.

---

### 3.3. Informasi & Praktik Terbaik (Informational)

#### SEC-INFO-01: Kebijakan Retensi & Pengarsipan Audit Log
- **Kondisi:**
  - Seluruh operasi data penting (termasuk akses baca BK) menulis baris baru ke tabel `audit_logs`.
- **Rekomendasi:**
  - Implementasikan command pembersihan atau pengarsipan otomatis (misal: memindahkan log yang berumur lebih dari 365 hari ke cold storage / file archive) agar ukuran basis data transaksi utama tetap optimal.

#### SEC-INFO-02: Pembersihan Token Sanctum Kedaluwarsa
- **Kondisi:**
  - Pengguna yang sering login/logout menghasilkan baris pada tabel `personal_access_tokens`.
- **Rekomendasi:**
  - Pastikan perintah `php artisan sanctum:prune-expired` didaftarkan pada task scheduler harian di `routes/console.php`.

---

## 4. Evaluasi Pencegahan Celah Berbahaya Spesifik Pendidikan

### 4.1. Pencegahan Insecure Direct Object Reference (IDOR / BOLA)
Audit melakukan sampling verifikasi pada 3 modul kritis:
1. **Modul BK (`CounselingService.php`):**
   - Siswa atau wali kelas tidak dapat membuka endpoint kasus konseling siswa lain.
   - Hak akses dibatasi ketat hanya untuk guru BK terdaftar, Super Admin, dan Kepala Sekolah (dengan masking data sensitif).
2. **Modul Portal Wali (`GuardianPortalDashboardTest.php`):**
   - Wali murid hanya dapat melihat data nilai, presensi, dan jadwal dari siswa yang terdaftar sah dalam tabel relasi `guardian_students`. Request ke ID siswa lain menghasilkan penolakan otorisasi (`403` / `404`).
3. **Modul Penyimpanan Berkas (`FileStorageService.php:260`):**
   - Fungsi `authorizeFileAccess()` memvalidasi keterikatan berkas privat dengan aktor peminta (apakah pemilik langsung, wali murid yang sah dari siswa bersangkutan, atau administrator).

### 4.2. Perlindungan Ujian Digital (CBT Anti-Cheating Baseline)
1. **Jawaban Benar (`is_correct`):** Tidak pernah disertakan dalam payload pengerjaan siswa.
2. **Acak Soal & Opsi Berbasis Benih (`Seed`):** Menggunakan `shuffleWithSeed($session->seed)` sehingga urutan soal dan pilihan ganda siswa satu dengan yang lain berbeda secara konsisten.
3. **Penyimpanan Bertahap:** Setiap perubahan jawaban disimpan secara atomik (`SaveExamAnswerRequest`) untuk mencegah kehilangan data akibat pemadaman listrik atau gangguan jaringan lokal sekolah.

---

## 5. Kesimpulan Audit Keamanan

> **VERDIK KEAMANAN: LAYAK & AMAN (SECURE FOR INTEGRATION & PRE-PRODUCTION)**  
> Arsitektur backend SISTLABUM telah dibangun dengan kedisiplinan keamanan yang sangat tinggi, menerapkan prinsip *defense-in-depth*, *deny-by-default*, enkripsi data anak, serta perlindungan kerahasiaan konseling dan ujian. Temuan level Medium dan Low bersifat penguatan operasional (hardening) yang dapat diselesaikan sebelum peluncuran production publik.
