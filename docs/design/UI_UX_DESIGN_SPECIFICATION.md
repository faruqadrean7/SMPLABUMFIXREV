# SISTLABUM — UI/UX DESIGN SYSTEM & SCREEN SPECIFICATION
**Spesifikasi Desain Antarmuka: Web Portal (Publik & Admin) & Mobile Portal (Android Native)**  
**Versi:** 1.0.0 (Master Design Blueprint)  
**Target Platform:** Web (Next.js + Tailwind + shadcn/ui) & Mobile (Android Kotlin + Jetpack Compose)  
**Tanggal:** 2026-10-12  

---

## DAFTAR ISI
1. [Fondasi Desain & Design Tokens (Unified)](#1-fondasi-desain--design-tokens-unified)
2. [Pustaka Komponen UI Bersama](#2-pustaka-komponen-ui-bersama)
3. [Spesifikasi Web Portal Publik & PPDB (Next.js)](#3-spesifikasi-web-portal-publik--ppdb-nextjs)
4. [Spesifikasi Web Portal & Dashboard Terpadu (Next.js)](#4-spesifikasi-web-portal--dashboard-terpadu-nextjs)
   - 4.1 Shell Navigasi Multi-Role
   - 4.2 Layar Admin & Guru (`/admin/*`)
   - 4.3 Layar Portal Siswa (`/portal/siswa/*` & Web CBT Desktop)
   - 4.4 Layar Portal Orang Tua (`/portal/ortu/*` & Multi-Anak Switcher)
5. [Spesifikasi Mobile Portal Android (Jetpack Compose)](#5-spesifikasi-mobile-portal-android-jetpack-compose)
6. [Prinsip State & Interaksi UI (Loading, Empty, Error, Offline)](#6-prinsip-state--interaksi-ui)

---

## 1. FONDASI DESAIN & DESIGN TOKENS (UNIFIED)

### 1.1 Palet Warna (Brand & Semantic)
Sistem warna menggunakan kombinasi biru akademik Labschool UM dengan aksen modern yang bersih dan memiliki kontras tinggi (memenuhi WCAG AA):

| Token Name | Hex Code | Compose / Tailwind | Penggunaan Utama |
|---|---|---|---|
| **Primary Blue** | `#1E3A8A` | `PrimaryBlue` / `blue-900` | Header web, top app bar, primary button, brand identity |
| **Primary Dark** | `#172554` | `PrimaryDarkBlue` / `blue-950` | Sidebar admin web, active state, hero background dark |
| **Secondary Teal** | `#0D9488` | `SecondaryTeal` / `teal-600` | Aksen akademik, badge sukses, tombol sekunder |
| **Accent Amber** | `#F59E0B` | `AccentAmber` / `amber-500` | Highlight pengumuman, bintang prestasi, status ragu-ragu CBT |
| **Background Light** | `#F8FAFC` | `BackgroundLight` / `slate-50` | Latar belakang seluruh halaman aplikasi dan admin |
| **Surface Light** | `#FFFFFF` | `SurfaceLight` / `white` | Latar kartu (card), modal, dialog, tabel |
| **Text Primary** | `#0F172A` | `TextPrimary` / `slate-900` | Judul, label utama, konten teks terbaca |
| **Text Secondary** | `#64748B` | `TextSecondary` / `slate-500` | Subtitle, metadata, placeholder, helper text |
| **Border / Divider** | `#E2E8F0` | `BorderColor` / `slate-200` | Garis batas card, pembatas baris tabel, divider |
| **Success** | `#16A34A` | `StatusSuccess` / `green-600` | Status hadir, lulus seleksi, autosave tersimpan |
| **Warning** | `#D97706` | `StatusWarning` / `amber-600` | Status izin, verifikasi tertunda, sisa waktu CBT tipis |
| **Danger / Error** | `#DC2626` | `StatusError` / `red-600` | Status alpa, ditolak, form error, sesi CBT terkunci |
| **Info** | `#2563EB` | `StatusInfo` / `blue-600` | Status informasi, panduan, tiket diproses |

### 1.2 Skala Tipografi
- **Web Desktop**: Inter / Outfit (Modern Sans).
- **Mobile Android**: Roboto / System Default.

| Style Name | Size (Web / Mobile) | Weight | Line Height | Penggunaan |
|---|---|---|---|---|
| **Display / Hero** | `36px` / `28sp` | Bold (700) | 1.2 | Hero title website, judul splash screen |
| **Heading 1** | `28px` / `24sp` | Bold (700) | 1.3 | Judul halaman admin, judul modul mobile |
| **Heading 2** | `22px` / `20sp` | SemiBold (600) | 1.35 | Judul seksi, nama siswa di kartu, judul modal |
| **Title Medium** | `18px` / `16sp` | SemiBold (600) | 1.4 | Judul kartu ringkasan, nama mapel |
| **Body Regular** | `15px` / `14sp` | Normal (400) | 1.5 | Paragraf berita, isi soal CBT, form label |
| **Body Small** | `13px` / `12sp` | Normal (400) | 1.4 | Helper text, caption tanggal, timestamp notifikasi |
| **Badge / Label** | `11px` / `11sp` | Medium (500) | 1.2 | StatusBadge, chip tag, nomor butir soal CBT |

### 1.3 Sudut Lengkung (Border Radius) & Bayangan (Shadow)
- **Radius Sm (`4px`)**: Input field, checkbox, tag kecil.
- **Radius Md (`8px`)**: Tombol, chip, dropdown, badge status.
- **Radius Lg (`12px`)**: Kartu konten (card), container ringkasan, modal dialog.
- **Radius Xl (`16px` - `24px`)**: Bottom sheet mobile, kartu kartu pelajar digital, hero container.
- **Elevation / Shadow**:
  - `shadow-sm`: Kartu data, input fokus.
  - `shadow-md`: Dropdown menu, popover, tombol aksi mengambang (FAB).
  - `shadow-lg`: Modal dialog admin, bottom sheet mobile.

---

## 2. PUSTAKA KOMPONEN UI BERSAMA

### 2.1 Komponen Web (shadcn/ui + Tailwind)
1. **`DataTable`**:
   - Fitur: Search realtime, filter kolom (faceted filter), sort multi-kolom, paginasi (10/20/50/100), bulk selection dengan action bar mengambang, ekspor Excel/CSV.
2. **`FormBuilder`**:
   - Form 1 atau 2 kolom berbasis React Hook Form + Zod dengan pesan kesalahan inline di bawah setiap input.
3. **`StatusBadge`**:
   - Badge warna semantik (`variant="success|warning|destructive|secondary|outline"`).
4. **`WizardStepper`**:
   - Komponen multi-step untuk formulir pendaftaran PPDB dan kenaikan kelas.
5. **`RichTextEditor`**:
   - Editor Tiptap WYSIWYG untuk pembuatan soal CBT, artikel berita CMS, dan pengumuman.
6. **`FileDropzone`**:
   - Area drag-and-drop unggah berkas dengan progress bar, preview thumbnail gambar/PDF, dan validasi tipe berkas.
7. **`ConfirmDialog`**:
   - Dialog peringatan aksi destruktif (hapus, kunci periode, diskualifikasi ujian).
8. **`TimetableGrid`**:
   - Grid jadwal pelajaran harian/mingguan drag-and-drop dengan deteksi bentrok otomatis.

### 2.2 Komponen Mobile (Jetpack Compose Material 3)
1. **`SistTopAppBar`**:
   - App bar dinamis: judul, tombol kembali, icon bel notifikasi dengan badge merah, dan dropdown pemilih profil anak untuk Orang Tua.
2. **`SistBottomNavBar`**:
   - Bottom navigation bar dengan 5 tab yang berganti ikon dan label otomatis sesuai role login.
3. **`QuickAttendanceCard`**:
   - Kartu presensi siswa dengan tombol tap cepat: 1 tap ganti status (Hadir ➔ Sakit ➔ Izin ➔ Alpa), tekan tahan untuk catatan.
4. **`CbtQuestionWorkspace`**:
   - Area render soal: teks kaya, zoomable image, LaTeX formula, dan panel input opsi interaktif.
5. **`CbtTimerHeader`**:
   - Bar hitung mundur waktu ujian dengan progress indicator dan status koneksi/autosave (ikon awan tersimpan).
6. **`QuestionGridDrawer`**:
   - Bottom sheet nomor soal: grid tombol nomor (Abu-abu: kosong, Hijau: terjawab, Kuning: ragu-ragu).
7. **`DigitalCardView`**:
   - Kartu pelajar / kartu ujian bergradien biru dengan foto siswa, NIS, barcode/QR code, dan stempel dinamis.

---

## 3. SPESIFIKASI WEB PORTAL PUBLIK & PPDB (NEXT.JS)

### 3.1 Layout Publik
- **Header**: Logo sekolah, navigasi menu (Beranda, Profil, Direktori, Berita, PPDB, Layanan, Unduhan), tombol CTA **"Pintu Portal Siswa/Guru"**.
- **Footer**: Identitas sekolah, alamat lengkap, peta interaktif, sosial media resmi, jam operasional, akreditasi.

### 3.2 Inventaris Layar Web Publik

#### 1. Beranda Publik (`/`)
- **Hero Slider**: Banner foto kegiatan sekolah (maks 5 slide) dengan teks sambutan dan tombol aksi utama ("Daftar PPDB Online" & "Jelajahi Profil").
- **Banner PPDB Aktif (Dynamic)**: Muncul hanya bila ada gelombang PPDB aktif; menampilkan nama gelombang, tanggal penutupan, hitung mundur (countdown), dan tombol "Daftar Sekarang".
- **Statistik Sekolah**: Counter animasi: Total Siswa Aktif, Guru Berprestasi, Akreditasi "A", Capaian Prestasi Nasional.
- **Sambutan Singkat Kepala Sekolah**: Foto kepala sekolah, kutipan visi, tombol "Baca Selengkapnya".
- **Program Unggulan**: 3 kartu interaktif (Bilingual Class, Tahfidz Karakter, Inkubasi Riset Siswa).
- **Warta & Berita Terbaru**: Grid 3 kartu artikel terbaru (gambar sampul, judul, tanggal, kategori).
- **Agenda Mendatang**: Widget kalender agenda sekolah terdekat.

#### 2. Profil Sekolah (`/profil/[slug]`)
- **Sub-halaman**:
  - `/profil/sambutan`: Sambutan lengkap kepala sekolah.
  - `/profil/visi-misi`: Visi, misi, dan nilai budaya sekolah.
  - `/profil/akreditasi`: Sertifikat akreditasi resmi BAN-S/M, nilai, masa berlaku, dan pratinjau sertifikat.
  - `/profil/fasilitas`: Galeri foto fasilitas (Lab Komputer, Lab IPA, Perpustakaan Digital, Lapangan, Aula).

#### 3. Direktori Guru & Tenaga Kependidikan (`/guru-staf`)
- **Pencarian & Filter**: Filter jabatan (Wali Kelas, Guru Mapel, Staf TU), search bar nama guru.
- **Grid Kartu Guru**: Foto resmi, nama lengkap + gelar, mata pelajaran yang diampu. *(Data NIP dan kontak privat disembunyikan)*.

#### 4. Warta Berita & Artikel (`/berita`, `/berita/[slug]`)
- **Daftar Berita**: Tab kategori berita (Prestasi, Akademik, Kegiatan, Pengumuman), pencarian, paginasi.
- **Detail Berita**: Gambar utama, tanggal, nama penulis, pembaca teks kaya (konten artikel), tombol bagikan sosial media, daftar berita terkait.

#### 5. Kalender & Agenda Publik (`/kegiatan`)
- Tampilan kalender interaktif bulanan berisi agenda ujian, libur nasional, rapat wali murid, dan study trip.

#### 6. Galeri Foto & Buletin Mading Digital (`/galeri`, `/mading`)
- **Galeri**: Album foto kegiatan dengan fitur lightbox zoom.
- **Mading Digital**: Buletin karya siswa dan majalah sekolah dengan flipbook viewer.

#### 7. Layanan Tamu & Aspirasi Publik (`/layanan/buku-tamu`, `/layanan/aspirasi`)
- **Buku Tamu Digital**: Form instansi, nama tamu, tujuan kunjungan, foto tanda tangan digital.
- **Aspirasi & Pengaduan**: Form aspirasi dengan opsi anonim (rahasia terenkripsi) + fitur kode lacak status penanganan (`/layanan/aspirasi/lacak`).

#### 8. Pusat Unduhan (`/unduhan`)
- Daftar berkas publik (Brosur PPDB, Kalender Pendidikan PDF, Panduan Akademik) dengan counter unduhan.

#### 9. Portal PPDB Online (`/ppdb/*`)
- **`/ppdb` (Landing Info)**: Persyaratan pendaftaran, jalur seleksi, jadwal gelombang, kuota pendaftaran.
- **`/ppdb/daftar` & `/ppdb/masuk`**: Form registrasi akun orang tua/calon siswa (Nama, Email/WhatsApp, Password) dan login pendaftar.
- **`/ppdb/dashboard` (Area Pendaftar)**:
  - Stepper 4 langkah formulir:
    1. Data Diri Calon Siswa (NISN, Nama, Tempat/Tgl Lahir, Asal Sekolah).
    2. Data Orang Tua / Wali (Nama, NIK, Pekerjaan, Kontak).
    3. Pilihan Jalur Seleksi & Jurusan/Peminatan.
    4. Unggah Dokumen Persyaratan (Kartu Keluarga, Akta Kelahiran, Rapor, Piagam Prestasi).
  - Status Verifikasi Dokumen: *Draft*, *Menunggu Verifikasi*, *Revisi Diperlukan*, *Terverifikasi*.
  - Unduh Bukti Tanda Pendaftaran PPDB ber-QR Code.
- **`/ppdb/pengumuman`**: Pencarian hasil seleksi berdasarkan Nomor Pendaftaran / NISN. Tampil pengumuman lulus bersyarat dan panduan daftar ulang.

#### 10. Verifikasi Dokumen Publik (`/verifikasi/[token]`)
- Halaman verifikasi publik keaslian e-rapor dan progress report saat QR code dokumen dipindai. Menampilkan nama siswa, kelas, tanggal pengesahan, dan status valid.

#### 11. Pintu Masuk Portal (`/masuk`)
- Halaman gateway terpadu modern & bersih:
  - **Form Login Terpadu**: Satu formulir kredensial (NIS/NIP/Username/Email + Password) dengan deteksi otomatis role pengguna (`student`, `teacher`, `guardian`, `admin`, `staff`) yang langsung mengarahkan ke shell web masing-masing.
  - **Kartu Unduh Aplikasi Mobile**: QR Code dan tautan langsung untuk mengunduh APK Android Native (atau Play Store) bagi Siswa, Guru, dan Orang Tua yang ingin mengakses lewat smartphone.

---

## 4. SPESIFIKASI WEB PORTAL & DASHBOARD TERPADU (NEXT.JS)

### 4.1 Layout Shell Navigasi Multi-Role
Web Portal SISTLABUM menggunakan App Router Next.js dengan 3 shell antarmuka terpisah sesuai peran otentikasi:
1. **Shell Manajemen Admin & Guru (`/admin/*`)**:
   - **Sidebar**: Navigasi hirarkis berakar role pengguna; dapat diciutkan (collapsible).
   - **Topbar**: Pemilih Tahun Ajaran Aktif (Dropdown), Search global, Bell notifikasi realtime, Menu profil & logout.
   - **Breadcrumbs**: Panduan navigasi hirarki (misal: `Akademik > Nilai > Kelas VII-A`).
2. **Shell Portal Siswa (`/portal/siswa/*`)**:
   - **Header Navigasi**: Tab menu ringkas (Beranda, Jadwal, Tugas LMS, Ujian CBT, Rapor, Poin).
   - **Distraction-Free Mode (CBT)**: Mode fullscreen khusus ujian di lab komputer tanpa sidebar/topbar navigasi umum.
3. **Shell Portal Orang Tua (`/portal/ortu/*`)**:
   - **Multi-Child Selector Bar**: Dropdown/kartu identitas pemilih anak aktif di sudut kanan atas. Semua data presensi, jadwal, dan nilai otomatis sinkron dengan anak yang dipilih.
   - **Header Navigasi**: Tab menu orang tua (Beranda, Kehadiran, Nilai & Rapor, Izin & Sakit, Bantuan/BK).

### 4.2 Inventaris Layar per Modul Admin & Guru (`/admin/*`)

#### 1. Modul Dashboard (`/admin/dashboard`)
- **Dashboard Admin TU**: Metrik total siswa aktif, total guru, pendaftar PPDB baru, antrean verifikasi, aktivitas log sistem terbaru.
- **Dashboard Guru**: Jadwal mengajar hari ini, tombol cepat isi jurnal, antrean koreksi esai CBT, progres entri nilai bulanan dengan bar indikator tenggat.
- **Dashboard BK**: Total kasus konseling terbuka, grafik kerentanan/peringatan dini, jadwal temu konseling hari ini.
- **Dashboard Sarpras**: Status ketersediaan ruang lab, permohonan booking menunggu persetujuan, tiket kerusakan alat.
- **Dashboard Panitia PPDB**: Grafik corong konversi pendaftar: Mendaftar ➔ Berkas Lengkap ➔ Terverifikasi ➔ Lulus Seleksi ➔ Daftar Ulang.

#### 2. Modul Master Data (`/admin/master/*`)
- **Tahun Ajaran & Semester (`/admin/master/academic-years`)**:
  - Daftar tahun ajaran, set status aktif, modal ganti semester (konfirmasi ketat).
- **Data Siswa (`/admin/master/students`)**:
  - DataTable siswa dengan foto thumbnail, NIS, NISN, kelas aktif, status.
  - Dialog Import Siswa Excel (pratinjau baris + error report per baris).
  - Form Tambah/Ubah Siswa (data personal, data orang tua, data akademik).
- **Data Guru & Staf (`/admin/master/teachers`)**:
  - Direktori tenaga pendidik, NIP/NUPTK, status kepegawaian, mapel pengampu, import Excel.
- **Data Orang Tua / Wali (`/admin/master/guardians`)**:
  - Pengelolaan data wali dan relasi penghubung ke multi-siswa (`student_guardians`).
- **Kelas & Rombongan Belajar (`/admin/master/classes`)**:
  - Daftar kelas per tingkat, penugasan wali kelas, kuota daya tampung, riwayat anggota kelas.
- **Mata Pelajaran & Penugasan Mengajar (`/admin/master/subjects`, `/teaching-assignments`)**:
  - Penugasan guru ke kelas per mata pelajaran dan alokasi jam per minggu.
- **Ruang & Fasilitas (`/admin/master/rooms`)**:
  - Manajemen ruang kelas, lab komputer, aula, kapasitas meja/kursi.
- **Kenaikan Kelas & Kelulusan (`/admin/master/promotions`)**:
  - Wizard proses massal: Pilih kelas asal ➔ Pratinjau syarat kelulusan/kenaikan ➔ Eksekusi massal (bisa dibatalkan sebelum dikunci).

#### 3. Modul PPDB Admin (`/admin/ppdb/*`)
- **Konfigurasi Gelombang & Jalur (`/admin/ppdb/waves`)**:
  - Atur gelombang (Reguler, Prestasi, Afirmasi), tanggal buka-tutup, kuota per jalur, biaya pendaftaran.
- **Verifikasi Berkas Pendaftar (`/admin/ppdb/verification`)**:
  - Split-view UI: Sisi kiri formulir data siswa, sisi kanan pratinjau dokumen PDF/foto.
  - Aksi: *Setujui Berkas* atau *Tolak dengan Catatan Perbaikan* (otomatis kirim WA/email).
- **Seleksi & Pemeringkatan Nilai (`/admin/ppdb/selection`)**:
  - Simulasi perangkingan otomatis berdasarkan formula bobot nilai rapor + poin prestasi.
  - Tombol kunci hasil seleksi dan terbitkan pengumuman.
- **Daftar Ulang & Konfirmasi Siswa Baru (`/admin/ppdb/enrollment`)**:
  - Pemantauan status daftar ulang, verifikasi pembayaran administrasi, konversi calon siswa menjadi siswa aktif kelas VII/X.

#### 4. Modul Akademik (`/admin/academic/*`)
- **Penyusunan Jadwal Pelajaran (`/admin/academic/schedules`)**:
  - Tampilan grid matriks jadwal harian. Deteksi bentrok ruang dan guru secara visual.
- **Monitoring Jurnal & Presensi Kelas (`/admin/academic/journals`)**:
  - Rekap keterisian jurnal harian guru dan persentase absensi kelas realtime.
- **Buku Nilai & Kunci Periode (`/admin/academic/grades`)**:
  - Rekap nilai per mapel dan kelas. Tombol kunci periode input nilai (*lock period*).
- **Progress Report Bulanan (`/admin/academic/progress-reports`)**:
  - Generator massal rapor bulanan via antrean background worker.
  - Layar persetujuan kepala sekolah (ACC massal) dan pratinjau PDF ber-QR code.
- **E-Rapor Semester (`/admin/academic/report-cards`)**:
  - Manajemen ledger nilai akhir semester, deskripsi capaian otomatis, cetak massal e-rapor resmi.

#### 5. Modul CBT Admin (`/admin/cbt/*`)
- **Bank Soal (`/admin/cbt/questions`)**:
  - Pengelolaan butir soal: filter mapel, level kognitif (LOTS/MOTS/HOTS), tingkat kesulitan (1–5).
  - Editor soal: Teks kaya, rumus LaTeX math, media gambar/audio, opsi jawaban dinamis per 6 tipe soal.
- **Paket & Jadwal Ujian (`/admin/cbt/exams`)**:
  - Pembuatan ujian (STS, SAS, Ulangan Harian), durasi menit, KKTP, tanggal pelaksanaan.
  - Fitur acak nomor soal dan acak opsi jawaban (A/B/C/D/E).
- **Plotting Ruang & Token Ujian (`/admin/cbt/rooms`)**:
  - Distribusi peserta ke ruang ujian dan sesi waktu.
  - Panel rilis & regenerasi token ruang ujian (token 6 huruf kapital).
- **Monitoring Ruang Ujian Realtime (`/admin/cbt/monitoring/[roomId]`)**:
  - Grid status peserta ujian: *Belum Mulai*, *Sedang Mengerjakan* (sisa waktu detik), *Selesai*, *Terkunci*.
  - Aksi pengawas: Tambah waktu pengerjaan siswa, reset sesi ujian, buka kunci pelanggaran.
- **Koreksi Esai & Analisis Butir Soal (`/admin/cbt/grading`, `/analysis`)**:
  - Meja koreksi uraian esai dan statistik daya beda / tingkat kesukaran soal.

#### 6. Modul Kesiswaan & BK (`/admin/student-affairs/*`, `/admin/counseling/*`)
- **Poin Disiplin & Prestasi (`/admin/student-affairs/points`)**:
  - Katalog jenis pelanggaran & prestasi, master ambang poin, pencatatan poin siswa.
- **Konseling & Catatan Rahasia BK (`/admin/counseling/cases`)**:
  - Akses terbatas khusus konselor (guard role ketat).
  - Form pencatatan kasus privat, riwayat sesi bimbingan, jadwal temu wali murid.
  - Sistem deteksi dini kerentanan siswa (kehadiran rendah, penurunan drastis nilai).

#### 7. Modul Sarpras & Logistik (`/admin/facilities/*`)
- **Peminjaman Ruang & Alat (`/admin/facilities/bookings`)**:
  - Kalender persetujuan peminjaman laboratorium, aula, dan proyektor.
- **Aset & Tiket Kerusakan (`/admin/facilities/tickets`)**:
  - Meja bantuan (helpdesk) pelaporan kerusakan fasilitas sekolah dengan bukti foto dan status perbaikan.

#### 8. Modul Humas & Alumni (`/admin/public-relations/*`)
- **Buku Tamu Digital & Pengaduan (`/admin/public-relations/visitors`, `/complaints`)**.
- **Kemitraan Sekolah & Tracer Study Alumni (`/partnerships`, `/alumni`)**.

#### 9. Modul Konten Web CMS (`/admin/cms/*`)
- Pengaturan profil sekolah, upload berita/artikel, manajemen album galeri, agenda kalender, dan dokumen pusat unduhan.

#### 10. Modul Pengaturan Sistem (`/admin/settings/*`)
- **Manajemen Pengguna & RBAC (`/admin/settings/users`, `/roles`)**:
  - Pengaturan multi-role, mapping permission per role.
- **Template Notifikasi (`/admin/settings/notification-templates`)**:
  - Template pesan WhatsApp, email, dan push notif dengan variabel dinamis (`{{student_name}}`).
- **Audit Log Terpusat (`/admin/settings/audit-logs`)**:
  - Catatan immutable aktivitas aktor, aksi, IP address, user agent, dan perbandingan diff nilai lama vs baru.

---

### 4.3 Spesifikasi Layar Web Portal Siswa (`/portal/siswa/*`)

Dirancang untuk perangkat layar lebar (PC Lab Komputer, Laptop, Chromebook) dengan resolusi target 1366×768 px hingga 1920×1080 px:

#### 1. Dashboard Siswa Web (`/portal/siswa`)
- **Banner Identitas & Presensi Hari Ini**: Foto siswa, NIS, kelas, nama wali kelas, badge status kehadiran hari ini (Hadir / Belum Presensi).
- **Jadwal Pelajaran Hari Ini**: Kartu timeline jam ke-1 s.d ke-8, mata pelajaran, nama guru, ruang lab/kelas.
- **Banner Ujian CBT Terdekat**: Kotak aksi cepat jika ada jadwal ujian aktif hari ini (Nama ujian, waktu mulai, tombol "Masuk Ujian").
- **Daftar Tenggat Tugas (LMS)**: Daftar tugas dengan status *Mendekati Tenggat* (< 24 jam).
- **Pengumuman & Warta Sekolah**: Carousel berita/pengumuman internal.

#### 2. Web CBT Exam Workspace - Resolusi Desktop / Lab Komputer (`/portal/siswa/cbt/[examId]`)
- **Mode Ujian Penuh (Distraction-Free Desktop UI)**:
  - **Header Bar (Tinggi 64px, Sticky)**:
    - Kiri: Logo sekolah, nama ujian, mata pelajaran, nama siswa & NIS.
    - Tengah: Indikator status koneksi (Pill hijau *Online* / Pill merah *Mencoba Menghubungkan*), Autosave status (*Semua jawaban tersimpan* ber-icon centang hijau).
    - Kanan: **Timer Countdown Server** (Font monospaced tebal; berubah oranye bila < 10 menit, merah berkedip bila < 5 menit), Tombol *Kumpulkan Ujian*.
  - **Panel Soal (Kiri, Lebar 75%)**:
    - Nomor soal aktif & bobot nilai butir.
    - Konten teks soal: Render teks kaya, formula KaTeX matematika, gambar resolusi tinggi (dengan zoom popover), audio player untuk listening (dengan batas putar), tabel data.
    - Lembar Jawaban Interaktif (6 format soal):
      1. *Pilihan Ganda*: Radio button berukuran besar (A, B, C, D, E) dengan shortcut keyboard (1/2/3/4/5 atau A/B/C/D/E).
      2. *Pilihan Ganda Kompleks*: Checkbox multi-opsi.
      3. *Benar / Salah*: Tabel matriks pernyataan dengan kolom radio Benar / Salah.
      4. *Menjodohkan*: UI dua kolom pasangan dengan dropdown pencocokan atau drag line.
      5. *Isian Singkat*: Single-line input text field.
      6. *Uraian / Esai*: Rich text editor dengan preview formula dan batas kata.
    - Bar Navigasi Bawah: Tombol *<- Soal Sebelumnya*, Checkbox Amber *Ragu-Ragu*, Tombol *Soal Selanjutnya ->*.
  - **Panel Navigasi Butir Soal (Kanan, Lebar 25%, Sticky)**:
    - Grid tombol nomor 1 s.d 50 (5 kolom).
    - Indikator Warna Status:
      - *Abu-abu / Border tipis*: Belum dijawab.
      - *Biru Tua (PrimaryBlue)*: Sudah terjawab.
      - *Kuning Amber (AccentAmber)*: Ditandai ragu-ragu.
      - *Border Emas*: Soal yang sedang aktif dibuka.
    - Legend ringkasan: "Terjawab: 38/40 | Ragu-ragu: 2 | Kosong: 0".
    - Tombol Merah/Biru di bawah grid: "*Selesaikan Ujian*".
- **Sistem Keamanan Anti-Cheat Web**:
  - Fullscreen API otomatis saat token diverifikasi.
  - Modal Peringatan Pelanggaran: Memicu peringatan jika kursor berpindah ke tab/layar lain (`visibilitychange`, `window.onblur`), mencatat counter peringatan ke server via `POST /exams/{id}/log-violation`.
  - Proteksi inspect element, disable right-click (`contextmenu`), dan blokir shortcut keyboard devtools (F12, Ctrl+Shift+I, Alt+Tab warning).
  - Modal Konfirmasi Kumpulkan Berlapis: Menampilkan rekap nomor yang belum terjawab atau masih ditandai ragu-ragu sebelum submit final.

#### 3. Jadwal & Kalender Akademik Siswa (`/portal/siswa/jadwal`)
- Grid jadwal mingguan Senin-Jumat dengan pewarnaan per kelompok mata pelajaran (Wajib, Peminatan, Muatan Lokal).

#### 4. Tugas & Modul Ajar LMS (`/portal/siswa/tugas`, `/portal/siswa/modul`)
- **Modul Ajar**: Folder daftar unduhan bahan tayang, e-book, dan rekaman materi per mata pelajaran.
- **Tugas Siswa**: Daftar tugas aktif, tab tugas telah selesai dinilai.
- **Form Kumpul Tugas**: Unggah berkas dokumen (PDF/DOCX/ZIP) maksimal 25 MB dengan progres bar atau masukkan tautan Google Drive / YouTube link; riwayat revisi dan catatan guru.

#### 5. E-Rapor & Progress Report Siswa (`/portal/siswa/rapor`)
- Pratinjau digital laporan perkembangan belajar bulanan dan rapor semester.
- Viewer PDF interaktif di browser dan tombol unduh PDF resmi ber-QR verification.

#### 6. Catatan Poin Karakter & Ekskul Siswa (`/portal/siswa/poin`, `/portal/siswa/ekskul`)
- Catatan transparansi riwayat poin sikap (prestasi / penghargaan dan catatan pembinaan).
- Direktori kegiatan ekstrakurikuler & status keikutsertaan.

#### 7. Bimbingan Konseling & Layanan Siswa (`/portal/siswa/bk`)
- Formulir booking janji temu konseling tatap muka dengan guru BK (privat & terenkripsi).
- Tautan pengaduan perundungan / whistleblowing anonim (modul GUEST).

---

### 4.4 Spesifikasi Layar Web Portal Orang Tua (`/portal/ortu/*`)

Dirancang untuk wali murid melalui peramban PC/Laptop/Tablet:

#### 1. Multi-Anak Switcher & Dashboard Orang Tua Web (`/portal/ortu`)
- **Selector Bar Multi-Anak (Top Header)**:
  - Kartu pemilih anak aktif: Avatar foto siswa, Nama Lengkap, NISN, Rombel Kelas, Wali Kelas.
  - Jika orang tua memiliki >1 anak, dapat beralih anak dalam 1 klik tanpa memutus sesi login.
- **Ringkasan Kehadiran Hari Ini**: Status tap mesin gerbang (Jam datang, jam pulang) dan status kehadiran di kelas.
- **Jadwal Pelajaran Anak Hari Ini**: Menampilkan mata pelajaran anak yang sedang berlangsung.
- **Widget Nilai & Rapor Terbaru**: Pengumuman penerbitan E-Progress Report bulanan terbaru dengan tombol unduh langsung.
- **Pengumuman & Agenda Pertemuan Orang Tua**: Undangan rapat komite sekolah / pembagian rapor.

#### 2. Riwayat Presensi & Log Gerbang Anak (`/portal/ortu/kehadiran`)
- Kalender bulanan berukuran lebar dengan warna indikator per tanggal (Hijau: Hadir, Biru: Izin, Kuning: Sakit, Merah: Alpa).
- Rekapitulasi persentase kehadiran semester berjalan.

#### 3. Pengajuan Izin & Sakit via Web (`/portal/ortu/izin`)
- **Tabel Riwayat Pengajuan Izin**: Tanggal, jenis izin, alasan, lampiran surat dokter, dan badge status persetujuan (*Menunggu Konfirmasi*, *Disetujui Walas*, *Ditolak*).
- **Dialog Form Pengajuan Izin Baru**:
  - Rentang tanggal ketidakhadiran (Date Range Picker).
  - Pilihan Jenis: *Sakit* / *Izin Kepentingan Keluarga*.
  - Kotak catatan keterangan.
  - Komponen File Uploader (foto surat dokter dari RS/Klinik atau surat permohonan tertulis bertanda tangan orang tua, format PDF/JPG/PNG).

#### 4. E-Progress Report & E-Rapor Semester Anak (`/portal/ortu/rapor`)
- Grafik capaian kompetensi per mata pelajaran anak.
- Arsip lengkap E-Progress Report bulanan dan E-Rapor semester berformat PDF resmi yang telah disahkan kepala sekolah, lengkap dengan kode QR verifikasi publik.

#### 5. Catatan Poin Karakter & Prestasi Anak (`/portal/ortu/poin`)
- Buku catatan poin disiplin dan prestasi anak di sekolah.
- Indikator peringatan dini transparan mengenai ambang batas pemanggilan orang tua.

#### 6. Informasi Administrasi & Biaya Sekolah (Informatif) (`/portal/ortu/keuangan`)
- Halaman catatan transparansi biaya pendidikan anak (SPP bulanan, uang kegiatan, study trip) yang tercatat di bagian Tata Usaha sekolah.
- Bersifat catatan informatif tanpa modul transaksi payment gateway.

#### 7. Janji Temu Konseling & Komunikasi Guru BK (`/portal/ortu/bk`)
- Formulir permohonan konsultasi pendampingan belajar atau bimbingan psikologis anak bersama Guru BK atau Wali Kelas.

---

## 5. SPESIFIKASI MOBILE PORTAL ANDROID (JETPACK COMPOSE)

### 5.1 Navigasi Shell Mobile
Aplikasi Android Native SISTLABUM menggunakan arsitektur Single-Activity (`MainActivity`) dengan Jetpack Compose Navigation:
- **Role Shell Ortu**: Bottom navigation (Beranda · Akademik · Izin · Pesan · Akun)
- **Role Shell Guru**: Bottom navigation (Beranda · Jadwal · Nilai · Pengumuman · Akun)
- **Role Shell Siswa**: Bottom navigation (Beranda · Jadwal · Ujian · Nilai · Akun)
- **CBT Fullscreen Mode**: Layar ujian independen tanpa bottom nav, dengan `FLAG_SECURE` aktif.

---

### 5.2 Rincian 36 Layar Mobile

#### KELOMPOK 1: UMUM & AUTENTIKASI (9 LAYAR)
1. **`SplashScreen`**: Animasi logo Labschool, pengecekan token tersimpan di Android Keystore, auto-route ke dashboard role atau layar login.
2. **`LoginScreen`**: Input Identifier (NIS/NIP/Username/Email) + Password (toggle tampil/sembunyi), tombol login biometrik (Fingerprint).
3. **`ForgotPasswordScreen`**: Input email/NIS terdaftar, kirim instruksi reset token 15 menit.
4. **`ChangePasswordScreen`**: Wajib saat login pertama (`mustChangePassword == true`). Input sandi lama, sandi baru min 8 karakter, konfirmasi.
5. **`RoleSelectionScreen`**: Tampil jika user memiliki peran ganda (contoh: Guru sekaligus Orang Tua). Kartu pilihan peran yang rapi.
6. **`NotificationInboxScreen`**: Daftar notifikasi push (Akademik, Ujian, Pengumuman), tanda belum dibaca, tombol "Tandai Semua Dibaca", deep link router.
7. **`AnnouncementListScreen`**: Tab filter (Semua, Sekolah, Kelas), daftar kartu berita dengan thumbnail gambar terkompresi.
8. **`AnnouncementDetailScreen`**: Konten artikel lengkap, tanggal, penulis, tombol unduh berkas lampiran.
9. **`AcademicCalendarScreen`**: Kalender bulanan bertitik warna agenda, daftar kegiatan akademik di bawah kalender.
10. **`ProfileAndSettingsScreen`**: Profil user, ubah nomor kontak, ganti sandi, daftar sesi perangkat aktif (`auth/sessions`), tombol Logout aman.

#### KELOMPOK 2: ORANG TUA / ORT (8 LAYAR)
11. **`GuardianHomeScreen`**:
    - Header: Dropdown pemilih anak (berganti profil anak instan tanpa login ulang).
    - Kartu Ringkasan Anak: Foto, nama, kelas, NISN.
    - Donut Chart Kehadiran bulan berjalan (Hadir, Izin, Sakit, Alpa).
    - Kartu kutipan catatan pembinaan karakter dari wali kelas.
    - Quick Action: Unduh Rapor Bulanan PDF, Hubungi Walas, Ajukan Izin.
12. **`GuardianAttendanceScreen`**: Kalender kehadiran warna-warni (Hijau/Kuning/Biru/Merah) per tanggal dan rekap sesi mapel yang terlewat.
13. **`GuardianGradesScreen`**: Nilai per mapel anak, nilai rata-rata komponen (Tugas, UH, PTS, PAS), grafik tren capaian belajar anak.
14. **`GuardianProgressReportScreen`**: Daftar arsip E-Progress Report bulanan terbitan sekolah, tombol pratinjau PDF dan tombol bagikan PDF.
15. **`GuardianSemesterReportScreen`**: Daftar E-Rapor semester resmi (Kurikulum Merdeka), nilai capaian, status kenaikan kelas, unduh PDF resmi.
16. **`GuardianLeaveRequestListScreen`**: Riwayat pengajuan izin/sakit anak beserta status verifikasi wali kelas (*Menunggu*, *Disetujui*, *Ditolak*).
17. **`GuardianLeaveRequestCreateScreen`**: Form permohonan izin (Tanggal mulai-selesai, jenis sakit/izin, alasan, unggah foto surat via kamera/galeri terkompresi). Didukung antrean offline.
18. **`GuardianPointsScreen`**: Ringkasan akumulasi poin prestasi dan poin pelanggaran disiplin anak beserta catatan tindak lanjut.
19. **`GuardianContactTeacherScreen`**: Kartu kontak Wali Kelas & Guru BK anak. Tombol chat WhatsApp resmi dan tombol tiket pesan internal aplikasi.

#### KELOMPOK 3: GURU / GRU (8 LAYAR)
20. **`TeacherHomeScreen`**: Kartu kelas mengajar hari ini dengan tombol cepat "Jurnal & Presensi", badge antrean koreksi esai CBT, bar progres pengisian nilai bulanan, tombol booking ruang lab & lapor kerusakan.
21. **`TeacherScheduleScreen`**: Jadwal mengajar harian & mingguan (Jam ke-N, jam mulai-selesai, kelas, ruang, status jurnal selesai/belum).
22. **`TeacherAttendanceSessionScreen`**:
    - Form jurnal mengajar (topik bahasan, catatan kelas, tatap muka/daring).
    - Quick Tap Presensi Siswa: Daftar 36 siswa kelas (default semua Hadir; tap sekali untuk ganti Sakit/Izin/Alpa; tahan untuk catatan). Selesai < 60 detik. Antrean offline sinkronisasi otomatis.
23. **`TeacherGradeEntryScreen`**: Filter kelas & mapel & komponen nilai, tabel input angka numerik cepat dengan auto-pindah kursor ke siswa berikutnya. Peringatan jika periode terkunci.
24. **`TeacherHomeroomCharacterNoteScreen`**: Khusus Wali Kelas: Daftar siswa perwalian, form penilaian kepribadian bulanan untuk progress report.
25. **`TeacherLeaveApprovalScreen`**: Khusus Wali Kelas: Daftar surat izin/sakit siswa, pratinjau foto surat dokter, tombol aksi Setujui / Tolak + catatan.
26. **`TeacherCbtGradingListScreen`**: Daftar ujian dengan butir uraian/esai yang butuh koreksi guru.
27. **`TeacherCbtEssayGradeScreen`**: Meja koreksi esai: Pertanyaan, kunci rubrik, jawaban teks siswa, input skor angka, feedback guru.
28. **`TeacherFacilityBookingScreen`**: Form peminjaman ruang lab/alat sarpras dan pelaporan tiket kerusakan fasilitas sekolah.

#### KELOMPOK 4: SISWA / SIS (5 LAYAR)
29. **`StudentHomeScreen`**: Banner dinamis "Ujian Berlangsung Hari Ini" dengan tombol cepat input token, jadwal pelajaran hari ini & besok, rekap absensi bulan berjalan, menu pintasan kartu pelajar digital & unduh rapor.
30. **`StudentScheduleScreen`**: Jadwal pelajaran harian dan mingguan kelas siswa.
31. **`StudentDigitalCardScreen`**: Tab 1: Kartu Pelajar Digital ber-QR Code untuk absensi gerbang/perpustakaan. Tab 2: Kartu Peserta Ujian (ruang, sesi, jadwal mapel).
32. **`StudentGradesScreen`**: Buku nilai siswa, riwayat nilai tugas/kuis, tombol unduh progress report dan rapor semester.
33. **`StudentAssignmentsScreen`**: Daftar tugas belajar (Belum Dikumpulkan, Selesai, Terlambat), form upload pengumpulan berkas/foto tugas.

#### KELOMPOK 5: CBT MOBILE / CBT (6 LAYAR)
34. **`CbtTokenEntryScreen`**: Informasi judul ujian, mapel, durasi, pengawas. Input token ruang ujian (6 huruf kapital).
35. **`CbtRulesAndInstructionScreen`**: Tata tertib ujian, peringatan anti-cheat, checkbox persetujuan, tombol "Mulai Ujian".
36. **`CbtExamWorkspaceScreen`**:
    - Timer bar sinkronisasi waktu server mutlak.
    - Indikator status penyimpanan (autosave atomic).
    - Soal teks, rumus LaTeX, media audio/gambar zoomable.
    - Input dinamis: PG (A–E), PG Kompleks (multi-checkbox), Benar/Salah, Menjodohkan, Isian Singkat, Uraian.
    - Tombol navigasi: Sebelumnya, Ragu-ragu (kuning), Selanjutnya.
    - BottomSheet Grid Navigator nomor soal (Abu/Hijau/Kuning).
    - Anti-Cheat Service: Deteksi keluar aplikasi, blur, screenshot protection (`FLAG_SECURE`).
37. **`CbtReviewAndSubmitScreen`**: Ringkasan soal (Terjawab, Ragu-ragu, Kosong), peringatan konfirmasi, tombol merah kirim ujian (*Finish*).
38. **`CbtSessionLockedScreen`**: Layar darurat merah jika terdeteksi pelanggaran melebihi batas. Instruksi menghubungi pengawas ruang.
39. **`CbtExamResultScreen`**: Tampilan ucapan selesai, skor ujian (jika diatur rilis langsung), tombol kembali ke beranda siswa.

---

## 6. PRINSIP STATE & INTERAKSI UI

Setiap halaman dan komponen data wajib memiliki **4 State Visual Wajib**:
1. **Loading State**:
   - Web: Shimmer skeleton loading (`Skeleton` dari shadcn/ui) yang menyerupai layout asli.
   - Mobile: Shimmer placeholder pada kartu data; tombol menampilkan `CircularProgressIndicator` saat proses kirim.
2. **Empty State**:
   - Ilustrasi vektor yang ramah, pesan penjelas ("Belum ada data nilai untuk semester ini"), dan tombol aksi penuntun bila relevan.
3. **Error State**:
   - Ikon peringatan, pesan berbahasa Indonesia yang jelas sesuai kode error backend kanonis (`VALIDATION_FAILED`, `FORBIDDEN`, `RATE_LIMITED`), dan tombol **"Coba Lagi"** (*Retry*).
4. **Offline / Syncing State (Khusus Mobile)**:
   - Banner kuning halus di bagian atas: *"Bekerja secara offline. Aksi Anda tersimpan di perangkat dan akan dikirim otomatis saat tersambung kembali."*
   - Status ikon awan jam kecil pada data yang belum tersinkronisasi.
