# MATRIKS RUTE, RBAC & OTORISASI PORTAL SISTLABUM

- **Repository:** `/Users/macbookprom12020/Desktop/Project/SISTLABUM`
- **Branch:** `develop` | **HEAD:** `448a1a499d3c3a87aa61c3d28428c616dd8f0405`
- **Tanggal Audit:** 16 Oktober 2026
- **Klasifikasi:** Dokumen Teknis Audit Kesiapan Staging UAT & Operasional Harian
- **Single Source of Truth:** `13_MASTER_MODULES.md`, `08_DATABASE_SCHEMA.md`, `09_SECURITY_PRIVACY.md`

---

## 1. Rangkuman Arsitektur Navigasi & Shell

Sistem Portal Sekolah Satu Pintu (SISTLABUM) membagi akses antarmuka ke dalam 7 lingkungan (*shells / persona spaces*):
1. **Web Publik & PPDB** (`/*`, `/ppdb/*`): Akses terbuka untuk calon siswa, orang tua calon siswa, dan masyarakat umum.
2. **Portal Siswa** (`/portal/siswa/*`, alias `/siswa/*`): Layanan mandiri siswa aktif (jadwal, tugas, nilai, CBT, poin, konseling).
3. **Portal Orang Tua / Wali** (`/portal/ortu/*`, alias `/ortu/*`): Monitoring akademik anak perwalian sah dengan proteksi anti-IDOR.
4. **Portal Guru** (`/portal/guru/*`, alias `/guru/*`): Manajemen KBM harian (presensi kelas, jurnal, materi, tugas, nilai, ujian, slip gaji).
5. **Portal Staf** (`/portal/staf/*`, alias `/staf/*`): Layanan mandiri tenaga kependidikan (presensi kerja, cuti, tiket helpdesk sarpras, slip gaji).
6. **Portal Pimpinan Eksekutif** (`/portal/kepsek/*`, alias `/kepsek/*`): Dashboard KPI manajerial dan antrean persetujuan resmi Kepala Sekolah/Wakasek.
7. **Admin Back-Office Shell** (`/admin/*`): Tata kelola terpusat master data, akademik, sarpras, CBT, PPDB, dan konfigurasi sistem.

---

## 2. Matriks URL Aliases & Rute Kanonis

| URL Spesifikasi (Alias) | Rute Kanonis Fisik | Mekanisme Next.js | Preservasi Query String | Route Guard | Hak Akses Role |
|---|---|:---:|:---:|:---:|---|
| `/guru` & `/guru/:path*` | `/portal/guru/:path*` | HTTP 307 Redirect (`next.config.mjs`) | ✅ Ya | `RoleGuard(portal='guru')` | `teacher`, `homeroom`, `super_admin` |
| `/siswa` & `/siswa/:path*` | `/portal/siswa/:path*` | HTTP 307 Redirect (`next.config.mjs`) | ✅ Ya | `RoleGuard(portal='siswa')` | `student`, `super_admin` |
| `/ortu` & `/ortu/:path*` | `/portal/ortu/:path*` | HTTP 307 Redirect (`next.config.mjs`) | ✅ Ya | `RoleGuard(portal='ortu')` | `guardian`, `super_admin` |
| `/staf` & `/staf/:path*` | `/portal/staf/:path*` | HTTP 307 Redirect (`next.config.mjs`) | ✅ Ya | `RoleGuard(portal='staf')` | `facility_staff`, `librarian`, `admin_tu`, `super_admin` |
| `/kepsek` & `/kepsek/:path*` | `/portal/kepsek/:path*` | HTTP 307 Redirect (`next.config.mjs`) | ✅ Ya | `RoleGuard(portal='kepsek')` | `principal`, `vice_principal`, `super_admin` |
| `/admin/grades` | `/admin/academic/grades` | HTTP 307 Redirect (`next.config.mjs`) | ✅ Ya | `AdminLayout` + Permission | `super_admin`, `admin_tu`, `principal` |
| `/admin/grades/progress-report` | `/admin/academic/progress-reports` | HTTP 307 Redirect (`next.config.mjs`) | ✅ Ya | `AdminLayout` + Permission | `super_admin`, `admin_tu`, `principal` |
| `/admin/grades/report-cards` | `/admin/academic/report-cards` | HTTP 307 Redirect (`next.config.mjs`) | ✅ Ya | `AdminLayout` + Permission | `super_admin`, `admin_tu`, `principal` |

