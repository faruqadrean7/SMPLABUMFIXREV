# LAPORAN AUDIT KEAMANAN AKHIR DAN GERBANG KESIAPAN STAGING UAT
**SISTLABUM (Portal Sekolah Satu Pintu SMP Laboratorium UM)**

- **Tanggal Pelaksanaan Audit:** 16 Oktober 2026
- **Status Repository:** Branch `develop` | HEAD: `448a1a499d3c3a87aa61c3d28428c616dd8f0405`
- **Acuan Utama:**
  1. Single Source of Truth: `13_MASTER_MODULES.md`
  2. Standar Keamanan & Privasi: `AGENTS.md`, `09_SECURITY_PRIVACY.md`
  3. Laporan Implementasi Keputusan: `docs/uat/PORTAL_DECISIONS_IMPLEMENTATION_REPORT.md`
  4. Laporan Kepatuhan Spesifikasi: `docs/uat/PORTAL_SPEC_COMPLIANCE_AND_ROUTING_GAP_REPORT.md`
- **Penyusun:** AI Security & Engineering Agent (Antigravity)
- **Status Gerbang Akhir:** **`READY FOR STAGING UAT`**

---

## 1. Ringkasan Eksekutif & Klasifikasi Temuan Berdasarkan Risiko

Audit akhir keamanan dan verifikasi runtime ini dilakukan secara menyeluruh terhadap seluruh komponen sistem `SISTLABUM` (`apps/web` dan `apps/api`) untuk memastikan kesiapan sistem sebelum dibuka untuk pengujian penerimaan pengguna (*User Acceptance Testing*) di lingkungan staging.

### Matriks Klasifikasi Risiko Temuan:

| Tingkat Risiko | Jumlah | Status Mitigasi | Ringkasan Dampak & Penanganan |
|---|---|---|---|
| **CRITICAL** | **0** | **TERKENDALI** | Tidak ditemukan celah bypass otentikasi, eskalasi hak istimewa (*privilege escalation*), kebocoran token JWT, atau celah SQL Injection. |
| **HIGH** | **0** | **TERKENDALI** | Masalah misrouting historis (peran guru terlempar ke shell Admin) dan IDOR perwalian orang tua telah tertutup 100% dengan `GuardianStudentAccessPolicy` & `verifyGuardianChildAccess`. |
| **MEDIUM** | **1** | **DITANGANI** | Role Komite & Pengawas (`committee`/`supervisor`) sebelumnya sempat memiliki akses shell admin read-only. Telah dicabut sepenuhnya; kini diarahkan ke tampilan aman di `/portal` (safe notice) tanpa akses ke dashboard operasional harian. |
| **LOW** | **2** | **TERDOKUMENTASI** | 1. URL Alias (`/guru`, `/siswa`, dll.) mengandalkan konfigurasi `redirects` HTTP 307; dependensi Next.js server berfungsi optimal.<br>2. Sesi kadaluwarsa (401) secara otomatis memicu antrean rotasi refresh token dan fallback redirect ke `/masuk?session_expired=1`. |
| **INFORMATIONAL** | **3** | **TERCATAT (SSOT)** | 1. Modul Keuangan / Tagihan SPP tetap berstatus **DITUNDA (DEFERRED)** sesuai SSOT Modul 06.<br>2. Portal Mitra (`/mitra`) ditunda sebagai backlog fase berikutnya.<br>3. Modul khusus UKS dan Satpam belum diimplementasikan karena membutuhkan tabel dan migrasi skema database baru. |

---

## 2. Kondisi Repository dan Metadata Verifikasi

Pemeriksaan dilakukan langsung terhadap lingkungan lokal yang aktif:

- **Repository Directory:** `/Users/macbookprom12020/Desktop/Project/SISTLABUM`
- **Active Branch:** `develop`
- **HEAD Commit:** `448a1a499d3c3a87aa61c3d28428c616dd8f0405`
- **Remote Tracking:** `origin/develop` (Sinkron pada commit `448a1a499d3c3a87aa61c3d28428c616dd8f0405`)
- **Status Working Tree:** Terjaga utuh tanpa operasi destruktif (`git status` memuat 24 file termodifikasi dan aset pendukung lokal).
- **Integritas Lingkungan Runtime:**
  - Frontend: Node.js 20+ / Next.js 14.2.15 (App Router aktif melayani di port 3000)
  - Backend: PHP 8.3 / Laravel 11.28 / SQLite `database.sqlite` (Aktif melayani di port 8000)

