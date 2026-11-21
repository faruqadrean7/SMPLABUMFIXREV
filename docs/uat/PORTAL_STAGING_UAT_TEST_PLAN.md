# RENCANA UJI SKENARIO OPERASIONAL HARIAN (STAGING UAT TEST PLAN)

- **Repository:** `/Users/macbookprom12020/Desktop/Project/SISTLABUM`
- **Branch:** `develop` | **HEAD:** `448a1a499d3c3a87aa61c3d28428c616dd8f0405`
- **Target Lingkungan:** Staging Server SMP Laboratorium Universitas Negeri Malang
- **Prasyarat:** Database Staging dengan data seeder realistis (`php artisan db:seed --class=LocalRbacTestSeeder`)
- **Tujuan Uji:** Memverifikasi kesiapan operasional harian 14 modul bagi 6 persona pengguna nyata (Siswa, Orang Tua, Guru, Staf TU, Kepala Sekolah, dan Panitia PPDB).

---

## 1. Matriks Persona Pengguna & Kredensial Uji Staging

| Persona Pengguna | Akun Contoh Uji | Role Sistem | Destinasi Awal | Batasan Akses |
|---|---|---|:---:|---|
| **Calon Siswa PPDB** | `pendaftar.ppdb@labum.sch.id` | Calon Pendaftar | `/ppdb/dashboard` | Hanya portal PPDB, dilarang masuk shell internal |
| **Siswa Aktif** | `siswa.ahmad@labum.sch.id` | `student` | `/portal/siswa` | Hanya data pribadi siswa bersangkutan |
| **Orang Tua / Wali** | `wali.budi@labum.sch.id` | `guardian` | `/portal/ortu` | Hanya anak kandung/perwalian sah (Anti-IDOR) |
| **Guru Pengampu** | `guru.siti@labum.sch.id` | `teacher` | `/portal/guru` | Hanya rombel & mapel yang ditugaskan |
| **Wali Kelas** | `walikelas.hendra@labum.sch.id` | `teacher`, `homeroom` | `/portal/guru` | Tab Wali Kelas rombel binaan |
| **Staf Tata Usaha** | `staf.tu@labum.sch.id` | `admin_tu`, `facility_staff` | `/portal/staf` + `/admin` | Layanan mandiri karyawan + back-office TU |
| **Kepala Sekolah** | `kepsek@labum.sch.id` | `principal` | `/portal/kepsek` | Dashboard KPI pimpinan & persetujuan resmi |
| **Wakil Kepala Sekolah**| `wakasek.kurikulum@labum.sch.id`| `vice_principal` | `/portal/kepsek` | Dashboard KPI pimpinan & persetujuan resmi |
| **Komite / Pengawas** | `pengawas.dinas@labum.sch.id` | `supervisor`, `committee`| `/portal` | Safe Notice (baca-saja non-operasional) |

---

## 2. Delapan Skenario Pengujian Operasional Harian

### SKENARIO 01: Alur Pendaftaran & Seleksi Siswa Baru (PPDB End-to-End)
- **Tujuan:** Memvalidasi seluruh siklus penerimaan siswa dari pendaftaran publik hingga daftar ulang.
- **Langkah Pengujian:**
  1. Calon siswa membuka `/ppdb` dan memilih gelombang aktif.
  2. Buka `/ppdb/daftar`, isi NIK, nama lengkap, nomor WhatsApp, dan selesaikan verifikasi OTP.
  3. Masuk ke `/ppdb/dashboard`, lengkapi data orang tua, nilai rapor, dan unggah berkas KK serta Akta Kelahiran.
  4. Lakukan finalisasi submit dan cetak kartu bukti pendaftaran ber-QR.
  5. Panitia PPDB login ke `/admin/ppdb/applicants`, verifikasi keabsahan dokumen berkas yang diunggah.
  6. Panitia membuka `/admin/ppdb/selection`, jalankan mesin perangkingan otomatis nilai komposit zonasi & prestasi.
  7. Calon siswa mengecek hasil kelulusan di `/ppdb#cek-pengumuman` menggunakan nomor registrasi.
  8. Calon siswa yang diterima mengisi form konfirmasi daftar ulang di `/ppdb/dashboard`.
- **Kriteria Keberhasilan (Pass):** Seluruh status berkas terbarui di database, perangkingan mematuhi pagu kuota, dan data calon siswa berhasil dikonfirmasi ke master data siswa baru.

---

### SKENARIO 02: Alur KBM & Presensi Harian Guru
- **Tujuan:** Memvalidasi operasional jam pertama masuk sekolah dan pengisian absensi siswa.
- **Langkah Pengujian:**
  1. Guru login melalui `/masuk` atau URL alias `/guru`. Sistem secara otomatis mengarahkan ke `/portal/guru`.
  2. Pada Beranda Guru, periksa jadwal mengajar hari ini (jam pelajaran, rombel, dan ruang kelas).
  3. Buka `/portal/guru/presensi-saya`, klik tombol *Catat Kehadiran (Check-In)* jam kerja.
  4. Buka `/portal/guru/presensi`, pilih kelas yang sedang berlangsung.
  5. Tandai status kehadiran tiap siswa: *Hadir*, *Sakit*, *Izin*, atau *Alpa*.
  6. Masukkan topik pembelajaran di jurnal mengajar dan klik *Simpan Presensi & Jurnal*.