---

## 3. Matriks Peran Terhadap Lingkungan Shell (`canAccessShell`)

| Role Sistem (`RoleCode`) | Deskripsi Persona | Shell `admin` | Shell `guru` | Shell `siswa` | Shell `ortu` | Shell `staf` | Shell `kepsek` | Destinasi Login Utama (`resolveUserDestination`) |
|---|---|:---:|:---:|:---:|:---:|:---:|:---:|---|
| `super_admin` | Administrator Sistem Penuh | ✅ Ya | ✅ Ya | ✅ Ya | ✅ Ya | ✅ Ya | ✅ Ya | `/admin` |
| `admin_tu` | Tata Usaha & Administrasi | ✅ Ya | ❌ Tidak | ❌ Tidak | ❌ Tidak | ✅ Ya | ❌ Tidak | `/portal/staf` (bila tanpa multi-peran eksekutif) |
| `principal` | Kepala Sekolah | ✅ Ya | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | ✅ Ya | `/portal/kepsek` |
| `vice_principal` | Wakil Kepala Sekolah | ✅ Ya | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | ✅ Ya | `/portal/kepsek` |
| `teacher` | Guru Mata Pelajaran | ❌ Tidak | ✅ Ya | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | `/portal/guru` |
| `homeroom` | Wali Kelas | ❌ Tidak | ✅ Ya | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | `/portal/guru` |
| `student` | Siswa Aktif | ❌ Tidak | ❌ Tidak | ✅ Ya | ❌ Tidak | ❌ Tidak | ❌ Tidak | `/portal/siswa` |
| `guardian` | Orang Tua / Wali Murid | ❌ Tidak | ❌ Tidak | ❌ Tidak | ✅ Ya | ❌ Tidak | ❌ Tidak | `/portal/ortu` |
| `counselor` | Guru Bimbingan Konseling (BK) | ✅ Ya | ✅ Ya (bila ganda) | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | `/admin` |
| `facility_staff` | Petugas Sarana & Prasarana | ✅ Ya | ❌ Tidak | ❌ Tidak | ❌ Tidak | ✅ Ya | ❌ Tidak | `/portal/staf` |
| `librarian` | Petugas Perpustakaan | ✅ Ya | ❌ Tidak | ❌ Tidak | ❌ Tidak | ✅ Ya | ❌ Tidak | `/portal/staf` |
| `ppdb_committee` | Panitia Seleksi PPDB | ✅ Ya | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | `/admin` |
| `treasurer` | Bendahara Sekolah | ✅ Ya | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | `/admin` |
| `student_affairs` | Pembina Kesiswaan | ✅ Ya | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | `/admin` |
| `public_relations` | Tim Humas & Publikasi | ✅ Ya | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | `/admin` |
| `operator_cms` | Pengelola Konten Web Sekolah | ✅ Ya | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | `/admin` |
| `committee` | Komite Sekolah (Mitra) | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | `/portal` (Notice Aman - Non Operasional) |
| `supervisor` | Pengawas Dinas Pendidikan | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | ❌ Tidak | `/portal` (Notice Aman - Non Operasional) |

---

## 4. Matriks Rute Halaman Frontend (`apps/web`)

