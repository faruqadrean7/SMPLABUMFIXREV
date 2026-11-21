# LAPORAN AUDIT KEPATUHAN SPESIFIKASI PORTAL DAN ANALISIS GAP ROUTING
**SISTLABUM (Portal Sekolah Satu Pintu SMP Laboratorium UM)**

- **Tanggal Audit:** 16 Oktober 2026
- **Status Repository:** Branch `develop` | HEAD `448a1a499d3c3a87aa61c3d28428c616dd8f0405`
- **Acuan Utama:** 
  1. Spesifikasi Kebutuhan Portal: `SPEC PORTAL SEKOLAH.md`
  2. Single Source of Truth: `13_MASTER_MODULES.md`
  3. Tata Kelola & Keamanan: `AGENTS.md`, `09_SECURITY_PRIVACY.md`
  4. Laporan Audit Sebelumnya: `docs/uat/FULL_ROUTING_AND_PERSONA_PORTAL_AUDIT_REPORT.md`, `docs/uat/SYSTEM_INTEGRATION_AND_ROUTING_AUDIT_REPORT.md`
- **Penyusun:** AI Engineering Agent (Antigravity)

---

## 1. Ringkasan Eksekutif Audit

Audit kepatuhan spesifikasi dan analisis gap routing ini dilakukan untuk memverifikasi tingkat kesesuaian antara dokumen kebutuhan **`SPEC PORTAL SEKOLAH.md`** dengan implementasi aktual pada basis kode `SISTLABUM` (`apps/web` dan `apps/api`), dengan tetap mengacu pada Single Source of Truth (SSOT) **`13_MASTER_MODULES.md`** dan panduan integritas **`AGENTS.md`**.

### Temuan Kunci:
1. **Pemisahan Shell Portal vs Admin:** 
   Sistem telah memisahkan secara fisik dan logis antara Dashboard Admin (`/admin/*`, 50 rute App Router) dan Portal Pengguna (`/portal/*`, 31 rute App Router).
2. **Perbedaan Prefix Rute (Naming Convention):**
   Dokumen `SPEC PORTAL SEKOLAH.md` mengharapkan rute root langsung per persona: `/guru`, `/siswa`, `/ortu`, `/staf`, `/kepsek`, dan `/mitra`. 
   Implementasi aktual pada Next.js App Router menggunakan route group `(portal)` dengan namespace kanonis `/portal/guru/*`, `/portal/siswa/*`, `/portal/ortu/*`, dan `/portal/staf/*`.
3. **Ketersediaan Portal Persona:**
   - **Guru (`teacher`, `homeroom`)**: **LAYAK & BERFUNGSI** (13 rute di `/portal/guru/*`).
   - **Siswa (`student`)**: **LAYAK & BERFUNGSI** (10 rute di `/portal/siswa/*`).
   - **Orang Tua (`guardian`)**: **LAYAK & BERFUNGSI** (3 rute di `/portal/ortu/*` dengan multi-child selector dan anti-IDOR).
   - **Staf (`facility_staff`, `librarian`, `admin_tu`)**: **BERSIFAT HIBRIDA**. Layanan mandiri karyawan (tiket, absensi, cuti, slip gaji) ada di `/portal/staf/*` (5 rute), sedangkan tugas operasional fasilitas, perpustakaan, dan administrasi diselenggarakan melalui modul Admin Shell terkait. Sub-peran laboran, petugas UKS, dan satpam belum memiliki portal mandiri.
   - **Pimpinan (`principal`, `vice_principal`)**: **BELUM ADA DEDICATED PORTAL** `/kepsek`. Saat ini pimpinan diarahkan ke modul eksekutif di Admin Shell (`/admin/executive`, `/admin/executive/approvals`, `/admin/reports`).
   - **Mitra (`committee`, `supervisor`)**: **BELUM ADA DEDICATED PORTAL** `/mitra`. Akses yang tersedia bersifat read-only di `/admin/reports` dengan anonimisasi data pribadi.
4. **Pencegahan Misrouting Admin:**
   Bug historis di mana akun guru dan homeroom terlempar ke layout `/admin` telah diverifikasi dan diperbaiki tuntas di `apps/web/src/lib/rbac/evaluator.ts` dan `apps/web/src/app/(admin)/admin/layout.tsx`. Seluruh evaluasi otorisasi dan navigasi kini 100% lulus uji tipe (*typecheck*) dan uji regresi (757 tes frontend lulus).

---