- **Kriteria Keberhasilan (Pass):** Data presensi siswa tersimpan di tabel `attendances`, jurnal tersimpan di `journals`, dan indikator kehadiran kelas terbarui di Dashboard Kepala Sekolah.

---

### SKENARIO 03: Alur Tugas & Evaluasi Siswa (LMS Ringan)
- **Tujuan:** Memvalidasi distribusi materi ajar, pengumpulan tugas, dan penilaian guru.
- **Langkah Pengujian:**
  1. Guru membuka `/portal/guru/materi`, unggah modul ajar berformat PDF.
  2. Guru membuka `/portal/guru/tugas`, buat tugas baru dengan batas tenggat waktu 3 hari ke depan.
  3. Siswa login ke `/portal/siswa/tugas`, unduh modul materi ajar.
  4. Siswa mengunggah berkas jawaban tugas (PDF atau tautan Google Drive) dan menambahkan catatan pengerjaan.
  5. Guru membuka `/portal/guru/tugas/[id]/penilaian`, periksa lembar jawaban siswa.
  6. Guru memasukkan nilai angka (misal: 88) dan catatan umpan balik evaluasi.
  7. Siswa membuka `/portal/siswa/nilai` untuk memverifikasi nilai tugas telah masuk ke buku nilai pribadinya.
- **Kriteria Keberhasilan (Pass):** Berkas tugas tersimpan di tabel `files`, relasi `assignment_submissions` terbarui, dan siswa dapat melihat nilainya secara transparan.

---

### SKENARIO 04: Alur Ujian Berbasis Komputer (CBT Anti-Cheat & Live Monitor)
- **Tujuan:** Memvalidasi ketahanan asesmen digital lab komputer dan pengawasan real-time proktor.
- **Langkah Pengujian:**
  1. Staf/Guru membuka `/admin/cbt/exams`, aktifkan paket ujian semester dan buat token ruang lab (misal: `LAB01A`).
  2. Buka `/admin/cbt/monitor` pada komputer pengawas ujian.
  3. Siswa duduk di komputer lab, buka `/portal/siswa/cbt`, pilih ujian aktif dan masukkan token `LAB01A`.
  4. Siswa masuk ke workspace ujian `/portal/siswa/ujian/[id]`, timer sinkron dengan server.
  5. Siswa menjawab beberapa butir soal; pastikan indikator autosave berwarna hijau (*Tersimpan*).
  6. Uji Anti-Kecurangan: Siswa mencoba berpindah tab browser atau meminimalkan jendela.
  7. Verifikasi: Modal peringatan pelanggaran muncul di layar siswa, pelanggaran tercatat di monitor pengawas.
  8. Jika siswa mengulang 3 kali pelanggaran, sesi otomatis terkunci (*Locked*).
  9. Pengawas menekan tombol *Buka Blokir (Unlock)* pada `/admin/cbt/monitor`, siswa dapat melanjutkan pengerjaan.
  10. Siswa menyelesaikan ujian dan klik *Kumpulkan Ujian*. Nilai pilihan ganda otomatis terhitung.
- **Kriteria Keberhasilan (Pass):** Jawaban tidak hilang saat koneksi terputus sesaat, deteksi pelanggaran akurat, autosave berjalan setiap 15 detik, dan nilai terkalkulasi otomatis.

---

### SKENARIO 05: Alur Monitoring Orang Tua / Wali & Pengujian Anti-IDOR
- **Tujuan:** Memvalidasi keterbukaan informasi bagi orang tua dan memastikan isolasi data anak tanpa celah kebocoran.
- **Langkah Pengujian:**
  1. Orang tua login melalui `/ortu` atau `/portal/ortu`.
  2. Verifikasi kartu ringkasan anak yang ditampilkan hanya memuat anak perwalian sah pengguna.
  3. Klik kartu anak pertama menuju `/portal/ortu/anak/[childId]`.
  4. Pantau rekap kehadiran harian (persentase hadir, riwayat tanggal sakit/izin).
  5. Periksa buku nilai tengah semester dan catatan sikap karakter dari wali kelas.
  6. **Uji Penetrasi IDOR (Insecure Direct Object Reference):**
     - Ubah `[childId]` pada bilah URL browser menjadi UUID anak lain yang bukan perwaliannya.
     - Tekan Enter.
- **Kriteria Keberhasilan (Pass):** Sistem wajib menolak dengan layar *Akses Ditolak (403 Forbidden)*. Backend API `/api/v1/me/children/{childId}/*` mengembalikan respons JSON `403 FORBIDDEN` melalui `GuardianChildPolicy`. Tidak ada data anak lain yang bocor.

