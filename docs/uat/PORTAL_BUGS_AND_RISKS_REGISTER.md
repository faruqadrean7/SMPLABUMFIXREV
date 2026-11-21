# REGISTER TEMUAN, CACAT & RISIKO OPERASIONAL PORTAL SISTLABUM

- **Repository:** `/Users/macbookprom12020/Desktop/Project/SISTLABUM`
- **Branch:** `develop` | **HEAD:** `448a1a499d3c3a87aa61c3d28428c616dd8f0405`
- **Tanggal Pembaruan:** 16 Oktober 2026
- **Status Dokumen:** Terverifikasi Terhadap Kode Aktual & Suite Pengujian
- **Standar Klasifikasi Risiko:** P0 (Kritis), P1 (Tinggi / Blocker UAT), P2 (Masalah Fungsional Penting), P3 (Minor / Kosmetik), INFO (Batasan Kebijakan / Desain v1).

---

## 1. Rekapitulasi Status Temuan & Distribusi Risiko

| Kategori Prioritas | Definisi & Dampak Operasional | Jumlah Temuan | Status Pemblokir Staging | Status Pemblokir Produksi |
|---|---|:---:|:---:|:---:|
| **P0 (Kritis)** | Kerentanan keamanan fatal, kehilangan data, kegagalan build/tes sistemik | **0** | Tidak Ada | Tidak Ada |
| **P1 (Tinggi)** | Celah keamanan tinggi, kegagalan otorisasi, ketidakkonsistenan transaksi | **0** | Tidak Ada | Tidak Ada |
| **P2 (Penting)** | Masalah tata kelola operasional, risiko pembersihan berkas tanpa kebijakan | **1** | Bukan Blocker Staging | **Blocker Produksi** |
| **P3 (Minor)** | Data contoh lokal pada tab sekunder, transisi visual antar-shell | **2** | Bukan Blocker UAT | Perlu Perbaikan Sprint |
| **INFO (Kebijakan)** | Batasan desain terencana v1 (*pending policies* SSOT Bagian 5) | **3** | Sesuai Spesifikasi | Sesuai Spesifikasi |
| **TOTAL** | | **6** | **0 Blocker Staging** | **1 Blocker Produksi (SOP Retensi)** |

---

## 2. Register Temuan & Analisis Rinci per Kategori

### A. Kategori P0 (Risiko Kritis)
*(Nihil — Tidak ditemukan kerentanan kritis, kebocoran memori, race condition fatal, atau kegagalan kompilasi. Suite pengujian backend 1.149 tests dan frontend 775 tests lulus 100%).*

---

### B. Kategori P1 (Risiko Tinggi / Blocker UAT)
*(Nihil — Tidak ditemukan celah pembobolan IDOR, bypass otentikasi Sanctum, atau kerusakan integritas data nilai/CBT).*

---

### C. Kategori P2 (Masalah Fungsional & Tata Kelola Penting)

#### TEMUAN REG-P2-01: Kebijakan Retensi Berkas Yatim & Penjadwalan Otomatis Cron
- **ID:** `REG-P2-01`
- **Prioritas:** **P2** (Penting / Persyaratan Sebelum Rilis Produksi)
- **Komponen Terdampak:** `apps/api/app/Console/Commands/CleanupOrphanFilesCommand.php`, `apps/api/app/Services/Storage/OrphanFileService.php`.
- **Dampak Operasional:** Perintah pembersihan berkas yatim (`php artisan files:cleanup-orphans`) dan 32 pemeriksaan foreign key telah teruji 100% lulus (10 unit test). Namun, jika cron otomatis diaktifkan di server produksi tanpa kebijakan masa tenggang yang disahkan, berkas draf pendaftaran PPDB yang belum disubmit dalam beberapa hari berisiko terhapus sebelum pendaftar menyelesaikan formulir.
- **Bukti Aktual:** `CleanupOrphanFilesCommand` saat ini tidak didaftarkan di scheduler `routes/console.php`. Secara default perintah berjalan dalam mode dry-run laporan.
- **Langkah Reproduksi:**
  1. Calon siswa mengunggah KK pada draf pendaftaran PPDB (berkas tersimpan di `files` dengan relasi belum dikunci).
  2. Eksekusi pembersihan dengan ambang batas usia rendah (`--hours=12 --execute --force`).
  3. Berkas draf calon siswa terhapus dari storage fisik.
