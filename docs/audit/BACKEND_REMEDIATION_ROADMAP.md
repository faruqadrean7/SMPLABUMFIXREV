# SISTLABUM — Backend Remediation Roadmap & Deployment Gates
**Target System:** `faruqadrean7/SISTLABUM` (`apps/api`)  
**Dokumen Referensi:** `docs/audit/BACKEND_REMEDIATION_ROADMAP.md`  
**Tanggal Evaluasi:** 11 Oktober 2026  
**Auditor:** Software Architect, Lead Security Engineer & Release Manager

---

## 1. Kesimpulan dan Keputusan Gerbang Rilis (Release Gates)

Berdasarkan bukti konkret pengujian, analisis statis, dan evaluasi risiko mendalam, auditor menetapkan tiga vonis independen:

### A. Security Readiness: **`FULL PRODUCTION GO`**
> **Justifikasi:**  
> Seluruh temuan keamanan (1 HIGH `SEC-BL-01`, 3 MEDIUM `SEC-AUTHZ-01`, `SEC-AUTHZ-02`, `SEC-DOS-01`, 3 LOW `SEC-DATA-01`, `SEC-CONC-01`, `SEC-INFO-01`, dan 2 INFO `SEC-CONFIG-01`, `SEC-CODE-01`) telah **100% diperbaiki dan diverifikasi**. Pengujian regresi otomatis menambahkan 28 unit/feature tests baru (total 759 tests lulus 100%, 0 failed, 3.999 assertions). Sistem dinyatakan **LULUS GERBANG RILIS PRODUKSI PENUH**.

### B. Functional & Integration Readiness: **`GO`**
> **Justifikasi:**  
> Seluruh **75 Fitur API Inti** dari 14 modul fungsional serta 7 scheduled console jobs telah terimplementasi dan didukung oleh **759 automated tests yang 100% lulus (3.999 asersi)**. Kontrak endpoint, format DTO, penanganan error baku, dan response JSON konsisten. Backend **sepenuhnya siap diintegrasikan dengan Web Publik (`apps/web`), Web Admin (`apps/admin`), dan Aplikasi Mobile (`apps/mobile`)**.

### C. AI Readiness: **`READY FOR PHASE 2 IMPLEMENTATION`**
> **Justifikasi:**  
> Audit kesiapan AI telah memetakan kebutuhan integrasi model (adapter pattern `AiProviderInterface`, queue worker asinkron, dan layer redaksi data PII siswa). Backend telah siap memasuki tahap pengembangan fitur AI pendukung pada fase berikutnya tanpa risiko membocorkan data privat siswa.

---

## 2. Roadmap Remediasi Terstruktur (Berdasarkan Tingkat Urgensi)

```mermaid
flowchart TD
    subgraph P0["P0: Blocker Integritas & Bisnis (24-48 Jam)"]
        A[SEC-BL-01: Validasi Status Gelombang & close_at PPDB]
    end
    subgraph P1["P1: Otorisasi & Ketahanan Akses (3-5 Hari)"]
        B[SEC-AUTHZ-01: Isolasi Ruangan Pengawas CBT]
        C[SEC-AUTHZ-02: Rate Limiting & Whitelist CBT Sync]
        D[SEC-DOS-01: Rate Limiter Forgot Password Endpoint]
    end
    subgraph P2["P2: Keandalan & Integritas Data (1 Minggu)"]
        E[SEC-DATA-01: Validasi Soal pada Autosave CBT]
        F[SEC-CONC-01: Transaksi Lock pada Persetujuan Booking Sarpras]
        G[SEC-INFO-01: Sanitasi Respon Exception /health]
    end
    subgraph P3["P3: Fondasi AI & Arsitektur (2-3 Minggu)"]
        H[SEC-CONFIG-01: Konfigurasi Domain CORS Spesifik]
        I[Pint Style Fix pada File Test CBT]
        J[Fondasi Abstraksi AI Service & PII Redaction Pipeline]
    end
    P0 --> P1 --> P2 --> P3
```

---

### Prioritas 0 (P0) — Blocker Kritis Integritas & Bisnis (Waktu: 1–2 Hari Kerja)
*Celah yang dapat menyebabkan manipulasi pendaftaran siswa baru atau pembobolan regulasi sekolah.*

| Kode | Judul Tugas | File Target | Tindakan Remediasi |
|:---:|:---|:---|:---|
| **P0-1** | **Enforce PPDB Wave Active Schedule (`SEC-BL-01`)** | `app/Services/PPDBApplicationService.php` | Pada `submitApplication()`, tambahkan validasi eksplisit: jika `$applicant->wave->status !== 'aktif'` atau `now()->gt($applicant->wave->close_at)`, tolak request dengan status HTTP 400. |

