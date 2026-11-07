# 04. PRD Backend API

Platform: NestJS + PostgreSQL + Redis. Kontrak: OpenAPI 3. Prefiks: `/api/v1`.
Format ID: `BE-{MODUL}-{NN}`. Prioritas: P0/P1/P2 (lihat README).

## 1. Standar API (berlaku semua modul)

### 1.1 Format
```jsonc
// Sukses (tunggal)
{ "data": { ... } }
// Sukses (daftar)
{ "data": [ ... ], "meta": { "page": 1, "limit": 20, "total": 134, "totalPages": 7 } }
// Error
{ "error": { "code": "VALIDATION_FAILED", "message": "Data tidak valid", "details": [{ "field": "nik", "message": "Wajib 16 digit" }], "requestId": "..." } }
```

### 1.2 Kode error baku
| HTTP | code | Arti |
|---|---|---|
| 400 | `VALIDATION_FAILED` | Input salah |
| 401 | `UNAUTHENTICATED` / `TOKEN_EXPIRED` | Belum login/token habis |
| 403 | `FORBIDDEN` | Tidak punya permission |
| 404 | `NOT_FOUND` | Data tidak ada |
| 409 | `CONFLICT` | Duplikat/bentrok (jadwal, NIK, kuota) |
| 422 | `BUSINESS_RULE_VIOLATION` | Melanggar aturan bisnis |
| 429 | `RATE_LIMITED` | Terlalu banyak request |
| 500 | `INTERNAL_ERROR` | Kesalahan server |

### 1.3 Konvensi
- Query list: `?page=&limit=&q=&sort=field:asc&filter[status]=...` (limit maks 100).
- Penghapusan: **soft delete** (`deleted_at`) untuk data akademik/personal.
- Semua tulis (POST/PUT/PATCH/DELETE) pada data penting menulis `audit_logs`.
- Idempotency: header `Idempotency-Key` untuk submit jawaban CBT, pendaftaran PPDB, pembayaran.
- Pencarian teks: `q` memakai ILIKE/trigram (P1: full-text search).
- Semua respons menyertakan header `X-Request-Id`.
- Rate limit: publik 60/menit/IP; login 5/menit/akun+IP; terautentikasi 300/menit/user.
- Waktu: ISO 8601 UTC.

### 1.4 Permission
Format `modul.resource.aksi`, contoh `ppdb.application.verify`, `academic.grade.write`, `counseling.case.read`.
Role = kumpulan permission. User dapat memiliki beberapa role. Cek permission dilakukan di guard + policy level data (misal guru hanya kelas yang diajarnya).

---

## 2. AUTH: Autentikasi & Otorisasi

### BE-AUTH-01: Login & token [P0]
Aktor: semua pengguna terdaftar. 
Aturan: login pakai `username/email/NIS/NIP` + password; maks 5 gagal → kunci 15 menit; token akses 15 m, refresh 14 hari (rotasi).
AC:
- Login benar mengembalikan access+refresh token dan profil + daftar role/permission.
- Password salah tidak membocorkan apakah akun ada.
- Refresh token bekas dipakai ulang → semua sesi perangkat dicabut.
Endpoint: `POST /auth/login`, `POST /auth/refresh`, `POST /auth/logout`, `GET /auth/me`.

### BE-AUTH-02: Reset & ubah password [P0]
Aturan: reset via link/OTP (email/WA) berlaku 15 menit sekali pakai; password min 8 karakter, hash argon2id; akun baru wajib ganti password saat login pertama.
Endpoint: `POST /auth/forgot-password`, `POST /auth/reset-password`, `POST /auth/change-password`.

### BE-AUTH-03: RBAC [P0]
AC: CRUD role, atur permission per role, assign multi-role ke user, guard menolak 403 bila tidak punya permission. Role bawaan (seed) tidak bisa dihapus.
Endpoint: `/roles`, `/permissions`, `/users/{id}/roles`.

