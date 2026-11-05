# 12. PRD Perbaikan Bug Portal Guru (FIX-GURU)

> Dokumen perbaikan bug untuk **Portal Guru** (web Next.js + API Laravel).
> Basis investigasi: branch `feat/be-prog-01-dashboard-program-khusus`, tanggal 2026-11-04.
> Semua akar masalah di bawah sudah **diverifikasi terhadap kode nyata** (bukan tebakan), lengkap dengan file & endpoint.
> Patuhi `AGENTS.md`: satu ID = satu tugas, tulis uji berawalan ID, jangan turunkan coverage, jangan ada data pribadi/rahasia, cek kepemilikan/cakupan (IDOR).

## 0. Ringkasan & Prioritas

| ID | Judul | Area | Prioritas | Akar masalah ringkas |
|----|-------|------|-----------|----------------------|
| FIX-GURU-01 | Riwayat jurnal menampilkan Hadir 0 padahal hadir semua | BE | P0 | `journals()` tidak mengembalikan `attendance_summary` |
| FIX-GURU-02 | Lembar presensi sulit dibaca (teks putih) | FE | P2 | Kontras teks pada tabel/lembar presensi |
| FIX-GURU-03 | Penilaian tugas error saat menyimpan nilai | FE+BE | P1 | Pesan error generik menutupi penyebab; validasi skor vs `max_score` |
| FIX-GURU-04 | Tugas baru langsung berstatus "Selesai Dinilai" | FE | P1 | Badge menilai `unreviewed === 0` saat 0 submission + due date default lampau |
| FIX-GURU-05 | Tambah soal diklik berkali-kali → soal duplikat | FE+BE | P1 | Tombol submit tidak di-disable; tanpa `Idempotency-Key` |
| FIX-GURU-06 | Form tambah soal PG tanpa input opsi & kunci jawaban | FE+BE | P1 | Form hanya kirim teks; `options`/`answer_key` tidak dikirim/disimpan |
| FIX-GURU-07 | Perizinan siswa: "Lihat Berkas" tidak berfungsi | FE+BE | P1 | Tombol hanya `toast`, tidak ada resolusi URL file dari `attachment_file_id` |
| FIX-GURU-08 | Perizinan siswa: keputusan terima/tolak tidak tersimpan | FE | P0 | Halaman dibangun dari data mock; keputusan tak menyentuh/merefresh API |
| FIX-GURU-09 | Pencatatan poin siswa belum tersimpan | FE | P1 | UI tidak memanggil `recordStudentPoint` (state lokal saja) |
| FIX-GURU-10 | Buat rujukan BK belum masuk ke backend | FE | P1 | UI tidak memanggil `createCounselingReferral` (state lokal saja) |
| FIX-GURU-11 | Reservasi fasilitas error "data tidak valid" | FE | P1 | `facility_id` fallback UUID dummy gagal aturan `exists:facilities,id` |
| FIX-GURU-12 | Lapor kendala sarpras "batas waktu permintaan habis" | FE+BE | P1 | Timeout request `POST /me/teacher/tickets` — perlu investigasi performa/timeout |

**Catatan konteks arsitektur:**
- Web client memanggil API lewat `apps/web/src/lib/lms-api.ts` (`lmsApi`) dan `apps/web/src/features/portal/guru/guru-api.ts` (`guruApi`).
- Route guru backend: `apps/api/routes/api/portal.php` grup `me/teacher` → controller `apps/api/app/Http/Controllers/Api/V1/Teacher/*`.
- Halaman `guru/sarpras/page.tsx` hanya redirect ke `guru/fasilitas` (tab `booking` & `tiket`).

---

## FIX-GURU-01 — Riwayat jurnal: Hadir 0 padahal hadir semua (P0)

**Area:** Backend (utama), FE (verifikasi).
**File:**
- `apps/api/app/Http/Controllers/Api/V1/Teacher/TeacherAttendanceController.php` → `journals()`
- `apps/web/src/lib/lms-api.ts` → `getJournals()` (baris 114–147), `createJournalWithAttendance()` (166–251)
- `apps/web/src/app/(portal)/portal/guru/jurnal/page.tsx`

