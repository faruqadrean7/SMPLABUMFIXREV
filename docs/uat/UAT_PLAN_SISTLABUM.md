# Rencana dan Matriks User Acceptance Testing (UAT) SISTLABUM
**Portal Sekolah Satu Pintu — SMP Laboratorium Universitas Negeri Malang**
*Dokumen Rencana Pengujian Penerimaan Pengguna (User Acceptance Testing / UAT)*

---

| Dokumen | Versi | Status | Tanggal Penyusunan | Single Source of Truth |
|---|:---:|:---:|:---:|---|
| **UAT-PLAN-01** | **1.0** | **Ready for Review** | 15 Oktober 2026 | `13_MASTER_MODULES.md`, `10_TESTING_QA.md` |

---

## 1. Pendahuluan & Tujuan Dokumen

Dokumen ini disusun sebagai panduan resmi pelaksanaan **User Acceptance Testing (UAT)** untuk sistem informasi terintegrasi **Portal Sekolah Satu Pintu (SISTLABUM)** pada lingkungan **Staging**. Dokumen ini menjembatani hasil pengujian otomatis (*automated tests*) dengan verifikasi fungsional nyata oleh pemangku kepentingan sekolah: Kepala Sekolah, Panitia PPDB, Tim Kurikulum, Guru Mata Pelajaran, Wali Kelas, Guru BK, Staf Tata Usaha & Sarpras, Siswa, Orang Tua/Wali Murid, serta Tamu Publik.

### Prinsip Utama UAT
1. **Automated Test $\neq$ UAT Lulus**: Lulusnya 1.082 pengujian PHPUnit backend dan 719 pengujian Vitest frontend adalah prasyarat teknis (*Entry Criteria*), bukan pengganti penerimaan pengguna akhir (*Acceptance Sign-off*).
2. **Strict Data Safety**: UAT hanya menggunakan data sintetis di lingkungan Staging. Data pribadi nyata dilarang keras dimasukkan ke sistem uji.
3. **Zero Tolerance terhadap Kebocoran Data (IDOR & Privacy)**: Skenario akses data anak, privasi konseling BK, dan dokumen berkas wajib diverifikasi secara manual lintas peran.
4. **Uji Negatif & Ketahanan Sistem**: Setiap alur wajib menguji penolakan akses ilegal, data tidak valid, pemutusan jaringan, kegagalan storage, dan pencegahan duplikasi data.

---

## 2. Kriteria Masuk dan Keluar UAT (Entry & Exit Criteria)

### A. Kriteria Masuk UAT (Entry Criteria)
UAT **hanya boleh dimulai** apabila seluruh kondisi berikut terpenuhi:
- [x] **Backend Automation**: 100% tes fitur backend lulus (`php artisan test` 1.082 tests, 5.921 assertions).
- [x] **Frontend Automation**: 100% tes komponen web lulus (`npx vitest run` 719 tests across 60 test suites).
- [x] **Compiler & Linter**: TypeScript typecheck 0 error, Next.js linting 0 error, production build 90/90 rute lulus.
- [ ] **Staging Infrastructure**: Backend API dan Frontend Web telah ter-deploy pada domain staging HTTPS terisolasi (`https://staging.smp-labum.sch.id`).
- [ ] **Background Services**: Laravel Queue Worker daemon (`php artisan queue:work`) dan OS Cron Scheduler (`schedule:run`) aktif berjalan di staging.
- [ ] **Storage S3/MinIO**: Bucket storage staging siap dengan pemisahan izin: publik untuk gambar cover/aset umum, privat untuk dokumen berkas PPDB, slip gaji, dan BK.
- [ ] **Synthetic Test Seed**: Database staging telah di-seed dengan dataset sintetis terstandarisasi.
- [ ] **Daftar Akun Penguji**: Akun dan kredensial uji sintetis telah didistribusikan kepada tim penguji yang ditunjuk.

### B. Kriteria Keluar UAT (Exit Criteria / Definition of Acceptance)
Sistem dinyatakan **lulus UAT dan siap dirilis ke produksi** apabila:
1. **100% skenario P0 (Critical Path)** berstatus **LULUS (PASS)** tanpa pengecualian.
2. Minimal **95% skenario P1** berstatus **LULUS**, dan sisa 5% memiliki *workaround* operasional yang disetujui tertulis oleh Kepala Sekolah.
3. **Nol Cacat Kritis (Zero S1 Defects)**: Tidak ada kerentanan keamanan (IDOR, kebocoran data BK/nilai/CBT, SQLi/XSS), sistem crash, atau kebocoran kunci jawaban ujian.
4. **Nol Cacat Utama (Zero S2 Defects)**: Tidak ada pemblokiran alur utama PPDB, presensi, rapor, atau CBT.
5. **Berita Acara UAT**: Ditandatangani secara resmi oleh perwakilan pemangku kepentingan sekolah (Kepala Sekolah, Panitia PPDB, Koordinator Kurikulum, Perwakilan Guru, Perwakilan Orang Tua).

---

## 3. Matriks Pengujian 14 Modul Lengkap

Keterangan Status Bukti Awal:
- `automated-tested`: Logika dan kontrak telah teruji otomatis via unit/feature test.
- `partially-tested`: Sebagian endpoint/UI teruji otomatis, interaksi browser nyata belum terbukti.
- `manual-UAT-required`: Memerlukan interaksi manual end-user di browser untuk verifikasi penerimaan.
- `unverified`: Belum memiliki bukti eksekusi E2E Playwright atau pengujian manusia.

---

### Modul 1: AUTH (Autentikasi & Hak Akses)

| ID Skenario | Fitur Master | Aktor / Role | Prasyarat & Data Uji | Langkah Pengujian | Hasil yang Diharapkan | Bukti yang Dikumpulkan | Status Bukti Awal |
|---|---|---|---|---|---|---|:---:|
| **UAT-AUTH-01** | `BE-AUTH-01`<br>`WEB-AUTH-01` | Seluruh Pengguna | Akun aktif: Guru, Siswa, Ortu, Staf, Admin | 1. Buka `/masuk`<br>2. Masukkan NIP/NISN/Email & password valid<br>3. Klik Masuk | Berhasil login, menerima token JWT/Sanctum, diarahkan ke dashboard portal sesuai peran masing-masing. | Screenshot dashboard, response headers HTTP 200, JWT token tersimpan di cookie HTTP-only/storage aman. | `automated-tested` & `manual-UAT-required` |
| **UAT-AUTH-02** | `BE-AUTH-01`<br>`WEB-AUTH-01` | Pengguna Umum | Kredensial tidak valid | 1. Masukkan email benar, password salah 3 kali<br>2. Masukkan akun terblokir | Muncul pesan error bahasa Indonesia yang ramah ("Kredensial tidak cocok"), akun terkunci sementara jika melewati batas rate limit. | Screenshot notifikasi error UI, HTTP 422/429 response. | `automated-tested` & `manual-UAT-required` |
| **UAT-AUTH-03** | `BE-AUTH-04` | Guru / Staf | Akun dengan sesi aktif di 2 perangkat berbeda | 1. Login di browser A<br>2. Login di browser B<br>3. Buka Pengaturan Sesi di browser A, klik "Cabut Sesi Perangkat B" | Sesi perangkat B langsung terputus; request berikutnya di browser B diarahkan ke halaman login (HTTP 401). | Screenshot daftar sesi aktif, log audit pencabutan sesi, redirected screen browser B. | `automated-tested` & `manual-UAT-required` |
| **UAT-AUTH-04** | `BE-AUTH-03`<br>`ADM-SET-01` | Guru Biasa | Akun Guru tanpa role Admin | 1. Login sebagai Guru<br>2. Coba akses langsung URL `/admin/settings/roles` atau kirim request ke `GET /api/v1/roles` | Ditampilkan halaman *Access Denied* (403 Forbidden), backend menolak dengan error kanonis `E403_FORBIDDEN`. Tidak ada data bocor. | Screenshot halaman Access Denied, console network 403. | `automated-tested` & `manual-UAT-required` |
| **UAT-AUTH-05** | `BE-AUTH-05` | Admin Sistem | Aksi login, ubah nilai, reset password | 1. Lakukan aksi kritikal di sistem<br>2. Buka menu `/admin/settings/audit-logs` | Aksi tercatat lengkap dengan timestamp, IP address, user_id, action, dan payload diff tanpa memuat password mentah. | Screenshot tabel audit log, detail rekaman audit JSON. | `automated-tested` & `manual-UAT-required` |