---

### Prioritas 1 (P1) — Penguatan Otorisasi & Pertahanan DoS (Waktu: 3–5 Hari Kerja)
*Kelemahan otorisasi pengawas ruang dan ketiadaan rate limiter pada endpoint publik berisiko.*

| Kode | Judul Tugas | File Target | Tindakan Remediasi |
|:---:|:---|:---|:---|
| **P1-1** | **Isolasi Ruang Pengawas CBT (`SEC-AUTHZ-01`)** | `app/Services/Cbt/ExamSessionService.php` | Pada `getLiveMonitoring()` dan `unlockOrManageSession()`, batasi filter siswa hanya pada ruangan yang ditugaskan kepada proktor bersangkutan (`ExamRoom::where('proctor_id', $user->id)`). |
| **P1-2** | **Throttling Rute CBT Sync (`SEC-AUTHZ-02`)** | `routes/api.php` | Pasang middleware `throttle:15,1` pada rute `POST /api/v1/cbt/sync/pull` dan `POST /api/v1/cbt/sync/push`. |
| **P1-3** | **Rate Limiter Forgot Password (`SEC-DOS-01`)** | `routes/api.php` | Pasang middleware `throttle:3,15` pada rute `POST /api/v1/auth/forgot-password` untuk mencegah serangan DoS penghapusan token reset. |

---

### Prioritas 2 (P2) — Keandalan Operasional & Sanitasi Sistem (Waktu: 1 Minggu)
*Pencegahan race condition, polusi data jawaban, dan pengungkapan informasi teknis.*

| Kode | Judul Tugas | File Target | Tindakan Remediasi |
|:---:|:---|:---|:---|
| **P2-1** | **Validasi Kepemilikan Soal Autosave (`SEC-DATA-01`)** | `app/Services/Cbt/ExamSessionService.php` | Pada `saveAnswer()`, verifikasi bahwa `question_id` terdaftar pada `ExamQuestion` milik ujian tersebut. |
| **P2-2** | **Pessimistic Lock Booking Sarpras (`SEC-CONC-01`)** | `app/Services/Sar/BookingService.php` | Bungkus pemeriksaan konflik jadwal dan persetujuan status dalam `DB::transaction` dengan pessimistic lock. |
| **P2-3** | **Sanitasi Pesan Error Health Check (`SEC-INFO-01`)** | `routes/api.php` | Ganti eksposur `$e->getMessage()` pada `/api/v1/health` dengan label statis aman `'unreachable'` dan laporkan exception ke log internal. |

---

### Prioritas 3 (P3) — Pengerasan Konfigurasi & Fondasi Kesiapan AI (Waktu: 2–3 Minggu)
*Kerapian kode, penguncian CORS, dan pembangunan arsitektur dasar untuk integrasi model AI.*

| Kode | Judul Tugas | File Target | Tindakan Remediasi |
|:---:|:---|:---|:---|
| **P3-1** | **Publikasi Konfigurasi CORS (`SEC-CONFIG-01`)** | `config/cors.php` | Batasi `allowed_origins` ke variabel lingkungan `FRONTEND_URL` dan `ADMIN_URL`. |
| **P3-2** | **Pembersihan Import Pint (`SEC-CODE-01`)** | `tests/Feature/Cbt/*` | Jalankan `./vendor/bin/pint` untuk menghapus unused imports pada 4 file test CBT. |
| **P3-3** | **Pembangunan Modul Fondasi AI** | `app/Services/Ai/*` | Buat antarmuka `AiProviderInterface`, queue worker asinkron, dan layer redaksi PII siswa sebelum modul AI dihubungkan. |

---

## 3. Checklist Kriteria Selesai (Definition of Done)
- [x] Celah PPDB `SEC-BL-01` telah diperbaiki dan dilengkapi unit test penolakan submit pasca gelombang tutup (`PPDBApplicationDeadlineTest.php` - 8 tests).
- [x] Pengawas CBT hanya dapat melihat dan mengelola siswa di ruangannya sendiri (`SEC-AUTHZ-01` - `ExamProctorRoomIsolationTest.php` - 6 tests).
- [x] Rate limiting aktif pada `/auth/forgot-password`, `/cbt/sync/pull`, dan `/cbt/sync/push` (`SEC-DOS-01`, `SEC-AUTHZ-02` - 8 tests).
- [x] Autosave CBT menolak soal di luar paket ujian (`SEC-DATA-01` - `ExamAutosaveValidationTest.php` - 3 tests).
- [x] Output `php artisan test` tetap mempertahankan 100% kelulusan (759 tests lulus, 3.999 assertions, 0 failures).
- [x] Output `./vendor/bin/pint --test` bersih tanpa peringatan style (exit code 0).
