# Prompt Perbaikan Bug Portal Guru (FIX-GURU)

> Prompt siap-pakai untuk dikerjakan satu per satu (satu ID = satu tugas, AGENTS.md §2).
> PRD acuan: `docs/prd/12_BUGFIX_PORTAL_GURU.md`.
> Aturan umum tiap prompt: baca `AGENTS.md` dulu; tulis **rencana singkat** & tunggu persetujuan sebelum coding; gunakan `systematic-debugging` untuk mencari akar masalah; tulis uji berawalan ID; jangan ubah kontrak API tanpa persetujuan (AGENTS.md §8); jangan commit data pribadi/rahasia; cek IDOR & cakupan data.

---

## FIX-GURU-01 — Riwayat jurnal Hadir 0

```
Kerjakan FIX-GURU-01 dari docs/prd/12_BUGFIX_PORTAL_GURU.md.
Masalah: setelah input jurnal (semua Hadir), tab Riwayat menampilkan H:0.
Akar masalah terverifikasi: TeacherAttendanceController::journals() tidak mengembalikan attendance_summary; FE jatuh ke default 0. Tambahan: presensi hanya di-POST saat schedule_id berformat UUID.
Batasan: jangan ubah kontrak OpenAPI lain tanpa persetujuan; pertahankan filter kepemilikan teacher_id.
Hasil diharapkan (AC): journals() menghitung attendance_summary nyata (join sesi presensi schedule_id+session_date) + attendance_rate; Riwayat menampilkan H/S/I/A benar; alur simpan tidak gagal diam saat schedule_id bukan UUID; uji FIX-GURU-01_* (happy path, 401, 403, jurnal tanpa presensi → summary 0 eksplisit).
File: apps/api/.../Teacher/TeacherAttendanceController.php (journals), apps/web/src/lib/lms-api.ts (getJournals/createJournalWithAttendance), apps/web/src/app/(portal)/portal/guru/jurnal/page.tsx.
Ikuti AGENTS.md. Tulis rencana singkat dulu, tunggu persetujuan sebelum coding.
```

## FIX-GURU-02 — Lembar presensi teks putih

```
Kerjakan FIX-GURU-02 dari docs/prd/12_BUGFIX_PORTAL_GURU.md.
Masalah: teks pada lembar presensi berwarna putih di latar terang → tidak terbaca.
Batasan: murni presentasi, jangan ubah kontrak/logika API.
Hasil diharapkan (AC): audit kontras lembar/tabel presensi; teks default token gelap, teks putih hanya di latar berwarna; kontras WCAG AA ≥ 4.5:1 di mode terang; verifikasi viewport mobile & desktop (dan tampilan cetak bila ada); tanpa regresi state tombol status.
File: apps/web/src/app/(portal)/portal/guru/presensi/page.tsx (+ komponen/lembar cetak terkait).
Gunakan browser-testing (preview) untuk verifikasi visual. Ikuti AGENTS.md. Rencana singkat dulu, tunggu persetujuan.
```

## FIX-GURU-03 — Penilaian tugas error

```
Kerjakan FIX-GURU-03 dari docs/prd/12_BUGFIX_PORTAL_GURU.md.
Masalah: menyimpan nilai tugas gagal dengan pesan generik "Gagal menyimpan nilai.".
Langkah: pakai systematic-debugging — reproduksi POST /me/teacher/submissions/{id}/grade dan baca error backend nyata sebelum memperbaiki.
Hasil diharapkan (AC): simpan skor valid berhasil & persist; skor di luar rentang menampilkan pesan validasi spesifik server; validasi skor mengacu max_score tugas (bukan 100 hardcode); IDOR dicegah (guru bukan pemilik → 403); uji FIX-GURU-03_* (happy path, validasi gagal, 401, 403).
File: apps/web/src/app/(portal)/portal/guru/tugas/[id]/penilaian/page.tsx, apps/web/src/lib/lms-api.ts (gradeSubmission), apps/api/.../Teacher/TeacherLmsController.php (gradeSubmission).
Ikuti AGENTS.md. Rencana singkat dulu, tunggu persetujuan.
```