---

### Modul 2: MST (Master Data)

| ID Skenario | Fitur Master | Aktor / Role | Prasyarat & Data Uji | Langkah Pengujian | Hasil yang Diharapkan | Bukti yang Dikumpulkan | Status Bukti Awal |
|---|---|---|---|---|---|---|:---:|
| **UAT-MST-01** | `BE-MST-01`<br>`ADM-MST-01` | Admin TU | Tahun ajaran baru 2026/2027 Ganjil | 1. Buka `/admin/master/academic-years`<br>2. Tambah tahun ajaran & aktifkan semester Ganjil | Tahun ajaran aktif berubah, seluruh modul otomatis merujuk ke tahun ajaran baru; tahun ajaran lama terkunci. | Screenshot status tahun ajaran aktif, data semester aktif di portal. | `automated-tested` & `manual-UAT-required` |
| **UAT-MST-02** | `BE-MST-02`<br>`ADM-MST-02` | Admin TU | Template Excel impor 10 siswa baru | 1. Buka `/admin/master/students`<br>2. Unggah file Excel impor data siswa sintetis<br>3. Periksa NISN ganda | 10 siswa masuk ke database; baris yang memiliki NISN ganda ditolak dengan laporan baris error spesifik. | Screenshot preview impor, log error validasi NISN. | `automated-tested` & `manual-UAT-required` |
| **UAT-MST-03** | `BE-MST-03`<br>`ADM-MST-03` | Admin TU & Guru | Slip gaji guru bulan berjalan | 1. Admin TU unggah slip gaji pada profil guru<br>2. Guru login ke `/portal/guru/slip-gaji`<br>3. Klik unduh slip gaji | Guru melihat dan mengunduh slip gajinya sendiri. Guru lain atau siswa dilarang mengunduh slip gaji tersebut (HTTP 403). | PDF slip gaji terunduh, pengujian cross-user IDOR 403. | `automated-tested` & `manual-UAT-required` |
| **UAT-MST-04** | `BE-MST-05`<br>`ADM-MST-05` | Kurikulum | Rombel Kelas 7-A & 7-B | 1. Buka `/admin/master/classes`<br>2. Buat rombel, assign wali kelas, masukkan 30 siswa<br>3. Pindahkan 1 siswa dari 7-A ke 7-B | Rombel terbentuk; perpindahan siswa tercatat dalam riwayat transfer kelas dan daftar presensi terbarui otomatis. | Screenshot roster kelas 7-A dan 7-B sebelum/sesudah mutasi. | `automated-tested` & `manual-UAT-required` |
| **UAT-MST-05** | `BE-MST-06`<br>`ADM-MST-06` | Kurikulum | 5 Mata Pelajaran & Plotting Guru | 1. Buka `/admin/master/subjects`<br>2. Tambah mapel Matematika<br>3. Plotting guru Matematika ke kelas 7-A | Teaching assignment tersimpan; guru Matematika kini memiliki hak akses input nilai dan presensi di kelas 7-A. | Screenshot teaching assignment, portal guru menampilkan kelas 7-A. | `automated-tested` & `manual-UAT-required` |

---

### Modul 3: CMS (Konten, Profil Sekolah, & Konfigurasi Situs)

| ID Skenario | Fitur Master | Aktor / Role | Prasyarat & Data Uji | Langkah Pengujian | Hasil yang Diharapkan | Bukti yang Dikumpulkan | Status Bukti Awal |
|---|---|---|---|---|---|---|:---:|
| **UAT-CMS-01** | `BE-CMS-01`<br>`ADM-CMS-01`<br>`WEB-PRF-01` | Admin CMS & Publik | Perubahan Visi Misi & Sambutan Kepala Sekolah | 1. Buka `/admin/cms`, tab Pengaturan Situs<br>2. Ubah sambutan Kepala Sekolah Bapak Tamyis Suliantoro, M.Pd.<br>3. Simpan<br>4. Buka halaman publik `/profil` | Konten `/profil` dan `/` langsung menampilkan teks sambutan baru tanpa kegagalan formatting atau teks terpotong. | Screenshot form admin CMS, screenshot halaman `/profil` publik. | `automated-tested` & `manual-UAT-required` |
| **UAT-CMS-02** | `BE-CMS-02`<br>`ADM-CMS-01`<br>`WEB-NEWS-01` | Admin CMS & Publik | Berita baru + Unggah Cover Image (INT-04/05) | 1. Buka `/admin/cms`, tab Berita<br>2. Klik Tambah Berita, unggah gambar sampul (JPG/PNG < 2MB)<br>3. Tulis konten artikel, isi tag, terbitkan<br>4. Buka `/berita` dan `/berita/[slug]` | Artikel terbit dengan gambar sampul presisi, waktu baca terhitung, dan slug URL SEO-friendly. | Screenshot cover banner di `/berita/[slug]`, payload HTTP upload presign. | `automated-tested` & `manual-UAT-required` |
| **UAT-CMS-03** | `BE-CMS-03`<br>`ADM-CMS-02`<br>`WEB-GAL-01` | Admin CMS & Publik | Album foto kegiatan (INT-01/03) | 1. Buka `/admin/cms`, tab Galeri<br>2. Buat album "Peringatan Hari Guru"<br>3. Unggah 5 foto via file-upload modal<br>4. Buka `/galeri` | Album foto tampil dengan thumbnail sampul valid (bukan placeholder kosong), modal lightbox dapat dibuka satu per satu. | Screenshot lightbox galeri, URL foto berawalan `/storage/` atau signed S3. | `automated-tested` & `manual-UAT-required` |
| **UAT-CMS-04** | `BE-CMS-01`<br>`INT-06-FIX` | Admin CMS | Simpan site settings dengan string kosong pada invariant | 1. Kosongkan field alamat atau nama sekolah pada form CMS<br>2. Simpan pengaturan<br>3. Panggil API `GET /api/v1/public/site` | Sistem menolak data rusak atau secara cerdas menerapkan fallback identitas resmi "SMP Laboratorium UM" tanpa menampilkan teks kosong/undefined. | Response JSON `/api/v1/public/site`, rendering footer publik. | `automated-tested` & `manual-UAT-required` |

---

### Modul 4: PPDB (Penerimaan Peserta Didik Baru)