- **Cakupan Terdampak:** Penyimpanan berkas pendaftaran PPDB dan media unggahan sementara.
- **Rekomendasi Remediasi:**
  1. Tetapkan SOP manual pembersihan di Staging UAT menggunakan opsi `--dry-run` mingguan.
  2. Jangan mengaktifkan cron otomatis di produksi sampai disahkannya SK Kebijakan Retensi resmi dari pihak sekolah:
     - Berkas draf PPDB: Masa tenggang minimal 30 hari kalender.
     - Berkas draf umum lainnya: Masa tenggang minimal 7 hari kalender.
- **Kriteria Penutupan (Closure Criteria):** Adanya dokumen kebijakan retensi resmi bertanda tangan pimpinan sekolah dan konfigurasi parameter ambang batas usia berkas (`--hours=720` untuk PPDB).

---

### D. Kategori P3 (Masalah Fungsional Minor / Kosmetik)

#### TEMUAN REG-P3-01: Data Contoh Lokal pada Tab Ekstrakurikuler Buku Poin Siswa
- **ID:** `REG-P3-01`
- **Prioritas:** **P3** (Minor / UX Gap)
- **Komponen Terdampak:** `apps/web/src/app/(portal)/portal/siswa/poin/page.tsx` (baris 45–64).
- **Dampak Operasional:** Pada Portal Siswa halaman buku poin, tab *Buku Poin Karakter* berfungsi penuh via API dinamis (`portalApi.getStudentPoints()`). Namun, tab *Ekstrakurikuler* mendefinisikan array statis lokal (`Coding & Robotika Club` dan `PMR Madya`) sehingga siswa melihat contoh ekskul seragam yang belum mencerminkan data rombel ekskul yang sebenarnya ia ikuti.
- **Bukti Aktual:**
  ```typescript
  // apps/web/src/app/(portal)/portal/siswa/poin/page.tsx:45-64
  const extracurriculars = [
    {
      id: 'ekskul-01',
      name: 'Coding & Robotika Club',
      coach: 'Budi Santoso, S.Kom., M.T.',
      schedule: 'Setiap Rabu, 15:00 - 17:00 WIB',
      room: 'Lab Komputer 1',
      role: 'Anggota Inti',
      attendance_rate: '100%',
    },
    ...
  ];
  ```
- **Langkah Reproduksi:**
  1. Login sebagai siswa Kelas 7A (`siswa.ahmad@labum.sch.id`).
  2. Buka `/portal/siswa/poin`.
  3. Klik tab *Ekstrakurikuler*.
  4. Amati bahwa daftar ekskul yang muncul adalah 2 klub statis di atas terlepas dari ekskul siswa di database.
- **Cakupan Terdampak:** Tab sekunder ekstrakurikuler pada buku poin siswa. (Tidak mempengaruhi fungsi utama buku poin kedisiplinan dan prestasi).
- **Rekomendasi Remediasi (Usulan Patch Terkecil Pasca-UAT):**
  Tambahkan fungsi pemanggil API pada `portalApi`:
  ```typescript
  async getStudentExtracurriculars(): Promise<any[]> {
    const res = await apiClient.get('/extracurricular-members');
    return res.data?.data || [];
  }
  ```
  Dan gunakan `useQuery` pada `StudentPoinPage`. Selama Staging UAT, hal ini dapat diuji sebagai representasi antarmuka.
- **Kriteria Penutupan (Closure Criteria):** Tab ekstrakurikuler menampilkan data riil dari relasi `extracurricular_members` siswa aktif atau menampilkan `EmptyState` jika siswa belum memilih kegiatan ekskul.

---