### BE-AUTH-04: Manajemen sesi perangkat [P1]
Daftar perangkat aktif, cabut sesi, registrasi device token FCM.
Endpoint: `GET /auth/sessions`, `DELETE /auth/sessions/{id}`, `POST /devices`.

### BE-AUTH-05: Audit log [P0]
Mencatat aktor, aksi, entitas, nilai lama/baru (diff), IP, user-agent, waktu. Hanya baca; tidak bisa diubah/dihapus lewat API.
Endpoint: `GET /audit-logs` (permission `audit.read`).

---

## 3. MST: Master Data

### BE-MST-01: Tahun ajaran & semester [P0]
Aturan: hanya satu tahun ajaran/semester aktif; mengganti aktif memerlukan konfirmasi dan tidak menghapus data lama.
Endpoint: `/academic-years`, `/semesters`, `PATCH /semesters/{id}/activate`.

### BE-MST-02: Siswa [P0]
Field inti: NIS, NISN, NIK, nama, jenis kelamin, tempat/tanggal lahir, agama, alamat, foto, status (aktif/lulus/pindah/keluar), tanggal masuk.
Aturan: NIS dan NISN unik; NIK terenkripsi; perubahan status tercatat riwayatnya.
AC: CRUD, pencarian, filter kelas/status, import Excel dengan validasi baris (laporan error per baris), ekspor.
Endpoint: `/students`, `/students/import`, `/students/export`.

### BE-MST-03: Guru & staf [P0]
NIP/NUPTK, nama, jabatan, mapel diampu, status kepegawaian, foto. CRUD + import.
Endpoint: `/teachers`, `/staff`.

### BE-MST-04: Orang tua/wali & relasi [P0]
Aturan: satu wali bisa banyak anak; satu siswa bisa banyak wali; ada `relationship` dan `is_primary`. Orang tua hanya melihat anak yang tertaut.
Endpoint: `/guardians`, `/students/{id}/guardians`.

### BE-MST-05: Kelas & rombel [P0]
Kelas per tingkat/jurusan/tahun ajaran, wali kelas, kapasitas, daftar siswa (riwayat kelas per tahun). Pindah kelas tercatat.
Endpoint: `/classes`, `/classes/{id}/students`.

### BE-MST-06: Mata pelajaran & penugasan guru [P0]
Mapel, kelompok mapel, penugasan guru-mapel-kelas per semester, jam per minggu.
Endpoint: `/subjects`, `/teaching-assignments`.

### BE-MST-07: Ruang & fasilitas dasar [P1]
Ruang kelas/lab/aula dengan kapasitas dan tipe (dipakai jadwal, ujian, booking).
Endpoint: `/rooms`.

### BE-MST-08: Kenaikan kelas & kelulusan [P1]
Proses massal dengan pratinjau; aturan kenaikan dikonfigurasi; bisa dibatalkan sebelum dikunci.
Endpoint: `POST /promotions/preview`, `POST /promotions/execute`.

---

## 4. CMS: Konten & Profil

### BE-CMS-01: Pengaturan situs & profil sekolah [P0]
Nama, logo, kontak, alamat, koordinat, sosial media, visi-misi, sambutan, akreditasi, program unggulan.
Endpoint: `GET /public/site`, `PUT /site-settings`.

### BE-CMS-02: Berita & pengumuman [P0]
Status draft/terbit/arsip, kategori, tag, gambar sampul, slug, tanggal terbit terjadwal, target (publik/internal).
Endpoint: `/posts`, `/public/posts`, `/post-categories`.

### BE-CMS-03: Galeri & mading digital [P1]
Album, foto/video (URL), buletin/majalah (PDF) dengan sampul.
Endpoint: `/albums`, `/media`, `/bulletins`.

### BE-CMS-04: Agenda/kalender [P0]
Agenda dengan visibilitas publik/internal, kategori (libur, ujian, event), rentang tanggal.
Endpoint: `/events`, `/public/events`.