| ID Skenario | Fitur Master | Aktor / Role | Prasyarat & Data Uji | Langkah Pengujian | Hasil yang Diharapkan | Bukti yang Dikumpulkan | Status Bukti Awal |
|---|---|---|---|---|---|---|:---:|
| **UAT-PPDB-01** | `BE-PPDB-01`<br>`ADM-PPDB-01` | Panitia PPDB | Gelombang 1 Jalur Prestasi (Kuota: 50) | 1. Buka `/admin/ppdb/waves`<br>2. Buat Gelombang 1, set tanggal buka/tutup, jalur Prestasi & Zonasi, syarat KK & Rapor | Gelombang aktif muncul di halaman publik `/ppdb` dan tombol "Daftar Sekarang" dapat diklik. | Screenshot tabel gelombang admin, kartu gelombang di `/ppdb`. | `automated-tested` & `manual-UAT-required` |
| **UAT-PPDB-02** | `BE-PPDB-02`<br>`WEB-PPDB-01` | Calon Siswa | Nomor WhatsApp & Email sintetis | 1. Buka `/ppdb/daftar`<br>2. Masukkan NIK, nama, WhatsApp<br>3. Terima simulasi OTP di staging<br>4. Verifikasi OTP | Akun calon siswa teraktivasi; token sesi pendaftaran aktif dan diarahkan ke form bertahap. | Screenshot halaman OTP, log dispatch SMS/WA/staging OTP. | `automated-tested` & `manual-UAT-required` |
| **UAT-PPDB-03** | `BE-PPDB-03`<br>`BE-PPDB-04`<br>`WEB-PPDB-03` | Calon Siswa | Dokumen PDF Kartu Keluarga & Rapor | 1. Lengkapi Langkah 1 s/d 4 (Data diri, orang tua, nilai, pilihan jalur)<br>2. Unggah berkas KK & Rapor pada Langkah 5<br>3. Submit pendaftaran final | Berkas terunggah ke direktori privat pendaftar; status aplikasi berubah menjadi `SUBMITTED`, pendaftar menerima nomor pendaftaran dan bukti PDF. | PDF bukti pendaftaran, status di dashboard pendaftar. | `automated-tested` & `manual-UAT-required` |
| **UAT-PPDB-04** | `BE-PPDB-05`<br>`ADM-PPDB-02` | Panitia Verifikator | Berkas pendaftar baru | 1. Buka `/admin/ppdb/applicants`<br>2. Buka berkas KK pendaftar<br>3. Klik "Tolak Berkas" dengan catatan "Foto KK buram"<br>4. Login pendaftar: periksa notifikasi revisi berkas | Pendaftar menerima notifikasi penolakan, dapat mengunggah ulang berkas KK revisi; panitia dapat memverifikasi ulang menjadi `VERIFIED`. | Screenshot verifikasi admin, riwayat audit dokumen pendaftar. | `automated-tested` & `manual-UAT-required` |
| **UAT-PPDB-05** | `BE-PPDB-06`<br>`ADM-PPDB-03` | Panitia PPDB | 60 pendaftar terverifikasi (kuota 50) | 1. Jalankan proses pemeringkatan otomatis di `/admin/ppdb/selection`<br>2. Periksa urutan ranking berdasarkan bobot nilai & zonasi<br>3. Tetapkan status `ACCEPTED` (ranking 1-50) dan `WAITLIST` (ranking 51-60) | Pemeringkatan konsisten sesuai formula PRD; tidak ada calon siswa di luar kuota yang berstatus lolos tanpa override panitia. | Tabel ranking seleksi, log override keputusan panitia. | `automated-tested` & `manual-UAT-required` |
| **UAT-PPDB-06** | `BE-PPDB-07`<br>`BE-PPDB-08`<br>`WEB-PPDB-07` | Calon Siswa & Panitia | Calon siswa yang dinyatakan lolos | 1. Cek hasil pengumuman di `/ppdb` menggunakan nomor pendaftaran & tanggal lahir<br>2. Klik tombol "Daftar Ulang Mandiri"<br>3. Panitia klik "Konversi ke Siswa Baru" | Status pendaftar berubah menjadi `ENROLLED`; akun siswa resmi terbentuk pada master data siswa (`MST-02`) lengkap dengan NIS baru. | Screenshot surat kelulusan, rekaman siswa baru di `/admin/master/students`. | `automated-tested` & `manual-UAT-required` |

---

### Modul 5: GUEST (Buku Tamu Digital & Aspirasi Whistleblowing)

| ID Skenario | Fitur Master | Aktor / Role | Prasyarat & Data Uji | Langkah Pengujian | Hasil yang Diharapkan | Bukti yang Dikumpulkan | Status Bukti Awal |
|---|---|---|---|---|---|---|:---:|
| **UAT-GST-01** | `BE-GUEST-01`<br>`BE-GUEST-03`<br>`WEB-GUEST-01` | Tamu Dinas (Kiosk) | Formulir Kiosk Buku Tamu Publik | 1. Buka antarmuka buku tamu di `/kontak` atau kiosk tablet<br>2. Isi nama, instansi, tujuan kunjungan, foto & tanda tangan digital pada canvas<br>3. Submit | Data kunjungan tersimpan; barcode / ID kunjungan terbit; notifikasi kunjungan terkirim ke staf penerima tamu. | Screenshot tanda tangan digital tersimpan, baris baru di `/admin/tickets` atau tamu. | `automated-tested` & `manual-UAT-required` |
| **UAT-GST-02** | `BE-GUEST-02`<br>`WEB-ASP-01` | Masyarakat / Pelapor | Formulir Aspirasi Anonim Whistleblowing | 1. Buka menu Pengaduan & Aspirasi di `/kontak`<br>2. Pilih kategori "Fasilitas Belajar", pilih mode "Anonim", isi deskripsi & unggah bukti foto<br>3. Simpan kode tiket pengaduan unik (misal: `ASP-202610-0982`) | Pengaduan tersimpan tanpa merekam identitas IP/nama pelapor; status awal `PENDING`. | Kode tiket unik, screenshot receipt pengaduan. | `automated-tested` & `manual-UAT-required` |
| **UAT-GST-03** | `BE-GUEST-02`<br>`ADM-GUEST-02` | Petugas Humas & Pelapor | Tiket `ASP-202610-0982` | 1. Petugas buka `/admin/public-relations`<br>2. Ubah status menjadi `IN_PROGRESS` dan tambahkan tanggapan tindak lanjut<br>3. Pelapor memasukkan kode tiket pada halaman pelacakan publik | Pelapor dapat memantau respons sekolah secara transparan tanpa perlu login. | Screenshot status tindak lanjut pada halaman pelacakan publik. | `automated-tested` & `manual-UAT-required` |

---

### Modul 6: ACD (Akademik, Jadwal, Presensi, & LMS Ringan)