### A. Rute Publik & PPDB (`(public)`)
| Rute Halaman | Komponen Page | Perlindungan / Guard | Sumber Data API | Deskripsi Fungsional |
|---|---|:---:|---|---|
| `/` | `(public)/page.tsx` | Publik | CMS Posts, Events, Profile | Beranda sekolah, sambutan kepala sekolah, berita & agenda terkini |
| `/profil` | `(public)/profil/page.tsx` | Publik | CMS Site Settings | Profil visi-misi, sejarah sekolah, struktur dewan guru |
| `/akademik` | `(public)/akademik/page.tsx` | Publik | Public Academic API | Informasi kurikulum, daftar rombel, kalender akademik |
| `/berita` | `(public)/berita/page.tsx` | Publik | `GET /api/v1/posts` | Arsip warta dan agenda kegiatan sekolah |
| `/berita/[slug]` | `(public)/berita/[slug]/page.tsx` | Publik | `GET /api/v1/posts/{slug}` | Detail berita, dokumentasi foto, dan artikel mading |
| `/galeri` | `(public)/galeri/page.tsx` | Publik | `GET /api/v1/albums` | Galeri dokumentasi visual fasilitas dan prestasi sekolah |
| `/kontak` | `(public)/kontak/page.tsx` | Publik | `POST /api/v1/guests`, `POST /api/v1/complaints` | Buku tamu umum dan form pengaduan masyarakat / whistleblowing |
| `/ppdb` | `(public)/ppdb/page.tsx` | Publik | `GET /api/v1/ppdb/waves`, `GET /api/v1/ppdb/tracks` | Informasi alur, kuota, syarat berkas, dan cek pengumuman hasil |
| `/ppdb/daftar` | `(public)/ppdb/daftar/page.tsx` | Publik | `POST /api/v1/ppdb/register` | Formulir registrasi akun pendaftar calon siswa baru |
| `/ppdb/masuk` | `(public)/ppdb/masuk/page.tsx` | Publik | `POST /api/v1/ppdb/login` | Login akun calon siswa (Email / No. HP / No. Registrasi) |
| `/ppdb/dashboard` | `(public)/ppdb/dashboard/page.tsx` | Auth Token PPDB | `GET /api/v1/ppdb/me`, `PATCH /steps`, `POST /documents` | Dashboard pendaftar: isi berkas bertahap, upload syarat, kartu bukti |

### B. Rute Portal Siswa (`(portal)/portal/siswa`)
| Rute Halaman | Komponen Page | RoleGuard | Sumber Data API | Deskripsi Fungsional |
|---|---|:---:|---|---|
| `/portal/siswa` | `portal/siswa/page.tsx` | `siswa` | `GET /api/v1/me/student-dashboard` | Beranda siswa: sapaan, jadwal hari ini, ujian CBT mendatang, presensi |
| `/portal/siswa/jadwal` | `portal/siswa/jadwal/page.tsx` | `siswa` | `GET /api/v1/me/schedule` | Jadwal mingguan mata pelajaran dan ruang kelas |
| `/portal/siswa/tugas` | `portal/siswa/tugas/page.tsx` | `siswa` | `GET /api/v1/assignments`, `POST /submissions` | Daftar tugas KBM, batas waktu, unggah jawaban/link Drive |
| `/portal/siswa/nilai` | `portal/siswa/nilai/page.tsx` | `siswa` | `GET /api/v1/me/grades` | Rekapitulasi nilai formatif, sumatif, dan capaian kompetensi |
| `/portal/siswa/cbt` | `portal/siswa/cbt/page.tsx` | `siswa` | `GET /api/v1/cbt/exams`, `POST /start` | Katalog ujian CBT aktif dan input token ruang lab komputer |
| `/portal/siswa/ujian/[id]` | `portal/siswa/ujian/[id]/page.tsx` | `siswa` | `cbtApi` (session questions, answers, violations) | Workspace ujian CBT anti-cheat, autosave, dan sinkronisasi timer |
| `/portal/siswa/poin` | `portal/siswa/poin/page.tsx` | `siswa` | `GET /api/v1/student-points` | Buku poin karakter siswa (saldo poin, riwayat prestasi/pelanggaran) |
| `/portal/siswa/konseling` | `portal/siswa/konseling/page.tsx` | `siswa` | `GET /api/v1/counseling/requests`, `POST /me/counseling-requests` | Permohonan konseling privat dengan Guru BK |
| `/portal/siswa/notifications` | `portal/siswa/notifications/page.tsx` | `siswa` | `GET /api/v1/me/notifications` | Notifikasi pengumuman sekolah dan pengingat tugas KBM |