#### TEMUAN REG-P3-02: Transisi Antarmuka dari Portal Pimpinan ke Shell Admin pada Tautan Laporan
- **ID:** `REG-P3-02`
- **Prioritas:** **P3** (Minor / UX Navigation Polish)
- **Komponen Terdampak:** `apps/web/src/app/(portal)/portal/kepsek/page.tsx` (baris 101).
- **Dampak Operasional:** Tombol *Pusat Laporan Eksekutif* pada Dashboard Pimpinan mengarahkan ke `/admin/reports`. Kepala Sekolah dan Wakasek memiliki hak akses sah ke shell admin (`ADMIN_SHELL_ROLES`), namun tautan ini memindahkan layout dari portal tab horizontal ke admin sidebar vertikal.
- **Bukti Aktual:** `<Link href="/admin/reports">` di `apps/web/src/app/(portal)/portal/kepsek/page.tsx`.
- **Langkah Reproduksi:**
  1. Login sebagai Kepala Sekolah di `/portal/kepsek`.
  2. Klik tombol *Pusat Laporan Eksekutif*.
  3. Halaman beralih ke `/admin/reports` dengan layout admin shell.
- **Cakupan Terdampak:** Alur navigasi antarmuka pimpinan saat mengakses laporan agregat.
- **Rekomendasi Remediasi:** Tambahkan tombol shortcut kembali (*"Kembali ke Portal Pimpinan"*) pada header admin untuk peran eksekutif, atau sematkan halaman laporan agregat langsung di `/portal/kepsek/reports` pada pembaruan v2.
- **Kriteria Penutupan (Closure Criteria):** Pengguna pimpinan dapat kembali ke portal persona pimpinan dengan satu klik navigasi yang konsisten.

---

### E. Kategori INFO (Batasan Desain Terencana v1 / Pending Policies)

#### TEMUAN REG-INFO-01: Batasan Modul Keuangan & SPP (SSOT Bagian 5 No. 1)
- **ID:** `REG-INFO-01`
- **Prioritas:** **INFO**
- **Deskripsi:** Integrasi gateway pembayaran tagihan SPP online ditunda atas ketetapan sekolah. Profil siswa pada rilis v1 hanya menampilkan string teks catatan bebas tanggungan administrasi.
- **Evaluasi Kepatuhan:** 100% Sesuai Spesifikasi. Tidak ada kode pura-pura atau transaksi pembayaran fiktif.

---

#### TEMUAN REG-INFO-02: Batasan Sinkronisasi Dapodik & e-Rapor Kemdikbud (SSOT Bagian 5 No. 2)
- **ID:** `REG-INFO-02`
- **Prioritas:** **INFO**
- **Deskripsi:** Sinkronisasi web-service otomatis dengan server pusat kementerian ditunda menunggu perizinan resmi sekolah. Sistem menyediakan fungsi ekspor/impor berkas spreadsheet Excel terstruktur yang kompatibel dengan format Kemdikbud.
- **Evaluasi Kepatuhan:** 100% Sesuai Spesifikasi. Data dapat diekspor secara valid tanpa risiko kegagalan integrasi otomatis.

---

#### TEMUAN REG-INFO-03: Batasan Komunikasi Chat Interaktif Dua Arah (SSOT Bagian 5 No. 4)
- **ID:** `REG-INFO-03`
- **Prioritas:** **INFO**
- **Deskripsi:** Komunikasi soket real-time dua arah guru-ortu ditiadakan untuk menjaga kenyamanan istirahat guru di luar jam kerja. Komunikasi dialihkan melalui tautan deep-link WhatsApp resmi sekolah atau permohonan janji temu konseling BK pada jam dinas.
- **Evaluasi Kepatuhan:** 100% Sesuai Spesifikasi.

---

## 3. Matriks Keputusan Empat Tahap Terpisah

Keempat keputusan berikut **tidak boleh disamakan** karena memiliki kriteria kelayakan, lingkungan target, dan penanggung jawab yang berbeda:

```
[ KELAYAKAN 1: STAGING ] ────────► [ KELAYAKAN 2: UAT PENGGUNA ] ────────► [ KELAYAKAN 3: OPERASIONAL ] ────────► [ KELAYAKAN 4: PRODUKSI ]
      (Lulus Teknis)                      (Siap Diuji Lapangan)                  (Menunggu Hasil UAT)                (Menunggu Kebijakan)
```

### 1. KELAYAKAN TEKNIS MEMASUKI LINGKUNGAN STAGING
- **Keputusan:** **`DISETUJUI (APPROVED)`**
- **Dasar Pertimbangan:**
  1. Build frontend Next.js 14 menghasilkan 95 rute bersih dengan 0 error kompilasi.
  2. Typecheck TypeScript (`tsc --noEmit`) lulus 100% dengan 0 error.
  3. Linter Next.js lulus dengan exit code 0.
  4. Suite unit & integration test backend Laravel lulus 1.149 tests (0 failure).
  5. Suite test frontend vitest lulus 775 tests (0 failure).
  6. Seluruh rute, URL aliases, dan redirect kanonis berfungsi sesuai arsitektur.

---

### 2. KELAYAKAN MEMULAI UAT BERSAMA PENGGUNA SEKOLAH (STAGING UAT)
- **Keputusan:** **`DISETUJUI (APPROVED FOR USER TESTING)`**
- **Dasar Pertimbangan:**
  1. Seluruh 8 alur kerja operasional (PPDB, KBM, Presensi, LMS, CBT Anti-Cheat, Ortu Anti-IDOR, Kepsek Approvals, Staf TU, Verifikasi Rapor) memiliki rute antarmuka nyata dan endpoint database terhubung.
  2. Celah keamanan IDOR terbukti kebal pada 3 lapisan (UI, Service, Database).
  3. Telah tersedia dokumen panduan pengujian terstruktur [`STAGING_UAT_EXECUTION_CHECKLIST.md`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/docs/uat/STAGING_UAT_EXECUTION_CHECKLIST.md).
  4. Temuan minor P3 (tab ekskul pada buku poin siswa) tidak menghambat skenario evaluasi kedisiplinan dan karakter.

---

### 3. KELAYAKAN OPERASIONAL HARIAN SETELAH UAT
- **Keputusan:** **`MENUNGGU HASIL EKSEKUSI UAT (CONDITIONAL ON UAT SIGNOFF)`**
- **Dasar Pertimbangan:**
  1. Sistem baru dapat dinyatakan layak untuk kegiatan operasional harian sekolah setelah perwakilan nyata pengguna (Kepala Sekolah, perwakilan dewan guru, panitia PPDB, dan perwakilan wali murid) menandatangani lembar pengesahan pada Checklist UAT.
  2. UAT lapangan akan memvalidasi pengalaman pengguna (*UX friction*), kejelasan teks Bahasa Indonesia, serta keakuratan formula perangkingan PPDB pada data nyata.

---

### 4. KELAYAKAN RILIS PRODUKSI (PRODUCTION READINESS)
- **Keputusan:** **`BELUM MEMENUHI SYARAT (NOT YET CLEARED FOR PRODUCTION)`**
- **Prasyarat Wajib yang Belum Terpenuhi:**
  1. **Pengesahan SK Kebijakan Retensi Berkas:** Surat Keputusan Kepala Sekolah mengenai masa tenggang draf PPDB (30 hari) dan berkas umum (7 hari) sebelum cron scheduler otomatis boleh diaktifkan.
  2. **Persetujuan Tertulis Tata Kelola Pasfoto Terpusat:** Pernyataan resmi bahwa pasfoto profil siswa dan guru dikelola secara terpusat oleh Tata Usaha.
  3. **Penandatanganan Berita Acara UAT:** Seluruh 8 skenario pada Checklist UAT diselesaikan dengan status Lulus tanpa temuan cacat P0/P1.
  4. **Pencadangan Penuh & Rencana Pemulihan Bencana (DRP):** Prosedur backup teruji pada infrastruktur produksi server sekolah.
