# LAPORAN IMPLEMENTASI KEPUTUSAN PORTAL & ROUTING SISTLABUM

- **Tanggal Pelaksanaan:** 16 Oktober 2026
- **Branch:** `develop` | HEAD: `448a1a499d3c3a87aa61c3d28428c616dd8f0405`
- **Acuan Utama:** 
  1. Petunjuk Keputusan Pemilik Proyek (User Request)
  2. Single Source of Truth: `13_MASTER_MODULES.md`
  3. Tata Kelola & Keamanan: `AGENTS.md`, `09_SECURITY_PRIVACY.md`
  4. Laporan Kepatuhan & Gap: `docs/uat/PORTAL_SPEC_COMPLIANCE_AND_ROUTING_GAP_REPORT.md`
- **Status Akhir:** **`IMPLEMENTATION VERIFIED`**

---

## 1. Keputusan yang Diterapkan dan yang Ditunda

Berdasarkan instruksi dan keputusan resmi Pemilik Proyek, berikut adalah rangkuman keputusan yang telah diimplementasikan dan item yang ditunda secara terukur:

### A. Keputusan yang Diterapkan:
1. **Pertahankan Rute Kanonis & Pasang URL Aliases (Decision A):**
   - Rute kanonis `/portal/guru`, `/portal/siswa`, `/portal/ortu`, dan `/portal/staf` dipertahankan sepenuhnya tanpa perombakan direktori fisik.
   - Mengonfigurasi mekanisme Next.js `redirects` (HTTP 307 temporary redirect) di `apps/web/next.config.mjs` untuk URL alias spesifikasi:
     - `/guru` & `/guru/:path*` $\rightarrow$ `/portal/guru/:path*`
     - `/siswa` & `/siswa/:path*` $\rightarrow$ `/portal/siswa/:path*`
     - `/ortu` & `/ortu/:path*` $\rightarrow$ `/portal/ortu/:path*`
     - `/staf` & `/staf/:path*` $\rightarrow$ `/portal/staf/:path*`
     - `/kepsek` & `/kepsek/:path*` $\rightarrow$ `/portal/kepsek/:path*`
   - Terbukti: Preservasi parameter query string berjalan otomatis, tidak terjadi redirect loop, dan evaluasi otorisasi `RoleGuard` tetap berjalan pada rute kanonis.
2. **Implementasi Portal Pimpinan `/portal/kepsek` (Decision B):**
   - Membangun dedicated portal pimpinan di `apps/web/src/app/(portal)/portal/kepsek/` dengan layout dan navigasi khusus pimpinan.
   - Menyediakan Dashboard KPI Eksekutif Pimpinan (`/portal/kepsek`) dan Antrean Persetujuan Resmi Pimpinan (`/portal/kepsek/approvals`).
   - Melindungi rute dengan `RoleGuard(portal='kepsek')` khusus untuk role `principal` dan `vice_principal`.
   - Mengintegrasikan identitas `PortalShell` (judul "Portal Pimpinan", ikon `Building`, tab navigasi eksekutif).
3. **Pemberhentian Akses Otomatis Admin untuk Mitra (Decision C):**
   - Role `committee` dan `supervisor` dikeluarkan dari `ADMIN_SHELL_ROLES`.
   - `resolveUserDestination` tidak lagi mengarahkan role mitra ke `/admin`. Mereka diarahkan secara aman ke hub `/portal`.
   - Halaman `/portal` kini menyediakan tampilan aman (*safe notice*) yang menjelaskan bahwa Portal Mitra sedang dalam persiapan rilis (backlog) tanpa memberikan akses operasional harian back-office.
   - URL alias `/mitra` **tidak dibuat** karena portal mitra belum dibangun.
4. **Pertahankan Model Hibrida Portal Staf (Decision D):**
   - `/portal/staf` tetap dipertahankan untuk layanan mandiri karyawan (presensi kerja, cuti, slip gaji, dan tiket penugasan).
   - Hak operasional back-office bagi `librarian`, `facility_staff`, dan `admin_tu` tetap dilayani melalui modul terkait di Admin Shell sesuai permission eksplisit.
   - Tidak ada penambahan modul atau tabel fiktif untuk UKS dan Satpam.
5. **Penataan Ulang Resolusi Login & Fallback:**
   - Menghapus fallback liar ke `/admin` atau ke `/portal/siswa`. Akun tanpa portal terdefinisi secara aman diarahkan ke `/portal`.

---

## 2. Daftar Rute Kanonis dan URL Aliases

