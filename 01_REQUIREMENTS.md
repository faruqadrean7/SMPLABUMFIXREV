# 01. Requirements

## 1. Latar Belakang
Sekolah membutuhkan portal terpadu ("satu pintu") yang menggabungkan: website profil, PPDB, administrasi akademik, ujian digital (CBT), kesiswaan, sarpras, humas, dan aplikasi mobile untuk ortu/guru/siswa. Saat ini proses tersebar dan manual.

## 2. Tujuan Produk
1. Satu data induk siswa/guru/kelas yang dipakai semua modul.
2. Proses PPDB, presensi, nilai, rapor, dan ujian berjalan digital dari awal sampai akhir.
3. Orang tua dapat memantau anak lewat HP secara real-time.
4. Kepala sekolah memantau kondisi sekolah lewat dashboard eksekutif.
5. Arsitektur API terpisah agar web, admin, dan mobile berbagi satu backend.

### Indikator Keberhasilan
| Indikator | Target |
|---|---|
| Pendaftar PPDB mendaftar online | ≥ 90% |
| Presensi guru dicatat digital | ≥ 95% kelas/hari |
| Nilai bulanan terinput tepat waktu | ≥ 90% guru |
| Ortu aktif di mobile app | ≥ 70% dalam 3 bulan |
| Ujian CBT selesai tanpa gangguan sistem | ≥ 99% sesi |

## 3. Ruang Lingkup
### Dalam scope (v1)
Website publik, PPDB, sistem admin, API backend, mobile (ortu/guru/siswa), CBT, akademik, kesiswaan, BK, sarpras, perpustakaan dasar, humas, alumni, notifikasi, laporan, dashboard eksekutif.

### Di luar scope v1 (perlu keputusan sekolah)
| Item | Status |
|---|---|
| Keuangan/SPP & pembayaran online | Belum ada di dokumen sekolah, hanya "status administrasi" |
| Chat real-time ortu-guru | v1 cukup tombol hubungi (WhatsApp/tiket) |
| Integrasi Dapodik / e-Rapor Kemdikbud | Belum ditetapkan |
| Absensi guru biometrik | Tidak termasuk |
| E-learning video conference | Tidak termasuk |

## 4. Pengguna (Role)
| Kode role | Nama | Platform utama |
|---|---|---|
| `super_admin` | Super Admin | Web Portal (Admin) |
| `admin_tu` | Admin Tata Usaha | Web Portal (Admin) |
| `principal` | Kepala Sekolah | Web Portal (Admin), Mobile Android |
| `teacher` | Guru | Web Portal, Mobile Android |
| `homeroom` | Wali Kelas (guru + penugasan kelas) | Web Portal, Mobile Android |
| `counselor` | Guru BK | Web Portal, Mobile Android |
| `facility_staff` | Staf Sarpras | Web Portal (Admin) |
| `ppdb_committee` | Panitia PPDB | Web Portal (Admin) |
| `librarian` | Pustakawan | Web Portal (Admin) |
| `student` | Siswa | Web Portal (CBT/LMS/Rapor), Mobile Android |
| `guardian` | Orang Tua/Wali | Web Portal (Pantau/Rapor), Mobile Android |
| `guest` | Tamu/publik/calon siswa | Web Portal (Publik & PPDB) |

Satu user bisa punya lebih dari satu role (contoh: guru sekaligus wali kelas).

## 5. Kebutuhan Fungsional (ringkas)
Rincian ada di PRD per platform dan `13_MASTER_MODULES.md`. Ringkasan per 14 modul:

| Modul | Prioritas | Kebutuhan inti |
|---|:-:|---|
| AUTH | P0 | Login, refresh token, reset password, RBAC multi-role, audit log, sesi perangkat |
| MST | P0 | Master tahun ajaran, siswa, guru, staf, ortu, rombel/kelas, mapel, ruang, import Excel |
| CMS | P0 | Profil sekolah, berita & pengumuman, agenda publik, unduhan, galeri & mading digital [P1] |
| PPDB | P0 | Gelombang/jalur/kuota, formulir bertahap, berkas, verifikasi, seleksi ranking, daftar ulang, konversi siswa |
| GUEST | P0 | Buku tamu digital + notifikasi otomatis, aspirasi/whistleblowing tiket anonim, tanda tangan digital tamu [P2] |
| ACD | P0 | Jadwal pelajaran, jurnal mengajar guru, presensi siswa & gerbang, izin/sakit, presensi staf [P1], modul ajar & tugas LMS [P1] |
| GRD | P0 | Komponen & buku nilai bulanan, catatan karakter walas, e-progress report PDF+QR, e-rapor semester, antrean generate |
| CBT | P0 | Bank soal (6 tipe, CP, LOTS/MOTS/HOTS), jadwal ujian, token, anti-cheat, monitor live, koreksi, analisis butir, deteksi anomali IP/device [P1] |
| STD | P1 | Poin pelanggaran & prestasi, ekskul/OSIS [P1], lomba [P1], study trip / outing class [P2] (biaya hanya catatan teks) |
| BK | P1 | Kasus & konseling privat terenkripsi, riwayat pembinaan, deteksi dini (kehadiran/nilai/poin), permintaan konseling [P1] |
| SAR | P1 | Booking fasilitas [P0], aset inventaris QR [P1], helpdesk perbaikan [P0], perpustakaan & alat lab [P1] |
| HUM | P2 | Kemitraan & MoU [P1], program pertukaran pelajar [P1], direktori alumni & survei tracer study [P1] |
| RPT | P0 | Laporan rekapitulasi data (kehadiran, nilai, PPDB, poin, tiket, pinjaman), ekspor PDF/Excel (queue), dashboard eksekutif kepsek & manajerial |
| NTF/SYS | P0 | Notifikasi in-app & push, WhatsApp & email [P1], pengumuman terarah, file storage bertanda tangan, scheduler, job queue, app_config |

