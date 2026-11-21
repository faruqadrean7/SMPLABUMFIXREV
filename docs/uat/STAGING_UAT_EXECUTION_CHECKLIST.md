# CHECKLIST EKSEKUSI PENGUJIAN STAGING UAT SISTLABUM

- **Nama Proyek:** Portal Sekolah Satu Pintu (SISTLABUM)
- **Instansi:** SMP Laboratorium Universitas Negeri Malang
- **Repository:** `/Users/macbookprom12020/Desktop/Project/SISTLABUM`
- **Branch Target:** `develop` | **HEAD:** `448a1a499d3c3a87aa61c3d28428c616dd8f0405`
- **Tanggal Penyusunan:** 16 Oktober 2026
- **Klasifikasi:** Dokumen Kontrol Mutu & Pelaksanaan UAT Staging
- **Pedoman Acuan:** `AGENTS.md`, `10_TESTING_QA.md`, `13_MASTER_MODULES.md`

---

## 1. Protokol & Persiapan Lingkungan Staging (Pre-Flight Checklist)

Sebelum memulai pengujian bersama pengguna sekolah, tim teknis wajib memverifikasi dan mencentang prasyarat berikut:

- [ ] **1.1 Integritas Repositori:**
  - Branch berada pada `develop`.
  - HEAD berada pada komit terverifikasi `448a1a499d3c3a87aa61c3d28428c616dd8f0405`.
  - Tidak ada perubahan kode tak terlacak (*untracked drifts*) pada server staging.
- [ ] **1.2 Konfigurasi Lingkungan (`.env` Staging):**
  - `APP_ENV=staging`, `APP_DEBUG=false`.
  - Database terhubung ke instans PostgreSQL 16 staging terisolasi (bukan database produksi).
  - Cache & Session driver menggunakan Redis terverifikasi (`REDIS_CLIENT=phpredis`).
  - Storage driver disiapkan (`FILESYSTEM_DISK=s3` dengan MinIO staging atau `local` terisolasi).
  - Cron scheduler otomatis dipastikan **NONAKTIF** (pembersihan berkas yatim hanya manual).
- [ ] **1.3 Pencadangan Awal (Baseline Backup):**
  - Database staging di-dump sebelum data uji dimasukkan: `pg_dump -h 127.0.0.1 -p 5432 -U postgres -d sistlabum_staging -F c -b -v -f backup_pre_uat.dump`.
  - Direktori `storage/app` dicadangkan secara terpisah (`tar -czvf backup_storage_pre_uat.tar.gz -C storage/app .`).
- [ ] **1.4 Pemuatan Data Uji (Seeding Terkontrol):**
  - Eksekusi seeder akun pengujian: `UAT_SEED_ENABLED=true php artisan db:seed --class=UatStagingSeeder`.
  - Verifikasi seluruh akun persona siap digunakan dengan kredensial uji aman dari `UAT_STAGING_PASSWORD`.
- [ ] **1.5 Verifikasi Status Layanan (Health Check):**
  - Akses `GET /api/v1/health`, pastikan mengembalikan HTTP 200 dengan status `database: ok` dan `redis: ok`.

---

## 2. Matriks Akun Pengujian Resmi Staging UAT

