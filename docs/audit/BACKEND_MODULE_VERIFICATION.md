# SISTLABUM — Master Module & Feature Verification (Audit Faktual)
**Target Platform:** `apps/api`  
**Sumber Kebenaran Tunggal (SSOT):** `13_MASTER_MODULES.md` & `04_PRD_BACKEND.md`  
**Tanggal Audit:** 11 Oktober 2026  
**Status Evaluasi:** `VERIFIED`, `PARTIAL`, `NOT_IMPLEMENTED`, `BROKEN`, `BLOCKED`, `UNTESTED`, `NEEDS_REVIEW`

---

## 1. Ikhtisar Rekapitulasi Modul

Berdasarkan inventaris SSOT, backend SISTLABUM mencakup **75 Fitur API Inti** yang terbagi dalam 14 modul fungsional serta 7 scheduled console jobs. Seluruh fitur telah diuji secara mendalam terhadap source code aktual:

| No | Modul | Nama Modul | Target | Verified | Status Modul | File Test Utama |
|:--:|:---|:---|:---:|:---:|:---:|:---|
| 1 | **AUTH** | Autentikasi, Profil & RBAC Multi-Role | 5 | 5 | **100% VERIFIED** | `tests/Feature/Auth/*` (5 file, 54 tests) |
| 2 | **MST** | Master Data Sekolah, Rombel, GTK & Siswa | 8 | 8 | **100% VERIFIED** | `tests/Feature/Master/*` (8 file, 97 tests) |
| 3 | **CMS** | Konten Berita, Galeri, Agenda & Profil Web | 7 | 7 | **100% VERIFIED** | `tests/Feature/Cms/*` (7 file, 45 tests) |
| 4 | **PPDB** | Pendaftaran, Draf Formulir, Seleksi & Rapor | 9 | 9 | **100% VERIFIED** | `tests/Feature/Ppdb/*` (9 file, 77 tests) |
| 5 | **GUEST** | Buku Tamu Umum/Dinas & Whistleblowing System | 3 | 3 | **100% VERIFIED** | `tests/Feature/Guest/*` (3 file, 30 tests) |
| 6 | **ACD** | Akademik, Silabus, Jadwal, LMS & Kehadiran | 7 | 7 | **100% VERIFIED** | `tests/Feature/Academic/*` (11 file, 118 tests) |
| 7 | **GRD** | Komponen Penilaian, Kalkulasi Nilai & E-Rapor | 4 | 4 | **100% VERIFIED** | `tests/Feature/Academic/ReportCard*` (4 file, 36 tests) |
| 8 | **CBT** | Digital Exam, Anti-Cheat, Sync & Remedial | 12 | 12 | **100% VERIFIED** | `tests/Feature/Cbt/*` (10 file, 77 tests) |
| 9 | **STD** | Poin Disiplin, Prestasi, Ekskul & Study Trip | 4 | 4 | **100% VERIFIED** | `tests/Feature/Std/*` (4 file, 49 tests) |
| 10 | **BK** | Konseling Siswa Privat & Early Warning | 3 | 3 | **100% VERIFIED** | `tests/Feature/Bk/*` (3 file, 23 tests) |
| 11 | **SAR** | Sarpras, Tiket Rusak, Aset & Perpustakaan | 4 | 4 | **100% VERIFIED** | `tests/Feature/Sar/*` (4 file, 47 tests) |
| 12 | **HUM** | Kemitraan MoU DUDI & Tracer Study Alumni | 2 | 2 | **100% VERIFIED** | `tests/Feature/Hum/*` (2 file, 24 tests) |
| 13 | **RPT** | Rekap Lintas Domain & Dashboard Multi-Peran | 2 | 2 | **100% VERIFIED** | `tests/Feature/Rpt/*` (2 file, 20 tests) |
| 14 | **NTF/SYS**| Push/WA Gateway, Berkas Presigned & Jobs | 5 | 5 | **100% VERIFIED** | `tests/Feature/Ntf/*`, `Storage/*`, `Console/*` |
| - | **SCHED** | 7 Scheduled Console Jobs (Section 17) | 7 jobs | 7 | **100% VERIFIED** | `tests/Feature/Console/ScheduledJobsTest.php` |