## FIX-GURU-04 — Tugas baru langsung "Selesai Dinilai"

```
Kerjakan FIX-GURU-04 dari docs/prd/12_BUGFIX_PORTAL_GURU.md.
Masalah: tugas baru langsung berlabel "Selesai Dinilai".
Akar masalah terverifikasi: badge jatuh ke "Selesai Dinilai" saat unreviewed===0 padahal submissions_count===0; default dueDate form '2026-10-28T23:59' sudah lampau.
Hasil diharapkan (AC): submissions_count===0 → status "Aktif"/"Belum Ada Pengumpulan"; "Selesai Dinilai" hanya bila submissions_count>0 && unreviewed===0; default tenggat form = masa depan wajar (mis. +7 hari); uji komponen FIX-GURU-04_* untuk derivasi status (0 submission, sebagian dinilai, semua dinilai).
File: apps/web/src/app/(portal)/portal/guru/tugas/page.tsx.
Batasan: FE-only, tanpa perubahan kontrak API. Ikuti AGENTS.md. Rencana singkat dulu, tunggu persetujuan.
```

## FIX-GURU-05 — Tambah soal duplikat (klik ganda)

```
Kerjakan FIX-GURU-05 dari docs/prd/12_BUGFIX_PORTAL_GURU.md.
Masalah: klik "Tambah Soal" berkali-kali membuat banyak soal duplikat.
Akar masalah terverifikasi: handleCreateQuestion/handleCreateBank tanpa guard isSubmitting; tombol tidak disable saat request; tanpa Idempotency-Key.
Hasil diharapkan (AC): klik cepat berulang hanya menghasilkan 1 soal; tombol disable + indikator loading; dukungan Idempotency-Key agar POST berulang idempoten (AGENTS.md §4; konfirmasi ke PO bila perlu masuk kontrak OpenAPI — AGENTS.md §8); uji FIX-GURU-05_* (happy path, submit ganda idempotent, 401).
File: apps/web/src/app/(portal)/portal/guru/ujian/page.tsx, apps/web/src/features/portal/guru/guru-api.ts (createQuestion), apps/api/.../Teacher/TeacherExamController.php (storeQuestion/storeDirectQuestion).
Ikuti AGENTS.md. Rencana singkat dulu, tunggu persetujuan.
```

## FIX-GURU-06 — Form soal PG tanpa opsi & kunci jawaban

```
Kerjakan FIX-GURU-06 dari docs/prd/12_BUGFIX_PORTAL_GURU.md.
Masalah: form tambah soal tipe PG tidak punya input opsi jawaban maupun kunci jawaban.
Akar masalah terverifikasi: form hanya kirim question_type+body.text+cognitive_level+difficulty; lms-api createDirectQuestion membuang answer_key.
Hasil diharapkan (AC): form PG menampilkan input opsi dinamis + penanda jawaban benar (min 2 opsi, tepat 1 kunci); kirim & simpan options + answer_key; perbaiki createDirectQuestion agar tidak membuang answer_key; KEAMANAN (AGENTS.md §6.6): answer_key TIDAK terkirim ke klien siswa pada endpoint pengerjaan ujian — verifikasi; uji FIX-GURU-06_* (happy path PG lengkap, validasi gagal 0/1 opsi tanpa kunci, dan uji kunci tidak bocor ke endpoint siswa).
File: apps/web/src/app/(portal)/portal/guru/ujian/page.tsx, guru-api.ts (createQuestion), lms-api.ts (createDirectQuestion), apps/api/.../Teacher/TeacherExamController.php.
Perubahan kontrak soal menyentuh klien lain → konfirmasi dulu (AGENTS.md §8). Ikuti AGENTS.md. Rencana singkat dulu, tunggu persetujuan.
```

## FIX-GURU-07 — Perizinan "Lihat Berkas" tidak bisa