**Reproduksi:** Guru input jurnal, tandai semua siswa "Hadir", simpan → pindah ke tab Riwayat → kartu menampilkan `H: 0`.

**Akar masalah (terverifikasi):**
1. `TeacherAttendanceController::journals()` me-`map` jurnal **tanpa** field `attendance_summary` dan `studentAbsentees` dikembalikan `[]`. Akibatnya `getJournals()` di FE jatuh ke nilai default `{ total:0, hadir:0, ... }` (lms-api.ts baris 132–140) → selalu `H:0`.
2. Tambahan: di `createJournalWithAttendance()`, presensi hanya di-POST ke `/me/teacher/attendance/sessions` **jika `schedule_id` berformat UUID** (baris 199–216). Bila jadwal terpilih bukan UUID valid, presensi tidak pernah tersimpan sama sekali.

**Perbaikan yang diharapkan:**
- `journals()` menghitung `attendance_summary` nyata per jurnal, dengan menautkan sesi presensi (`schedule_id` + `session_date`) ke jurnal, lalu mengagregasi status (`present/hadir`, `sakit`, `izin`, `alpa`, `terlambat`) + `attendance_rate`. Isi juga `studentAbsentees` dari siswa non-hadir.
- Pastikan penyimpanan jurnal & presensi berada dalam **satu alur yang konsisten** (idealnya satu transaksi di endpoint), sehingga tidak bergantung pada format `schedule_id` di sisi klien. Bila presensi tetap endpoint terpisah, FE harus mengirim `schedule_id` yang valid atau backend memetakan jurnal→presensi tanpa syarat UUID di klien.

**Acceptance Criteria:**
- AC1: Setelah menyimpan jurnal dengan N siswa Hadir, `GET /me/teacher/journals` mengembalikan `attendance_summary.hadir === N` dan `attendance_rate` benar.
- AC2: Tab Riwayat menampilkan jumlah H/S/I/A sesuai input (bukan 0).
- AC3: Alur simpan tidak gagal/diam ketika `schedule_id` tidak dikirim sebagai UUID; data presensi tetap tersimpan dan terbaca.
- AC4: Uji feature `FIX-GURU-01_*`: happy path, 401, 403 (guru bukan pemilik jurnal), dan batas (jurnal tanpa sesi presensi → summary 0 yang eksplisit, bukan error).

**Keamanan/cakupan:** Guru hanya boleh membaca jurnal miliknya (`teacher_id`). Pertahankan filter kepemilikan yang sudah ada.

---

## FIX-GURU-02 — Lembar presensi sulit dibaca (teks putih) (P2)

**Area:** Frontend (CSS/kontras).
**File:**
- `apps/web/src/app/(portal)/portal/guru/presensi/page.tsx`
- Periksa juga komponen/lembar cetak presensi bila ada varian "print/export".

**Reproduksi:** Buka Lembar Presensi → sebagian teks berwarna putih di atas latar terang sehingga tidak terbaca.

**Akar masalah (hipotesis, perlu konfirmasi saat implementasi):** Penggunaan `text-white` pada elemen yang latarnya terang (atau state tombol non-aktif), atau stylesheet cetak yang mewarisi latar gelap. Lihat kelas status di `presensi/page.tsx` (mis. baris 333–336 `activeClass: 'bg-...-600 text-white'`) — pastikan teks putih hanya dipakai saat latar berwarna/gelap.

**Perbaikan yang diharapkan:** Audit kontras pada lembar/tabel presensi; teks default gunakan token gelap (`text-slate-900`/`text-slate-700`), teks putih hanya pada latar berwarna. Penuhi kontras WCAG AA (≥ 4.5:1 untuk teks normal).

**Acceptance Criteria:**
- AC1: Seluruh teks pada lembar presensi memiliki kontras ≥ 4.5:1 di mode terang.
- AC2: Tidak ada teks putih di atas latar putih/terang (verifikasi visual di viewport mobile & desktop, termasuk tampilan cetak jika ada).
- AC3: Tidak ada regresi pada state aktif tombol status (Hadir/Sakit/Izin/Alpa).

**Catatan:** Murni perubahan presentasi; tanpa perubahan kontrak API.