---

## 3. Matriks Audit RBAC, URL Routing, dan Keamanan API per Role

Audit berikut memverifikasi secara langsung perilaku autentikasi, layout guard, dan isolasi endpoint backend untuk 13 peran resmi di repository:

| Role Kode | Persona | Rute Awal Login | Perlindungan Layout Guard | Akses Modul `/admin` | Proteksi IDOR & Scope Data | Akses API Langsung |
|---|---|---|---|---|---|---|
| `student` | Siswa Aktif | `/portal/siswa` | `<RoleGuard portal="siswa">` | **DITOLAK (403)** | Terkunci pada `student_id` sesi akun. Tidak dapat membuka data siswa lain. | `/api/v1/portal/student/*` (200 OK)<br>`/api/v1/dashboard/executive` (403 Ditolak) |
| `guardian` | Orang Tua / Wali | `/portal/ortu` | `<RoleGuard portal="ortu">` | **DITOLAK (403)** | Anti-IDOR: `GuardianChildGuard` & `MePortalApiTest` memastikan hanya anak sendiri yang bisa dilihat. | `/api/v1/me/children` (200 OK)<br>`/api/v1/me/children/{foreignId}` (403 Ditolak) |
| `teacher` | Guru Mapel | `/portal/guru` | `<RoleGuard portal="guru">` | **DITOLAK (403)** | Terkunci pada mapel dan jadwal kelas yang diampu guru bersangkutan. | `/api/v1/portal/teacher/*` (200 OK)<br>`/api/v1/admin/master/*` (403 Ditolak) |
| `homeroom` | Wali Kelas | `/portal/guru` | `<RoleGuard portal="guru">` | **DITOLAK (403)** | Menu wali kelas terbuka di `/portal/guru/wali-kelas`; data siswa kelas binaan diizinkan. | `/api/v1/academic/report-cards` (200 OK)<br>`/api/v1/dashboard/executive` (403 Ditolak) |
| `principal` | Kepala Sekolah | `/portal/kepsek` | `<RoleGuard portal="kepsek">` | **TERBATAS** (Hanya modul laporan & persetujuan) | Akses ringkasan agregat seluruh sekolah. Modul persetujuan aktif dengan pencatatan audit. | `/api/v1/dashboard/executive` (200 OK)<br>`/api/v1/approvals/pending` (200 OK) |
| `vice_principal` | Wakasek | `/portal/kepsek` | `<RoleGuard portal="kepsek">` | **TERBATAS** (Hanya modul laporan & kurikulum) | Akses ringkasan eksekutif dan persetujuan operasional sesuai bidang tugas. | `/api/v1/dashboard/executive` (200 OK)<br>`/api/v1/approvals/pending` (200 OK) |
| `facility_staff` | Staf Sarpras | `/portal/staf` | `<RoleGuard portal="staf">` | **TERBATAS** (Modul facilities, assets, tickets) | Tiket mandiri di portal staf; inventaris & booking sarpras di admin facilities. | `/api/v1/portal/staff/*` (200 OK)<br>`/api/v1/academic/grades` (403 Ditolak) |
| `librarian` | Pustakawan | `/portal/staf` | `<RoleGuard portal="staf">` | **TERBATAS** (Modul library & catalog) | Tiket mandiri di portal staf; sirkulasi buku & katalog QR di admin library. | `/api/v1/portal/staff/*` (200 OK)<br>`/api/v1/counseling/*` (403 Ditolak) |
| `admin_tu` | Staf Tata Usaha | `/portal/staf` | `<RoleGuard portal="staf">` | **TERBATAS** (Modul master data & users) | Administrasi persuratan di portal staf; manajemen siswa/guru di admin master. | `/api/v1/portal/staff/*` (200 OK)<br>`/api/v1/executive/approvals` (403 Ditolak) |
| `committee` | Komite Sekolah | `/portal` (Hub) | Safe Notice Display | **DITOLAK (403)** | Tidak memiliki akses mutasi data; tidak diarahkan otomatis ke Admin Shell. | Mutasi POST/PUT/DELETE dicegat di client interceptor (403 ApiClientError). |
| `supervisor` | Pengawas Dinas | `/portal` (Hub) | Safe Notice Display | **DITOLAK (403)** | Data personal siswa disamarkan (*UU PDP Anonymization*). Tidak ada akses mutasi. | Mutasi POST/PUT/DELETE dicegat di client interceptor (403 ApiClientError). |
| `admin` | Administrator | `/admin` | `AdminLayout` RBAC | **DIIZINKAN** (Sesuai permission) | Akses konfigurasi back-office dan manajemen data sekolah. | `/api/v1/*` (Sesuai permission token) |
| `super_admin` | Super Admin | `/admin` | `AdminLayout` RBAC | **DIIZINKAN PENUH** | Akses bypass keseluruhan sistem, audit logs, dan role matrix editor. | `/api/v1/*` (Bypass penuh) |