| ID Skenario | Fitur Master | Aktor / Role | Prasyarat & Data Uji | Langkah Pengujian | Hasil yang Diharapkan | Bukti yang Dikumpulkan | Status Bukti Awal |
|---|---|---|---|---|---|---|:---:|
| **UAT-ACD-01** | `BE-ACD-01`<br>`ADM-ACD-01` | Kurikulum | 2 Guru di ruang yang sama pada jam ke-3 | 1. Buka `/admin/academic/schedules`<br>2. Tambah jadwal Guru A di Ruang Lab Komputer jam 08:40<br>3. Coba assign Guru B di ruang yang sama pada jam yang sama | Sistem secara instan menolak penyimpanan jadwal dengan pesan bentrok ruang (*conflict detected*). | Modal deteksi bentrok jadwal, respon HTTP 422 Conflict. | `automated-tested` & `manual-UAT-required` |
| **UAT-ACD-02** | `BE-ACD-02`<br>`BE-ACD-03`<br>`MOB-GRU-02` | Guru Mata Pelajaran | Jam mengajar aktif di kelas 7-A | 1. Buka `/portal/guru/jurnal`<br>2. Isi materi pembahasan & capaian pembelajaran<br>3. Buka `/portal/guru/presensi`<br>4. Tandai 28 siswa Hadir, 1 Sakit, 1 Alpa<br>5. Simpan presensi sesi | Jurnal KBM tersimpan; rekapitulasi kehadiran kelas 7-A terbarui seketika; orang tua siswa yang Sakit/Alpa menerima notifikasi status kehadiran. | Screenshot jurnal terisi, riwayat presensi harian siswa. | `automated-tested` & `manual-UAT-required` |
| **UAT-ACD-03** | `BE-ACD-04`<br>`MOB-ORT-06` | Orang Tua & Wali Kelas | Siswa sakit tidak dapat hadir | 1. Orang tua buka `/portal/ortu`, pilih menu Izin/Sakit<br>2. Masukkan tanggal, alasan, dan unggah surat dokter<br>3. Wali Kelas login ke `/portal/guru/wali-kelas`<br>4. Tinjau surat dokter dan klik "Setujui Izin" | Status kehadiran siswa pada hari tersebut otomatis berubah dari Alpa menjadi Sakit (S) pada rekapitulasi absensi sekolah. | Screenshot persetujuan izin walas, rekap absensi berstatus Sakit. | `automated-tested` & `manual-UAT-required` |
| **UAT-ACD-04** | `BE-ACD-05`<br>`MOB-GRU-06` | Guru & Staf | Presensi mandiri berbasis geolokasi | 1. Guru buka `/portal/guru/presensi-saya` di smartphone<br>2. Klik Check-in (berada di dalam radius geofence sekolah)<br>3. Coba check-in di luar radius sekolah | Check-in berhasil jika dalam radius sekolah; ditolak dengan pesan "Anda berada di luar area presensi sekolah" jika di luar radius. | Screenshot koordinat GPS, status check-in sukses vs gagal. | `automated-tested` & `manual-UAT-required` |
| **UAT-ACD-05** | `BE-ACD-07`<br>`MOB-SIS-02` | Guru & Siswa | Tugas LMS Matematika Bab 1 | 1. Guru buat tugas di `/portal/guru/tugas`<br>2. Siswa buka `/portal/siswa`, unggah file PDF jawaban<br>3. Guru periksa jawaban, beri nilai 88 dan catatan | Pengumpulan tugas tercatat tepat waktu; guru dapat mengunduh berkas; siswa menerima nilai dan feedback guru. | Screenshot daftar pengumpulan tugas, nilai tugas tampil di akun siswa. | `automated-tested` & `manual-UAT-required` |

---

### Modul 7: GRD (Buku Nilai, Catatan Karakter, & E-Rapor)

| ID Skenario | Fitur Master | Aktor / Role | Prasyarat & Data Uji | Langkah Pengujian | Hasil yang Diharapkan | Bukti yang Dikumpulkan | Status Bukti Awal |
|---|---|---|---|---|---|---|:---:|
| **UAT-GRD-01** | `BE-GRD-01`<br>`ADM-GRD-01` | Guru Mata Pelajaran | Spreadsheet nilai 30 siswa kelas 7-A | 1. Buka `/portal/guru/nilai`<br>2. Buka matriks input nilai (Tugas 1, Formatif, Sumatif)<br>3. Input massal via spreadsheet editor atau copy-paste angka<br>4. Simpan nilai | Akumulasi nilai akhir (NA) terhitung otomatis berdasarkan bobot KKTP kurikulum; nilai tersimpan aman di database. | Screenshot matriks buku nilai, perhitungan nilai akhir otomatis. | `automated-tested` & `manual-UAT-required` |
| **UAT-GRD-02** | `BE-GRD-02`<br>`ADM-GRD-02` | Wali Kelas | Evaluasi karakter bulanan Profil Pelajar Pancasila | 1. Buka `/portal/guru/wali-kelas`, tab Catatan Karakter<br>2. Pilih dimensi karakter (Gotong Royong, Mandiri, Bernalar Kritis)<br>3. Pilih opsi capaian & simpan catatan narasi | Catatan tersimpan dan otomatis menjadi bahan deskripsi rapor semester siswa terkait. | Screenshot matriks catatan karakter kelas 7-A. | `automated-tested` & `manual-UAT-required` |
| **UAT-GRD-03** | `BE-GRD-03`<br>`BE-GRD-04`<br>`ADM-GRD-03`<br>`ADM-GRD-04` | Wali Kelas, Kepala Sekolah, & Ortu | Penutupan semester & penerbitan rapor | 1. Wali kelas klik "Generate Draft Rapor Kelas 7-A"<br>2. Kepala sekolah review & lakukan "Bulk Approve Rapor"<br>3. Orang tua buka `/portal/ortu/anak/[id]`, unduh PDF Rapor<br>4. Pindai kode QR verifikasi pada dokumen PDF rapor | Rapor berformat resmi terbit dengan tanda tangan digital & QR token unik; pindaian QR membuka URL publik verifikasi dokumen valid. | Dokumen PDF Rapor resmi, screenshot verifikasi publik `/public/verify/{token}`. | `automated-tested` & `manual-UAT-required` |
| **UAT-GRD-04** | `BE-GRD-04` | Guru Mapel | Periode penilaian terkunci (*locked period*) | 1. Admin kurikulum mengunci periode nilai UTS<br>2. Guru mapel mencoba mengubah nilai siswa pada periode tersebut | Aksi pengubahan nilai ditolak sistem dengan pesan "Periode penilaian telah ditutup oleh Kurikulum". | Error toast UI HTTP 403 / Period Locked. | `automated-tested` & `manual-UAT-required` |

---

### Modul 8: CBT (Ujian Digital Berbasis Komputer & Mobile)