```
Kerjakan FIX-GURU-07 dari docs/prd/12_BUGFIX_PORTAL_GURU.md.
Masalah: tombol "Lihat Berkas" pada detail perizinan hanya memunculkan toast, berkas tak terbuka.
Akar masalah terverifikasi: attachmentUrl mock '#'; item API hanya punya attachment_file_id tanpa resolusi URL unduh.
Hasil diharapkan (AC): endpoint unduh aman berbasis attachment_file_id (signed URL/stream + otorisasi), tombol membuka berkas nyata; IDOR dicegah (tak bisa akses berkas kelas lain → 403); pengajuan tanpa lampiran tetap tampil "Tidak ada lampiran" tanpa error; uji FIX-GURU-07_* (happy path, 403 IDOR, 404 file hilang).
Keamanan: jangan taruh file_id/token di query string yang ter-log (AGENTS.md §6).
File: apps/web/src/app/(portal)/portal/guru/perizinan/page.tsx, apps/web/src/lib/use-guardian-data.ts, apps/api/app/Services/Storage/FileStorageService.php + route file.
Ikuti AGENTS.md. Rencana singkat dulu, tunggu persetujuan.
```

## FIX-GURU-08 — Perizinan keputusan tidak tersimpan

```
Kerjakan FIX-GURU-08 dari docs/prd/12_BUGFIX_PORTAL_GURU.md.
Masalah: keputusan terima/tolak perizinan tidak tersimpan/tak berubah setelah refresh.
Akar masalah terverifikasi: halaman memakai data mock hardcoded sebagai sumber utama; decideMutation tak menyentuh backend untuk item mock; daftar tidak di-invalidate.
Hasil diharapkan (AC): daftar bersumber dari getHomeroomLeaveRequests() (tanpa record fiktif); setuju/tolak memanggil PATCH /me/teacher/homeroom/leave-requests/{id}/decision lalu invalidate+refetch sehingga status persist; state loading/kosong/error lengkap; hapus data pribadi fiktif dari kode (AGENTS.md §6.2); uji FIX-GURU-08_* (approve, reject, 403 bukan wali kelas terkait, 401).
File: apps/web/src/app/(portal)/portal/guru/perizinan/page.tsx (endpoint & lmsApi.decideHomeroomLeaveRequest sudah ada).
Ikuti AGENTS.md. Rencana singkat dulu, tunggu persetujuan.
```

## FIX-GURU-09 — Pencatatan poin siswa belum tersimpan

```
Kerjakan FIX-GURU-09 dari docs/prd/12_BUGFIX_PORTAL_GURU.md.
Masalah: form poin siswa hanya menyimpan ke state lokal, tidak ke backend.
Akar masalah terverifikasi: siswa/page.tsx tidak memanggil lmsApi.recordStudentPoint.
Hasil diharapkan (AC): hubungkan form ke recordStudentPoint (POST /me/teacher/student-points) via useMutation; cocokkan field dengan validasi storeStudentPoint (konfirmasi: student_id, point_type_id/category, points, date, notes); invalidate/refetch daftar; validasi gagal tampil per-field; IDOR dicegah; uji FIX-GURU-09_* (happy path, validasi gagal, 401, 403).
File: apps/web/src/app/(portal)/portal/guru/siswa/page.tsx (tab poin), apps/api/.../Teacher/TeacherHomeroomController.php (storeStudentPoint).
Skema point_type/bobot yang baku → konfirmasi ke PO bila ambigu (AGENTS.md §8). Ikuti AGENTS.md. Rencana singkat dulu, tunggu persetujuan.
```

## FIX-GURU-10 — Rujukan BK belum ke backend