| Rute Alias (Spesifikasi) | Rute Kanonis (Implementasi) | Mekanisme | Preservasi Query | Otorisasi & Guard | Status |
|---|---|---|---|---|---|
| `/guru` | `/portal/guru` | HTTP 307 Redirect | Ya | `RoleGuard(guru)`: `teacher`, `homeroom` | **AKTIF** |
| `/guru/:path*` | `/portal/guru/:path*` | HTTP 307 Redirect | Ya | `RoleGuard(guru)`: `teacher`, `homeroom` | **AKTIF** |
| `/siswa` | `/portal/siswa` | HTTP 307 Redirect | Ya | `RoleGuard(siswa)`: `student` | **AKTIF** |
| `/siswa/:path*` | `/portal/siswa/:path*` | HTTP 307 Redirect | Ya | `RoleGuard(siswa)`: `student` | **AKTIF** |
| `/ortu` | `/portal/ortu` | HTTP 307 Redirect | Ya | `RoleGuard(ortu)`: `guardian` | **AKTIF** |
| `/ortu/:path*` | `/portal/ortu/:path*` | HTTP 307 Redirect | Ya | `RoleGuard(ortu)`: `guardian` | **AKTIF** |
| `/staf` | `/portal/staf` | HTTP 307 Redirect | Ya | `RoleGuard(staf)`: `facility_staff`, `librarian`, `admin_tu` | **AKTIF** |
| `/staf/:path*` | `/portal/staf/:path*` | HTTP 307 Redirect | Ya | `RoleGuard(staf)`: `facility_staff`, `librarian`, `admin_tu` | **AKTIF** |
| `/kepsek` | `/portal/kepsek` | HTTP 307 Redirect | Ya | `RoleGuard(kepsek)`: `principal`, `vice_principal` | **AKTIF** |
| `/kepsek/:path*` | `/portal/kepsek/:path*` | HTTP 307 Redirect | Ya | `RoleGuard(kepsek)`: `principal`, `vice_principal` | **AKTIF** |
| `/mitra` | *(Belum Dibangun)* | *Tidak Ada Alias* | N/A | Backlog Fase Berikutnya | **DITUNDA** |

---

## 3. Status Portal Pimpinan dan Fitur yang Berfungsi

Portal pimpinan beralamat di `/portal/kepsek` dengan sub-rute persetujuan di `/portal/kepsek/approvals`. Seluruh fitur terhubung langsung ke API eksekutif yang sudah ada (`/api/v1/dashboard/executive`, `/api/v1/approvals/pending`, `/api/v1/approvals/:id/review`) tanpa menggunakan data tiruan (mock) palsu:

### 3.1 Fitur Dashboard KPI (`/portal/kepsek`)
- **Banner Identitas Dinamis:** Menampilkan sapaan nama pimpinan, gelar jabatan (`Kepala Sekolah` atau `Wakil Kepala Sekolah`), status tahun ajaran aktif, dan tombol aksi cepat menuju antrean persetujuan atau pusat laporan.
- **Kartu Indikator Kunci (KPI):**
  1. *Kehadiran Siswa Hari Ini* (`metrics.studentAttendanceRate%` dan total siswa aktif).
  2. *Kehadiran Tenaga Pendidik* (`metrics.teacherAttendanceRate%` dan total guru).
  3. *Peringatan Dini Siswa* (`metrics.activeEarlyWarningsCount` kasus atensi disiplin/akademik).
  4. *Tiket Sarpras / Helpdesk Terbuka* (`metrics.openTicketsCount` laporan kerusakan fisik).
- **Progres Capaian PPDB 2026:** Persentase pendaftar diterima terhadap target kuota (dengan visual progress bar real-time).
- **Capaian Akademik per Jenjang:** Grafik capaian nilai rata-rata Kelas 7, Kelas 8, dan Kelas 9.
- **Monitoring Insiden / Kejadian Sekolah:** Riwayat catatan insiden kedisiplinan terkini beserta tingkat keparahannya (*severity*).

### 3.2 Fitur Antrean Persetujuan (`/portal/kepsek/approvals`)
- **Daftar Berkas Pengajuan:** Menampilkan berkas pengesahan rapor semester, permohonan cuti tenaga pendidik, pembelian logistik sarpras, dan kurikulum.
- **Filter Status Cepat:** Tab filter interaktif (Semua, Menunggu Keputusan, Disetujui, Ditolak).
- **Aksi Persetujuan Resmi:** Tombol *Setujui Berkas* dengan pencatatan nama pimpinan peninjau dan stempel waktu (*timestamp*).
- **Aksi Penolakan Berkas:** Tombol *Tolak Berkas* yang membuka modal dialog wajib mengisi alasan penolakan tertulis (`rejectionReason`) sebagai transparansi tata kelola.