| ID Skenario | Fitur Master | Aktor / Role | Prasyarat & Data Uji | Langkah Pengujian | Hasil yang Diharapkan | Bukti yang Dikumpulkan | Status Bukti Awal |
|---|---|---|---|---|---|---|:---:|
| **UAT-CBT-01** | `BE-CBT-01`<br>`BE-CBT-02`<br>`ADM-CBT-01`<br>`ADM-CBT-02` | Tim Penguji & Guru | Bank soal 40 butir (35 PG + 5 Uraian) | 1. Buka `/admin/cbt/questions`, import soal via Word/Excel<br>2. Buka `/admin/cbt/exams`, buat ujian STS Matematika<br>3. Atur durasi 90 menit, acak soal & opsi jawaban, aktifkan deteksi kecurangan | Jadwal ujian terbentuk, butir soal dan kunci jawaban terpetakan tanpa kebocoran kunci ke API siswa. | Screenshot detail ujian, verifikasi endpoint siswa bebas dari kunci jawaban. | `automated-tested` & `manual-UAT-required` |
| **UAT-CBT-02** | `BE-CBT-03`<br>`ADM-CBT-03` | Pengawas Ujian | 30 Siswa di Ruang Lab 1 | 1. Buka `/admin/cbt/monitor`<br>2. Generate token ruang ujian (6 digit)<br>3. Umumkan token kepada siswa | Token ruang ujian aktif dengan masa berlaku 15 menit dan dapat di-regenerate sewaktu-waktu oleh pengawas. | Screenshot token ruang aktif pada monitor pengawas. | `automated-tested` & `manual-UAT-required` |
| **UAT-CBT-03** | `BE-CBT-04`<br>`MOB-CBT-01`<br>`MOB-CBT-02` | Siswa Peserta | Sesi ujian aktif di Lab Komputer | 1. Siswa buka `/portal/siswa/ujian/[id]`<br>2. Masukkan token ruang ujian & klik "Mulai Ujian"<br>3. Jawab soal 1-10 secara berkala<br>4. Uji simulasi tutup browser / refresh tab | Timer ujian berjalan sinkron; jawaban tersimpan otomatis (*autosave* tiap klik / 20 detik); saat browser dibuka kembali, progres jawaban tidak hilang. | Log autosave jawaban, network payload `/exams/{id}/save-answer`. | `automated-tested` & `manual-UAT-required` |
| **UAT-CBT-04** | `BE-CBT-04`<br>`BE-CBT-06`<br>`MOB-CBT-04` | Siswa & Pengawas | Anti-cheat: Siswa berganti tab / minimize window | 1. Siswa melakukan switch tab browser atau membuka aplikasi lain<br>2. Sistem memicu popup peringatan pelanggaran (maksimal 3 kali)<br>3. Pada pelanggaran ke-3, ujian terkunci otomatis | Monitor pengawas mendeteksi anomali secara real-time; pengawas dapat mereset sesi atau memberi sanksi pembatalan ujian. | Screenshot peringatan pelanggaran di layar siswa & alert di monitor pengawas. | `automated-tested` & `manual-UAT-required` |
| **UAT-CBT-05** | `BE-CBT-05`<br>`BE-CBT-09`<br>`ADM-CBT-05` | Guru Pengoreksi | Sesi ujian selesai | 1. Siswa klik "Selesaikan Ujian"<br>2. Sistem menghitung skor PG otomatis<br>3. Guru membuka modul koreksi uraian & memberi nilai soal nomor 36-40<br>4. Klik "Publikasikan Nilai ke Buku Nilai" | Nilai akhir ujian terakumulasi; siswa di bawah KKTP (<75) otomatis terdaftar pada daftar peserta remedial. | Screenshot item analysis butir soal, transfer nilai ke modul GRD. | `automated-tested` & `manual-UAT-required` |

---

### Modul 9: STD (Kesiswaan, Poin Disiplin, & Ekstrakurikuler)

| ID Skenario | Fitur Master | Aktor / Role | Prasyarat & Data Uji | Langkah Pengujian | Hasil yang Diharapkan | Bukti yang Dikumpulkan | Status Bukti Awal |
|---|---|---|---|---|---|---|:---:|
| **UAT-STD-01** | `BE-STD-01`<br>`ADM-STD-01` | Tim Tata Tertib | Siswa terlambat masuk sekolah (+10 poin pelanggaran) | 1. Buka `/admin/student-affairs/points`<br>2. Cari nama siswa, pilih jenis pelanggaran "Terlambat > 15 Menit"<br>3. Simpan pencatatan poin | Poin akumulasi siswa bertambah; jika melampaui ambang batas SP-1 (misal 50 poin), surat peringatan otomatis digenerate dan dikirim ke orang tua. | Notifikasi poin ke orang tua, rekaman poin di profil siswa. | `automated-tested` & `manual-UAT-required` |
| **UAT-STD-02** | `BE-STD-01`<br>`ADM-STD-01` | Guru Pembina | Siswa Juara 1 OSN Matematika Tingkat Kota (+50 poin prestasi) | 1. Catat prestasi siswa pada menu Prestasi<br>2. Unggah bukti piagam penghargaan | Akumulasi poin prestasi bertambah dan tercatat pada portofolio buku induk siswa. | Piagam terunggah, rekapitulasi poin prestasi siswa. | `automated-tested` & `manual-UAT-required` |
| **UAT-STD-03** | `BE-STD-02`<br>`ADM-STD-02` | Pembina Ekskul & Siswa | Pendaftaran Ekskul Robotik | 1. Siswa mendaftar ekskul melalui portal siswa<br>2. Pembina ekskul menerima pendaftaran & mencatat kehadiran latihan mingguan | Siswa terdaftar pada rombel ekskul; nilai ekskul semesteran otomatis mengalir ke e-rapor. | Screenshot absensi ekskul, daftar anggota ekskul. | `automated-tested` & `manual-UAT-required` |
| **UAT-STD-04** | `BE-STD-04`<br>`MOB-ORT-08` | Kesiswaan & Ortu | Agenda Outing Class ke Laboratorium Sentral UM | 1. Kesiswaan menerbitkan agenda trip & lembar persetujuan digital<br>2. Orang tua membuka aplikasi, membaca agenda, dan mencentang "Saya Menyetujui"<br>3. Masukkan kontak darurat | Persetujuan digital tercatat tanpa pungutan biaya kas online (sesuai batasan v1); daftar manifest peserta siap diunduh panitia. | Manifest peserta trip terisi, catatan consent orang tua. | `automated-tested` & `manual-UAT-required` |

---

### Modul 10: BK (Bimbingan Konseling & Peringatan Dini / Early Warning)

| ID Skenario | Fitur Master | Aktor / Role | Prasyarat & Data Uji | Langkah Pengujian | Hasil yang Diharapkan | Bukti yang Dikumpulkan | Status Bukti Awal |
|---|---|---|---|---|---|---|:---:|
| **UAT-BK-01** | `BE-BK-01`<br>`ADM-BK-01` | Guru BK & Guru Lain | Kasus konseling siswa rahasia | 1. Guru BK membuat kasus konseling baru di `/admin/counseling`<br>2. Isi catatan konseling terenkripsi<br>3. Login sebagai Guru Biasa atau Admin TU, coba buka endpoint/halaman catatan konseling tersebut | Guru biasa / Admin TU ditolak dengan pesan 403 Forbidden. Data konseling tidak bocor pada response API maupun audit log. | Screenshot form BK hanya terbuka untuk Guru BK, HTTP 403 untuk guru lain. | `automated-tested` & `manual-UAT-required` |
| **UAT-BK-02** | `BE-BK-02`<br>`ADM-BK-02` | Guru BK & Kurikulum | Siswa dengan presensi Alpa > 3 hari berturut-turut | 1. Sistem menjalankan evaluasi otomatis di `/admin/counseling/early-warnings`<br>2. Periksa daftar peringatan dini (*Early Warning Radar*) | Siswa dengan risiko drop/alpa tinggi otomatis masuk ke radar deteksi dini; tombol "Panggil untuk Bimbingan" siap ditindaklanjuti. | Rekaman early warning aktif di dashboard BK. | `automated-tested` & `manual-UAT-required` |
| **UAT-BK-03** | `BE-BK-03`<br>`MOB-SIS-07` | Siswa & Guru BK | Siswa mengajukan janji temu konseling | 1. Siswa buka `/portal/siswa`, pilih Permohonan Konseling<br>2. Isi topik "Konsultasi Minat Bakat", pilih waktu yang diinginkan<br>3. Guru BK menerima permintaan & menyetujui jadwal | Jadwal sesi konseling terkonfirmasi; notifikasi privat terkirim ke portal siswa. | Screenshot konfirmasi janji temu di portal siswa dan guru BK. | `automated-tested` & `manual-UAT-required` |

---

### Modul 11: SAR (Sarana Prasarana, Inventaris QR, & Perpustakaan)