| No | Persona Pengguna | Akun Email / Username Uji | Role Sistem | Destinasi Login | Cakupan Otorisasi |
|:--:|---|---|---|:---:|---|
| 1 | **Calon Siswa PPDB** | `pendaftar.ppdb@labum.sch.id` | Akun Pendaftar | `/ppdb/dashboard` | Pengisian formulir, upload KK/Akta, cetak kartu bukti |
| 2 | **Siswa Aktif (Kelas 7A)**| `siswa.ahmad@labum.sch.id` | `student` | `/portal/siswa` | Jadwal 7A, tugas, nilai sendiri, CBT, poin, konseling |
| 3 | **Siswa Aktif (Kelas 8B)**| `siswa.zahra@labum.sch.id` | `student` | `/portal/siswa` | Jadwal 8B, ujian CBT, buku nilai sendiri |
| 4 | **Orang Tua / Wali 1** | `wali.budi@labum.sch.id` | `guardian` | `/portal/ortu` | Hanya anak perwalian sah (`Ahmad Fauzi`) |
| 5 | **Orang Tua / Wali 2** | `wali.dewi@labum.sch.id` | `guardian` | `/portal/ortu` | Hanya anak perwalian sah (`Zahra Amalia`) |
| 6 | **Guru Matematika & Wali**| `guru.hendra@labum.sch.id` | `teacher`, `homeroom` | `/portal/guru` | Presensi 7A, nilai MTK, jurnal, hub wali kelas 7A |
| 7 | **Guru Bahasa Indonesia**| `guru.siti@labum.sch.id` | `teacher` | `/portal/guru` | Presensi KBM BIN, tugas, materi, slip gaji |
| 8 | **Guru Bimbingan Konseling**| `counselor.rini@labum.sch.id` | `counselor` | `/admin` | Ruang kerja konseling BK, kasus privat, peringatan dini |
| 9 | **Staf Tata Usaha & Sarpras**| `staf.tu@labum.sch.id` | `admin_tu`, `facility_staff` | `/portal/staf` + `/admin` | Presensi kerja, tiket helpdesk, master data siswa/guru |
| 10| **Petugas Perpustakaan** | `librarian.buku@labum.sch.id` | `librarian` | `/portal/staf` + `/admin` | Sirkulasi buku, katalog koleksi, presensi mandiri |
| 11| **Ketua Panitia PPDB** | `panitia.ppdb@labum.sch.id` | `ppdb_committee` | `/admin` | Verifikasi dokumen, eksekusi perangkingan, kuota |
| 12| **Kepala Sekolah** | `kepsek@labum.sch.id` | `principal` | `/portal/kepsek` | Dashboard KPI pimpinan, antrean persetujuan resmi |
| 13| **Wakil Kepala Sekolah**| `wakasek.kurikulum@labum.sch.id`| `vice_principal` | `/portal/kepsek` | Dashboard KPI pimpinan, antrean persetujuan resmi |
| 14| **Komite Sekolah (Mitra)**| `komite.sekolah@labum.sch.id` | `committee` | `/portal` | Safe Notice aman, dilarang masuk shell admin/mutasi |
| 15| **Pengawas Dinas (Mitra)**| `pengawas.dinas@labum.sch.id` | `supervisor` | `/portal` | Safe Notice aman, dilarang masuk shell admin/mutasi |
| 16| **Super Administrator** | `superadmin@labum.sch.id` | `super_admin` | `/admin` | Audit log, hak akses pengguna, pemulihan darurat |

---

## 3. Skenario Pengujian Rinci per Alur Kerja (Workflows)

