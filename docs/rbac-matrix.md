# Matriks RBAC SISTLABUM: Role -> Menu -> Permissions

Dokumen ini mendefinisikan pemetaan resmi antara **Peran Pengguna (Role)**, **Menu Akses Navigasi**, dan **Hak Akses (Permissions)** pada sistem Portal Sekolah Satu Pintu (SMP Laboratorium UM).

---

## 1. Ringkasan Peran Non-Portal di `/admin`

| Role Code | Peran Resmi | Deskripsi Singkat & Tanggung Jawab |
|---|---|---|
| `super_admin` | Super Administrator | Akses tak terbatas ke seluruh sistem, audit log, konfigurasi keamanan, dan hak akses. |
| `principal` | Kepala Sekolah | Pimpinan tertinggi sekolah; otorisasi e-rapor, perizinan cuti guru/staf, overview eksekutif. |
| `vice_principal` | Wakil Kepala Sekolah | Membantu pimpinan sekolah dalam monitoring KBM, kurikulum, kesiswaan, dan sarpras. |
| `counselor` | Guru Bimbingan Konseling (BK) | Kasus konseling, rujukan, pemantauan psikososial, dan sistem peringatan dini (early warning). |
| `librarian` | Pustakawan | Sirkulasi buku perpustakaan, katalog buku, denda pinjaman, dan inventaris pustaka. |
| `facility_staff` | Petugas Sarana & Prasarana | Manajemen fasilitas, booking ruangan, inventaris aset QR code, dan perbaikan tiket helpdesk. |
| `student_affairs` | Kesiswaan & Pembina Ekskul | Poin kedisiplinan (pelanggaran/penghargaan), kegiatan ekstrakurikuler, dan kompetisi siswa. |
| `public_relations` | Humas & Kemitraan | Buku tamu digital terverifikasi, penanganan aduan & whistleblowing, dokumen kerja sama MoU. |
| `operator_cms` | Operator CMS & Publikasi | Pemeliharaan artikel berita sekolah, agenda kegiatan, pengumuman publik, dan galeri. |
| `treasurer` | Bendahara Sekolah | Verifikasi pembayaran pendaftaran ulang PPDB, riwayat slip gaji pegawai, laporan keuangan. |
| `ppdb_committee` | Panitia PPDB | Pengelolaan gelombang pendaftaran, verifikasi berkas pendaftar, dan eksekusi seleksi otomatis. |
| `admin_tu` | Staf Tata Usaha | Master data sekolah, administrasi rombel, penugasan guru, data siswa, dan persuratan. |
| `committee` | Komite Sekolah / Yayasan | Peran baca-saja (read-only) untuk pemantauan capaian agregat sekolah, tingkat kehadiran siswa/guru, progres PPDB, dan laporan sarpras/keuangan. |
| `supervisor` | Pengawas Sekolah / Dinas | Peran supervisi eksternal (read-only) untuk monitoring mutu akademik, kurikulum, dan evaluasi hasil belajar siswa secara agregat. |

---

## 2. Matriks Pemetaan Menu & Permission