---

## FIX-GURU-03 — Penilaian tugas error saat menyimpan nilai (P1)

**Area:** Frontend + Backend.
**File:**
- `apps/web/src/app/(portal)/portal/guru/tugas/[id]/penilaian/page.tsx` (`gradeMutation`, baris 61–89)
- `apps/web/src/lib/lms-api.ts` → `gradeSubmission()` (86–93)
- `apps/api/app/Http/Controllers/Api/V1/Teacher/TeacherLmsController.php` → `gradeSubmission()`
- (Bila "penilaian" yang dimaksud adalah Buku Nilai) `guru/nilai/page.tsx` + `saveBulkGrades()` → `updateBulkGrades()`

**Reproduksi:** Guru membuka penilaian tugas, isi skor, Simpan → muncul "Gagal menyimpan nilai." tanpa detail.

**Akar masalah (terverifikasi sebagian):**
- FE menampilkan pesan **generik** (`onError: toast.error('Gagal menyimpan nilai.')`) sehingga penyebab asli (422/403/500) tersembunyi.
- FE memvalidasi skor 0–100 secara hardcode, padahal tugas memiliki `max_score` sendiri → skor valid bisa ditolak backend atau sebaliknya.

**Perbaikan yang diharapkan:**
1. Konfirmasi penyebab nyata: panggil endpoint `POST /me/teacher/submissions/{id}/grade` dan baca error backend (`error.message`/`code`).
2. FE: tampilkan pesan error server per-kasus; validasi skor terhadap `max_score` tugas (bukan 100 tetap).
3. BE: pastikan validasi `score` (`numeric|min:0|max:max_score`), cek kepemilikan tugas/submission oleh guru, dan kode error baku.

**Acceptance Criteria:**
- AC1: Menyimpan skor valid berhasil (200/201) dan nilai tampil tersimpan.
- AC2: Skor di luar rentang menampilkan pesan validasi spesifik dari server (bukan "Gagal menyimpan nilai." generik).
- AC3: Guru lain (bukan pemilik tugas) mendapat 403 (uji IDOR).
- AC4: Uji `FIX-GURU-03_*`: happy path, validasi gagal, 401, 403.

---

## FIX-GURU-04 — Tugas baru langsung "Selesai Dinilai" (P1)

**Area:** Frontend.
**File:** `apps/web/src/app/(portal)/portal/guru/tugas/page.tsx`

**Reproduksi:** Buat tugas baru → di daftar langsung berlabel "Selesai Dinilai".

**Akar masalah (terverifikasi):**
1. Logika badge: `Terkunci → unreviewed > 0 ? "{n} Perlu Dinilai" : "Selesai Dinilai"`. Untuk tugas baru `submissions_count = 0` dan `reviewed/graded = 0` → `unreviewed = 0` → jatuh ke **"Selesai Dinilai"** walau belum ada pengumpulan.
2. `dueDate` default form = `'2026-10-28T23:59'` (sudah **lampau** terhadap 2026-11-04), sedangkan status aktif/selesai difilter dari `due_at < now`. Tugas baru langsung dianggap lewat tenggat.

**Perbaikan yang diharapkan:**
- Badge membedakan kondisi **belum ada pengumpulan**: jika `submissions_count === 0` → "Belum Ada Pengumpulan"/"Aktif", bukan "Selesai Dinilai". "Selesai Dinilai" hanya saat `submissions_count > 0 && unreviewed === 0`.
- Default `dueDate` form = waktu masa depan yang wajar (mis. +7 hari dari hari ini), bukan tanggal tetap yang lampau.

**Acceptance Criteria:**
- AC1: Tugas baru tanpa pengumpulan menampilkan status "Aktif"/"Belum Ada Pengumpulan".
- AC2: Status "Selesai Dinilai" hanya muncul bila ada ≥1 pengumpulan dan semua sudah dinilai.
- AC3: Default tenggat pada form tidak lampau.
- AC4: Uji unit/komponen `FIX-GURU-04_*` untuk fungsi derivasi status (0 submission, sebagian dinilai, semua dinilai).

---

## FIX-GURU-05 — Tambah soal duplikat karena klik berkali-kali (P1)