### BE-CMS-05: Direktori guru & staf publik [P0]
Menampilkan subset data guru yang boleh publik (nama, foto, mapel). Data sensitif tidak pernah keluar lewat endpoint publik.
Endpoint: `/public/teachers`.

### BE-CMS-06: Pusat unduhan [P1]
Dokumen dengan kategori dan visibilitas publik/internal.
Endpoint: `/downloads`, `/public/downloads`.

### BE-CMS-07: Halaman statis & menu [P2]
Halaman custom dan pengaturan navigasi.

---

## 5. PPDB

### BE-PPDB-01: Konfigurasi PPDB [P0]
Gelombang (tanggal buka/tutup), jalur (zonasi, prestasi, afirmasi, mutasi, reguler, dsb), kuota per jalur/jurusan, daftar syarat berkas per jalur, biaya (info).
Aturan: pendaftaran hanya dalam gelombang aktif; kuota tidak boleh melampaui kapasitas.
Endpoint: `/ppdb/waves`, `/ppdb/tracks`, `/ppdb/requirements`.

### BE-PPDB-02: Akun calon siswa [P0]
Registrasi dengan email/nomor WA + verifikasi OTP; 1 NIK = 1 pendaftaran aktif.
Endpoint: `POST /ppdb/register`, `POST /ppdb/verify-otp`.

### BE-PPDB-03: Form pendaftaran bertahap [P0]
Langkah: data diri → orang tua → asal sekolah → pilihan jalur/jurusan → berkas → konfirmasi. Simpan draf tiap langkah; submit mengunci data.
Status: `draft → submitted → verified | revision → selected | waitlist | rejected → enrolled`.
AC:
- Draf bisa dilanjutkan di perangkat lain.
- Submit menghasilkan nomor pendaftaran unik.
- Setelah submit, data hanya dapat diubah bila status `revision`.
Endpoint: `/ppdb/applications`, `PATCH /ppdb/applications/{id}/steps/{step}`, `POST /ppdb/applications/{id}/submit`.

### BE-PPDB-04: Upload berkas [P0]
Tipe PDF/JPG/PNG maks 2 MB; berkas privat; tiap berkas punya status (menunggu/valid/ditolak + alasan).
Endpoint: `POST /ppdb/applications/{id}/documents`.

### BE-PPDB-05: Verifikasi berkas [P0]
Panitia menandai valid/ditolak per berkas; ditolak → notifikasi revisi. Catatan verifikasi tersimpan.
Endpoint: `PATCH /ppdb/documents/{id}/verify`, `PATCH /ppdb/applications/{id}/verify`.

### BE-PPDB-06: Seleksi & ranking [P0]
Skor dihitung sesuai rumus per jalur (konfigurasi); ranking otomatis; penetapan diterima sesuai kuota; cadangan (waitlist). Penetapan dapat dikunci dan dibatalkan dengan alasan tercatat.
Endpoint: `POST /ppdb/waves/{id}/rank`, `POST /ppdb/waves/{id}/publish-results`.

### BE-PPDB-07: Pengumuman hasil [P0]
Publik cek hasil dengan nomor pendaftaran + tanggal lahir. Dibatasi rate limit. Tampil setelah waktu publish.
Endpoint: `POST /public/ppdb/results/check`.

### BE-PPDB-08: Daftar ulang & konversi ke siswa [P0]
Diterima mengonfirmasi daftar ulang; admin memvalidasi → sistem membuat data siswa, akun siswa & ortu, dan menempatkan di kelas (manual/otomatis).
Aturan: konversi idempoten; tidak membuat duplikat siswa.
Endpoint: `POST /ppdb/applications/{id}/re-register`, `POST /ppdb/applications/{id}/enroll`.

### BE-PPDB-09: Bukti pendaftaran & statistik [P1]
PDF bukti (QR), statistik pendaftar per jalur/hari (publik opsional).
Endpoint: `GET /ppdb/applications/{id}/receipt`, `GET /ppdb/stats`.

---

## 6. GUEST: Buku Tamu & Aspirasi