| ID Skenario | Fitur Master | Aktor / Role | Prasyarat & Data Uji | Langkah Pengujian | Hasil yang Diharapkan | Bukti yang Dikumpulkan | Status Bukti Awal |
|---|---|---|---|---|---|---|:---:|
| **UAT-SAR-01** | `BE-SAR-01`<br>`ADM-SAR-01` | Guru & Staf Sarpras | Peminjaman Ruang Aula untuk Seminar | 1. Guru ajukan booking Aula di `/admin/facilities` untuk tanggal 20 Oktober jam 09:00<br>2. Guru lain coba ajukan booking di ruangan dan waktu yang sama<br>3. Staf Sarpras buka dashboard booking & setujui booking pertama | Pengajuan kedua ditandai bentrok; booking pertama berubah status menjadi `APPROVED` dan jadwal ruang aula terkunci di kalender sekolah. | Screenshot kalender peminjaman ruang, respon penolakan bentrok. | `automated-tested` & `manual-UAT-required` |
| **UAT-SAR-02** | `BE-SAR-02`<br>`ADM-SAR-02` | Staf Sarpras | Scan Label QR Aset Proyektor Ruang 7-A | 1. Buka modul inventaris aset `/admin/assets`<br>2. Cetak stiker label QR aset `AST-PRJ-004`<br>3. Pindai QR menggunakan kamera smartphone | Informasi spesifikasi aset, tanggal pengadaan, riwayat perbaikan, dan status kelayakan langsung tampil di layar. | Screenshot hasil pindaian QR aset pada browser. | `automated-tested` & `manual-UAT-required` |
| **UAT-SAR-03** | `BE-SAR-03`<br>`ADM-SAR-03` | Guru, Staf Sarpras, & Teknisi | AC Ruang Guru bocor / rusak | 1. Guru buat tiket perbaikan di `/admin/tickets`<br>2. Sarpras disposisi tiket ke Teknisi Budi<br>3. Teknisi perbaiki AC, unggah foto bukti selesai, dan klik "Selesaikan Tiket" | Guru pelapor menerima notifikasi perbaikan selesai; tiket tertutup dengan riwayat biaya/tindakan lengkap. | Screenshot alur tiket dari `OPEN` $\rightarrow$ `IN_PROGRESS` $\rightarrow$ `RESOLVED`. | `automated-tested` & `manual-UAT-required` |
| **UAT-SAR-04** | `BE-SAR-04`<br>`ADM-SAR-04` | Petugas Perpustakaan & Siswa | Peminjaman Buku Paket IPA (Barcode Scan) | 1. Petugas perpus scan kartu siswa & barcode buku IPA<br>2. Buku tercatat dipinjam selama 14 hari<br>3. Kembalikan buku lewat dari 14 hari | Status buku berubah menjadi `BORROWED`; pengembalian terlambat menghitung denda otomatis (jika diaktifkan) dan stok buku kembali bertambah. | Bukti peminjaman perpustakaan, status sirkulasi buku. | `automated-tested` & `manual-UAT-required` |

---

### Modul 12: HUM (Humas, Kemitraan MoU, & Tracer Study Alumni)

| ID Skenario | Fitur Master | Aktor / Role | Prasyarat & Data Uji | Langkah Pengujian | Hasil yang Diharapkan | Bukti yang Dikumpulkan | Status Bukti Awal |
|---|---|---|---|---|---|---|:---:|
| **UAT-HUM-01** | `BE-HUM-01`<br>`ADM-HUM-01` | Staf Humas | Dokumen Kerjasama MoU dengan Fakultas MIPA UM | 1. Buka `/admin/public-relations`<br>2. Tambah data kemitraan, unggah PDF naskah MoU, atur masa berlaku 3 tahun<br>3. Cek filter "MoU Segera Berakhir" | Data kemitraan tersimpan rapi; sistem memberi peringatan 60 hari sebelum masa berlaku kerjasama berakhir. | Screenshot detail dokumen MoU, kartu mitra aktif. | `automated-tested` & `manual-UAT-required` |
| **UAT-HUM-02** | `BE-HUM-02`<br>`ADM-HUM-02` | Alumni & Staf Humas | Kuesioner Tracer Study Lulusan 2025 | 1. Alumni membuka formulir tracer study publik<br>2. Isi nama, tahun lulus, sekolah lanjutan (SMA/SMK favorit), dan kepuasan belajar<br>3. Humas membuka dashboard tracer study | Grafik distribusi sekolah lanjutan alumni langsung teragregasi secara otomatis tanpa kebocoran nomor kontak pribadi alumni. | Screenshot grafik distribusi alumni di dashboard humas. | `automated-tested` & `manual-UAT-required` |

---

### Modul 13: RPT (Laporan Terpadu & Dashboard Eksekutif)

| ID Skenario | Fitur Master | Aktor / Role | Prasyarat & Data Uji | Langkah Pengujian | Hasil yang Diharapkan | Bukti yang Dikumpulkan | Status Bukti Awal |
|---|---|---|---|---|---|---|:---:|
| **UAT-RPT-01** | `BE-RPT-01`<br>`ADM-RPT-01` | Kepala Sekolah & TU | Laporan Rekapitulasi Presensi & Nilai Semester | 1. Buka `/admin/reports`<br>2. Pilih jenis laporan "Rekap Kehadiran Bulanan", pilih filter kelas 7-A s/d 9-D<br>3. Klik "Ekspor Excel / PDF" | File spreadsheet/PDF terunduh dengan data akurat, header sekolah resmi, dan rekapitulasi persentase kehadiran lengkap. | File Excel laporan kehadiran yang valid dan dapat dibuka di MS Excel. | `automated-tested` & `manual-UAT-required` |
| **UAT-RPT-02** | `BE-RPT-02`<br>`ADM-DASH-01` | Kepala Sekolah | Dashboard Eksekutif Manajerial | 1. Buka `/admin/executive`<br>2. Periksa metrik KPI: total siswa aktif, kehadiran guru hari ini, progres verifikasi PPDB, dan status tiket sarpras<br>3. Buka menu persetujuan cepat (*quick approvals*) | Metrik KPI menampilkan data agregat real-time dari database; persetujuan usulan dapat dieksekusi dengan 1 klik. | Screenshot dashboard eksekutif, verifikasi tidak adanya data mock palsu. | `automated-tested` & `manual-UAT-required` |

---

### Modul 14: NTF/SYS (Notifikasi Terpadu, Antrean Job, & Utilitas Sistem)

| ID Skenario | Fitur Master | Aktor / Role | Prasyarat & Data Uji | Langkah Pengujian | Hasil yang Diharapkan | Bukti yang Dikumpulkan | Status Bukti Awal |
|---|---|---|---|---|---|---|:---:|
| **UAT-SYS-01** | `BE-NTF-01`<br>`BE-NTF-03` | Admin & Seluruh User | Pengumuman Libur Hari Raya | 1. Admin buat pengumuman terarah untuk segmen "Siswa & Orang Tua" di `/admin/notifications`<br>2. Siswa dan orang tua membuka aplikasi / web | Lonceng notifikasi menampilkan badge merah; notifikasi masuk ke inbox in-app; deep link mengarah langsung ke pengumuman lengkap. | Screenshot badge notifikasi, log pembaca pengumuman terdaftar. | `automated-tested` & `manual-UAT-required` |
| **UAT-SYS-02** | `BE-NTF-02` | Admin Sistem | Template Notifikasi WhatsApp Pengumuman PPDB | 1. Buat template WA dengan parameter `{{nama}}` dan `{{status}}`<br>2. Kirim pesan uji simulasi ke nomor penguji di staging | Pesan terkirim melalui WhatsApp Gateway staging dengan substitusi variabel yang tepat; log pengiriman berstatus `SENT`. | Screenshot pesan WA masuk di ponsel penguji, rekaman di `notification_logs`. | `automated-tested` & `manual-UAT-required` |
| **UAT-SYS-03** | `BE-FILE-01` | Pengguna Umum | Upload dokumen pendaftaran / tugas (Presigned S3) | 1. Pilih berkas PDF 5MB<br>2. Panggil flow presign-upload-confirm | Berkas terunggah langsung ke storage dengan aman tanpa membebani memori web server Laravel; hash dan checksum file valid. | Log presigned upload, URL bertanda tangan terbit dengan masa berlaku 15 menit. | `automated-tested` & `manual-UAT-required` |
| **UAT-SYS-04** | Scheduler & Queue Worker | Admin Sistem | Eksekusi massal (e.g. Generate 500 Rapor atau Tutup Ujian Otomatis) | 1. Picu job antrean massal<br>2. Pantau jalannya worker di staging (`php artisan queue:work`) | Job diproses bertahap di background tanpa menyebabkan antarmuka web mengalami timeout 504. | Log terminal queue worker, progres job selesai di database `jobs`. | `automated-tested` & `manual-UAT-required` |