### C. Rute Portal Orang Tua (`(portal)/portal/ortu`)
| Rute Halaman | Komponen Page | RoleGuard | Sumber Data API | Deskripsi Fungsional |
|---|---|:---:|---|---|
| `/portal/ortu` | `portal/ortu/page.tsx` | `ortu` | `GET /api/v1/me/children` | Ringkasan kartu anak-anak di bawah perwalian sah |
| `/portal/ortu/anak/[id]` | `portal/ortu/anak/[id]/page.tsx` | `ortu` (anti-IDOR) | `GET /api/v1/me/children/{id}/*` (attendance, grades, report-cards, character-notes) | Monitoring mendalam perkembangan belajar anak, catatan sikap, dan presensi |
| `/portal/ortu/notifications` | `portal/ortu/notifications/page.tsx` | `ortu` | `GET /api/v1/me/notifications` | Notifikasi presensi anak, penerbitan rapor, dan pengumuman sekolah |

### D. Rute Portal Guru (`(portal)/portal/guru`)
| Rute Halaman | Komponen Page | RoleGuard | Sumber Data API | Deskripsi Fungsional |
|---|---|:---:|---|---|
| `/portal/guru` | `portal/guru/page.tsx` | `guru` | `GET /api/v1/me/teacher/dashboard` | Beranda guru: jadwal mengajar hari ini, tugas yang perlu dinilai, presensi |
| `/portal/guru/jadwal` | `portal/guru/jadwal/page.tsx` | `guru` | `GET /api/v1/me/teacher/schedules` | Agenda KBM mingguan guru pengampu |
| `/portal/guru/presensi` | `portal/guru/presensi/page.tsx` | `guru` | `GET /classes/{id}/students`, `POST /attendance/sessions` | Form presensi siswa tatap muka di kelas KBM |
| `/portal/guru/jurnal` | `portal/guru/jurnal/page.tsx` | `guru` | `GET /journals`, `POST /journals` | Jurnal mengajar harian guru (materi, metode, catatan KBM) |
| `/portal/guru/materi` | `portal/guru/materi/page.tsx` | `guru` | `GET /materials`, `POST /materials` | Bank modul bahan ajar per mata pelajaran dan rombel |
| `/portal/guru/tugas` | `portal/guru/tugas/page.tsx` | `guru` | `GET /assignments`, `POST /assignments` | Manajemen tugas kelas siswa dan pemantauan submisi |
| `/portal/guru/tugas/[id]/penilaian` | `portal/guru/tugas/[id]/penilaian/page.tsx` | `guru` | `GET /assignments/{id}/submissions`, `POST /grade` | Penilaian lembar tugas siswa (skor angka dan umpan balik catatan) |
| `/portal/guru/nilai` | `portal/guru/nilai/page.tsx` | `guru` | `GET /grade-components`, `POST /grades/bulk` | Penginputan buku nilai formatif & sumatif per mata pelajaran |
| `/portal/guru/ujian` | `portal/guru/ujian/page.tsx` | `guru` | `GET /me/teacher/exams` | Daftar ujian CBT yang diampu dewan guru |
| `/portal/guru/wali-kelas` | `portal/guru/wali-kelas/page.tsx` | `guru` (`homeroom`) | `GET /me/teacher/homeroom` | Hub wali kelas: rekap presensi rombel, catatan karakter, status siswa |
| `/portal/guru/presensi-saya` | `portal/guru/presensi-saya/page.tsx` | `guru` | `GET /self-attendance`, `POST /self-attendance` | Presensi mandiri kedatangan & kepulangan jam kerja guru |
| `/portal/guru/izin-cuti` | `portal/guru/izin-cuti/page.tsx` | `guru` | `GET /leave-requests`, `POST /leave-requests` | Pengajuan izin ketidakhadiran / cuti mengajar |
| `/portal/guru/slip-gaji` | `portal/guru/slip-gaji/page.tsx` | `guru` | `GET /teacher/me/salary-slips`, `GET /download` | Arsip dan unduhan berkas slip gaji bulanan guru |