```
Kerjakan FIX-GURU-10 dari docs/prd/12_BUGFIX_PORTAL_GURU.md.
Masalah: membuat rujukan BK tidak tersimpan ke backend.
Akar masalah terverifikasi: siswa/page.tsx (tab rujukan_bk) tidak memanggil lmsApi.createCounselingReferral.
Hasil diharapkan (AC): hubungkan form ke createCounselingReferral (POST /me/teacher/counseling-referrals; student_id, reason, priority) dengan validasi backend; invalidate/refetch; PRIVASI (AGENTS.md §6.5): isi/alasan rujukan tidak bocor ke log/notifikasi; IDOR dicegah; uji FIX-GURU-10_* (happy path, validasi gagal, 401, 403).
File: apps/web/src/app/(portal)/portal/guru/siswa/page.tsx (tab rujukan_bk), apps/api/.../Teacher/TeacherHomeroomController.php (storeCounselingReferral).
Ikuti AGENTS.md. Rencana singkat dulu, tunggu persetujuan.
```

## FIX-GURU-11 — Reservasi fasilitas "data tidak valid"

```
Kerjakan FIX-GURU-11 dari docs/prd/12_BUGFIX_PORTAL_GURU.md.
Masalah: reservasi fasilitas gagal 422 "data tidak valid".
Akar masalah terverifikasi: backend validasi facility_id required|uuid|exists:facilities,id; FE mengirim fallback UUID dummy 00000000-0000-0000-0000-000000000001 → gagal exists; dropdown fasilitas kemungkinan tak terisi.
Hasil diharapkan (AC): isi dropdown dari getFacilities() + default fasilitas valid; hapus fallback UUID dummy; submit disable bila facility_id belum dipilih (pesan jelas, bukan 422); format start_at/end_at sesuai backend; konflik jadwal tetap 409; uji FIX-GURU-11_* (happy path, fasilitas tak dipilih ditolak di FE, konflik 409, 401).
File: apps/web/src/app/(portal)/portal/guru/fasilitas/page.tsx (handleCreateBooking), guru-api.ts (createFacilityBooking), apps/api/.../Teacher/TeacherHomeroomController.php (storeFacilityBooking).
Ikuti AGENTS.md. Rencana singkat dulu, tunggu persetujuan.
```

## FIX-GURU-12 — Lapor kendala sarpras timeout

```
Kerjakan FIX-GURU-12 dari docs/prd/12_BUGFIX_PORTAL_GURU.md.
Masalah: laporan kendala sarpras gagal "batas waktu permintaan ke server habis" (timeout).
Langkah: pakai systematic-debugging — reproduksi & UKUR latensi POST /me/teacher/tickets; temukan sumber timeout sebelum memperbaiki (JANGAN sekadar menaikkan timeout). Periksa: URL/hook yang dipakai FE benar; konfigurasi timeout axios di api-client.ts; side-effect create (AuditLog/notifikasi/relasi) yang menggantung; mapping category label.
Hasil diharapkan (AC): create tiket < 2 detik normal & mengembalikan ticket_number; tanpa timeout pada alur normal; operasi berat dipindah ke queue bila perlu (AGENTS.md §4); pesan error FE informatif; uji FIX-GURU-12_* (happy path, validasi gagal, 401) + catatan hasil pengukuran latensi.
File: apps/web/src/app/(portal)/portal/guru/fasilitas/page.tsx (tab tiket), guru-api.ts (useCreateSarprasTicket), apps/web/src/lib/api-client.ts, apps/api/.../Teacher/TeacherHomeroomController.php (storeTicket).
Ikuti AGENTS.md. Rencana singkat dulu, tunggu persetujuan.
```

---

### Urutan pengerjaan yang disarankan
1. P0 dulu: **FIX-GURU-08**, **FIX-GURU-01** (data salah/keputusan hilang).
2. P1 inti alur guru: **FIX-GURU-04, 05, 06, 03, 09, 10, 11, 12, 07**.
3. P2 kosmetik: **FIX-GURU-02**.

Tiap tugas = satu branch `fix/<id>-<slug>` dari `develop`, satu PR kecil (< 500 baris bermakna), CI hijau + review manusia sebelum merge (AGENTS.md §7).