---

## 4. Prioritas Skenario UAT (P0 / P1 Wajib Pertama)

Urutan pelaksanaan pengujian UAT wajib mengikuti tabel prioritas di bawah ini. Skenario P0 adalah gerbang kelulusan mutlak (*showstoppers*):

```
┌────────────────────────────────────────────────────────────────────────┐
│                        URUTAN FASE PENGUJIAN UAT                       │
│                                                                        │
│  [FASE 1: FONDASI KEAMANAN & MASTER DATA]                              │
│  UAT-AUTH-01 s/d 04 ──> UAT-MST-01 s/d 05                             │
│                                                                        │
│  [FASE 2: GERBANG MASUK & PUBLIK]                                      │
│  UAT-CMS-01 s/d 04  ──> UAT-PPDB-01 s/d 06 ──> UAT-GST-01 s/d 03      │
│                                                                        │
│  [FASE 3: INTI AKADEMIK & CBT DIGITAL]                                 │
│  UAT-ACD-01 s/d 05  ──> UAT-CBT-01 s/d 05 ──> UAT-GRD-01 s/d 04      │
│                                                                        │
│  [FASE 4: KESISWAAN, BK, & SARPRAS]                                    │
│  UAT-BK-01 s/d 03   ──> UAT-STD-01 s/d 04 ──> UAT-SAR-01 s/d 04      │
│                                                                        │
│  [FASE 5: HUMAS, DASHBOARD, & NOTIFIKASI SISTEM]                       │
│  UAT-HUM-01 s/d 02  ──> UAT-RPT-01 s/d 02 ──> UAT-SYS-01 s/d 04      │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 5. Skenario Negatif & Uji Ketahanan (Negative & Resilience Scenarios)

| ID Skenario | Kategori Pengujian | Langkah Pengujian Negatif | Perilaku Sistem yang Diharapkan |
|---|---|---|---|
| **NEG-SEC-01** | **IDOR Data Anak** | Akun Orang Tua A mencoba mengakses endpoint `/api/v1/me/children/{id_anak_b}` milik Orang Tua B | Backend melempar **403 Forbidden**; pesan: "Anda tidak memiliki akses terhadap data siswa ini". Data anak B sama sekali tidak muncul di browser. |
| **NEG-SEC-02** | **Akses Kunci Jawaban CBT** | Siswa membuka DevTools/Network Tab pada browser saat ujian berlangsung untuk menginspeksi payload `/exams/{id}/session-questions` | Field `correct_answer`, `rubric`, atau `explanation` **tidak ada** dalam response payload. Kunci jawaban aman di server. |
| **NEG-SEC-03** | **Manipulasi Berkas Privat** | Pengguna yang tidak login mencoba mengakses URL unduhan berkas pendaftaran PPDB atau slip gaji secara langsung | Server memblokir request dengan **401 Unauthorized** / **403 Forbidden**; URL langsung tanpa tandatangan kedaluwarsa seketika. |
| **NEG-VAL-01** | **Batas Ekstrem / Kuota Penuh** | Calon siswa ke-51 mencoba mendaftar pada jalur pendaftaran dengan kuota tepat 50 yang telah terisi penuh | Sistem menampilkan peringatan ramah bahwa kuota jalur telah terpenuhi dan menawarkan opsi jalur lain atau daftar tunggu (*waitlist*). |
| **NEG-VAL-02** | **File Berbahaya / Oversize** | Pengguna mencoba mengunggah file berekstensi `.exe` / `.php` atau gambar berukuran > 10MB | Sistem validasi client & server menolak seketika (*invalid mime type* / *file too large*). Tidak ada file yang tersimpan di disk. |
| **NEG-NET-01** | **Koneksi Terputus saat Ujian** | Siswa memutus koneksi internet (airplane mode) saat mengerjakan CBT, lalu menjawab soal | Sistem menampilkan indikator kuning "Koneksi Terputus — Jawaban tersimpan di memori lokal"; saat internet kembali, sinkronisasi autosave otomatis terkirim tanpa data hilang. |
| **NEG-IDEM-01**| **Double Click / Idempotensi** | Pengguna mengklik tombol "Kirim Pendaftaran" atau "Bayar Ujian" 5 kali secara cepat berturut-turut | Sistem mengunci tombol (*loading state*) dan memanfaatkan header `Idempotency-Key`; hanya tepat 1 transaksi yang tercatat di database. |

---

## 6. Audit Khusus Risiko Fallback Mock Data

### Latar Belakang & Analisis Kode
Di codebase `apps/web/src/lib/api/mock-mode.ts`, terdapat mekanisme proteksi fallback:
```ts
export function isMockEnabled(): boolean {
  return (
    process.env.NODE_ENV !== 'production' &&
    process.env.NEXT_PUBLIC_USE_MOCK === 'true'
  );
}
```
Aturan kepatuhan yang harus dibuktikan saat UAT:
1. **Di lingkungan Staging/Produksi (`NODE_ENV === 'production'`)**:
   `isMockEnabled()` wajib selalu bernilai `false`. Mock loader tidak boleh dievaluasi.
2. **Jika Backend Mengalami Kegagalan (HTTP 500 / 502 / 504 / 401 / 403)**:
   Sistem dilarang keras menampilkan data tiruan (seperti angka dummy pada eksekutif dashboard). Antarmuka wajib menampilkan **Error State** kanonis atau pesan *"Gagal memuat data, silakan coba lagi"*.

### Skenario Uji Pembuktian Mock Mode
| ID Skenario | Langkah Pengujian | Hasil yang Diharapkan |
|---|---|---|
| **UAT-MOCK-01** | 1. Deploy web frontend dengan `NEXT_PUBLIC_USE_MOCK=false`<br>2. Hentikan sementara service backend Laravel API di staging<br>3. Buka halaman `/admin/executive` dan `/portal/guru` | Layar menampilkan **Error State Component** (*Empty / Retry Button*). **DILARANG KERAS** muncul angka dummy metric 98%, daftar approval dummy, atau nama siswa mock. |

---

## 7. Kebutuhan Akun Penguji & Data Uji Sintetis

Untuk menjalankan matriks UAT di atas, staging database wajib diisi dengan **Akun Uji Sintetis Terstandarisasi**:

| Peran (Role) | Username / Email Uji | Persona & Tugas Pengujian |
|---|---|---|
| **Super Admin** | `admin.staging@smp-labum.sch.id` | Konfigurasi sistem, audit log, RBAC, kelola master data. |
| **Kepala Sekolah** | `kepsek.staging@smp-labum.sch.id` | Dashboard eksekutif, persetujuan cepat, approve e-rapor. |
| **Kurikulum** | `kurikulum.staging@smp-labum.sch.id` | Jadwal KBM, bentrok jam, ploting mapel, bank soal CBT, buka periode nilai. |
| **Wali Kelas** | `walas.7a.staging@smp-labum.sch.id` | Presensi harian, approve izin, catatan karakter, generate rapor 7-A. |
| **Guru Mapel (Matematika)** | `guru.mtk.staging@smp-labum.sch.id` | Jurnal KBM, input buku nilai, koreksi uraian CBT, upload modul ajar. |
| **Guru BK** | `bk.staging@smp-labum.sch.id` | Kasus privat terenkripsi, radar early warning, janji temu siswa. |
| **Staf Tata Usaha** | `tu.staging@smp-labum.sch.id` | Impor siswa baru, cetak surat keterangan, upload slip gaji guru. |
| **Staf Sarpras** | `sarpras.staging@smp-labum.sch.id` | Approve booking aula, inventaris label QR, disposisi tiket perbaikan. |
| **Panitia PPDB** | `ppdb.panitia@smp-labum.sch.id` | Verifikasi berkas, tolak/revisi, eksekusi ranking seleksi, konversi siswa. |
| **Siswa Aktif** | `siswa.lab.001@smp-labum.sch.id` | Kerjakan CBT, submit tugas LMS, cek nilai, ajukan konseling mandiri. |
| **Orang Tua / Wali** | `ortu.siswa.001@smp-labum.sch.id` | Pantau presensi anak, ajukan izin sakit, unduh rapor semester ber-QR. |
| **Calon Siswa PPDB** | `calon.ppdb.01@gmail.com` | Registrasi akun baru, isi 5 langkah form, upload KK & rapor, daftar ulang. |

> [!CAUTION]
> Seluruh kata sandi akun staging wajib menggunakan passphrase uji unik (misal `SistLabUM2026!UAT`) dan didistribusikan secara aman hanya kepada tim penguji yang bertandatangan.

---

## 8. Checklist Kesiapan Lingkungan Staging (Staging Readiness Checklist)

Sebelum penguji memulai sesi UAT, tim teknis wajib memverifikasi checklist berikut:

```markdown
- [ ] 1. Domain & Jaringan Staging:
      - Frontend Web: https://staging.smp-labum.sch.id (Sertifikat SSL Let's Encrypt aktif)
      - Backend API: https://api-staging.smp-labum.sch.id (Reverse proxy NGINX/Caddy aman)