## 2. Commit dan Kondisi Repository yang Diperiksa

Audit dan verifikasi runtime dilakukan secara langsung terhadap lingkungan repository lokal:

- **Repository Root:** `/Users/macbookprom12020/Desktop/Project/SISTLABUM`
- **Active Branch:** `develop`
- **HEAD Commit:** `448a1a499d3c3a87aa61c3d28428c616dd8f0405`
- **Remote Tracking:** `origin/develop` (Sinkron pada commit `448a1a499d3c3a87aa61c3d28428c616dd8f0405`)
- **Status Working Tree (Lokal Terjaga):**
  - Perbaikan defensif unwrap API dan array guard pada portal:
    - `apps/web/src/features/portal/portal-api.ts`
    - `apps/web/src/features/portal/guru/guru-api.ts`
    - `apps/web/src/features/portal/staf/staf-api.ts`
    - `apps/web/src/features/portal/shared/shared-portal-api.ts`
    - `apps/web/src/app/(portal)/portal/guru/page.tsx`
    - `apps/web/src/app/(portal)/portal/guru/jadwal/page.tsx`
    - `apps/web/src/app/(portal)/portal/guru/tugas/[id]/penilaian/page.tsx`
    - `apps/web/src/app/(portal)/portal/siswa/page.tsx`
    - `apps/web/src/app/(portal)/portal/siswa/jadwal/page.tsx`
    - `apps/web/src/app/(portal)/portal/siswa/tugas/page.tsx`
    - `apps/web/src/app/(portal)/portal/siswa/poin/page.tsx`
    - `apps/web/src/app/(portal)/portal/staf/tiket/page.tsx`
  - Perbaikan evaluator RBAC dan perlindungan layout admin:
    - `apps/web/src/lib/rbac/evaluator.ts`
    - `apps/web/src/app/(admin)/admin/layout.tsx`
    - `apps/web/src/test/evaluator.test.ts`
    - `apps/web/src/test/cross-role-matrix.test.ts`
- **Runtime Stack Terverifikasi:**
  - Backend: PHP 8.3 / Laravel 11 (Aktif melayani di port 8000)
  - Frontend: Node.js / Next.js 15.1.0 App Router (Aktif melayani di port 3000)
  - Database: MySQL `sistlabum_db` (44 migrasi ran)

---

## 3. Matriks Persona, Role, Prefix Spesifikasi vs Prefix Aktual

Tabel berikut memetakan setiap persona yang didefinisikan dalam `SPEC PORTAL SEKOLAH.md` terhadap implementasi aktual di `SISTLABUM`:

| Persona | Role Aktual Sistem | Prefix Spesifikasi | Prefix Aktual | Dashboard Tersedia | Fitur Inti Tersedia | API Terhubung | Status Kelayakan | Gap Teridentifikasi |
|---|---|---|---|---|---|---|---|---|
| **Guru** | `teacher`, `homeroom` | `/guru` | `/portal/guru` | Ya (`/portal/guru`) | Ya (13 fitur KBM, tugas, absensi, nilai, CBT) | Ya (`/api/v1/portal/teacher/*`, `/api/v1/academic/*`) | **LAYAK & AKTIF** | Booking Lab, Perpus, dan Lapor Sarpras mandiri belum ada di portal guru (masih terpusat di modul admin). |
| **Guru BK** | `counselor` (atau role `teacher` + permission BK) | `/guru/bk/*` | `/portal/siswa/konseling` (sisi siswa) & `/admin/counseling` (sisi konselor) | Parsial (di Admin BK) | Ya (Konseling, pelanggaran, poin karakter) | Ya (`/api/v1/academic/counseling/*`) | **PARSIAL / DI ADMIN** | Belum ada sub-menu `/portal/guru/bk/*` khusus; modul BK dipisahkan di `/admin/counseling` demi kerahasiaan data. |
| **Siswa** | `student` | `/siswa` | `/portal/siswa` | Ya (`/portal/siswa`) | Ya (Jadwal, tugas, nilai, CBT, presensi, poin) | Ya (`/api/v1/portal/student/*`, `/api/v1/academic/*`) | **LAYAK & AKTIF** | Tagihan/SPP (`/siswa/tagihan`) **ditunda** sesuai SSOT Modul 06. Peminjaman perpus mandiri belum ada. |
| **Orang Tua** | `guardian` | `/ortu` | `/portal/ortu` | Ya (`/portal/ortu`) | Ya (Switch anak, presensi, nilai, e-rapor, P3) | Ya (`/api/v1/portal/guardian/*`) | **LAYAK & AKTIF** | Pengajuan izin online (`/ortu/izin`) dialihkan ke komunikasi wali kelas. Tagihan/SPP ditunda. Ringkasan UKS belum ada. |
| **Staf** | `facility_staff`, `librarian`, `admin_tu` | `/staf` | `/portal/staf` (Self-service) & `/admin/*` (Operasional) | Ya (`/portal/staf`) | Ya (Tiket tugas, presensi, cuti, slip gaji karyawan) | Ya (`/api/v1/portal/staff/*`) | **HIBRIDA (PARSIAL)** | Sub-peran laboran, petugas UKS, dan satpam belum memiliki portal mandiri `/staf/*`. Tugas operasional perpus/sarpras ada di admin shell. |
| **Pimpinan** | `principal`, `vice_principal` | `/kepsek` | `/admin/executive` & `/admin/reports` | Tidak ada di `/kepsek` | Ya (Executive metrics, approval surat/kegiatan, rekap) | Ya (`/api/v1/reports/*`, `/api/v1/executive/*`) | **BELUM ADA DEDICATED PORTAL** | Tidak ada rute `/kepsek`. Akses dilayani melalui Admin Shell dengan role pembaca eksekutif. |
| **Mitra** | `committee`, `supervisor` | `/mitra` | `/admin/reports` | Tidak ada di `/mitra` | Parsial (Laporan rekapitulasi, anonim UU PDP) | Ya (`/api/v1/reports/*`) | **BELUM ADA DEDICATED PORTAL** | Tidak ada rute `/mitra`. Tidak ada modul agenda rapat atau pengumuman mitra mandiri. |
| **Admin** | `admin`, `super_admin` | `/admin` | `/admin` | Ya (`/admin`) | Ya (Kelola user, master data, konfigurasi) | Ya (`/api/v1/*`) | **LENGKAP (SSOT)** | Sesuai spesifikasi: back-office pengelola sistem tidak diubah. |

---

## 4. Status Seluruh Portal dan Fitur Utamanya

### 4.1 Portal Guru
- **Rute yang Tersedia (13 Halaman di `apps/web/src/app/(portal)/portal/guru`):**
  1. `/portal/guru` — Dashboard utama guru (ringkasan kelas, jadwal harian, aksi cepat).
  2. `/portal/guru/jadwal` — Jadwal mengajar mingguan dan kalender akademik.
  3. `/portal/guru/absensi` — Pengelolaan presensi sesi kelas dan rekapitulasi siswa.
  4. `/portal/guru/jurnal` — Pengisian jurnal mengajar harian KBM.
  5. `/portal/guru/nilai` — Input nilai formatif, sumatif, dan rekapitulasi capaian kompetensi.
  6. `/portal/guru/tugas` — Manajemen materi/bahan ajar dan penugasan siswa.
  7. `/portal/guru/tugas/buat` — Formulir pembuatan penugasan baru.
  8. `/portal/guru/tugas/[id]/penilaian` — SpeedGrader untuk pemeriksaan dan umpan balik tugas siswa.
  9. `/portal/guru/cbt` — Manajemen bank soal dan monitoring pengawasan ujian CBT real-time.
  10. `/portal/guru/cuti` — Pengajuan dan pemantauan permohonan cuti guru.
  11. `/portal/guru/presensi` — Presensi mandiri kehadiran kerja guru di sekolah.
  12. `/portal/guru/slip-gaji` — Riwayat dan slip gaji bulanan guru.
  13. `/portal/guru/wali-kelas` — Dasbor khusus wali kelas (rekap catatan siswa kelas binaan).
- **Kesesuaian vs Spesifikasi:**
  - Fitur inti akademik, KBM, penilaian, dan CBT **100% terpenuhi**.
  - **Gap:** Modul booking lab mandiri (`/guru/lab/booking`), peminjaman perpustakaan (`/guru/perpus`), dan pelaporan kerusakan fisik (`/guru/sarpras/lapor`) belum dibuat sebagai sub-halaman portal guru. Saat ini guru yang membutuhkan pelaporan sarpras dilayani melalui pembuatan tiket umum di portal staf atau oleh staf TU di admin.