### 3.3 Navigasi & Perlindungan Guard
- Terdaftar di `PortalShell` dengan ikon `Building` dan navigasi tab: *Dashboard KPI*, *Antrean Persetujuan*, *Pusat Laporan*, dan *Notifikasi*.
- Terlindungi oleh `RoleGuard(portal='kepsek')`: Pengguna dengan role selain `principal` dan `vice_principal` (misalnya siswa, guru, wali murid, atau staf) akan langsung ditolak dengan pesan: *"Akses Portal Pimpinan Ditolak"*.

---

## 4. Matriks Role, Portal, Permission, dan Tujuan Login

Tabel ini merupakan Single Source of Truth terkini untuk resolusi login (`resolveUserDestination`) dan batas kewenangan shell:

| Role Kode | Persona Sekolah | Tujuan Login Utama | Rute Halaman | Shell yang Diizinkan | Keterangan Otorisasi |
|---|---|---|---|---|---|
| `student` | Siswa Aktif | `'siswa'` | `/portal/siswa` | `siswa` | Hanya data pribadi (jadwal, tugas, nilai, CBT, presensi). |
| `guardian` | Orang Tua / Wali Murid | `'ortu'` | `/portal/ortu` | `ortu` | Anti-IDOR: hanya data anak yang sah terhubung. |
| `teacher` | Guru Mata Pelajaran | `'guru'` | `/portal/guru` | `guru` | Presensi kelas, jurnal KBM, tugas, nilai mapel sendiri. |
| `homeroom` | Wali Kelas | `'guru'` | `/portal/guru` | `guru` | Menu tambahan wali kelas di `/portal/guru/wali-kelas`. |
| `principal` | Kepala Sekolah | `'kepsek'` | `/portal/kepsek` | `kepsek`, `admin`* | Dashboard KPI, persetujuan dokumen, laporan eksekutif. |
| `vice_principal` | Wakil Kepala Sekolah | `'kepsek'` | `/portal/kepsek` | `kepsek`, `admin`* | Dashboard KPI, persetujuan operasional/akademik. |
| `admin_tu` | Staf Tata Usaha | `'staf'` | `/portal/staf` | `staf`, `admin`* | Layanan mandiri di staf; kelola master & user di admin. |
| `facility_staff` | Staf Sarpras | `'staf'` | `/portal/staf` | `staf`, `admin`* | Tiket mandiri di staf; kelola aset/lab di admin facilities. |
| `librarian` | Pustakawan | `'staf'` | `/portal/staf` | `staf`, `admin`* | Layanan mandiri di staf; katalog & sirkulasi di admin perpus. |
| `super_admin` | Super Administrator | `'admin'` | `/admin` | `admin` (bypass semua shell) | Akses konfigurasi menyeluruh back-office. |
| `operator_cms` | Pengelola Konten Web | `'admin'` | `/admin` | `admin` | Hanya modul `/admin/cms`. |
| `ppdb_committee` | Panitia PPDB | `'admin'` | `/admin` | `admin` | Hanya modul `/admin/ppdb`. |
| `counselor` | Guru Bimbingan Konseling | `'admin'` | `/admin` | `admin` | Hanya modul terisolasi `/admin/counseling`. |
| `committee` | Komite Sekolah (Mitra) | `'portal'` | `/portal` | *Tidak otomatis ke Admin* | Safe notice; akses laporan hanya bila permission diberikan. |
| `supervisor` | Pengawas Dinas (Mitra) | `'portal'` | `/portal` | *Tidak otomatis ke Admin* | Safe notice; akses laporan hanya bila permission diberikan. |
| `guest` / Tak dikenal | Tamu / Role Kosong | `'portal'` | `/portal` | *Tidak ada* | Fallback aman ke portal hub dengan pesan penugasan. |

*\* Catatan Akses Admin untuk Pimpinan & Staf:* Akun pimpinan dan staf hanya dapat membuka modul spesifik di `/admin` yang sesuai dengan daftar izinnya (`getAdminRoutePermission`). Halaman master yang tidak diizinkan tetap memunculkan status `AccessDenied`.

---

## 5. Hasil Audit Role Staf dan Mitra

### 5.1 Audit Role Staf Operasional
- **Status Repository Aktual:** Ditemukan 3 role staf operasional di backend dan database:
  - `admin_tu` (Admin Tata Usaha)
  - `facility_staff` (Staf Sarana & Prasarana)
  - `librarian` (Pustakawan)