### ALUR 01: Penerimaan Peserta Didik Baru (PPDB Online End-to-End)
- **Penanggung Jawab:** Panitia PPDB & Perwakilan Orang Tua Calon Siswa
- **Langkah & Verifikasi Kasus Uji:**
  - [ ] **TC-PPDB-01 (Informasi Publik):** Buka `/ppdb`, verifikasi rincian kuota jalur Zonasi (50%), Prestasi (30%), Afirmasi (15%), dan Mutasi (5%).
  - [ ] **TC-PPDB-02 (Registrasi Akun Baru):** Buka `/ppdb/daftar`, daftarkan akun dengan NIK 16 digit valid, nama lengkap, dan nomor WhatsApp. Pastikan kode OTP terkirim dan terverifikasi.
  - [ ] **TC-PPDB-03 (Uji Validasi NIK Ganda):** Coba daftarkan kembali NIK yang sama. Pastikan sistem menolak dengan pesan error yang jelas (*Validation Error: NIK sudah terdaftar*).
  - [ ] **TC-PPDB-04 (Pengisian Formulir Bertahap):** Login ke `/ppdb/dashboard`, isi formulir multi-langkah (biodata, kontak orang tua, asal sekolah, nilai rapor). Verifikasi fitur simpan draf otomatis berjalan lancar tanpa kehilangan data input saat refresh halaman.
  - [ ] **TC-PPDB-05 (Unggah Berkas Persyaratan):** Unggah dokumen KK dan Akta Kelahiran berformat PDF (< 2 MB). Pastikan pratinjau dokumen muncul.
  - [ ] **TC-PPDB-06 (Uji Negatif Upload Berkas):** Unggah berkas ilegal (ekstensi `.exe` atau ukuran > 2 MB). Pastikan sistem menolak di sisi klien dan server dengan pesan informatif Bahasa Indonesia.
  - [ ] **TC-PPDB-07 (Finalisasi Pendaftaran):** Lakukan kunci submit pendaftaran. Pastikan muncul dialog konfirmasi dan formulir terkunci (*read-only*).
  - [ ] **TC-PPDB-08 (Cetak Kartu Tanda Peserta):** Unduh dan cetak kartu bukti pendaftaran ber-QR code.
  - [ ] **TC-PPDB-09 (Verifikasi Panitia Back-Office):** Panitia login ke `/admin/ppdb/applicants`, buka detail berkas calon siswa, periksa dokumen PDF, dan ubah status verifikasi menjadi *Terverifikasi Sah*.
  - [ ] **TC-PPDB-10 (Mesin Perangkingan Otomatis):** Panitia membuka `/admin/ppdb/selection`, jalankan perangkingan otomatis. Verifikasi urutan peringkat mematuhi pembobotan nilai komposit dan kuota pagu jalur.
  - [ ] **TC-PPDB-11 (Pengumuman Hasil Mandiri):** Calon siswa membuka `/ppdb#cek-pengumuman`, masukkan nomor registrasi. Pastikan status kelulusan (*Diterima*) muncul dengan benar.
  - [ ] **TC-PPDB-12 (Konfirmasi Daftar Ulang):** Calon siswa yang diterima mengisi form daftar ulang online pada `/ppdb/dashboard` dan mengunggah surat pernyataan komitmen. Panitia mengonfirmasi status menjadi *Terdaftar Ulang*.

---

### ALUR 02: Manajemen KBM & Presensi Harian Guru
- **Penanggung Jawab:** Dewan Guru & Staf Tata Usaha
- **Langkah & Verifikasi Kasus Uji:**
  - [ ] **TC-KBM-01 (Login Guru & Redirect):** Buka URL alias `/guru`. Sistem wajib melakukan HTTP 307 temporary redirect secara mulus menuju `/portal/guru`.
  - [ ] **TC-KBM-02 (Presensi Jam Kerja Mandiri):** Buka `/portal/guru/presensi-saya`, klik tombol *Catat Kehadiran (Check-In)*. Pastikan waktu check-in terekam dan tombol beralih status.
  - [ ] **TC-KBM-03 (Jadwal Mengajar Hari Ini):** Periksa kartu jadwal hari ini pada beranda guru. Pastikan jam pelajaran, nama mapel, dan ruang kelas sesuai data kurikulum.
  - [ ] **TC-KBM-04 (Pengisian Presensi Kelas Siswa):** Buka `/portal/guru/presensi`, pilih kelas yang sedang diajar. Tandai siswa: 28 Hadir, 1 Sakit, 1 Izin, 0 Alpa.
  - [ ] **TC-KBM-05 (Pengisian Jurnal Mengajar):** Pada halaman yang sama, isi topik bahasan materi KBM, metode pembelajaran (Tatap Muka), dan catatan kelas. Klik *Simpan Presensi & Jurnal*.
  - [ ] **TC-KBM-06 (Verifikasi Database & Integritas Transaksi):** Pastikan data presensi siswa masuk ke tabel `attendances` dan jurnal masuk ke `journals` dengan ID yang saling terhubung.
  - [ ] **TC-KBM-07 (Uji Anti-IDOR Kelas Guru):** Coba tembak endpoint presensi untuk ID kelas yang tidak diajar oleh guru bersangkutan. Pastikan server menolak dengan respons HTTP `403 FORBIDDEN` (*Anda tidak ditugaskan di kelas ini*).