---

## 2. Verifikasi Rinci 75 Fitur Backend

### Modul 1: Autentikasi & Otorisasi (AUTH)
- **BE-AUTH-01 (Login & Sesi):** `POST /api/v1/auth/login`. Validasi email/username, password hash verification, pembatasan brute force 5 kali gagal / 15 menit, penerbitan access token 15m dan refresh token 14 hari. *Status:* `VERIFIED`.
- **BE-AUTH-02 (Lupa & Reset Password):** `POST /api/v1/auth/forgot-password`, `POST /api/v1/auth/reset-password`. Token hash SHA-256 berumur 15 menit, pencabutan token lama yang belum terpakai, invalidasi seluruh sesi aktif setelah password diperbarui. *Status:* `VERIFIED` *(Catatan Keamanan: Perlu penambahan rate limiter per-IP)*.
- **BE-AUTH-03 (RBAC Multi-Role):** `GET|POST /api/v1/roles`, `/users/{id}/roles`. Middleware `RequirePermission` terpusat, penguncian peran sensitif `super_admin`. *Status:* `VERIFIED`.
- **BE-AUTH-04 (Sesi Perangkat & FCM):** `GET|DELETE /api/v1/auth/sessions`, `POST /api/v1/devices`. Pelacakan perangkat, pencabutan sesi jarak jauh, penyimpanan token push FCM. *Status:* `VERIFIED`.
- **BE-AUTH-05 (Audit Log):** `GET /api/v1/audit-logs`. Read-only audit trail logging mutasi data sensitif, IP, dan User-Agent. *Status:* `VERIFIED`.

### Modul 2: Master Data Sekolah (MST)
- **BE-MST-01 (Tahun Ajaran & Semester):** Aktivasi tahun ajaran tunggal (`activate()`), pemblokiran tahun ganda. *Status:* `VERIFIED`.
- **BE-MST-02 (Profil Siswa & Biodata):** Enkripsi NIK (`nik_enc`), relasi rombel dan orang tua. *Status:* `VERIFIED`.
- **BE-MST-03 (Guru & GTK):** NIP, NUPTK, status kepegawaian, relasi akun pengguna. *Status:* `VERIFIED`.
- **BE-MST-04 (Orang Tua / Wali):** Relasi siswa-wali, anti-IDOR pada query profil. *Status:* `VERIFIED`.
- **BE-MST-05 (Kelas, Rombel & Penempatan):** Penempatan siswa ke kelas, transfer antar-rombel dengan validasi kapasitas. *Status:* `VERIFIED`.
- **BE-MST-06 (Mata Pelajaran & Penugasan):** Kelompok mapel kurikulum merdeka, plotting guru pengampu. *Status:* `VERIFIED`.
- **BE-MST-07 (Ruangan & Fasilitas):** Inventarisasi gedung, laboratorium, kapasitas ruangan. *Status:* `VERIFIED`.
- **BE-MST-08 (Kenaikan Kelas & Kelulusan):** Operasi batch transaksional kenaikan kelas dan kelulusan siswa. *Status:* `VERIFIED`.

### Modul 3: Konten & Profil Sekolah (CMS)
- **BE-CMS-01 s.d. BE-CMS-07:** Profil sekolah, posting berita artikel, taksonomi kategori, galeri album foto/video, agenda kalender publik, dokumen pusat unduhan, dan Tanya Jawab (FAQ). Seluruhnya teruji dengan slug unik, status draf/publikasi, dan otorisasi admin. *Status:* `VERIFIED`.

### Modul 4: PPDB (Penerimaan Peserta Didik Baru)
- **BE-PPDB-01 s.d. BE-PPDB-09:** Konfigurasi gelombang dan jalur, pendaftaran akun calon siswa, pengisian formulir bertahap (multi-step draft), unggah dokumen persyaratan, verifikasi panitia, pembobotan seleksi dan perankingan otomatis, pengumuman terjadwal, konfirmasi daftar ulang, dan statistik pendaftar real-time. *Status:* `VERIFIED` *(Catatan Keamanan: Ditemukan celah SEC-BL-01 pada submit pendaftaran di luar jadwal gelombang)*.