### BE-GUEST-01: Buku tamu digital [P0]
Nama, instansi, keperluan, tujuan (guru/pejabat), foto opsional, jam masuk/keluar. Saat disimpan memicu notifikasi ke pihak tujuan.
Endpoint: `POST /public/guests`, `GET /guests`, `PATCH /guests/{id}/checkout`.

### BE-GUEST-02: Aspirasi/whistleblowing [P0]
Kategori, isi, lampiran, opsi anonim, kode tiket pelacak. Pelapor dapat mengecek status tanpa login memakai kode. Anonim: IP tidak disimpan.
Status: `baru → ditinjau → ditindaklanjuti → selesai/ditolak`.
Endpoint: `POST /public/complaints`, `GET /public/complaints/{code}`, `/complaints`.

### BE-GUEST-03: Tanda tangan digital buku tamu [P2]
Fitur penandatanganan digital berbasis canvas/sentuh pada formulir buku tamu digital; gambar tanda tangan disimpan dengan hash integritas dan tersemat pada log kunjungan tamu.
Endpoint: `POST /public/guests/{id}/signature`, `GET /guests/{id}/signature`.

---

## 7. ACD: Akademik

### BE-ACD-01: Jadwal pelajaran [P0]
Slot jam, jadwal per kelas/guru/ruang, deteksi bentrok (guru, ruang, kelas) → 409.
Endpoint: `/schedules`, `/schedules/conflicts/check`, `/time-slots`.

### BE-ACD-02: Jurnal mengajar [P0]
Per pertemuan: tanggal, jam, kelas, mapel, materi, metode, catatan. Terhubung presensi siswa. Hanya guru penugas yang bisa mengisi; edit dibatasi N hari (konfigurasi).
Endpoint: `/journals`.

### BE-ACD-03: Presensi siswa [P0]
Status hadir/izin/sakit/alpa/terlambat; per sesi kelas (dari jurnal) dan/atau presensi gerbang harian; rekap harian/bulanan/semester.
Aturan: izin/sakit dapat berasal dari pengajuan ortu yang disetujui walas.
Endpoint: `/attendances`, `POST /attendances/bulk`, `/attendances/summary`.

### BE-ACD-04: Izin/sakit [P0]
Ortu/siswa mengajukan (tanggal, alasan, foto surat) → walas setujui/tolak → presensi otomatis terisi.
Endpoint: `/leave-requests`, `PATCH /leave-requests/{id}/decision`.

### BE-ACD-05: Presensi guru & staf [P1]
Check-in/out (opsional GPS/selfie dengan radius), rekap, persentase kehadiran untuk dashboard.
Endpoint: `/staff-attendances`.

### BE-ACD-06: Bank modul ajar & perangkat [P1]
Repositori modul ajar, ATP, silabus, modul P5 per tingkat/mapel dengan versi.
Endpoint: `/teaching-materials`.

### BE-ACD-07: Tugas & materi pembelajaran (LMS ringan) [P1]
Guru unggah materi dan tugas; siswa mengumpulkan; guru menilai; masuk komponen nilai.
Endpoint: `/materials`, `/assignments`, `/assignments/{id}/submissions`.

---

## 8. GRD: Penilaian & Rapor

### BE-GRD-01: Komponen & buku nilai bulanan [P0]
Komponen nilai (harian, tugas, proyek, ulangan, sikap) dengan bobot per mapel; input massal per kelas; validasi 0-100; kunci periode.
Aturan: nilai dari CBT dapat otomatis masuk komponen yang dipetakan; perubahan nilai setelah kunci perlu persetujuan dan tercatat.
Endpoint: `/grade-components`, `/grades`, `POST /grades/bulk`, `PATCH /grade-periods/{id}/lock`.

### BE-GRD-02: Catatan karakter bulanan [P0]
Penilaian profil pelajar/pembiasaan (skala/deskripsi) + catatan wali kelas per siswa per bulan.
Endpoint: `/character-notes`.