**Area:** Frontend (utama) + Backend (idempotency).
**File:**
- `apps/web/src/app/(portal)/portal/guru/ujian/page.tsx` → `handleCreateQuestion()` (107–125), `handleCreateBank()` (84–105)
- `apps/web/src/features/portal/guru/guru-api.ts` → `createQuestion()`
- `apps/api/app/Http/Controllers/Api/V1/Teacher/TeacherExamController.php` → `storeQuestion()` / `storeDirectQuestion()`

**Reproduksi:** Klik "Tambah Soal" beberapa kali cepat → banyak soal identik tercipta.

**Akar masalah (terverifikasi):** `handleCreateQuestion`/`handleCreateBank` tidak memiliki guard `isSubmitting`; tombol submit tidak di-disable selama request berjalan → beberapa POST paralel. Tidak ada `Idempotency-Key`.

**Perbaikan yang diharapkan:**
- FE: state `isSubmitting` lokal (atau pakai mutation `isPending`), disable tombol & cegah submit ganda hingga request selesai.
- BE (sesuai AGENTS.md §4): dukung `Idempotency-Key` untuk operasi create soal agar aman dari retry/klik ganda.

**Acceptance Criteria:**
- AC1: Klik cepat berkali-kali hanya menghasilkan **satu** soal.
- AC2: Tombol dinonaktifkan dengan indikator loading selama proses simpan.
- AC3: POST berulang dengan `Idempotency-Key` sama tidak menggandakan data.
- AC4: Uji `FIX-GURU-05_*`: happy path, submit ganda (idempotent), 401.

---

## FIX-GURU-06 — Form tambah soal PG tanpa opsi & kunci jawaban (P1)

**Area:** Frontend + Backend.
**File:**
- `apps/web/src/app/(portal)/portal/guru/ujian/page.tsx` (modal "Tambah Butir Soal", form soal baris 53–57 & 107–125)
- `apps/web/src/features/portal/guru/guru-api.ts` → `createQuestion()`
- `apps/web/src/lib/lms-api.ts` → `createDirectQuestion()` (430–456) — **membuang `answer_key`** dari body
- `apps/api/app/Http/Controllers/Api/V1/Teacher/TeacherExamController.php` → `storeQuestion()`/`storeDirectQuestion()` (validasi & penyimpanan `options`, `answer_key`)

**Reproduksi:** Pilih tipe "PG", form hanya menyediakan teks soal — tidak ada input opsi A–E maupun penanda jawaban benar.

**Akar masalah (terverifikasi):** Form hanya mengirim `question_type`, `body.text`, `cognitive_level`, `difficulty`. Tidak ada input `options` maupun `answer_key`. Selain itu `createDirectQuestion()` di lms-api menyusun `body` **tanpa** menyertakan `answer_key` walau parameter tersedia.

**Perbaikan yang diharapkan:**
- FE: saat `question_type === 'pg'` (dan tipe lain relevan), tampilkan input dinamis opsi jawaban + penanda jawaban benar (dan untuk isian singkat/benar-salah sesuai tipe). Validasi: minimal 2 opsi, tepat satu kunci benar untuk PG.
- Kirim `options` dan `answer_key` ke backend; perbaiki `createDirectQuestion()` agar tidak membuang `answer_key`.
- BE: validasi & simpan `options` + `answer_key`.

**Acceptance Criteria:**
- AC1: Form PG mewajibkan ≥2 opsi dan tepat satu jawaban benar sebelum simpan.
- AC2: Soal PG tersimpan beserta opsi dan kunci jawaban; terbaca saat detail soal.
- AC3: **Keamanan (AGENTS.md §6.6):** `answer_key` **tidak** ikut terkirim ke klien siswa pada endpoint pengerjaan ujian. Verifikasi payload ujian siswa tidak memuat kunci.
- AC4: Uji `FIX-GURU-06_*`: happy path PG lengkap, validasi gagal (0/1 opsi, tanpa kunci), dan uji bahwa kunci tidak bocor ke endpoint siswa.

---

## FIX-GURU-07 — Perizinan siswa: "Lihat Berkas" tidak berfungsi (P1)