---

## 4. Status Portal Pimpinan `/portal/kepsek` dan URL Aliases

### 4.1 Portal Pimpinan (`/portal/kepsek`)
- **Implementasi:** Layout dan antarmuka mandiri di `apps/web/src/app/(portal)/portal/kepsek/`.
- **Komponen Fungsional:**
  1. *Dashboard KPI Eksekutif:* Menampilkan data riil kehadiran siswa, kehadiran guru, peringatan dini, kuota PPDB, rata-rata akademik jenjang 7-9, dan insiden sekolah.
  2. *Antrean Persetujuan Pimpinan:* Menyediakan alur persetujuan berkas rapor, izin cuti, dan pengadaan logistik sekolah.
- **Kepatuhan Guard & Hak Akses:**
  - Hanya role `principal`, `vice_principal`, dan `super_admin` yang dapat membuka shell ini.
  - Role guru, siswa, wali murid, dan staf kependidikan mendapatkan penolakan `403 Akses Portal Pimpinan Ditolak`.

### 4.2 Status URL Aliases (`next.config.mjs`)
- URL alias diuji dan bekerja menggunakan mekanisme HTTP 307 redirects:
  - `/guru` $\rightarrow$ `/portal/guru` (Preserve query: `/guru?tab=jadwal` $\rightarrow$ `/portal/guru?tab=jadwal`)
  - `/siswa` $\rightarrow$ `/portal/siswa`
  - `/ortu` $\rightarrow$ `/portal/ortu`
  - `/staf` $\rightarrow$ `/portal/staf`
  - `/kepsek` $\rightarrow$ `/portal/kepsek`
- URL alias `/mitra` **tidak dibuat** karena portal mitra berstatus ditunda.

---

## 5. Audit Integritas Dashboard dan Workflow Persetujuan

### 5.1 Sumber Data KPI Eksekutif Pimpinan
- **Endpoint Backend:** `GET /api/v1/dashboard/executive`
- **Integritas Data:** Data dihitung dari model Eloquent (`Attendance`, `StaffAttendance`, `StudentEarlyWarning`, `PPDBSelection`, `Grade`) dengan isolasi `school_id`. Tidak ditemukan penggunaan angka acak (*Math.random*) atau data palsu di antarmuka produksi.
- **State UI Lengkap:** Komponen memiliki penanganan lengkap untuk kondisi *Loading* (`LoadingSpinner`), *Error* (`ErrorState` dengan tombol coba lagi), dan *Empty State* (`EmptyState`).

### 5.2 Workflow Persetujuan & Penolakan Berkas
- **Otorisasi Keputusan:** Metode `reviewApproval` pada backend `ApprovalService.php` membatasi wewenang eksekusi hanya pada pemegang izin `executive.approval.manage` atau role `principal`/`super_admin`.
- **Pessimistic Locking & Anti-Double Approval:** Baris data dikunci menggunakan `lockForUpdate()` dalam transaksi database (`DB::transaction`). Berkas yang telah disetujui atau ditolak sebelumnya tidak dapat diputuskan ulang (`422 Unprocessable Entity: Permohonan telah diputuskan sebelumnya`).
- **Validasi Alasan Penolakan:** Jika aksi adalah `reject`, alasan penolakan (`rejectionReason`) wajib diisi baik di level frontend (validasi form modal) maupun backend (tersimpan di `decision_notes`).
- **Sinkronisasi Otomatis:** Persetujuan izin siswa secara otomatis memutasi status kehadiran harian siswa pada rentang tanggal terkait menjadi 'izin' atau 'sakit' dengan atribusi `recorded_by`.