### 4.2 Portal Siswa
- **Rute yang Tersedia (10 Halaman di `apps/web/src/app/(portal)/portal/siswa`):**
  1. `/portal/siswa` — Dashboard siswa (jadwal hari ini, pengumuman, status tugas, ranking poin).
  2. `/portal/siswa/jadwal` — Jadwal pelajaran kelas mingguan.
  3. `/portal/siswa/tugas` — Daftar tugas aktif, tenggat waktu, dan riwayat tugas.
  4. `/portal/siswa/tugas/[id]` — Detail instruksi tugas dan unggah lembar jawaban siswa.
  5. `/portal/siswa/nilai` — Transkrip nilai mata pelajaran dan capaian kompetensi.
  6. `/portal/siswa/cbt` — Ruang ujian CBT daring (kunci browser, token, anti-cheat).
  7. `/portal/siswa/konseling` — Formulir pengajuan permohonan konseling privat ke Guru BK.
  8. `/portal/siswa/poin` — Buku saku digital siswa (poin pelanggaran dan poin prestasi).
  9. `/portal/siswa/presensi` — Riwayat kehadiran siswa di setiap mata pelajaran.
  10. `/portal/siswa/profil` — Profil biodata dan informasi kesiswaan.
- **Kesesuaian vs Spesifikasi:**
  - Fitur pembelajaran mandiri, tugas, nilai, CBT, dan konseling **terpenuhi dengan baik**.
  - **Gap vs Spesifikasi:** Menu `/siswa/tagihan` **tidak diimplementasikan** karena Modul 06 (Finance/SPP) berstatus **DITUNDA** berdasarkan SSOT `13_MASTER_MODULES.md`. Menu `/siswa/perpus` (katalog dan pinjam buku) belum tersedia di portal siswa.

### 4.3 Portal Orang Tua
- **Rute yang Tersedia (3 Halaman di `apps/web/src/app/(portal)/portal/ortu`):**
  1. `/portal/ortu` — Dasbor utama orang tua dengan pemilih anak (*child selector*), rekap presensi harian, nilai rata-rata, dan catatan karakter Profil Pelajar Pancasila (P3).
  2. `/portal/ortu/akademik` — Detail riwayat akademik anak, transkrip nilai per semester, catatan guru, dan unduh E-Rapor bertanda tangan digital.
  3. `/portal/ortu/presensi` — Pemantauan riwayat kehadiran dan keterlambatan anak.
- **Kesesuaian vs Spesifikasi:**
  - Verifikasi isolasi data keluarga (Anti-IDOR) berjalan ketat di backend (`GuardianStudentAccessPolicy` & `GuardianChildGuard`). Orang tua hanya dapat melihat anak yang terdaftar secara sah pada tabel relasi orang tua-siswa.
  - **Gap vs Spesifikasi:** 
    - Spesifikasi memecah rute per anak dengan pola `/ortu/anak/:id/*`. Implementasi aktual menggunakan *Stateful Child Selector* pada rute `/portal/ortu` dan `/portal/ortu/akademik?childId=:id`, yang secara fungsional setara dan lebih mulus bagi pengguna ponsel.
    - Pengajuan izin tidak masuk online (`/ortu/izin`) belum memiliki form khusus di portal (masih via komunikasi langsung wali kelas).
    - Modul tagihan/SPP (`/ortu/anak/:id/tagihan`) ditunda per SSOT.
    - Ringkasan kesehatan UKS anak belum tersedia di portal.

### 4.4 Portal Staf
- **Rute yang Tersedia (5 Halaman di `apps/web/src/app/(portal)/portal/staf`):**
  1. `/portal/staf` — Dasbor staf (ringkasan tiket penugasan, status kehadiran kerja).
  2. `/portal/staf/tiket` — Pengelolaan tiket kerja operasional yang ditugaskan kepada staf.
  3. `/portal/staf/presensi` — Catat kehadiran mandiri kerja staf.
  4. `/portal/staf/cuti` — Pengajuan izin dan cuti kerja staf.
  5. `/portal/staf/slip-gaji` — Riwayat dan slip gaji bulanan staf.
- **Kesesuaian vs Spesifikasi:**
  - Portal staf yang ada saat ini berfungsi sebagai *Staff Self-Service Portal* (layanan personal karyawan).
  - **Gap vs Spesifikasi:** Spesifikasi merinci portal staf berbasis sub-peran teknis: Laboran (`/staf/lab/*`), Petugas UKS (`/staf/uks/*`), Pustakawan (`/staf/perpus/*`), Petugas Sarpras (`/staf/sarpras/*`), Satpam (`/staf/satpam/*`), dan TU (`/staf/tu/*`).
  - Dalam arsitektur eksisting, tugas operasional pustakawan dikerjakan di `/admin/library`, sarpras di `/admin/facilities`, dan TU di `/admin/users` & `/admin/master`. Sedangkan peran petugas UKS dan Satpam belum memiliki modul tersendiri baik di portal maupun di admin.