### BE-GRD-03: E-Progress Report bulanan [P0]
Ringkasan 1 halaman: nilai per mapel, % kehadiran, poin sikap, catatan walas. Alur: generate (queue) → kepala sekolah ACC → publish ke ortu/siswa. PDF bertanda tangan QR; QR mengarah ke halaman verifikasi publik yang memvalidasi keaslian.
Status: `draft → menunggu ACC → disetujui → terbit`.
Endpoint: `POST /progress-reports/generate`, `PATCH /progress-reports/{id}/approve`, `GET /progress-reports/{id}/pdf`, `GET /public/verify/{token}`.

### BE-GRD-04: E-Rapor semester [P0]
Nilai akhir (rumus bobot), deskripsi capaian otomatis (template + nilai), absensi, ekstrakurikuler, catatan walas. Kunci/buka oleh admin, pengesahan kepala sekolah, cetak massal (queue), arsip.
Endpoint: `/report-cards`, `POST /report-cards/generate`, `PATCH /report-cards/{id}/lock`.

---

## 9. CBT: Ujian Digital

### BE-CBT-01: Bank soal [P0]
Tipe: PG, PG kompleks, benar/salah, menjodohkan, isian singkat, uraian. Atribut: mapel, tingkat, bab, CP, level kognitif (LOTS/MOTS/HOTS), tingkat kesulitan, bobot, pembahasan, lampiran (gambar/audio), rumus (LaTeX).
Aturan: soal yang sudah dipakai ujian terkunci edit (buat versi baru).
Endpoint: `/questions`, `/question-packages`, `POST /questions/import`.

### BE-CBT-02: Pembuatan ujian [P0]
Jenis: kuis/ulangan harian, STS, SAS/ASAT, remedial, pengayaan. Atur: jadwal, durasi, KKTP, kelas/peserta, ruang & sesi, acak soal/opsi, paket A/B, bobot skor, aturan lihat hasil/pembahasan.
Endpoint: `/exams`, `/exams/{id}/questions`, `/exams/{id}/rooms`.

### BE-CBT-03: Token & plotting ruang [P0]
Token ujian per sesi/ruang (kedaluwarsa), plotting peserta-ruang-pengawas, kartu ujian.
Endpoint: `POST /exams/{id}/token`, `/exam-rooms`.

### BE-CBT-04: Sesi pengerjaan [P0]
Mulai (validasi token, jendela waktu, 1 sesi aktif), ambil soal terurut (seed acak tetap per siswa), autosave jawaban idempotent, tandai ragu, lanjut setelah putus, submit, auto-submit saat waktu habis (waktu server).
Endpoint: `POST /exams/{id}/start`, `PUT /exam-sessions/{id}/answers`, `POST /exam-sessions/{id}/submit`, `GET /exam-sessions/{id}`.

### BE-CBT-05: Anti-cheat & pelanggaran [P0]
Klien melaporkan event (pindah tab, keluar fullscreen, blur); server mencatat; batas pelanggaran → sesi dikunci menunggu pengawas.
Endpoint: `POST /exam-sessions/{id}/violations`.

### BE-CBT-06: Monitoring live [P0]
Status peserta per ruang (belum mulai/mengerjakan/selesai/terkunci), progres, pelanggaran; aksi pengawas: reset sesi, tambah waktu, paksa selesai, buka kunci (semua diaudit). Realtime via WebSocket.
Endpoint: `GET /exams/{id}/monitor`, WS `/ws/exams/{id}`, `POST /exam-sessions/{id}/actions`.

### BE-CBT-07: Koreksi & penilaian [P0]
PG/BS/PG kompleks otomatis (aturan skor parsial dikonfigurasi); isian singkat dengan kunci alternatif; uraian dinilai guru dengan rubrik; antrean koreksi; nilai akhir masuk buku nilai.
Endpoint: `/exam-sessions/{id}/grading`, `/grading-queue`.