**Area:** Frontend + Backend.
**File:**
- `apps/web/src/app/(portal)/portal/guru/perizinan/page.tsx` (tombol "Lihat Berkas" ~ baris 592: hanya `toast.success(...)`; `attachmentUrl: '#'`)
- `apps/web/src/lib/use-guardian-data.ts` (field `attachment_file_id`, baris ~204/223)
- Endpoint file/storage: `apps/api/app/Services/Storage/FileStorageService.php` dan route file terkait.

**Reproduksi:** Buka detail perizinan → klik "Lihat Berkas" → tidak terjadi apa-apa (hanya toast).

**Akar masalah (terverifikasi):** Tombol tidak membuka/mengunduh berkas; `attachmentUrl` mock bernilai `'#'`, dan item API hanya memiliki `attachment_file_id` tanpa resolusi URL unduh.

**Perbaikan yang diharapkan:**
- Sediakan/ gunakan endpoint unduh berkas aman berbasis `attachment_file_id` (signed URL atau stream dengan otorisasi), lalu buka berkas dari respons tersebut.
- FE: tombol memanggil endpoint unduh dan membuka berkas; tampilkan state loading & error.

**Acceptance Criteria:**
- AC1: Klik "Lihat Berkas" membuka/mengunduh lampiran nyata milik pengajuan tersebut.
- AC2: Guru hanya dapat mengakses berkas pengajuan dalam cakupan wewenangnya (uji IDOR: tidak bisa akses `attachment_file_id` milik kelas lain).
- AC3: Pengajuan tanpa lampiran menampilkan pesan "Tidak ada lampiran" (sudah ada), tanpa error.
- AC4: Uji `FIX-GURU-07_*`: happy path unduh, 403 IDOR, 404 file hilang.

**Keamanan:** Jangan letakkan `file_id`/token di query string yang ter-log; otorisasi kepemilikan wajib (AGENTS.md §6.4).

---

## FIX-GURU-08 — Perizinan siswa: keputusan terima/tolak tidak tersimpan (P0)

**Area:** Frontend (utama).
**File:** `apps/web/src/app/(portal)/portal/guru/perizinan/page.tsx`
Endpoint backend sudah ada: `PATCH /me/teacher/homeroom/leave-requests/{id}/decision` (`decideHomeroomLeaveRequest`).

**Reproduksi:** Setujui/tolak pengajuan → status di UI tidak berubah / tidak persist setelah refresh.

**Akar masalah (terverifikasi):** Halaman menggabungkan **data mock hardcoded** (baris ~82–187: daftar `attachmentUrl:'#'`, status statis) sebagai sumber utama tampilan. Header pun menunjukkan "Mode Demo" saat `apiRequests` kosong. `decideMutation` memanggil API nyata, tetapi bila item aktif adalah record mock, keputusan tidak menyentuh backend; dan daftar tidak di-`invalidate`/refetch dari API.

**Perbaikan yang diharapkan:**
- Hapus data mock; render daftar dari `getHomeroomLeaveRequests()`.
- Setelah `decideHomeroomLeaveRequest` sukses → `invalidateQueries` daftar perizinan agar status terbaru tampil & persist.
- State loading/kosong/error lengkap (AGENTS.md §Web).

**Acceptance Criteria:**
- AC1: Daftar perizinan bersumber dari API (tanpa record fiktif).
- AC2: Setuju/tolak memanggil `PATCH .../decision`, UI memperbarui status, dan tetap setelah refresh.
- AC3: Error backend ditampilkan jelas; state kosong tampil saat tidak ada pengajuan.
- AC4: Uji `FIX-GURU-08_*`: happy path approve, reject, 403 (bukan wali kelas terkait), 401.

**Keamanan/data pribadi (AGENTS.md §6.2):** Hilangkan nama/berkas fiktif yang menyerupai data nyata dari kode.

---

## FIX-GURU-09 — Pencatatan poin siswa belum tersimpan (P1)

**Area:** Frontend.
**File:** `apps/web/src/app/(portal)/portal/guru/siswa/page.tsx` (tab "poin")
API tersedia: `lmsApi.recordStudentPoint` → `POST /me/teacher/student-points` (`TeacherHomeroomController::storeStudentPoint`).