### 4.5 Portal Pimpinan (`/kepsek`)
- **Status:** **BELUM TERSEDIA SEBAGAI DEDICATED PORTAL.**
- **Implementasi Saat Ini:** Kepala Sekolah (`principal`) dan Wakil Kepala Sekolah (`vice_principal`) menggunakan shell Admin dengan izin khusus yang dibatasi:
  - `/admin/executive` — Dasbor eksekutif dan KPI sekolah.
  - `/admin/executive/approvals` — Antrean persetujuan surat dan permohonan kegiatan.
  - `/admin/reports` — Laporan analitik akademik, presensi, dan kinerja sekolah.
- **Gap vs Spesifikasi:** Belum ada layout khusus, menu sidebar terpisah, atau prefix rute `/kepsek`.

### 4.6 Portal Mitra (`/mitra`)
- **Status:** **BELUM TERSEDIA SEBAGAI DEDICATED PORTAL.**
- **Implementasi Saat Ini:** Komite Sekolah (`committee`) dan Pengawas (`supervisor`) diklasifikasikan sebagai *read-only roles* (`isReadOnlyRole`) di `apps/web/src/lib/rbac/evaluator.ts`. Mereka hanya memiliki hak akses baca terbatas pada `/admin/reports` dengan kepatuhan sensor data pribadi (UU PDP).
- **Gap vs Spesifikasi:** Belum ada portal `/mitra` dengan modul pengumuman khusus, agenda rapat komite, atau repositori dokumen sekolah.

---

## 5. Daftar Persona yang Portalnya Belum Ada atau Belum Layak

| Persona | Status Portal | Deskripsi Kondisi Terkini | Rekomendasi Tindak Lanjut |
|---|---|---|---|
| **Pimpinan (Kepala Sekolah & Wakasek)** | **Belum Ada Portal Mandiri** | Saat ini dilayani melalui Admin Shell (`/admin/executive`). Fungsionalitas pemantauan dan persetujuan sudah berjalan di backend, namun antarmukanya masih menyatu dengan shell admin. | Membutuhkan keputusan pemilik proyek: apakah perlu dibangun `/portal/kepsek` tersendiri, atau cukup mempertahankan modul eksekutif di dalam shell admin. |
| **Mitra (Komite & Pengawas)** | **Belum Ada Portal Mandiri** | Tidak ada rute portal `/mitra`. Akses sangat terbatas di `/admin/reports`. | Sesuai `SPEC PORTAL SEKOLAH.md`, portal mitra bersifat opsional di fase akhir. Direkomendasikan untuk ditunda sampai fase staging selesai. |
| **Sub-Peran Staf: Petugas UKS** | **Belum Ada Modul** | Tidak ada rute kunjungan UKS, rekam medis sederhana, atau stok obat di portal staf maupun admin. | Jika sekolah membutuhkan pencatatan UKS digital, modul ini harus dirancang dari tingkat backend dan migrasi database baru. |
| **Sub-Peran Staf: Satpam** | **Belum Ada Modul** | Tidak ada rute buku tamu satpam, izin keluar gerbang, atau log barang. | Saat ini buku tamu dan izin gerbang masih dikelola secara manual/offline oleh satpam sekolah. |
| **Sub-Peran Staf: Laboran** | **Parsial** | Inventaris lab dikelola bersama sarpras di `/admin/facilities`. Belum ada jadwal booking khusus lab di `/portal/staf/lab`. | Dapat diintegrasikan ke dalam modul fasilitas/sarpras tanpa perlu membuat portal baru. |

---

## 6. Daftar Perbedaan Spesifikasi dengan SSOT dan Implementasi

Berikut adalah perbedaan substansial antara dokumen `SPEC PORTAL SEKOLAH.md` dengan SSOT `13_MASTER_MODULES.md` serta implementasi kode saat ini:

### 6.1 Status Modul Keuangan / Pembayaran SPP
- **Dokumen Spesifikasi:** Meminta modul keuangan pada semua portal: `/siswa/tagihan`, `/ortu/anak/:id/tagihan`, `/staf/tu/*`, dan `/kepsek/keuangan`.
- **SSOT `13_MASTER_MODULES.md` (Modul 06):** Menetapkan secara eksplisit bahwa **Modul Keuangan / Tagihan SPP berstatus DITUNDA (DEFERRED)** dan tidak boleh diaktifkan atau diekspos ke antarmuka sebelum ada persetujuan formal dari yayasan/sekolah.
- **Implementasi:** Seluruh rute keuangan dinonaktifkan di frontend. Penundaan ini wajib dipertahankan sesuai SSOT.

### 6.2 Kerahasiaan Catatan Konseling BK dan Kepatuhan Privasi
- **Dokumen Spesifikasi:** Menyebutkan rute `/guru/bk/*` berada di dalam portal guru.
- **SSOT `09_SECURITY_PRIVACY.md` & `13_MASTER_MODULES.md`:** Mengharuskan catatan konseling BK diisolasi secara mutlak dengan enkripsi dan *strict policy*. Guru mata pelajaran biasa dan wali kelas dilarang keras membaca catatan konseling siswa.
- **Implementasi:** Konseling BK dipisahkan ke modul khusus dengan controller dan policy tersendiri (`CounselingRecordPolicy`). Siswa memiliki rute pengajuan di `/portal/siswa/konseling`, sedangkan guru BK/konselor mengelolanya melalui antarmuka khusus di admin yang dilindungi permission `counseling:manage`. Ini mencegah kebocoran data sensitif antar guru.

### 6.3 Pemisahan Portal vs Admin Shell untuk Staf Operasional
- **Dokumen Spesifikasi:** Mengasumsikan staf operasional (pustakawan, sarpras, TU) bekerja di dalam sub-rute `/staf/*` dan bukan di `/admin/*`.
- **Implementasi Eksisting:** Sistem membedakan antara **kegiatan operasional institusi** (katalogisasi buku di perpus, pencatatan aset inventaris, manajemen user) yang berada di shell Admin dengan hak akses berbasis RBAC, dan **kegiatan ketenagakerjaan mandiri** (presensi staf, tiket tugas, cuti, slip gaji) yang berada di `/portal/staf`.

### 6.4 Penanganan Multi-Role dan Role Switcher
- **Dokumen Spesifikasi:** Meminta akun dengan multi-peran dasar (misal Guru yang juga menjadi Orang Tua murid) memiliki *role switcher* atau pemilih konteks.
- **Implementasi Eksisting:** Model autentikasi backend saat ini mendukung multi-role pada tabel relasi, namun evaluator frontend memetakan pengguna ke peran operasional berprioritas tertinggi secara deterministik. Pengguna yang memiliki peran guru dan orang tua saat ini diarahkan ke `/portal/guru`. Belum ada tombol dropdown manual di header untuk beralih konteks sesi antara Guru dan Orang Tua tanpa relogin.

---

## 7. Temuan Routing dan Pencegahan Salah Masuk Admin

### 7.1 Analisis Akar Masalah Historis
Pada audit awal, ditemukan bahwa pengguna dengan peran `teacher` atau `homeroom` yang mencoba membuka halaman tertentu terlempar ke shell Admin (`/admin`). Investigasi mendalam menemukan akar penyebabnya pada:
1. `apps/web/src/lib/rbac/evaluator.ts`: Konstanta `ADMIN_SHELL_ROLES` secara keliru memasukkan `'teacher'` dan `'homeroom'`. Hal ini menyebabkan fungsi `isAdminShellRole(role)` mengembalikan `true` untuk guru.
2. `apps/web/src/app/(admin)/admin/layout.tsx`: Layout admin tidak melakukan redirect protektif ke portal persona jika pengguna yang terautentikasi adalah peran non-admin.

### 7.2 Perbaikan yang Telah Diterapkan dan Terverifikasi
Perbaikan telah dilakukan dan diverifikasi tanpa merusak dependensi:
1. **Pembersihan `ADMIN_SHELL_ROLES`:** Hanya memuat peran back-office institusi:
   ```typescript
   export const ADMIN_SHELL_ROLES = [
     'super_admin',
     'admin',
     'principal',
     'vice_principal',
     'facility_staff',
     'librarian',
     'admin_tu',
     'committee',
     'supervisor',
   ] as const;
   ```