- [ ] 2. Variabel Lingkungan Staging (apps/web/.env.production):
      - NEXT_PUBLIC_SITE_URL=https://staging.smp-labum.sch.id
      - NEXT_PUBLIC_API_URL=https://api-staging.smp-labum.sch.id/api/v1
      - NEXT_PUBLIC_USE_MOCK=false
      - NODE_ENV=production
- [ ] 3. Variabel Lingkungan Backend (apps/api/.env):
      - APP_ENV=staging
      - APP_DEBUG=false
      - APP_URL=https://api-staging.smp-labum.sch.id
      - SANCTUM_STATEFUL_DOMAINS=staging.smp-labum.sch.id
      - SESSION_DOMAIN=.smp-labum.sch.id
- [ ] 4. Driver Penyimpanan Berkas:
      - Driver aktif S3 / MinIO staging
      - Bucket publik untuk foto berita & sampul galeri
      - Bucket privat bertanda tangan (Presigned S3) untuk KK PPDB, Slip Gaji, dan BK
- [ ] 5. Layanan Latar Belakang (Background Services):
      - Daemon Supervisor aktif menjalankan `php artisan queue:work --tries=3`
      - Cron server aktif menjalankan `* * * * * php /path/to/artisan schedule:run`
- [ ] 6. Database & Cache Staging:
      - PostgreSQL / MySQL Staging terisolasi dari database pengembangan/lokal
      - Redis terhubung untuk session, throttle, dan queue broker
      - Database di-seed menggunakan dataset sintetis lengkap
- [ ] 7. Logging & Diagnostik:
      - File log Laravel (`storage/logs/laravel.log`) dapat dipantau real-time
      - Rotasi log aktif (daily rotation)
- [ ] 8. Backup & Prosedur Reset Staging:
      - Script reset database staging tersedia (`php artisan migrate:fresh --seed`) jika terjadi data corruption saat pengujian
```

---

## 9. Rekonsiliasi Hasil Audit & Daftar Kesenjangan Bukti (Gap Analysis)

### A. Rekonsiliasi Jumlah Tes `SiteSettingTest.php`
- Pada laporan audit sebelumnya, dicatat: *"SiteSettingTest.php (12 tests lulus)"*.
- **Hasil Verifikasi Ulang Runner Aktual**:
  Perintah `php artisan test tests/Feature/Cms/SiteSettingTest.php` menghasilkan:
  ```json
  {"tool":"phpunit","result":"passed","tests":11,"passed":11,"assertions":107,"duration_ms":644}
  ```
- **Koreksi Resmi**: Jumlah tes aktual pada `SiteSettingTest.php` adalah **11 tes** (107 assertions), bukan 12 tes. Seluruh 51 pengujian pada direktori `tests/Feature/Cms/` (514 assertions) lulus 100%.

### B. Daftar Klaim Audit yang Belum Memiliki Bukti Spesifik (Evidence Gaps)
1. **Fitur Aplikasi Mobile (MOB-*)**:
   - Master modul memuat 44 fitur berkode `MOB-*`.
   - Kode aplikasi Android Kotlin berada di luar lingkup repositori web/api ini, sehingga status pembuktiannya di level sistem satu pintu adalah **unverified** dan wajib dijadwalkan pada sesi UAT terpisah dengan APK staging.
2. **Pengujian E2E Otomatis Playwright Lintas Browser**:
   - Walaupun 719 pengujian Vitest dan 1.082 pengujian PHPUnit telah lulus, sistem belum memiliki pipeline Playwright E2E otomatis yang mengemulasi klik browser Chrome, Safari, dan Firefox secara terus-menerus.
   - Oleh sebab itu, skenario UAT manual pada dokumen ini menjadi **kunci validasi utama** sebelum sistem dilepas ke publik.
3. **Penyedia Layanan Eksternal Nyata (WhatsApp & Mail Gateway)**:
   - Pengujian otomatis saat ini melakukan *mocking* terhadap WhatsApp API dan Mailer. Verifikasi pengiriman pesan nyata ke nomor WhatsApp dan inbox email penguji harus dibuktikan pada sesi UAT Staging.

---

## 10. Keputusan Akhir: Kesiapan Pelaksanaan UAT

### Status Keputusan: **UAT SIAP DIMULAI DENGAN PRASYARAT STAGING (READY WITH PREREQUISITES)**

Rencana dan Matriks UAT telah lengkap, terstruktur, dan siap dijadikan instrumen uji resmi. Tim dapat segera mengeksekusi UAT dengan memenuhi 2 prasyarat operasional berikut:
1. **Penyelesaian Deployment Staging**: Memastikan checklist staging (Bagian 8) telah diverifikasi hijau oleh tim DevOps/Infrastruktur.
2. **Penyebaran Dataset & Kredensial Uji**: Database staging telah di-seed dengan akun sintetis (Bagian 7) dan formulir UAT dibagikan kepada masing-masing penguji perwakilan sekolah.

---

*Dokumen disusun secara independen, mematuhi prinsip strict read-only, tanpa mengubah kode program, konfigurasi lingkungan, skema database, atau riwayat Git.*