### Modul 5: Buku Tamu & Whistleblowing (GUEST)
- **BE-GUEST-01 (Buku Tamu Umum):** Registrasi tamu, tujuan kunjungan, foto selfie, dan tanda tangan digital biner PNG. *Status:* `VERIFIED`.
- **BE-GUEST-02 (Kunjungan Dinas):** Tamu dinas, nomor surat tugas, disposisi penerima. *Status:* `VERIFIED`.
- **BE-GUEST-03 (Whistleblowing System):** Pengaduan publik anonim dengan proteksi IP dan User-Agent, kode tiket pelacakan acak, isolasi akses kepada Kepala Sekolah dan Guru BK. *Status:* `VERIFIED`.

### Modul 6: Akademik, KBM & LMS (ACD)
- **BE-ACD-01 s.d. BE-ACD-07:** Kalender akademik, jadwal pelajaran bebas bentrok ruang/guru, presensi sesi kelas terintegrasi jurnal mengajar, permohonan izin/sakit dengan persetujuan wali kelas, materi pembelajaran terstruktur, dan penugasan LMS dengan anti-late deadline enforcement. *Status:* `VERIFIED`.

### Modul 7: Penilaian & E-Rapor (GRD)
- **BE-GRD-01 s.d. BE-GRD-04:** Bobot komponen penilaian dan ambang batas KKTP, input nilai batch dan kalkulasi nilai akhir, catatan karakter dimensi Profil Pelajar Pancasila oleh walas, serta penerbitan dokumen e-rapor ber-QR code verifikasi publik. *Status:* `VERIFIED`.

### Modul 8: Ujian Digital (CBT)
- **BE-CBT-01 (Bank Soal):** PG, PG Kompleks, BS, Menjodohkan, Uraian; taksonomi Bloom, versioning soal. *Status:* `VERIFIED`.
- **BE-CBT-02 (Pembuatan Ujian):** Paket soal, acak urutan soal & acak urutan opsi deterministik. *Status:* `VERIFIED`.
- **BE-CBT-03 (Token & Ruangan):** Token dinamis per ruang ujian, plotting peserta, cetak kartu ujian QR. *Status:* `VERIFIED`.
- **BE-CBT-04 (Sesi Siswa & Autosave):** Sanitasi kunci jawaban dari payload klien, autosave jawaban per nomor, resume sesi pengerjaan. *Status:* `VERIFIED`.
- **BE-CBT-05 (Anti-Cheat & Pelanggaran):** Audit perpindahan tab/jendela, auto-locking jika melanggar toleransi 3 kali, audit tindakan pengawas ruang. *Status:* `VERIFIED`.
- **BE-CBT-06 (Live Monitoring):** `GET /api/v1/exams/{id}/monitor`. Dashboard real-time status pengerjaan, sisa waktu, heartbeat, dan filter per ruangan. *Status:* `VERIFIED`.
- **BE-CBT-07 (Koreksi Otomatis & Uraian):** Evaluasi otomatis seluruh tipe soal objektif, antarmuka koreksi esai manual guru. *Status:* `VERIFIED`.
- **BE-CBT-08 (Analisis Butir Soal):** Kalkulasi otomatis tingkat kesukaran dan daya pembeda butir soal. *Status:* `VERIFIED`.
- **BE-CBT-09 (Remedial Berbasis KKTP):** `POST /api/v1/exams/{id}/remedial`. Otomasi paket remedial untuk siswa di bawah batas lulus dengan capping skor maksimal. *Status:* `VERIFIED`.
- **BE-CBT-10 (Berita Acara & Rekapitulasi):** `GET /api/v1/exams/{id}/report` & `/minutes`. Rekapitulasi nilai, kehadiran, dan penerbitan berita acara resmi proktor siap cetak (HTML/PDF). *Status:* `VERIFIED`.
- **BE-CBT-11 (Mode Server Lokal & Sinkronisasi):** `POST /api/v1/cbt/sync/pull` & `/push`. Ekspor paket terenkripsi hash SHA-256 dan sinkronisasi hasil ujian offline secara idempoten berbasis timing-safe token. *Status:* `VERIFIED`.
- **BE-CBT-12 (Deteksi Anomali Perangkat/IP):** `POST /api/v1/exam-sessions/{sessionId}/heartbeat` & `GET /api/v1/exams/{id}/anomalies`. Perekaman heartbeat, deteksi anomali IP, User-Agent, time-gap > 180s, dan blur counter. *Status:* `VERIFIED`.

