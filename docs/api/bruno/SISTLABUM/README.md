# SISTLABUM — Bruno API Collection

Koleksi API resmi untuk pengujian dan otomasi endpoint **SISTLABUM (Portal Sekolah Satu Pintu)** menggunakan aplikasi [Bruno](https://www.usebruno.com/).

## Struktur Koleksi (521 Endpoint Terpetakan)
- **`00-Health/`**: Pemeriksaan kesehatan server, PostgreSQL, Redis, dan sanitasi exception.
- **`01-Auth/`**: Login multi-role, rotasi refresh token, anti-replay, profil user, RBAC permissions, manajemen sesi perangkat, dan audit log.
- **`02-MasterData/`**: Tahun ajaran, semester, data siswa, guru, staf, rombel kelas, mapel, ruangan, dan kenaikan kelas.
- **`03-CMS/`**: Artikel berita, kategori, agenda, galeri foto/video, mading digital, unduhan publik, profil, dan navigasi.
- **`04-PPDB/`**: Konfigurasi gelombang, jalur, akun calon siswa, draf pendaftaran, upload berkas, verifikasi panitia, seleksi, dan daftar ulang.
- **`05-Guest/`**: Buku tamu kiosk umum/kedinasan, tanda tangan digital, dan whistleblowing system anonim.
- **`06-Academic/`**: Jadwal pelajaran, time-slot, jurnal mengajar, presensi siswa/staf, perizinan, materi KBM, dan tugas LMS.
- **`07-Grading/`**: Komponen penilaian, input nilai batch, catatan karakter walas, progress report, dan e-rapor ber-QR.
- **`08-CBT/`**: Bank soal, paket soal, jadwal ujian, token ruang, engine live exam, autosave, anti-cheat, koreksi otomatis, analisis butir, remedial KKTP, dan berita acara PDF.
- **`09-StudentAffairs/`**: Poin kedisiplinan & sanksi, prestasi, ekstrakurikuler, lomba, dan persetujuan study trip.
- **`10-CounselingBK/`**: Kasus konseling privat terenkripsi, sesi konseling, rujukan eksternal, dan sistem peringatan dini (early warning).
- **`11-SarprasLibrary/`**: Peminjaman fasilitas ruang/alat, inventaris aset label QR, helpdesk tiket perbaikan, dan sirkulasi perpustakaan.
- **`12-PublicRelations/`**: MoU kemitraan DUDI, pertukaran pelajar, dan kuesioner tracer study alumni.
- **`13-Reports/`**: Dashboard eksekutif kepala sekolah dan dashboard wali murid (anti-IDOR).
- **`14-NotificationsSystem/`**: Push notifications, SMS/WA broadcast template, preferensi notifikasi, dan signed storage.
- **`15-Public/`**: Endpoint publik terbuka tanpa autentikasi (verifikasi dokumen QR, feed berita, statistik umum).

## Cara Menggunakan di Bruno
1. Buka aplikasi **Bruno**.
2. Klik **Open Collection** dan pilih direktori: `docs/api/bruno/SISTLABUM`.
3. Pilih Environment di pojok kanan atas:
   - **Local**: untuk pengujian ke server lokal `http://127.0.0.1:8000/api/v1`.
   - **Testing**: untuk automated CI / staging.
4. Lakukan request `01-Auth -> 01-POST-login` untuk mendapatkan `access_token`, lalu masukkan ke variabel `adminToken` / `studentToken` di environment Bruno.
5. Jalankan **Run Collection** untuk mengeksekusi automated test scripts secara terintegrasi.