2. **Konsolidasi `resolveUserDestination`:**
   Memetakan pengguna ke portal yang semestinya:
   - `teacher`, `homeroom` $\rightarrow$ `/portal/guru`
   - `student` $\rightarrow$ `/portal/siswa`
   - `guardian` $\rightarrow$ `/portal/ortu`
   - `facility_staff`, `librarian`, `admin_tu` $\rightarrow$ `/portal/staf`
   - `admin`, `super_admin`, `principal`, `vice_principal`, `committee`, `supervisor` $\rightarrow$ `/admin`
3. **Pemasangan Guard Defensif pada `AdminLayout`:**
   Jika pengguna dengan peran guru, siswa, atau ortu mencoba mengakses rute `/admin/*`, `AdminLayout` secara otomatis mengalihkannya ke rute tujuan portal yang valid melalui `router.replace(resolveUserDestination(user.role))`.
4. **Pencegahan Error Runtime:**
   Semua pemanggilan API jadwal, tugas, poin, dan tiket telah diproteksi dengan unwrap terstandarisasi (`Array.isArray(data) ? data : data?.data || []`), sehingga tidak ada lagi insiden unhandled exception `TypeError: .filter is not a function` yang merusak rendering antarmuka portal.

---

## 8. Temuan Role Switcher dan Multi-Role

1. **Resolusi Otomatis Deterministik:**
   Ketika satu akun pengguna memiliki lebih dari satu peran di array `user.roles`:
   - Sistem memprioritaskan peran spesifik: jika akun memiliki role `teacher` dan `homeroom`, sistem memprioritaskan dashboard guru dengan hak akses wali kelas aktif (`/portal/guru`).
   - Jika akun staf memiliki beberapa hak modul, seluruh hak akses digabungkan (*union of permissions*) di level frontend dan backend.
2. **Keterbatasan Saat Ini:**
   Jika sebuah akun memegang dua persona yang portalnya terpisah (misalnya seorang Guru yang anak kandungnya bersekolah di SMP Laboratorium UM sehingga juga berstatus Wali Murid/`guardian`), sistem saat ini akan menguncinya pada `/portal/guru`. 
   Belum tersedia antarmuka *context switcher* di navbar yang memungkinkan akun tersebut berpindah ke tampilan `/portal/ortu` tanpa menggunakan akun terpisah.

---

## 9. Hasil Pengujian Aktual

Seluruh pengujian teknis telah dijalankan ulang secara lokal dan mencatatkan hasil **100% LULUS**:

### 9.1 Frontend Typecheck
- **Perintah:** `npm run typecheck` (di `apps/web`)
- **Hasil:** **0 Errors.** Seluruh definisi tipe TypeScript, kontrak properti halaman portal, dan evaluator RBAC valid.

### 9.2 Frontend Unit & Integration Tests (Vitest)
- **Perintah:** `npm run test` (di `apps/web`)
- **Hasil:**
  - **Test Files:** 64 passed (100%)
  - **Tests:** 757 passed (100%)
  - **Durasi:** 23.47 detik
  - Pengujian mencakup otorisasi rute, role evaluator, layout portal, child selector anti-IDOR, dan rendering seluruh halaman portal.

### 9.3 Backend Otorisasi & Autentikasi Tests (PHPUnit)
- **Perintah:** `php artisan test --filter=Auth` (di `apps/api`)
- **Hasil:**
  - **Test Files:** 2 passed
  - **Tests:** 99 passed (100%)
  - **Assertions:** 627 assertions
  - Pengujian mencakup login, JWT token issuance, multi-role assignment, refresh token, route authorization, dan IDOR protection.

---

## 10. Rekomendasi Fase Implementasi Berikutnya

Berdasarkan hasil audit, arsitektur portal saat ini sudah sangat solid untuk kebutuhan kegiatan belajar mengajar (KBM) utama. Tahapan pengembangan yang direkomendasikan adalah:

- **Fase Segera (Staging UAT):**
  Lanjutkan pengujian UAT dengan rute portal saat ini (`/portal/guru`, `/portal/siswa`, `/portal/ortu`, `/portal/staf`). Fungsionalitas inti telah siap diuji oleh pengguna nyata.
- **Fase Penyempurnaan Fitur Mandiri Guru & Siswa:**
  Menambahkan widget pelaporan sarpras mandiri dan peminjaman buku perpustakaan langsung di dalam portal guru dan siswa.
- **Fase Keputusan Arsitektur Portal Pimpinan:**
  Menentukan apakah Pimpinan memerlukan layout mandiri (`/portal/kepsek`) atau tetap dipertahankan di modul eksekutif `/admin/executive`.

---