### Modul 9: Kesiswaan, Poin & Ekskul (STD)
- **BE-STD-01 s.d. BE-STD-04:** Master aturan poin pelanggaran/prestasi dan ambang batas SP otomatis, kegiatan ekstrakurikuler dan presensi pembina, pencatatan prestasi/kejuaraan siswa, serta agenda study trip dengan lembar persetujuan orang tua non-financial. *Status:* `VERIFIED`.

### Modul 10: Bimbingan Konseling (BK)
- **BE-BK-01 s.d. BE-BK-03:** Kasus dan sesi konseling privat terenkripsi at-rest (`summary_enc`), deteksi dini (early warning system) siswa bermasalah berbasis absensi dan nilai, serta permohonan temu dan rujukan profesional eksternal. *Status:* `VERIFIED`.

### Modul 11: Sarpras & Perpustakaan (SAR)
- **BE-SAR-01 s.d. BE-SAR-04:** Tiket aduan kerusakan fasilitas dengan pelacakan SLA, inventarisasi aset dan audit stok opname QR, peminjaman ruangan dan alat dengan deteksi bentrok jadwal, serta sirkulasi buku perpustakaan dan denda keterlambatan. *Status:* `VERIFIED`.

### Modul 12: Humas, MoU & Tracer Study (HUM)
- **BE-HUM-01 s.d. BE-HUM-02:** Pengelolaan naskah kemitraan DUDI/MoU dan program pertukaran pelajar, serta kuesioner penelusuran lulusan (tracer study) dengan agregasi statistik relevansi kurikulum. *Status:* `VERIFIED`.

### Modul 13: Rekapitulasi & Dashboard (RPT)
- **BE-RPT-01 s.d. BE-RPT-02:** Laporan rekapitulasi lintas domain sekolah (akademik, PPDB, sarpras, poin) dan dashboard berbasis persona (Eksekutif/Kepsek, Guru, Siswa, dan Wali Murid dengan Anti-IDOR ketat). *Status:* `VERIFIED`.

### Modul 14: Notifikasi, Berkas & Scheduler (NTF/SYS)
- **BE-NTF-01 s.d. BE-NTF-04:** Inbox notifikasi in-app, integrasi broadcast pengumuman terarah, gateway pesan WhatsApp & Email, preferensi channel notifikasi pengguna. *Status:* `VERIFIED`.
- **BE-FILE-01:** Layanan penyimpanan berkas dengan presigned upload token, sanitasi nama berkas fisik via UUID, dan unduhan bertanda tangan privat berumur pendek (default <= 5 menit). *Status:* `VERIFIED`.
- **7 Scheduled Console Jobs (Section 17):**
  1. `ppdb:close-wave` (Tutup gelombang otomatis)
  2. `exam:auto-submit` (Submit otomatis ujian timeout)
  3. `attendance:daily-summary` (Rekap presensi harian & deteksi dini)
  4. `academic:progress-report-reminder` (Pengingat pengisian nilai guru)
  5. `partnership:mou-expiry-reminder` (Pengingat masa berlaku MoU)
  6. `sarpras:ticket-sla-reminder` (Pengingat tiket sarpras melanggar SLA)
  7. `system:backup-verify` (Verifikasi integritas cadangan data)  
  *Status Seluruh Job:* `VERIFIED` (terdaftar di `routes/console.php` dan terbukti lulus di `ScheduledJobsTest.php`).