- **Role yang Disebut Spesifikasi Namun Belum Ada di Kode:**
  - `laboran` (Petugas Laboratorium): Saat ini diintegrasikan ke dalam penugasan sarpras / `facility_staff`.
  - `petugas_uks` (Petugas UKS): Belum memiliki definisi role, tabel, maupun izin di backend.
  - `satpam` (Petugas Keamanan): Belum memiliki definisi role, tabel, maupun izin di backend.
- **Kesimpulan:** Sesuai batasan instruksi, sistem tidak mengada-ada atau menambah migrasi/tabel baru. Kebutuhan pencatatan UKS dan buku tamu satpam dicatat sebagai item backlog.

### 5.2 Audit Role Mitra (Komite & Pengawas)
- **Status Otomasi Admin:** Sebelumnya, role `committee` dan `supervisor` terdaftar di `ADMIN_SHELL_ROLES` sehingga terlempar ke layout admin. Kondisi ini telah **dicabut sepenuhnya**.
- **Perlakuan Terkini:** Pengguna dengan role `committee` atau `supervisor` yang melakukan login akan diarahkan ke `/portal`. Halaman `/portal` mendeteksi bahwa peran mereka adalah mitra dan menyajikan pemberitahuan resmi bahwa portal mitra sedang dipersiapkan. Modul `/admin/reports` tidak dibuka secara otomatis tanpa hak akses eksplisit.

---

## 6. Daftar File yang Diubah dan Dibuat

| File Path | Jenis | Alasan Perubahan |
|---|---|---|
| `apps/web/next.config.mjs` | Modifikasi | Menambahkan konfigurasi `redirects` untuk alias URL persona (`/guru`, `/siswa`, `/ortu`, `/staf`, `/kepsek`). |
| `apps/web/src/types/auth.ts` | Modifikasi | Menambahkan nilai `'kepsek'` dan `'portal'` ke dalam union type `PortalDestination`. |
| `apps/web/src/lib/rbac/evaluator.ts` | Modifikasi | Mencabut `committee` & `supervisor` dari `ADMIN_SHELL_ROLES`; mengarahkan `principal` & `vice_principal` ke `'kepsek'`; memperbarui `canAccessShell`, `getPortalRoleOptions`, dan `getAccessiblePortals`. |
| `apps/web/src/components/shared/role-guard.tsx` | Modifikasi | Menambahkan dukungan portal `'kepsek'` pada `RoleGuardProps` dan label pesan verifikasi/penolakan. |
| `apps/web/src/components/shell/portal/portal-shell.tsx` | Modifikasi | Menambahkan identitas portal "Portal Pimpinan", ikon `Building`, dan navigasi tab eksekutif untuk rute `/portal/kepsek`. |
| `apps/web/src/app/(portal)/portal/layout.tsx` | Modifikasi | Menambahkan sinkronisasi `activeRole` untuk segmen rute `/portal/kepsek`. |
| `apps/web/src/app/(portal)/portal/page.tsx` | Modifikasi | Menambahkan penanganan aman untuk akun mitra / zero-option portal guna mencegah redirect loop dan menyajikan pemberitahuan status rilis. |
| `apps/web/src/app/(portal)/portal/kepsek/layout.tsx` | **File Baru** | Layout pelindung portal pimpinan dengan `<RoleGuard portal="kepsek">`. |
| `apps/web/src/app/(portal)/portal/kepsek/page.tsx` | **File Baru** | Halaman Dashboard KPI Eksekutif Pimpinan (ringkasan kehadiran, PPDB, nilai rata-rata jenjang, dan insiden sekolah). |
| `apps/web/src/app/(portal)/portal/kepsek/approvals/page.tsx` | **File Baru** | Halaman Antrean Persetujuan Dokumen resmi pimpinan (rapor, cuti, pengadaan) dengan modal alasan penolakan. |
| `apps/web/src/test/portal-kepsek-and-aliases.test.ts` | **File Baru** | Test suite komprehensif (8 skenario) untuk pengujian URL alias redirects, otorisasi kepsek, role switcher, dan restriksi mitra. |
| `apps/web/src/test/evaluator.test.ts` | Modifikasi | Menyesuaikan ekspektasi pengujian: `principal` diarahkan ke `'kepsek'` (bukan `'admin'`). |
| `apps/web/src/test/portal-admin-rbac.test.ts` | Modifikasi | Menyesuaikan ekspektasi pengujian: akun ganda `teacher` + `vice_principal` menghasilkan opsi `kepsek` dan `guru`. |
| `apps/web/src/test/read-only-roles.test.ts` | Modifikasi | Menyesuaikan ekspektasi pengujian: `committee` dan `supervisor` diarahkan ke `'portal'` dan tidak diizinkan masuk ke shell admin secara otomatis. |