### E. Rute Portal Staf Kependidikan (`(portal)/portal/staf`)
| Rute Halaman | Komponen Page | RoleGuard | Sumber Data API | Deskripsi Fungsional |
|---|---|:---:|---|---|
| `/portal/staf` | `portal/staf/page.tsx` | `staf` | `GET /api/v1/me/staff/dashboard` | Beranda staf TU/Sarpras/Perpus: rekap jam kerja, status tiket, cuti |
| `/portal/staf/presensi-saya` | `portal/staf/presensi-saya/page.tsx` | `staf` | `GET /me/staff/self-attendance`, `POST /self-attendance` | Check-in & check-out presensi harian karyawan |
| `/portal/staf/izin-cuti` | `portal/staf/izin-cuti/page.tsx` | `staf` | `GET /me/staff/leave-requests`, `POST /leave-requests` | Pengajuan izin/cuti tenaga kependidikan |
| `/portal/staf/tiket` | `portal/staf/tiket/page.tsx` | `staf` | `GET /me/staff/tickets` | Daftar tiket penugasan helpdesk / perbaikan fasilitas |
| `/portal/staf/slip-gaji` | `portal/staf/slip-gaji/page.tsx` | `staf` | `GET /teacher/me/salary-slips` | Arsip slip penerimaan gaji karyawan |

### F. Rute Portal Pimpinan Eksekutif (`(portal)/portal/kepsek`)
| Rute Halaman | Komponen Page | RoleGuard | Sumber Data API | Deskripsi Fungsional |
|---|---|:---:|---|---|
| `/portal/kepsek` | `portal/kepsek/page.tsx` | `kepsek` | `GET /api/v1/dashboard/executive` | Dashboard KPI pimpinan: presensi real-time, capaian KBM, PPDB, sarpras |
| `/portal/kepsek/approvals` | `portal/kepsek/approvals/page.tsx` | `kepsek` | `GET /approvals/pending`, `POST /approvals/{id}/review` | Antrean pengesahan resmi persetujuan cuti, rapor, booking sarpras |