## 6. Kebutuhan Non-Fungsional
| ID | Kebutuhan | Target / Ukuran |
|---|---|---|
| NFR-PERF-01 | Waktu respons API (non-CBT) | p95 < 500 ms |
| NFR-PERF-02 | Beban CBT serentak | ≥ 500 siswa aktif, autosave p95 < 300 ms |
| NFR-PERF-03 | Website publik | Lighthouse Performance ≥ 90, LCP < 2,5 s |
| NFR-PERF-04 | Generate rapor massal | 500 PDF < 10 menit (via queue) |
| NFR-AVAIL-01 | Ketersediaan | 99,5% bulanan; masa ujian dijaga ketat |
| NFR-SEC-01 | Keamanan | Lihat dokumen 09 |
| NFR-PRIV-01 | Privasi data anak | Patuh UU PDP, akses minimum |
| NFR-BKP-01 | Backup | DB harian, retensi 30 hari, uji restore tiap kuartal |
| NFR-OBS-01 | Observability | Log terstruktur, health check, alert error |
| NFR-UX-01 | Aksesibilitas | Kontras WCAG AA, target sentuh ≥ 44 px |
| NFR-I18N-01 | Bahasa | Bahasa Indonesia utama, struktur siap multi-bahasa |
| NFR-COMP-01 | Kompatibilitas | Browser 2 versi terakhir; Android 8+ ; iOS 14+ |
| NFR-MNT-01 | Pemeliharaan | Test coverage backend ≥ 70% di logika bisnis |

## 7. Asumsi
- Satu sekolah (single-tenant) pada v1, tetapi skema siap multi-sekolah (kolom `school_id` pada tabel utama).
- Jenjang dan jumlah siswa akan dikonfirmasi (asumsi 300-1500 siswa).
- Internet sekolah cukup untuk CBT; mode server lokal bersifat opsional (P2).
- Sekolah menyediakan template rapor, kop surat, logo, dan data awal (Excel).

## 8. Ketergantungan Eksternal
| Layanan | Fungsi | Alternatif |
|---|---|---|
| Firebase Cloud Messaging | Push notification | OneSignal |
| WhatsApp gateway | Notifikasi/OTP | Fonnte, Wablas, WA Business API |
| SMTP/Email provider | Email | SES, Mailgun |
| Object storage | File | S3, MinIO, R2 |
| Peta | Lokasi sekolah | Google Maps / OpenStreetMap |

## 9. Pertanyaan Terbuka & Status Keputusan Sekolah
Item bertanda **[Menunggu Keputusan]** tidak boleh dirancang mendalam sampai sekolah menetapkan keputusan resmi:

1. **Keuangan / Pembayaran SPP [Menunggu Keputusan]**: Apakah modul pembayaran SPP/keuangan masuk proyek atau tidak? (v1 hanya mencatat status administrasi bebas tanggungan teks; fitur gateway keuangan ditunda).
2. **Sinkronisasi Data Dapodik / e-Rapor Kemdikbud [Menunggu Keputusan]**: Apakah sistem wajib ekspor/sinkronisasi dua arah ke Dapodik? (v1 menyediakan import/export spreadsheet format standar).
3. **Struktur Biaya & Pembiayaan Study Trip [Menunggu Keputusan]**: Biaya pelaksanaan trip hanya dicatat sebagai teks catatan non-keuangan di formulir izin ortu, tanpa modul buku kas/pembayaran digital.
4. **Chat Real-time Interaktif Ortu-Guru [Menunggu Keputusan]**: Obrolan langsung pesan instan tidak dibangun pada v1; komunikasi dialihkan ke integrasi tombol WhatsApp resmi atau tiket permohonan konseling/tamu.
5. Jenjang sekolah dan jumlah siswa definitif? (sizing server, variasi kurikulum P5/KKTP).
6. Hosting cloud vs server lokal sekolah untuk server ujian CBT (arsitektur deploy).
7. Format resmi rapor semester, progress report, dan kop surat resmi dari sekolah.
8. Skema seleksi PPDB: variasi jalur (prestasi, afirmasi, zonasi/tes), bobot penilaian, dan rumus ranking tiap jalur.
9. Sistem poin pelanggaran: tabel master jenis poin dan batasan sanksi bertingkat resmi dari sekolah.
10. Kebijakan retensi data alumni dan siswa lulus.

## 10. Glosarium
| Istilah | Arti |
|---|---|
| PPDB | Penerimaan Peserta Didik Baru |
| CBT | Computer Based Test |
| STS / SAS (ASAT) | Sumatif Tengah Semester / Sumatif Akhir Semester |
| CP | Capaian Pembelajaran |
| ATP | Alur Tujuan Pembelajaran |
| KKTP | Kriteria Ketercapaian Tujuan Pembelajaran (pengganti standar KKM pada Kurikulum Merdeka) |
| P5 | Projek Penguatan Profil Pelajar Pancasila |
| LOTS/MOTS/HOTS | Tingkat kognitif soal: rendah/menengah/tinggi |
| Walas | Wali Kelas |
| BK | Bimbingan dan Konseling |
| Sarpras | Sarana dan Prasarana |
| E-Progress Report | Ringkasan nilai bulanan 1 halaman dengan tanda tangan QR |
| RBAC | Role-Based Access Control |

## Changelog
- 1.0: Draf awal.
- 1.1: Penyelarasan master 14 modul (pemisahan GRD & GUEST, penambahan RPT), pembaruan istilah KKTP, penegasan 4 area berstatus "Menunggu Keputusan" (Dapodik, Keuangan/SPP, Chat real-time, Pembiayaan trip).