**Reproduksi:** Isi form poin prestasi/pelanggaran → simpan → data hanya muncul lokal, tidak tersimpan (hilang setelah refresh).

**Akar masalah (terverifikasi):** `siswa/page.tsx` hanya memakai `lmsApi.getStudentRoster`; handler poin menyetel **state lokal** (`status:'menunggu'`) dan **tidak** memanggil `recordStudentPoint`.

**Perbaikan yang diharapkan:**
- Hubungkan form poin ke `recordStudentPoint` via `useMutation`; cocokkan field payload dengan validasi backend `storeStudentPoint` (konfirmasi: `student_id`, `point_type_id`/`category`, `points`, `date`, `notes`).
- Invalidasi/refetch daftar poin setelah sukses; state loading/error.

**Acceptance Criteria:**
- AC1: Menyimpan poin memanggil `POST /me/teacher/student-points` dan persist setelah refresh.
- AC2: Validasi gagal menampilkan pesan per-field dari server.
- AC3: Guru hanya mencatat poin untuk siswa dalam cakupannya (IDOR).
- AC4: Uji `FIX-GURU-09_*`: happy path, validasi gagal, 401, 403.

---

## FIX-GURU-10 — Buat rujukan BK belum masuk ke backend (P1)

**Area:** Frontend.
**File:** `apps/web/src/app/(portal)/portal/guru/siswa/page.tsx` (tab "rujukan_bk")
API tersedia: `lmsApi.createCounselingReferral` → `POST /me/teacher/counseling-referrals` (`TeacherHomeroomController::storeCounselingReferral`).

**Reproduksi:** Buat rujukan BK → tidak tersimpan ke backend.

**Akar masalah (terverifikasi):** UI tidak memanggil `createCounselingReferral` (state lokal saja).

**Perbaikan yang diharapkan:**
- Hubungkan form rujukan ke `createCounselingReferral` (`student_id`, `reason`, `priority`), konfirmasi aturan validasi backend.
- Invalidasi/refetch daftar rujukan; state loading/error.

**Acceptance Criteria:**
- AC1: Membuat rujukan memanggil `POST /me/teacher/counseling-referrals` dan persist.
- AC2: Validasi gagal tampil jelas; IDOR dicegah (siswa dalam cakupan guru).
- AC3: **Privasi (AGENTS.md §6.5):** isi/alasan rujukan BK tidak masuk ke log/notifikasi yang tidak semestinya.
- AC4: Uji `FIX-GURU-10_*`: happy path, validasi gagal, 401, 403.

---

## FIX-GURU-11 — Reservasi fasilitas: error "data tidak valid" (P1)

**Area:** Frontend (utama).
**File:**
- `apps/web/src/app/(portal)/portal/guru/fasilitas/page.tsx` → `handleCreateBooking()` (payload `facility_id`, baris ~76–83)
- `apps/web/src/features/portal/guru/guru-api.ts` → `createFacilityBooking()` (~426)
- `apps/api/app/Http/Controllers/Api/V1/Teacher/TeacherHomeroomController.php` → `storeFacilityBooking()` (533–600)

**Reproduksi:** Ajukan reservasi fasilitas → gagal "data tidak valid" (422).

**Akar masalah (terverifikasi):** Backend memvalidasi `facility_id => required|uuid|exists:facilities,id`. FE mengirim **fallback UUID dummy** `00000000-0000-0000-0000-000000000001` saat `facilityId` kosong → gagal aturan `exists` → 422. Kemungkinan dropdown fasilitas tidak terisi/terpilih dari data `GET /facilities`.

**Perbaikan yang diharapkan:**
- Isi dropdown fasilitas dari `getFacilities()`; pilih default fasilitas pertama yang valid.
- Hilangkan fallback UUID dummy; tombol simpan disable bila `facility_id` belum dipilih, dengan pesan jelas.
- Format `start_at`/`end_at` sesuai yang diterima backend (`date`/ISO8601).