---

## 7. Hasil Pengujian Aktual

Seluruh pengujian teknis dijalankan ulang secara lokal dan mencatatkan hasil **100% LULUS**:

### 7.1 Frontend TypeScript Typecheck
- **Perintah:** `npm run typecheck` (di `apps/web`)
- **Hasil:**
  ```text
  > sistlabum-web@1.0.0 typecheck
  > tsc --noEmit
  Exit code: 0 (Bersih, 0 error)
  ```

### 7.2 Frontend Vitest Test Suite
- **Perintah:** `npm run test -- --run` (di `apps/web`)
- **Hasil:**
  ```text
  Test Files: 65 passed (65)
       Tests: 765 passed (765)
    Duration: 9.94s
  Exit code: 0 (100% Lulus)
  ```
- **Rincian:** Bertambah 8 tes baru dari baseline sebelumnya (757 $\rightarrow$ 765 tes). Semua skenario pengujian alias rute, proteksi role guard pimpinan, mitigasi misrouting, dan restriksi peran mitra lulus tanpa kegagalan.

### 7.3 Backend Authentication & RBAC Suite
- **Perintah:** `php artisan test --filter=Auth` (di `apps/api`)
- **Hasil:**
  ```text
  {"tool":"phpunit","result":"passed","tests":204,"passed":204,"assertions":1130,"duration_ms":6205}
  Exit code: 0 (100% Lulus)
  ```

---

## 8. Risiko Regresi dan Tindakan Pencegahan

1. **Risiko Redirect Loop pada URL Alias:**
   - *Pencegahan:* Rute alias menggunakan Next.js `redirects` satu arah dari root spesifikasi (`/guru`) ke kanonis (`/portal/guru`). Rute kanonis tidak mengalihkan kembali ke root alias, dan query parameter dipertahankan secara transparan.
2. **Risiko Pimpinan Kehilangan Akses ke Laporan Terpadu:**
   - *Pencegahan:* Walaupun login utama pimpinan diarahkan ke `/portal/kepsek`, pimpinan tetap memegang permission `reports.view`. Tautan cepat ke `/admin/reports` disematkan di header portal kepsek dan diizinkan secara eksplisit oleh `AdminLayout`.
3. **Risiko Akses Bocor untuk Peran Mitra:**
   - *Pencegahan:* Pengguna `committee` dan `supervisor` tidak lagi dianggap anggota `ADMIN_SHELL_ROLES`. Jika mereka mencoba memasukkan URL `/admin` secara manual, `AdminLayout` mencegatnya dengan komponen `AccessDenied`.
4. **Risiko Regresi Multi-Peran:**
   - *Pencegahan:* Pengguna dengan peran ganda (misal Guru yang merangkap Kepala Sekolah) secara deterministik diarahkan ke portal pimpinan dengan opsi beralih ke Portal Guru melalui `RoleSwitcher`.

---

## 9. Gap yang Memerlukan Tindak Lanjut Pemilik Proyek

1. **Jadwal Pengembangan Portal Mitra (Komite & Pengawas):**
   - Saat ini ditunda sesuai keputusan. Apabila yayasan/sekolah menginginkan portal publikasi rapat dan pengumuman untuk komite, rute `/portal/mitra` dapat dijadwalkan pada fase rilis berikutnya.
2. **Kebutuhan Modul Khusus Petugas UKS dan Satpam:**
   - Sesuai audit, belum ada tabel/role untuk petugas UKS (rekam kunjungan sakit) dan satpam (buku tamu fisik gerbang). Jika institusi membutuhkan digitalisasi UKS dan pos satpam, diperlukan perencanaan skema database dan penambahan role resmi di backend.
3. **Penyempurnaan Fitur Mandiri Booking Lab & Perpus untuk Guru:**
   - Modul booking laboratorium dan peminjaman buku perpustakaan saat ini masih dikelola terpusat oleh staf admin sarpras/perpus. Integrasi tombol pemesanan mandiri di portal guru dapat dijadikan penyempurnaan minor berikutnya.

---

## 10. Status Akhir

Berdasarkan seluruh bukti verifikasi kode, pengujian otomatis yang lulus 100%, penerapan otorisasi ketat tanpa melemahkan keamanan, dan ketersediaan antarmuka pimpinan yang fungsional, fase implementasi ini dinyatakan:

# **`IMPLEMENTATION VERIFIED`**
*(Seluruh keputusan pemilik proyek telah diimplementasikan secara aman, teruji secara menyeluruh, dan siap untuk deployment staging UAT).*