---

### ALUR 03: Penugasan Belajar & Penilaian LMS Ringan
- **Penanggung Jawab:** Guru Mata Pelajaran & Siswa Aktif
- **Langkah & Verifikasi Kasus Uji:**
  - [ ] **TC-LMS-01 (Unggah Modul Bahan Ajar):** Guru membuka `/portal/guru/materi`, unggah modul ajar berformat PDF dan tentukan kelas sasaran (Kelas 7A).
  - [ ] **TC-LMS-02 (Pemberian Tugas KBM):** Guru membuka `/portal/guru/tugas`, buat tugas baru ("Latihan Soal Aljabar Linear") dengan tenggat waktu 3 hari mendatang.
  - [ ] **TC-LMS-03 (Akses Materi oleh Siswa):** Siswa Kelas 7A login ke `/portal/siswa/tugas`, pastikan materi dan tugas baru langsung muncul pada daftar.
  - [ ] **TC-LMS-04 (Isolasi Siswa Kelas Lain):** Siswa Kelas 8B login ke `/portal/siswa/tugas`, pastikan tugas Kelas 7A tidak terlihat pada akunnya.
  - [ ] **TC-LMS-05 (Pengumpulan Tugas Siswa):** Siswa Kelas 7A mengunggah lembar jawaban PDF, mengisi catatan pengerjaan, dan mengonfirmasi submit tugas.
  - [ ] **TC-LMS-06 (Evaluasi & Penilaian Guru):** Guru membuka `/portal/guru/tugas/[id]/penilaian`, unduh berkas jawaban siswa, berikan skor angka (misal: 92) dan catatan evaluasi.
  - [ ] **TC-LMS-07 (Transparansi Nilai Siswa):** Siswa membuka kembali `/portal/siswa/tugas` dan `/portal/siswa/nilai`, verifikasi status tugas telah berubah menjadi *Dinilai* beserta skor dan catatannya.

---

### ALUR 04: Ujian Berbasis Komputer (CBT Anti-Cheat & Live Monitor)
- **Penanggung Jawab:** Tim Laboratorium Komputer, Proktor & Siswa
- **Langkah & Verifikasi Kasus Uji:**
  - [ ] **TC-CBT-01 (Persiapan Paket Ujian & Token):** Administrator/Guru membuka `/admin/cbt/exams`, jadwalkan paket ujian ASAT Matematika, set durasi 60 menit, dan rilis token ruang lab komputer (misal: `MTK07A`).
  - [ ] **TC-CBT-02 (Persiapan Monitor Proktor):** Pengawas membuka `/admin/cbt/monitor` pada komputer pengawas. Pastikan daftar peserta lab tampil dengan status *Belum Mulai*.
  - [ ] **TC-CBT-03 (Aktivasi Ujian oleh Siswa):** Siswa membuka `/portal/siswa/cbt`, pilih ujian ASAT Matematika, masukkan token `MTK07A`, dan klik *Mulai Ujian*.
  - [ ] **TC-CBT-04 (Workspace Ujian & Sinkronisasi Timer):** Siswa masuk ke workspace `/portal/siswa/ujian/[id]`. Pastikan timer mundur berjalan presisi sesuai clock skew server (bukan waktu lokal perangkat).
  - [ ] **TC-CBT-05 (Autosave Jawaban Siswa):** Siswa menjawab soal pilihan ganda dan esai. Pastikan indikator status menyimpan (*Autosave*) berwarna hijau secara berkala.
  - [ ] **TC-CBT-06 (Uji Putus Jaringan Sesaat):** Putuskan koneksi jaringan komputer siswa selama 30 detik saat siswa menjawab soal. Sambungkan kembali. Pastikan jawaban yang diinput selama offline tersimpan di antrean lokal (`localStorage` scoped) dan langsung terkirim (*flush*) ke server begitu online.
  - [ ] **TC-CBT-07 (Uji Anti-Kecurangan Tab Switch):** Siswa mencoba berpindah tab browser atau meminimalkan jendela. Pastikan muncul dialog peringatan pelanggaran (*Violation Warning*).
  - [ ] **TC-CBT-08 (Penguncian Sesi Otomatis):** Ulangi perpindahan tab hingga 3 kali pelanggaran. Pastikan sesi siswa langsung terkunci (*Locked*) dan siswa tidak dapat melanjutkan menjawab soal.
  - [ ] **TC-CBT-09 (Aksi Proktor Buka Blokir):** Pengawas pada `/admin/cbt/monitor` melihat siswa terkait berstatus *Terkunci*. Pengawas mengklik *Buka Blokir (Unlock)* dengan alasan verifikasi proktor. Sesi siswa terbuka kembali.
  - [ ] **TC-CBT-10 (Aksi Tambah Waktu & Submit Paksa):** Uji tombol proktor untuk menambah waktu (+15 menit) dan verifikasi timer siswa bertambah. Uji fungsi submit paksa pada siswa yang kehabisan waktu.
  - [ ] **TC-CBT-11 (Submit Final & Koreksi Otomatis):** Siswa menyelesaikan pengerjaan dan klik *Kumpulkan Ujian*. Pastikan nilai pilihan ganda otomatis terhitung dan masuk ke rekap nilai.