## 11. KEPUTUSAN YANG DIBUTUHKAN DARI PEMILIK PROYEK

Bagian ini menyajikan secara berimbang keputusan-keputusan strategis yang membutuhkan arahan dan persetujuan dari Pemilik Proyek sebelum pekerjaan konstruksi kode lebih lanjut dilakukan:

### Pertanyaan 1: Kebijakan Prefix Rute Portal
Dokumen spesifikasi (`SPEC PORTAL SEKOLAH.md`) mengusulkan rute root: `/guru`, `/siswa`, `/ortu`, `/staf`, `/kepsek`, `/mitra`. 
Sementara itu, kode yang sudah berjalan mengelompokkan portal di bawah route group App Router dengan prefix: `/portal/guru`, `/portal/siswa`, `/portal/ortu`, `/portal/staf`.

*Opsi Keputusan:*
- **Opsi A (Pertahankan `/portal/*`):** Mempertahankan struktur `/portal/guru`, `/portal/siswa`, `/portal/ortu`, `/portal/staf` sebagai rute kanonis. Keuntungan: tidak ada risiko regresi, seluruh pengujian otomatis tetap hijau, dan struktur App Router Next.js sangat rapi.
- **Opsi B (Gunakan Next.js Rewrites / Redirects):** Mengonfigurasi `next.config.js` sehingga URL ramah pengguna `/guru` secara transparan mengarah ke `/portal/guru` (dan seterusnya). Keuntungan: memenuhi spesifikasi visual pengguna tanpa harus merombak struktur direktori kode.
- **Opsi C (Migrasi Total Struktur Direktori ke Root):** Memindahkan folder halaman dari `app/(portal)/portal/*` langsung ke `app/(portal)/*`. Konsekuensi: membutuhkan pembaruan pada seluruh link navigasi internal, pengujian frontend, dan dokumentasi API.

### Pertanyaan 2: Pembangunan Portal Pimpinan (`/kepsek`)
Saat ini Kepala Sekolah dan Wakasek menggunakan modul eksekutif di Admin Shell (`/admin/executive`, `/admin/executive/approvals`, `/admin/reports`) dengan izin baca dan persetujuan dokumen.

*Opsi Keputusan:*
- **Opsi A (Pertahankan di Admin Executive):** Tetap mengarahkan pimpinan ke `/admin/executive` karena mereka adalah manajer institusi yang membutuhkan akses menyeluruh ke laporan data master dan persetujuan surat sekolah.
- **Opsi B (Bangun Portal Khusus Pimpinan di `/portal/kepsek`):** Membuat layout dan dasbor terpisah khusus kepala sekolah yang hanya menampilkan ringkasan visual tingkat tinggi dan antrean tanda tangan dokumen.

### Pertanyaan 3: Kebutuhan Portal Mitra (`/mitra`)
Spesifikasi mencantumkan portal mitra untuk Komite Sekolah dan Pengawas sebagai fitur opsional di fase akhir.

*Opsi Keputusan:*
- **Opsi A (Tunda Sesuai Spesifikasi):** Tidak membangun portal mitra pada fase ini. Komite dan Pengawas tetap menggunakan akses laporan read-only yang sudah tersedia di `/admin/reports`.
- **Opsi B (Bangun Portal Sederhana Mitra):** Membuat rute baru `/portal/mitra` berisi agenda komite, pengumuman sekolah, dan unduhan laporan publik yang telah disensor dari data pribadi siswa.

### Pertanyaan 4: Cakupan Portal Staf dan Kebutuhan Sub-Modul Khusus
Spesifikasi mengharapkan modul tersendiri untuk Laboran, Petugas UKS, Satpam, Pustakawan, dan TU. Saat ini sistem baru menyediakan modul tiket tugas karyawan di `/portal/staf` dan modul operasional terpusat di `/admin`.

*Opsi Keputusan:*
- **Opsi A (Pertahankan Model Hibrida Saat Ini):** Staf operasional (Perpus, Sarpras, TU) tetap menggunakan modul terkelola di Admin Shell dengan izin RBAC masing-masing, sedangkan `/portal/staf` difokuskan untuk kebutuhan ketenagakerjaan mandiri (absensi, cuti, slip gaji).
- **Opsi B (Kembangkan Sub-Modul Baru di Portal Staf):** Membangun antarmuka khusus di portal staf untuk modul yang belum ada, yaitu UKS digital dan buku tamu Satpam (memerlukan penambahan tabel dan migrasi database baru).