### BE-CBT-08: Analisis butir soal [P1]
Tingkat kesukaran, daya pembeda, efektivitas distraktor, rekap per CP; dijalankan sebagai job setelah ujian selesai.
Endpoint: `GET /exams/{id}/analysis`.

### BE-CBT-09: Remedial & pengayaan [P1]
Peserta otomatis dari siswa di bawah KKTP; buat ujian remedial dari paket soal berbeda; aturan nilai maksimum remedial dikonfigurasi.
Endpoint: `POST /exams/{id}/remedial`.

### BE-CBT-10: Berita acara & rekap ujian [P1]
PDF berita acara (hadir, pelanggaran, catatan pengawas).

### BE-CBT-11: Mode server lokal & sinkronisasi [P2]
Paket ujian diekspor ke server lokal; hasil disinkronkan kembali.

### BE-CBT-12: Deteksi anomali perangkat/IP pada sesi ujian [P1]
Pencatatan pergantian IP, perubahan user-agent/fingerprint, atau lonjakan jeda waktu janggal selama ujian berlangsung; dicatat ke tabel pelanggaran/log audit untuk ditinjau pengawas tanpa memblokir sesi ujian siswa secara otomatis.
Endpoint: `POST /exam-sessions/{id}/heartbeat`, `GET /exams/{id}/anomalies`.

### BE-CBT-11-RTE: Sanitasi & Validasi Konten Kaya Butir Soal [P0]
Format teks kaya (HTML tersanitasi) pada `body.content` (max 100.000 karakter) dan opsi jawaban (max 20.000 karakter per opsi). Sanitasi menggunakan whitelist tag (`p, br, strong, em, u, s, ul, ol, li, table, thead, tbody, tr, td, th, img, span`) dan menghapus atribut berbahaya (XSS, inline style, event handler, script) serta atribut `src` eksternal saat persist.
Endpoint: `POST /questions`, `PUT /questions/{id}`.

### BE-CBT-12-RTE: Unggah Media Gambar Butir Soal [P0]
Unggah berkas gambar pendukung soal (JPEG, PNG, WebP, GIF; maksimal 2 MB) dengan verifikasi magic bytes fisik (`getimagesize`) dan otorisasi kepemilikan bank soal (Anti-IDOR). Berkas disimpan privat dengan visibility `privat` dan bucket `cbt`, mengembalikan `file_id` dan `preview_url` bertanda-tangan (TTL 15 menit).
Endpoint: `POST /cbt/question-media` (`permission:cbt.bank.write`).

### BE-CBT-13-RTE: Resolusi Media Bertanda-Tangan & Penjagaan Kunci Jawaban [P0]
Server menyuntikkan URL bertanda-tangan (`src`) secara dinamis ke elemen `<img data-file-id="...">` pada saat pembacaan data soal terotorisasi (guru/admin show, sesi siswa). Memastikan invarian keamanan ketat: kunci jawaban (`is_correct`, `match_key`, `explanation`) tidak pernah bocor ke payload siswa saat sesi ujian aktif.
Endpoint: `GET /questions/{id}`, `GET /exams/{id}/session-questions`.

---

## 10. STD: Kesiswaan

### BE-STD-01: Poin pelanggaran & prestasi [P0]
Master jenis & bobot poin; pencatatan oleh guru; akumulasi poin; ambang memicu tindak lanjut (peringatan, panggilan ortu) otomatis tercatat dan notifikasi.
Endpoint: `/point-types`, `/student-points`, `/students/{id}/points/summary`.

### BE-STD-02: Ekstrakurikuler & OSIS [P1]
Master ekskul, pembina, pendaftaran daring (kuota), jurnal kehadiran pelatih/pembina, nilai ekskul untuk rapor, repositori proposal/laporan kegiatan.
Endpoint: `/extracurriculars`, `/extracurricular-members`, `/extracurricular-journals`, `/activity-documents`.

### BE-STD-03: Lomba & prestasi [P1]
Pendaftaran delegasi, hasil lomba, galeri piala, otomatis tercatat sebagai poin prestasi.
Endpoint: `/competitions`, `/achievements`.