---

### ALUR 05: Monitoring Orang Tua / Wali Murid & Pengujian Penetrasi IDOR
- **Penanggung Jawab:** Perwakilan Orang Tua / Komite & Security QA
- **Langkah & Verifikasi Kasus Uji:**
  - [ ] **TC-ORT-01 (Login Orang Tua & Multi-Anak):** Login melalui URL alias `/ortu`. Pastikan kartu anak yang ditampilkan sesuai perwalian sah pengguna di database.
  - [ ] **TC-ORT-02 (Pemantauan Presensi Real-Time):** Buka profil anak pada `/portal/ortu/anak/[childId]`. Periksa persentase kehadiran dan riwayat sakit/izin.
  - [ ] **TC-ORT-03 (Buku Rapor & Nilai Anak):** Buka tab nilai dan rapor, pastikan nilai kompetensi siswa tampil akurat sesuai buku nilai guru.
  - [ ] **TC-ORT-04 (Catatan Sikap Karakter):** Tinjau catatan dimensi Profil Pelajar Pancasila yang diinput oleh wali kelas.
  - [ ] **TC-ORT-05 (Uji Penetrasi IDOR 1 - Akses Antarmuka):** Ganti parameter `[childId]` pada bilah URL browser dengan UUID siswa lain yang bukan anaknya. Pastikan halaman menampilkan layar *Akses Ditolak (403 Forbidden)*.
  - [ ] **TC-ORT-06 (Uji Penetrasi IDOR 2 - Akses Langsung Endpoint API):** Kirim request HTTP langsung ke endpoint `/api/v1/me/children/{otherChildId}/grades` menggunakan token bearer orang tua. Pastikan backend mengembalikan respons JSON `403 FORBIDDEN` (*Anda tidak memiliki hak akses perwalian sah terhadap siswa ini*).

---