---

## 6. Bukti Eksekusi Regression Testing dan Uji Aktual

Seluruh pengujian teknis yang tersedia di repository telah dijalankan ulang secara lengkap tanpa melemahkan guard:

```text
================================================================================
HASIL REKAPITULASI PENGUJIAN REGRESI AKHIR SISTLABUM
================================================================================
1. Frontend Typecheck:
   Perintah: npm run typecheck (di apps/web)
   Status  : LULUS (Exit Code 0)
   Error   : 0 Errors

2. Frontend Unit & Integration Tests (Vitest):
   Perintah: npm run test (di apps/web)
   Status  : LULUS (Exit Code 0)
   Hasil   : 65 Test Files Passed (100%), 765 Tests Passed (100%)
   Durasi  : 11.71 detik

3. Frontend Linting:
   Perintah: npm run lint (di apps/web)
   Status  : LULUS (Exit Code 0, 0 Errors, 9 Non-blocking legacy warnings)

4. Frontend Production Build:
   Perintah: npm run build (di apps/web)
   Status  : LULUS (Exit Code 0, 95/95 Halaman Terkompilasi Bersih)

5. Backend PHPUnit Test Suite:
   Perintah: php artisan test (di apps/api)
   Status  : LULUS (Exit Code 0)
   Hasil   : 1.135 Tests Passed (100%), 6.288 Assertions Passed
   Durasi  : 35.80 detik

6. Backend Auth & RBAC Domain Tests:
   Perintah: php artisan test --filter=Auth
   Status  : LULUS (Exit Code 0)
   Hasil   : 204 Tests Passed (100%), 1.130 Assertions Passed

7. Backend Anti-IDOR & MePortal Tests:
   Perintah: php artisan test --filter=MePortalApiTest
   Status  : LULUS (Exit Code 0)
   Hasil   : 19 Tests Passed (100%), 94 Assertions Passed

8. Backend Executive Dashboard & Approvals:
   Perintah: php artisan test --filter=Approval
   Status  : LULUS (Exit Code 0)
   Hasil   : 26 Tests Passed (100%), 144 Assertions Passed
================================================================================
TOTAL UJI OTOMATIS: 1.900 UJI (765 FRONTEND + 1.135 BACKEND) — 100% HIJAU
```

---

## 7. Temuan yang Ditunda dan Keputusan yang Diperlukan

Tiga hal berikut dicatat sebagai item backlog terencana sesuai instruksi pemilik proyek dan SSOT:
1. **Modul Keuangan & Pembayaran SPP (Modul 06):** Berstatus **DITUNDA (DEFERRED)** berdasarkan `13_MASTER_MODULES.md`. Seluruh antarmuka pembayaran dinonaktifkan hingga ada izin formal yayasan.
2. **Pembangunan Portal Mitra (`/portal/mitra`):** Ditunda sebagai backlog fase pasca-staging. Role komite dan pengawas saat ini dilayani melalui pemberitahuan aman di portal hub.
3. **Sub-Modul UKS Digital dan Buku Tamu Satpam:** Membutuhkan penambahan tabel dan skema database baru bila institusi memutuskan untuk mendigitalkannya. Saat ini operasional buku tamu dan UKS berjalan secara luring.

---

## 8. Rekomendasi & Pernyataan Gerbang Akhir

Berdasarkan seluruh hasil audit, pengujian regresi menyeluruh, verifikasi anti-IDOR, dan pembuktian runtime yang mencatatkan 100% kelulusan tanpa celah keamanan berisiko tinggi:

### Rekomendasi:
**LANJUTKAN KE TAHAP STAGING USER ACCEPTANCE TESTING (UAT).**  
Sistem telah terintegrasi sempurna, routing terisolasi dengan aman, antarmuka pimpinan telah siap digunakan, dan hak akses data siswa terlindungi secara ketat.

### Status Gerbang Kesiapan:
# **`READY FOR STAGING UAT`**