### BE-STD-04: Study trip / outing class [P2]
Rencana perjalanan, rute, panduan, persetujuan ortu per siswa (izin digital), daftar peserta. Rincian biaya hanya berupa catatan teks informatif tanpa modul transaksi keuangan.
Endpoint: `/trips`, `/trips/{id}/consents`.

---

## 11. BK: Bimbingan & Konseling (data sangat sensitif)

### BE-BK-01: Kasus & sesi konseling [P0]
Konseling privat siswa/ortu, catatan sesi, riwayat pembinaan, jadwal. Hanya `counselor` (dan kepala sekolah untuk ringkasan non-detail) yang dapat membaca isi; guru lain hanya melihat status rujukan.
Aturan: catatan terenkripsi at-rest; setiap akses baca dicatat di audit.
Endpoint: `/counseling/cases`, `/counseling/sessions`, `/counseling/referrals`.

### BE-BK-02: Deteksi dini [P1]
Aturan otomatis (kehadiran < X%, nilai turun > Y poin, poin pelanggaran > Z) menghasilkan *alert* untuk BK/walas. Ambang dapat dikonfigurasi.
Endpoint: `/early-warnings`.

### BE-BK-03: Permintaan konseling [P1]
Siswa/ortu mengajukan permintaan; BK menjadwalkan.
Endpoint: `/counseling/requests`.

---

## 12. SAR: Sarana & Prasarana

### BE-SAR-01: Booking fasilitas & ruang [P0]
Cek ketersediaan real-time, pengajuan, persetujuan, deteksi bentrok, status (diajukan/disetujui/ditolak/selesai), termasuk peralatan (proyektor, sound system).
Endpoint: `/facilities`, `/bookings`, `GET /bookings/availability`.

### BE-SAR-02: Aset & inventaris QR [P1]
Registrasi aset per ruangan, kode QR/barcode, kondisi, stok opname berkala (sesi opname dan hasil).
Endpoint: `/assets`, `/asset-audits`, `GET /assets/by-code/{code}`.

### BE-SAR-03: Helpdesk perbaikan [P0]
Tiket kerusakan (lokasi, foto, prioritas) → disposisi → teknisi → status `pending/diproses/selesai` + bukti, riwayat, notifikasi ke pelapor.
Endpoint: `/tickets`, `PATCH /tickets/{id}/status`, `POST /tickets/{id}/dispose`.

### BE-SAR-04: Perpustakaan & alat lab [P1]
Katalog buku (fisik/digital), peminjaman/pengembalian, denda opsional, peminjaman alat lab.
Endpoint: `/library/books`, `/loans`.

---

## 13. HUM: Humas & Kemitraan

### BE-HUM-01: Kerja sama & MoU [P1]
Dokumen MoU, mitra, masa berlaku, pengingat kedaluwarsa, program pertukaran pelajar dan pelacakan peserta.
Endpoint: `/partnerships`, `/exchange-programs`.

### BE-HUM-02: Alumni & tracer study [P1]
Data lulusan, kelanjutan studi/kerja, kuesioner tracer, direktori alumni terbatas.
Endpoint: `/alumni`, `/tracer-surveys`.

---

---

## 14. NTF: Notifikasi

### BE-NTF-01: Notifikasi in-app & push [P0]
Registrasi device, kirim push per user/role/kelas, inbox notifikasi (baca/belum), deep link.
Endpoint: `/notifications`, `PATCH /notifications/{id}/read`, `POST /notifications/broadcast`.

### BE-NTF-02: WhatsApp & email [P1]
Adapter provider, template berparameter, antrean, retry, log status.
Endpoint: `/notification-templates`, `/notification-logs`.

### BE-NTF-03: Pengumuman terarah [P0]
Pengumuman ke semua/kelas/tingkat/role; terbit terjadwal; pelacakan dibaca.
Endpoint: `/announcements`.