### ALUR 06: Tata Kelola Manajerial Kepala Sekolah & Wakasek
- **Penanggung Jawab:** Kepala Sekolah & Wakil Kepala Sekolah
- **Langkah & Verifikasi Kasus Uji:**
  - [ ] **TC-KPS-01 (Login Pimpinan Eksekutif):** Login dengan akun Kepala Sekolah melalui `/kepsek`. Verifikasi sapaan dinamis dan titel jabatan pimpinan.
  - [ ] **TC-KPS-02 (Dashboard KPI Real-Time):** Amati kartu indikator: persentase kehadiran siswa hari ini, persentase kehadiran guru, kasus peringatan dini, dan tiket sarpras terbuka.
  - [ ] **TC-KPS-03 (Tinjau Antrean Persetujuan):** Buka `/portal/kepsek/approvals`. Pastikan berkas pengajuan izin/cuti pegawai dan pengesahan rapor semester tampil pada antrean *Menunggu Keputusan*.
  - [ ] **TC-KPS-04 (Persetujuan Resmi Berkas):** Klik tombol *Setujui Berkas* pada salah satu pengajuan izin. Pastikan status pengajuan berubah seketika, notifikasi terkirim ke pemohon, dan aksi tercatat di tabel `audit_logs`.
  - [ ] **TC-KPS-05 (Penolakan Berkas dengan Alasan Wajib):** Klik tombol *Tolak Berkas* pada pengajuan lain. Pastikan modal penolakan mewajibkan pengisian alasan tertulis sebelum putusan dapat dikirimkan.
  - [ ] **TC-KPS-06 (Ekspor Laporan Resmi):** Buka `/admin/reports`, lakukan filter tanggal dan unduh laporan rekapitulasi kehadiran dan nilai dalam format Excel/PDF resmi.

---

### ALUR 07: Tenaga Kependidikan, Administrasi TU & Helpdesk Sarpras
- **Penanggung Jawab:** Staf Tata Usaha, Petugas Sarpras & Pustakawan
- **Langkah & Verifikasi Kasus Uji:**
  - [ ] **TC-STF-01 (Presensi Jam Kerja Mandiri):** Staf login ke `/portal/staf/presensi-saya`, lakukan pencatatan check-in kedatangan dan check-out kepulangan jam kerja.
  - [ ] **TC-STF-02 (Pengajuan Cuti Karyawan):** Staf membuka `/portal/staf/izin-cuti`, ajukan permohonan cuti tahunan dengan tanggal dan alasan keperluan keluarga.
  - [ ] **TC-STF-03 (Pelaporan Tiket Kerusakan Fasilitas):** Staf membuka `/portal/staf/tiket`, buat tiket laporan kendala (misal: "AC Lab Komputer 1 Mati/Bocor").
  - [ ] **TC-STF-04 (Disposisi Tiket Helpdesk):** Petugas Sarpras membuka `/admin/tickets`, ambil tiket laporan, tentukan prioritas (Tinggi), dan disposisikan ke teknisi.
  - [ ] **TC-STF-05 (Penyelesaian Tiket & Penutupan):** Setelah perbaikan, ubah status tiket menjadi *Selesai* dengan catatan tindakan teknisi. Staf pelapor melihat pembaruan status tiketnya.
  - [ ] **TC-STF-06 (Sirkulasi Buku Perpustakaan):** Pustakawan membuka `/admin/library`, lakukan pencatatan peminjaman buku teks paket oleh siswa dan verifikasi batas tanggal pengembalian.

---

### ALUR 08: Verifikasi Keabsahan Rapor Publik via Token QR
- **Penanggung Jawab:** Tim Humas & Verifikator Eksternal
- **Langkah & Verifikasi Kasus Uji:**
  - [ ] **TC-VER-01 (Penerbitan Rapor Ber-QR):** Wali kelas menerbitkan rapor semester pada `/admin/academic/report-cards`. Pastikan stempel QR code digital tertanam pada lembar PDF rapor.
  - [ ] **TC-VER-02 (Pemindaian Tanpa Sesi Login):** Dalam mode penyamaran (*Incognito*) tanpa login akun, buka tautan QR code yang mengarah ke `/public/verify/{token}`.
  - [ ] **TC-VER-03 (Validitas Informasi Publik):** Pastikan halaman verifikasi publik menampilkan status "TERVERIFIKASI RESMI", nama lengkap siswa, NISN, rombel, semester, dan tanggal pengesahan tanpa membocorkan data pribadi rahasia siswa.
  - [ ] **TC-VER-04 (Uji Token Palsu / Kedaluwarsa):** Masukkan token acak yang tidak terdaftar. Pastikan sistem menampilkan pesan dokumen tidak ditemukan atau tidak valid.