| Modul & Menu Sidebar | Path Rute URL | Permission yang Dibutuhkan | Role yang Diizinkan |
|---|---|---|---|
| **Beranda Admin** | `/admin` | *(Terbuka untuk semua shell admin)* | Seluruh Role Admin (termasuk `committee` & `supervisor`) |
| **Dashboard Eksekutif** | `/admin/executive` | `executive.dashboard.view`, `reports.view` | `super_admin`, `principal`, `vice_principal`, `committee` *(read-only)*, `supervisor` *(read-only)* |
| **Antrean Persetujuan** | `/admin/executive/approvals` | `executive.approval.manage` | `super_admin`, `principal`, `vice_principal` *(Ditolak untuk role baca-saja)* |
| **Master Data: T.A. & Semester** | `/admin/master/academic-years` | `master.academic_year.manage` | `super_admin`, `admin_tu`, `principal` |
| **Master Data: Siswa** | `/admin/master/students` | `master.student.read`, `master.student.write` | `super_admin`, `admin_tu`, `counselor`, `student_affairs` |
| **Master Data: Guru & Staf** | `/admin/master/teachers` | `master.teacher.manage` | `super_admin`, `admin_tu`, `principal` |
| **Master Data: Wali Murid** | `/admin/master/guardians` | `master.guardian.manage` | `super_admin`, `admin_tu`, `counselor` |
| **Master Data: Rombel & Kelas** | `/admin/master/classes` | `master.class.manage` | `super_admin`, `admin_tu`, `principal` |
| **Master Data: Mata Pelajaran** | `/admin/master/subjects` | `master.subject.manage` | `super_admin`, `admin_tu`, `principal` |
| **Akademik: Jadwal Pelajaran** | `/admin/academic/schedules` | `academic.schedule.manage` | `super_admin`, `admin_tu`, `principal` |
| **Akademik: Jurnal Mengajar** | `/admin/academic/journals` | `academic.journal.write`, `academic.journal.read` | `super_admin`, `principal`, `vice_principal`, `admin_tu` |
| **Akademik: Presensi KBM** | `/admin/academic/attendance` | `academic.attendance.write`, `academic.attendance.read` | `super_admin`, `principal`, `admin_tu` |
| **Akademik: Buku Nilai Mapel** | `/admin/academic/grades` | `academic.grade.write`, `academic.grade.read` | `super_admin`, `principal`, `admin_tu` |
| **Akademik: Progress Report** | `/admin/academic/progress-reports` | `academic.report.generate` | `super_admin`, `principal`, `admin_tu` |
| **Akademik: Cetak E-Rapor** | `/admin/academic/report-cards` | `academic.report.generate` | `super_admin`, `principal`, `admin_tu` |
| **CBT: Bank Soal** | `/admin/cbt/questions` | `cbt.bank.write`, `cbt.bank.read` | `super_admin`, `admin_tu`, `principal` |
| **CBT: Paket & Jadwal Ujian** | `/admin/cbt/exams` | `cbt.exam.manage` | `super_admin`, `admin_tu`, `principal` |
| **CBT: Live Monitor Lab** | `/admin/cbt/monitor` | `cbt.exam.read` | `super_admin`, `admin_tu`, `principal` |
| **PPDB: Gelombang & Jalur** | `/admin/ppdb/waves` | `ppdb.wave.manage` | `super_admin`, `ppdb_committee`, `principal` |
| **PPDB: Verifikasi Berkas** | `/admin/ppdb/applicants` | `ppdb.applicant.verify`, `ppdb.applicant.read` | `super_admin`, `ppdb_committee`, `treasurer` |
| **PPDB: Seleksi Otomatis** | `/admin/ppdb/selection` | `ppdb.selection.run` | `super_admin`, `ppdb_committee`, `principal` |
| **Kesiswaan: Poin Disiplin** | `/admin/student-affairs/points` | `student.point.write`, `student.point.read` | `super_admin`, `student_affairs`, `counselor` |
| **Kesiswaan: Ekstrakurikuler** | `/admin/student-affairs/extracurriculars` | `student.point.write` | `super_admin`, `student_affairs` |
| **Kesiswaan: Prestasi & Lomba** | `/admin/student-affairs/competitions` | `student.point.write` | `super_admin`, `student_affairs` |
| **Bimbingan Konseling (BK)** | `/admin/counseling` | `counseling.case.read`, `counseling.case.write` | `super_admin`, `counselor` *(Akses Sangat Terbatas)* |
| **Sarpras: Booking Fasilitas** | `/admin/facilities` | `facility.booking.manage` | `super_admin`, `facility_staff` |
| **Sarpras: Aset QR Code** | `/admin/assets` | `facility.asset.manage` | `super_admin`, `facility_staff` |
| **Sarpras: Helpdesk Tiket** | `/admin/tickets` | `facility.booking.manage`, `facility.asset.manage` | `super_admin`, `facility_staff`, `admin_tu` |
| **Sarpras: Perpustakaan** | `/admin/library` | `library.book.manage`, `facility.asset.manage` | `super_admin`, `librarian` |
| **Humas: Buku Tamu & Aduan** | `/admin/public-relations` | `guest.book.read`, `complaint.read` | `super_admin`, `public_relations`, `principal` |
| **CMS Portal Sekolah** | `/admin/cms` | `cms.post.manage`, `cms.site.manage` | `super_admin`, `operator_cms`, `public_relations` |
| **Pusat Laporan Terpadu** | `/admin/reports` | `reports.view`, `audit.read` | `super_admin`, `principal`, `vice_principal`, `admin_tu`, `committee` *(read-only)*, `supervisor` *(read-only)* |
| **Pengaturan Akun & RBAC** | `/admin/settings/*` | `auth.role.manage`, `auth.user.read` | `super_admin` |
| **Audit Log Keamanan** | `/admin/settings/audit-logs` | `audit.read` | `super_admin`, `principal` |

---

## 3. Aturan Keamanan & Akses Terbatas

1. **Deny-by-Default**: Pengguna tanpa peran yang terdaftar dalam `ADMIN_SHELL_ROLES` ditolak saat mengakses rute `/admin/*` dan diarahkan ke portal pengguna masing-masing (`/portal/*`).
2. **Kerahasiaan Kasus Konseling (BK)**: Catatan konseling siswa bersifat strictly confidential dan **hanya** dapat dibaca oleh role `counselor` dan `super_admin`. Tidak boleh ditampilkan pada rekap umum akademik.
3. **Pemberian Persetujuan (Approvals)**: Aksi `approve` dan `reject` pada berkas penting (e-rapor, izin dinas, anggaran sarpras) mencatat stempel audit log (`AuditLog`) dengan correlation ID dan nama pimpinan peninjau. Penolakan wajib menyertakan alasan penolakan.
4. **Proteksi Peran Baca-Saja (Komite & Pengawas)**:
   - Akses navigasi terbatas hanya pada rekapitulasi data agregat di `/admin/reports` dan overview metrik di `/admin/executive`.
   - Identitas personal siswa disamarkan secara otomatis (`anonymizeStudentName`) pada tabel pratinjau laporan demi perlindungan data pribadi (UU PDP & Permendikbudristek).
   - Seluruh tombol aksi mutasi data (`POST`, `PUT`, `PATCH`, `DELETE`) dan tombol persetujuan di UI dinonaktifkan/disembunyikan.
   - Panggilan mutasi API di sisi klien ditolak langsung dengan status `403 FORBIDDEN` melalui interceptor `apiClient`.
   - Ekspor data hanya diizinkan dalam format resmi yang diizinkan (CSV dan Cetak/PDF).