### BE-NTF-04: Preferensi notifikasi [P2]
Pengguna memilih kanal dan jenis notifikasi.

---

## 15. RPT: Laporan & Dashboard Eksekutif

### BE-RPT-01: Laporan rekap [P0]
Kehadiran, nilai, PPDB, poin, tiket, peminjaman; filter periode/kelas; ekspor Excel/PDF (queue untuk data besar).
Endpoint: `/reports/{type}`, `/reports/{type}/export`.

### BE-RPT-02: Dashboard eksekutif [P0]
Agregat: CBT live (siswa aktif per ruang), ketercapaian silabus, % guru tuntas nilai, rerata nilai per angkatan, kehadiran hari ini (guru/staf/siswa), kasus kesiswaan tindak lanjut, status sarpras kritis. Cache 1-5 menit; real-time hanya CBT.
Endpoint: `GET /dashboard/executive`, `GET /dashboard/teacher`, `GET /dashboard/guardian/{studentId}`, `GET /dashboard/student`.

---

## 16. SYS & FILE: Penyimpanan & Utilitas Sistem

### BE-FILE-01: Upload bertanda tangan [P0]
Presign upload, konfirmasi, validasi tipe/ukuran, bucket privat/publik, akses unduh dengan signed URL berumur pendek.
Endpoint: `POST /files/presign`, `POST /files/confirm`, `GET /files/{id}/url`.

---

## 17. Job Terjadwal (Scheduler)
| Job | Jadwal | Fungsi |
|---|---|---|
| ppdb-close-wave | Tiap menit | Tutup gelombang saat lewat waktu |
| exam-auto-submit | Tiap menit | Submit sesi yang melewati batas |
| daily-attendance-summary | Harian malam | Rekap presensi, hitung peringatan dini |
| progress-report-reminder | Bulanan | Ingatkan guru isi nilai & walas isi karakter |
| mou-expiry-reminder | Harian | Ingatkan MoU akan berakhir |
| ticket-sla-reminder | Per jam | Tiket pending lewat SLA |
| backup-verify | Mingguan | Verifikasi backup |

## 18. Matriks Ketergantungan Fitur (ringkas)
| Fitur | Bergantung pada |
|---|---|
| BE-PPDB-08 | MST-02, MST-04, MST-05, AUTH-01 |
| BE-ACD-02, 03 | MST-05, MST-06, ACD-01 |
| BE-GRD-03 | ACD-03, GRD-01, GRD-02, STD-01, NTF-01 |
| BE-GRD-04 | GRD-01, GRD-02, STD-02 |
| BE-CBT-04 | CBT-01, CBT-02, CBT-03 |
| BE-CBT-07 | CBT-04, GRD-01 |
| BE-CBT-12 | CBT-04, AUTH-04 |
| BE-BK-02 | ACD-03, GRD-01, STD-01 |
| BE-RPT-01, 02 | Modul sumber data terkait (MST, ACD, GRD, CBT, STD, BK, SAR, PPDB) |
| Semua modul | AUTH-01, AUTH-03, AUTH-05, FILE-01 |

## 19. Acceptance Umum Backend
- Semua endpoint punya validasi DTO, dokumentasi OpenAPI, dan test minimal happy path + 1 negatif.
- Tiap endpoint dilindungi permission; skenario 401 dan 403 diuji.
- Tidak ada N+1 query pada endpoint daftar (dicek lewat log query).
- Migrasi berjalan bersih dari DB kosong dan dari snapshot versi sebelumnya.
- Seed tersedia untuk role, permission, dan data demo.

## Changelog
- 1.0: Draf awal.
- 1.1: Pemisahan GRD dari ACD (BE-GRD-01..04), pemisahan modul GUEST (BE-GUEST-01..03 dengan penambahan tanda tangan digital), penambahan BE-CBT-12 deteksi anomali sesi ujian, penyesuaian matriks dependensi lintas modul, dan penegasan status menunggu keputusan untuk pembiayaan study trip.