---

## 4. Pengujian Batasan Ekstrem & Ketahanan Sistem

- [ ] **4.1 Skenario Beban Data Kosong (Empty State):**
  - Buka seluruh halaman saat database belum memiliki data transaksi (misal: belum ada jadwal, belum ada tugas, belum ada notifikasi).
  - Pastikan komponen menampilkan pesan *EmptyState* yang ramah pengguna, tidak menghasilkan layar putih (*blank screen*), dan tidak memicu error JavaScript.
- [ ] **4.2 Skenario Beban Data Besar (Pagination & Filter):**
  - Uji halaman master siswa (500+ siswa) dan daftar pendaftar PPDB.
  - Pastikan pagination berfungsi lancar, pencarian responsif (< 500 ms), dan tidak terjadi N+1 query pada database server.
- [ ] **4.3 Penanganan Kesalahan API & Jaringan (Degraded Network):**
  - Simulasikan kondisi server API lambat (throttling 3G).
  - Pastikan tombol aksi menampilkan indikator loading (*spinner disabled*) untuk mencegah klik ganda (*double submission*).
  - Jika server mengembalikan error 500, pastikan antarmuka menampilkan komponen *ErrorState* dengan tombol *Coba Lagi (Retry)*.

---

## 5. Protokol Penghentian UAT & Prosedur Rollback Darurat

Tim penguji dan administrator wajib **MENGHENTIKAN PENGUJIAN SEKETIKA (STOP-THE-LINE)** jika ditemukan salah satu kondisi berikut:

1. **Kebocoran Data Kritis (IDOR):** Ditemukan celah di mana orang tua dapat melihat nilai/biodata anak lain, atau guru dapat mengubah nilai kelas yang bukan binaannya.
2. **Kehilangan Integritas Data Nilai / CBT:** Jawaban ujian CBT atau nilai rapor hilang saat disimpan ke basis data.
3. **Kegagalan Crash Sistemik:** Server API mengalami 500 Internal Server Error berulang yang melumpuhkan lebih dari satu modul KBM utama.

### Prosedur Rollback Cepat:
1. Catat log error pada `apps/api/storage/logs/laravel.log`.
2. Hentikan trafik pengguna ke staging.
3. Pulihkan database dari backup baseline awal:
   ```bash
   pg_restore -h 127.0.0.1 -p 5432 -U postgres -d sistlabum_staging --clean --if-exists -v backup_pre_uat.dump
   ```
4. Bersihkan cache aplikasi dan sesi:
   ```bash
   php artisan cache:clear && php artisan config:clear
   ```
5. Buat tiket laporan cacat (Severity P0/P1) pada Register Temuan sebelum pengujian dapat dilanjutkan.

---

## 6. Lembar Pengesahan & Tanda Tangan Pelaksanaan UAT

| Peran Penilai | Nama Lengkap | Jabatan | Tanggal Uji | Status Rekomendasi | Tanda Tangan |
|---|---|---|:---:|:---:|:---:|
| **Koordinator Penguji** | ................................ | Tim QA Sistem | ... / ... / 2026 | [ ] Lulus  [ ] Ditolak | .................... |
| **Perwakilan Guru** | ................................ | Dewan Guru KBM | ... / ... / 2026 | [ ] Lulus  [ ] Ditolak | .................... |
| **Panitia PPDB** | ................................ | Ketua PPDB 2026 | ... / ... / 2026 | [ ] Lulus  [ ] Ditolak | .................... |
| **Tata Usaha / Sarpras** | ................................ | Kepala TU | ... / ... / 2026 | [ ] Lulus  [ ] Ditolak | .................... |
| **Kepala Sekolah** | ................................ | Pimpinan Satuan | ... / ... / 2026 | [ ] Lulus  [ ] Ditolak | .................... |