**Acceptance Criteria:**
- AC1: Reservasi dengan fasilitas nyata berhasil (201) dan muncul di daftar.
- AC2: Tanpa fasilitas terpilih, submit dicegah dengan pesan, bukan 422 "data tidak valid".
- AC3: Konflik jadwal tetap memunculkan pesan 409 yang sudah ada.
- AC4: Uji `FIX-GURU-11_*`: happy path, fasilitas tidak dipilih (ditolak di FE), konflik 409, 401.

---

## FIX-GURU-12 — Lapor kendala sarpras: "batas waktu permintaan ke server habis" (P1)

**Area:** Frontend + Backend (investigasi performa/timeout).
**File:**
- `apps/web/src/app/(portal)/portal/guru/fasilitas/page.tsx` → tab "tiket", `handleCreateTicket()` (~101–130)
- `apps/web/src/features/portal/guru/guru-api.ts` → `useCreateSarprasTicket` / pembuatan tiket
- `apps/web/src/lib/api-client.ts` → konfigurasi `timeout` axios
- `apps/api/app/Http/Controllers/Api/V1/Teacher/TeacherHomeroomController.php` → `storeTicket()` (605–671)

**Reproduksi:** Kirim laporan kendala sarpras → request timeout ("batas waktu permintaan ke server habis").

**Akar masalah (perlu konfirmasi saat implementasi):** Validasi `storeTicket` sendiri tidak berat (field string + optional `room_id/asset_id` dengan `exists`). Hipotesis yang harus diverifikasi dengan systematic-debugging:
1. Endpoint/hook tiket yang dipakai FE menuju URL yang salah/lambat, atau `timeout` axios terlalu kecil untuk kondisi cold-start.
2. Side-effect saat create (mis. notifikasi/AuditLog/relasi) yang menggantung.
3. `category` dikirim sebagai label ("Laboratorium Komputer") — pastikan backend tidak memicu kueri lambat/validasi menggantung.

**Perbaikan yang diharapkan:**
- Reproduksi & ukur latensi `POST /me/teacher/tickets`; temukan sumber timeout (bukan menaikkan timeout secara buta).
- Perbaiki penyebab (endpoint benar, side-effect dibuat async/queue jika berat sesuai AGENTS.md §4, atau perbaikan kueri).
- Selaraskan pesan error FE agar informatif.

**Acceptance Criteria:**
- AC1: Membuat tiket selesai < 2 detik pada kondisi normal dan mengembalikan `ticket_number`.
- AC2: Tidak ada timeout pada alur normal; error nyata (bila ada) ditampilkan jelas.
- AC3: Operasi berat (jika ada) dipindah ke queue, request tidak terblokir.
- AC4: Uji `FIX-GURU-12_*`: happy path, validasi gagal, 401; serta catatan hasil pengukuran latensi.

---

## Definition of Done (berlaku untuk setiap FIX-GURU-xx)

Mengacu `AGENTS.md` §10 & dokumen `10_TESTING_QA.md`:
- [ ] AC terpenuhi satu per satu (sebutkan AC mana yang diuji).
- [ ] `lint`, `typecheck`, `test`, dan `build` (bila struktural) lulus.
- [ ] Uji baru berawalan ID (`FIX-GURU-xx_*`), minimal: happy path, validasi gagal, 401, 403, dan satu batas bisnis.
- [ ] OpenAPI/klien/dokumen diperbarui bila kontrak berubah (perubahan kontrak butuh persetujuan — AGENTS.md §8).
- [ ] Permission + cakupan data (anti-IDOR) + audit untuk aksi penting.
- [ ] Tidak ada rahasia/data pribadi nyata (hapus mock yang menyerupai data nyata).
- [ ] State UI lengkap (loading/kosong/error).
- [ ] Ringkasan PR sesuai format AGENTS.md §11.

## Hal yang WAJIB ditanyakan ke sekolah/PO (jangan diputuskan sendiri — AGENTS.md §8)
- Apakah create soal/booking perlu `Idempotency-Key` resmi di kontrak OpenAPI (FIX-GURU-05).
- Skema `point_type`/kategori poin & bobot yang baku (FIX-GURU-09).
- Kebijakan retensi & akses berkas lampiran perizinan (FIX-GURU-07).
- Perubahan kontrak apa pun yang menyentuh klien lain (web/admin/mobile).