### G. Rute Admin Back-Office Shell (`(admin)/admin`)
| Rute Modul Admin | Komponen Page | Permission Wajib | Accessible Roles | Deskripsi Fungsional |
|---|---|---|---|---|
| `/admin` | `admin/page.tsx` | `ADMIN_SHELL_ROLES` | `super_admin`, `admin_tu`, dll. | Ringkasan operasional back-office sistem |
| `/admin/master/academic-years` | `academic-years/page.tsx` | `master.academic_year.manage` | `super_admin`, `admin_tu` | Pengaturan tahun ajaran & aktivasi semester |
| `/admin/master/classes` | `classes/page.tsx` | `master.class.manage` | `super_admin`, `admin_tu` | Rombongan belajar & penetapan wali kelas |
| `/admin/master/teachers` | `teachers/page.tsx` | `master.teacher.manage` | `super_admin`, `admin_tu` | Master guru, penugasan mengajar, slip gaji, foto |
| `/admin/master/students` | `students/page.tsx` | `master.student.read` / write | `super_admin`, `admin_tu` | Master siswa, nomor induk, status aktif, foto |
| `/admin/master/subjects` | `subjects/page.tsx` | `master.subject.manage` | `super_admin`, `admin_tu` | Mata pelajaran, bobot jam pelajaran, kode mapel |
| `/admin/master/guardians` | `guardians/page.tsx` | `master.guardian.manage` | `super_admin`, `admin_tu` | Data orang tua / wali dan relasi perwalian siswa |
| `/admin/academic/schedules` | `academic/schedules/page.tsx` | `academic.schedule.manage` | `super_admin`, `admin_tu` | Matriks jadwal pelajaran dan deteksi bentrok |
| `/admin/academic/journals` | `academic/journals/page.tsx` | `academic.journal.write` | `super_admin`, `admin_tu` | Pengawasan jurnal KBM harian seluruh guru |
| `/admin/academic/attendance` | `academic/attendance/page.tsx` | `academic.attendance.write` | `super_admin`, `admin_tu` | Rekapitulasi kehadiran KBM siswa per kelas |
| `/admin/academic/staff-attendance`| `staff-attendance/page.tsx` | `academic.attendance.write` | `super_admin`, `admin_tu` | Rekapitulasi presensi kerja guru dan staf |
| `/admin/academic/grades` | `academic/grades/page.tsx` | `academic.grade.write` | `super_admin`, `admin_tu` | Buku nilai komprehensif seluruh mata pelajaran |
| `/admin/academic/progress-reports`| `progress-reports/page.tsx`| `academic.report.generate` | `super_admin`, `admin_tu` | Penerbitan lembar kemajuan belajar berkala |
| `/admin/academic/report-cards` | `academic/report-cards/page.tsx` | `academic.report.generate` | `super_admin`, `admin_tu` | Generate massal e-rapor ber-QR code |
| `/admin/academic/teaching-materials`| `teaching-materials/page.tsx`| `academic.schedule.manage` | `super_admin`, `admin_tu` | Repositori modul dan bahan ajar kurikulum |
| `/admin/academic/assignments` | `assignments/page.tsx` | `academic.schedule.manage` | `super_admin`, `admin_tu` | Arsip tugas KBM dan rekap submisi |
| `/admin/academic/leave-requests` | `leave-requests/page.tsx` | `academic.attendance.write` | `super_admin`, `admin_tu` | Monitoring pengajuan izin/cuti siswa dan guru |
| `/admin/cbt/questions` | `cbt/questions/page.tsx` | `cbt.bank.write` | `super_admin`, `admin_tu` | Bank butir soal ujian pilihan ganda & uraian |
| `/admin/cbt/exams` | `cbt/exams/page.tsx` | `cbt.exam.manage` | `super_admin`, `admin_tu` | Pengaturan paket ujian, jadwal, dan token ruang |
| `/admin/cbt/monitor` | `cbt/monitor/page.tsx` | `cbt.exam.read` | `super_admin`, `admin_tu` | Live monitor ruang lab ujian CBT & aksi pengawas |
| `/admin/ppdb/waves` | `ppdb/waves/page.tsx` | `ppdb.wave.manage` | `super_admin`, `ppdb_committee` | Pengaturan gelombang pendaftaran & kuota jalur |
| `/admin/ppdb/applicants` | `ppdb/applicants/page.tsx` | `ppdb.applicant.verify` | `super_admin`, `ppdb_committee` | Verifikasi berkas administrasi calon siswa baru |
| `/admin/ppdb/selection` | `ppdb/selection/page.tsx` | `ppdb.selection.run` | `super_admin`, `ppdb_committee` | Perangkingan otomatis, penetapan hasil, daftar ulang |
| `/admin/student-affairs/points` | `student-affairs/points/page.tsx` | `student.point.write` | `super_admin`, `student_affairs` | Rekapitulasi poin prestasi dan pelanggaran tata tertib |
| `/admin/student-affairs/extracurriculars`| `extracurriculars/page.tsx`| `student.point.write` | `super_admin`, `student_affairs` | Pendaftaran dan jadwal pembinaan ekstrakurikuler |
| `/admin/student-affairs/competitions` | `competitions/page.tsx` | `student.point.write` | `super_admin`, `student_affairs` | Delegasi lomba dan arsip kejuaraan siswa |
| `/admin/counseling/early-warnings` | `counseling/early-warnings/page.tsx`| `counseling.case.read` | `super_admin`, `counselor` | Peringatan dini siswa berisiko & penanganan kasus |
| `/admin/facilities` | `facilities/page.tsx` | `facility.booking.manage` | `super_admin`, `facility_staff` | Peminjaman ruang laboratorium & sarana sekolah |
| `/admin/assets` | `assets/page.tsx` | `facility.asset.manage` | `super_admin`, `facility_staff` | Inventaris aset fisik sekolah & pelabelan QR |
| `/admin/tickets` | `tickets/page.tsx` | `facility.booking.manage` | `super_admin`, `facility_staff` | Helpdesk perbaikan sarpras & penugasan teknisi |
| `/admin/library` | `library/page.tsx` | `library.book.manage` | `super_admin`, `librarian` | Katalog buku perpustakaan & sirkulasi peminjaman |
| `/admin/public-relations` | `public-relations/page.tsx` | `guest.book.manage` | `super_admin`, `public_relations` | Rekap buku tamu, aduan masyarakat, MoU, tracer |
| `/admin/reports` | `reports/page.tsx` | `reports.view` | `super_admin`, `principal`, dll. | Ekspor laporan agregat (kehadiran, nilai, PPDB) |
| `/admin/executive/approvals` | `executive/approvals/page.tsx` | `executive.approval.manage` | `super_admin`, `principal` | Antrean persetujuan eksekutif via shell admin |
| `/admin/settings/users` | `settings/users/page.tsx` | `auth.user.manage` | `super_admin` | Manajemen akun login dan status pengguna |
| `/admin/settings/roles` | `settings/roles/page.tsx` | `auth.role.manage` | `super_admin` | Konfigurasi matriks permission RBAC sistem |
| `/admin/settings/audit-logs` | `settings/audit-logs/page.tsx` | `auth.audit.view` | `super_admin` | Audit log jejak aktivitas mutasi dan keamanan |