---

### SKENARIO 06: Alur Persetujuan & Monitoring Kepala Sekolah / Wakasek
- **Tujuan:** Memvalidasi fungsi manajerial pimpinan sekolah dalam memantau operasional dan mengambil keputusan resmi.
- **Langkah Pengujian:**
  1. Kepala Sekolah login ke `/portal/kepsek`.
  2. Amati kartu KPI: Persentase kehadiran siswa hari ini, kehadiran guru, insiden kedisiplinan terbuka, dan tiket sarpras.
  3. Buka `/portal/kepsek/approvals`. Tinjau daftar antrean persetujuan resmi.
  4. Klik berkas permohonan cuti guru; tekan tombol *Setujui Berkas*. Pastikan tercatat nama pimpinan dan stempel waktu.
  5. Klik berkas usulan kedua; tekan tombol *Tolak Berkas*. Modal dialog muncul mewajibkan pengisian alasan tertulis.
  6. Masukkan alasan penolakan dan konfirmasi.
- **Kriteria Keberhasilan (Pass):** Status pengajuan berubah seketika di database, notifikasi keputusan terkirim ke pemohon, dan aksi tercatat di audit log pimpinan.

---

### SKENARIO 07: Alur Tenaga Kependidikan & Helpdesk Sarpras
- **Tujuan:** Memvalidasi layanan mandiri staf operasional dan penanganan perbaikan fasilitas fisik.
- **Langkah Pengujian:**
  1. Staf Sarpras login ke `/portal/staf`.
  2. Lakukan presensi mandiri jam kerja pada `/portal/staf/presensi-saya`.
  3. Ajukan permohonan izin/cuti pada `/portal/staf/izin-cuti`.
  4. Buka `/portal/staf/tiket`, laporkan kendala fasilitas (misal: "Proyektor Lab IPA 2 Rusak/Mati Total").
  5. Petugas administrator sarpras login ke `/admin/tickets`, disposisikan tiket ke teknisi dan ubah status menjadi *Diproses*.
  6. Setelah perbaikan selesai, ubah status tiket menjadi *Selesai* dengan catatan resolusi perbaikan.
  7. Staf pelapor melihat pembaruan status pada tiket di `/portal/staf/tiket`.
- **Kriteria Keberhasilan (Pass):** Siklus tiket dari pelaporan hingga penutupan berjalan transparan dan riwayat pemeliharaan sarpras terarsip.

---

### SKENARIO 08: Verifikasi Dokumen Publik (E-Rapor Ber-QR Code)
- **Tujuan:** Membuktikan keaslian dokumen rapor sekolah kepada pihak ketiga (SMA tujuan / dinas) secara instan.
- **Langkah Pengujian:**
  1. Wali kelas menerbitkan rapor semester pada `/admin/academic/report-cards`.
  2. Buka lembar PDF rapor yang memuat stempel QR code digital.
  3. Tanpa login akun (mode penyamaran / peramban tamu), pindai QR code yang mengarah ke URL `/public/verify/{token}`.
  4. Periksa informasi keabsahan yang ditampilkan: Nama Siswa, NISN, Rombel, Semester, Nama Kepala Sekolah, dan status keaslian dokumen "TERVERIFIKASI RESMI".
- **Kriteria Keberhasilan (Pass):** Sistem menampilkan legalitas dokumen tanpa membocorkan nomor induk rahasia atau identitas sensitif lainnya.

---

## 3. Matriks Hasil Uji & Tanda Tangan Persetujuan UAT

| Skenario Uji | Penguji Lapangan | Hasil Aktual | Catatan Lapangan | Status Keputusan |
|---|---|:---:|---|:---:|
| Skenario 01 (PPDB) | Panitia PPDB & Calon Siswa | Menunggu Eksekusi | Siap uji di staging | **READY FOR UAT** |
| Skenario 02 (KBM & Absensi) | Dewan Guru & Staf TU | Menunggu Eksekusi | Siap uji di staging | **READY FOR UAT** |
| Skenario 03 (Tugas & LMS) | Guru Pengampu & Siswa | Menunggu Eksekusi | Siap uji di staging | **READY FOR UAT** |
| Skenario 04 (Ujian CBT) | Tim Lab Komputer & Siswa | Menunggu Eksekusi | Siap uji di staging | **READY FOR UAT** |
| Skenario 05 (Ortu & IDOR) | Perwakilan Wali Murid | Menunggu Eksekusi | Siap uji di staging | **READY FOR UAT** |
| Skenario 06 (Kepsek & Approvals)| Kepala Sekolah & Wakasek | Menunggu Eksekusi | Siap uji di staging | **READY FOR UAT** |
| Skenario 07 (Staf & Sarpras) | Staf TU & Teknisi | Menunggu Eksekusi | Siap uji di staging | **READY FOR UAT** |
| Skenario 08 (Verifikasi QR) | Tim Humas & Publik | Menunggu Eksekusi | Siap uji di staging | **READY FOR UAT** |