---

## 5. Matriks Endpoint API Backend (`apps/api`) & Scoping Keamanan

| Modul | Endpoint API (v1) | HTTP Method | Middleware / Guard | Batasan Otorisasi & Scoping Data (Anti-IDOR) |
|---|---|:---:|---|---|
| **AUTH** | `/api/v1/auth/login` | `POST` | Guest, Throttle | Autentikasi user & penerbitan token Sanctum |
| **AUTH** | `/api/v1/auth/me` | `GET` | `auth:sanctum` | Mengambil profil user aktif, roles, permissions |
| **AUTH** | `/api/v1/auth/change-password` | `POST` | `auth:sanctum` | Ganti password mandiri dengan verifikasi password lama |
| **AUTH** | `/api/v1/auth/sessions` | `GET`/`DELETE` | `auth:sanctum` | Monitoring dan pencabutan sesi perangkat login |
| **ME-SISWA**| `/api/v1/me/student-dashboard` | `GET` | `auth:sanctum` | Scoped: Hanya data siswa terikat dengan user login |
| **ME-SISWA**| `/api/v1/me/schedule` | `GET` | `auth:sanctum` | Scoped: Mengambil jadwal kelas yang diikuti siswa aktif |
| **ME-SISWA**| `/api/v1/me/grades` | `GET` | `auth:sanctum` | Scoped: Hanya nilai milik siswa aktif |
| **ME-SISWA**| `/api/v1/me/exams` | `GET` | `auth:sanctum` | Scoped: Hanya paket ujian yang dijadwalkan untuk kelas siswa |
| **ME-SISWA**| `/api/v1/me/counseling-requests` | `POST` | `auth:sanctum` | Scoped: Pengajuan konseling otomatis atas nama siswa login |
| **ME-ORTU** | `/api/v1/me/children` | `GET` | `auth:sanctum` | Scoped: Hanya anak yang sah berada di pivot `guardian_students` |
| **ME-ORTU** | `/api/v1/me/children/{child}/*` | `GET` | `auth:sanctum` + `Gate::view-child` | Anti-IDOR: Ditolak 403 bila anak bukan perwalian sah user |
| **ME-GURU** | `/api/v1/me/teacher/dashboard` | `GET` | `auth:sanctum` | Scoped: Hanya profil & jam mengajar guru bersangkutan |
| **ME-GURU** | `/api/v1/me/teacher/classes` | `GET` | `auth:sanctum` | Scoped: Kelas yang diajar guru atau dibina sebagai wali kelas |
| **ME-GURU** | `/api/v1/me/teacher/classes/{id}/students` | `GET` | `auth:sanctum` | Anti-IDOR: `verifyClassAccess` menolak 403 bila guru tidak mengajar kelas |
| **ME-GURU** | `/api/v1/me/teacher/attendance/sessions` | `POST` | `auth:sanctum` | Anti-IDOR: Simpan jurnal & presensi KBM hanya pada kelas penugasan |
| **ME-GURU** | `/api/v1/me/teacher/grades/bulk` | `POST` | `auth:sanctum` | Anti-IDOR: Input nilai hanya diizinkan untuk mapel & rombel yang diajar |
| **ME-GURU** | `/api/v1/me/teacher/self-attendance` | `POST` | `auth:sanctum` | Scoped: Presensi kerja mandiri guru aktif |
| **ME-STAF** | `/api/v1/me/staff/self-attendance` | `POST` | `auth:sanctum` | Scoped: Presensi kerja mandiri staf aktif |
| **ME-STAF** | `/api/v1/me/staff/leave-requests` | `POST` | `auth:sanctum` | Scoped: Cuti kerja atas nama staf login |
| **ME-STAF** | `/api/v1/me/staff/tickets` | `GET` | `auth:sanctum` | Scoped: Hanya tiket yang dilaporkan oleh staf bersangkutan |
| **KEPSEK** | `/api/v1/dashboard/executive` | `GET` | `auth:sanctum` | Wewenang eksekutif (`principal`, `vice_principal`, `super_admin`) |
| **KEPSEK** | `/api/v1/approvals/pending` | `GET` | `auth:sanctum` | Wewenang eksekutif: Antrean pengesahan cuti, sarpras, rapor |
| **KEPSEK** | `/api/v1/approvals/{id}/review` | `POST` | `auth:sanctum` + `permission:executive.approval.manage` | Audit Trail: Eksekusi putusan persetujuan/penolakan resmi |
| **CBT** | `/api/v1/cbt/exams/{id}/start` | `POST` | `auth:sanctum` | Token ruang lab valid + verifikasi siswa terdaftar di jadwal |
| **CBT** | `/api/v1/cbt/sessions/{id}/heartbeat`| `POST` | `auth:sanctum` | Live monitor ping & clock skew offset synchronization |
| **CBT** | `/api/v1/cbt/sessions/{id}/violations`| `POST` | `auth:sanctum` | Pencatatan pelanggaran kecurangan & penguncian otomatis |
| **STORAGE** | `/api/v1/files/upload-signed/{token}`| `POST` | Signed Token Valid | Validasi tipe MIME, ukuran 2MB, magic bytes server |
| **STORAGE** | `/api/v1/files/{id}/download` | `GET` | Signed URL Valid | Unduhan berkas privat terotentikasi & kedaluwarsa waktu |

---

## 6. Kesimpulan Audit RBAC

1. **Deny-by-Default:** Seluruh endpoint API back-office dan sub-portal dilindungi oleh autentikasi Sanctum dan otorisasi bertingkat (`RoleGuard`, `AdminLayout`, `Gate::view-child`, `verifyClassAccess`).
2. **Pencegahan IDOR:** Otorisasi orang tua terhadap anak dan otorisasi guru terhadap kelas/nilai telah diverifikasi berlapis pada UI layer, service layer, dan database query scoping.
3. **Pemisahan Peran Mitra:** Komite dan pengawas sekolah terbukti tidak memiliki akses ke Shell Admin dan tidak dapat memicu aksi mutasi data operasional (`canPerformWriteOperation = false`).
