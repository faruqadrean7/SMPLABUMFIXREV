# PRD — Dashboard Program Khusus (ICP · Tahfidz · Inklusi)

> Status: **Draft untuk ditinjau** · Tanggal: 2026-11-03 · Penulis: tim engineering
> Modul ID: `PROG` · Format ID fitur: `BE-PROG-NN` / `FE-PROG-NN` / `GURU-PROG-NN`
> Backend: Laravel (`apps/api`) · Web/Admin: Next.js (`apps/web`)
> Baca bersama: `04_PRD_BACKEND.md`, `08_DATABASE_SCHEMA.md`, `09_SECURITY_PRIVACY.md`, `10_TESTING_QA.md`, `AGENTS.md`

---

## 1. Latar Belakang & Masalah

Dashboard siswa program khusus saat ini hanya menampilkan **capaian/penilaian**:

| Program | Widget sekarang | Isi |
|---|---|---|
| **ICP** | `icp-widget.tsx` | Skor Cambridge Checkpoint + vocab of the day (paling "nilai-sentris") |
| **Tahfidz** | `tahfidz-widget.tsx` | Progress juz + predikat setoran + checklist mutaba'ah |
| **Inklusi** | `inklusi-widget.tsx` | Kontak GPK + milestone PPI + mood check-in |

Yang **belum ada** di ketiga program: **materi pembelajaran**, **tugas/aktivitas terstruktur**, dan **target belajar yang dikelola guru** (bukan sekadar angka hasil). Padahal program khusus intinya *perjalanan belajar*, bukan rapor. Loop belajar yang sehat: **materi → tugas/latihan → target → penilaian**. Sekarang berat di ujung "penilaian/target ringkas", kosong di "materi" dan "tugas".

Data sumbernya sekarang disimpan sebagai JSON `meta_data` pada tabel `student_program_profiles` (lihat `MeStudentController@submitDailyMood`/`submitMutabaah`), belum ada entitas terstruktur yang bisa diisi guru dan ditampilkan sebagai daftar materi/tugas/target.

## 2. Tujuan & Non-Tujuan

**Tujuan**
- Melengkapi loop belajar tiap program khusus dengan **Materi**, **Tugas/Aktivitas**, dan **Target** yang terkelola.
- Guru pembina program (Ustadz/ah tahfidz, guru ICP, GPK inklusi) bisa **mengisi** materi/tugas/target; siswa bisa **melihat & mengerjakan/menandai**.
- Mempertahankan keamanan: cakupan data per siswa/pembina (anti-IDOR), audit, deny-by-default.

**Non-Tujuan (YAGNI)**
- Bukan LMS penuh baru; penilaian akademik umum tetap di modul nilai/CBT existing.
- Tidak mengarang konten kurikulum (materi tahfidz, soal Cambridge, modul inklusi) — konten diisi sekolah.
- Tidak membangun chat real-time / notifikasi push baru (ikut kanal existing bila ada).

## 3. Keputusan yang Sudah Diambil (oleh pemilik produk)

1. **Scope:** loop penuh, **bertahap** — P0 tampilan siswa, P1 sisi input guru/admin.
2. **Model data:** **entitas khusus program** (bukan reuse `assignments`/`teaching_materials` umum). Didiskriminasi `program_type` + `meta` JSONB agar mengikuti pola `student_program_profiles` yang sudah ada, bukan 9 tabel terpisah.

## 4. Aktor & Hak Akses

| Aktor | Kemampuan |
|---|---|
| Siswa program khusus | Lihat materi & target miliknya; lihat/kumpulkan tugas; tandai progres (mutaba'ah/mood/milestone) |
| Guru pembina (ustadz tahfidz / guru ICP / GPK) | CRUD materi, tugas, target **untuk siswa/kelas binaannya saja** |
| Wali kelas / Kepala Sekolah | Lihat progres program siswa dalam cakupannya (read-only) |
| Orang tua | Lihat progres anak sendiri (read-only) — *P1, opsional* |

Permission (format `modul.resource.aksi`):
`program.material.read|write`, `program.task.read|write`, `program.target.read|write`, `program.submission.read|grade`.
Guard deny-by-default + **policy cakupan data** di service (siswa↔pembina↔kelas), bukan hanya cek permission.

## 5. Model Data (usulan — untuk ditinjau)

Tiga entitas baru + satu submission, semua UUID PK, `school_id` (multi-tenant), `soft delete`, `timestamps`. Mengikuti konvensi `08_DATABASE_SCHEMA.md`.

### 5.1 `program_materials`
| kolom | tipe | catatan |
|---|---|---|
| id | uuid | PK |
| school_id | uuid | tenant |
| program_type | string(30) | `tahfidz`/`icp`/`inklusi` (+ `reguler` bila perlu) |
| title | string | |
| description | text null | |
| material_type | string | tahfidz: `murottal`/`tajwid`/`panduan`; icp: `reading`/`listening`/`past_paper`; inklusi: `modul_adaptif` |
| file_id | uuid null | FK ke tabel `files` existing |
| external_url | string null | alternatif file (mis. tautan murottal) |
| meta | jsonb null | field khusus program (juz/surah, subject/level, domain) |
| created_by | uuid | guru pembina |
| class_id / target_scope | uuid/string null | lingkup: kelas, atau per-siswa |

### 5.2 `program_targets`
| kolom | tipe | catatan |
|---|---|---|
| id | uuid | PK |
| school_id | uuid | |
| student_id | uuid | FK students |
| program_type | string(30) | |
| title | string | tahfidz: "Target 1 Juz"; icp: "Checkpoint Math 5.0"; inklusi: "Modul membaca deskriptif" |
| domain | string null | inklusi: `akademik`/`motorik`/`sosial`; icp: `english`/`math`/`science` |
| target_value | string null | mis. "3 Juz", "5.0/6.0" |
| current_value | string null | progres terkini (bisa diturunkan dari penilaian) |
| period | string null | mis. "Semester Ganjil 2025/2026" / seri Cambridge |
| status | string | `aktif`/`tercapai`/`batal` |
| set_by | uuid | guru pembina |

### 5.3 `program_tasks` + `program_task_submissions`
`program_tasks`: id, school_id, program_type, title, instructions(text), due_at(null), file_id(null), meta(jsonb), created_by, class_id/assigned_student_id.
`program_task_submissions`: id, task_id, student_id, file_id(null), link(null), notes(null), status(`dikumpulkan`/`dinilai`), score(null), feedback(null), submitted_at, graded_by.

### 5.4 Migrasi mutaba'ah/mood/milestone (P1)
Saat ini `meta_data` JSON (hanya "hari ini"). Untuk **riwayat**, promosikan ke tabel ringan: `mutabaah_logs`, `mood_logs`, dan milestone PPI jadi baris `program_targets` (domain terkait). Kompatibel mundur: `meta_data` tetap dibaca hingga migrasi data selesai.

> ⚠️ Migrasi baru saja (jangan ubah migrasi lama; `prisma`/`artisan migrate` baru). Perubahan kompatibel mundur satu versi.

## 6. Endpoint (kontrak — tunduk OpenAPI)

Prefix `/api/v1`. Format sukses/error & kode baku ikut `04_PRD_BACKEND.md §1`.

**Siswa (P0)**
- `GET /me/program/materials` — materi untuk program & kelas siswa.
- `GET /me/program/targets` — target aktif + progres.
- `GET /me/program/tasks` — tugas program + status pengumpulan.
- `POST /me/program/tasks/{id}/submit` — kumpulkan (Idempotency-Key).
- *(existing, dipertahankan)* `POST /me/program/mood`, `POST /me/program/mutabaah`.

**Guru pembina (P1)**
- `GET|POST /program/materials`, `PATCH|DELETE /program/materials/{id}`
- `GET|POST /program/targets`, `PATCH /program/targets/{id}`
- `GET|POST /program/tasks`, `PATCH /program/tasks/{id}`
- `GET /program/tasks/{id}/submissions`, `PATCH /program/submissions/{id}` (nilai/feedback)

Semua tulis → `audit_logs`. Daftar berpaginasi, hindari N+1 (`with`/`select` terarah). Cakupan data diperiksa di service.

## 7. UI (Web)

**Siswa (P0)** — perluas tiap widget + halaman detail:
- Widget dashboard dapat blok ringkas "Materi & Tugas terbaru" + "Target berjalan".
- Halaman detail: `/siswa/hafalan` (tahfidz), `/siswa/cambridge` (icp), `/siswa/ppi` (inklusi) menampilkan daftar penuh materi/tugas/target dengan state **loading / kosong / error** (wajib, per AGENTS.md §4).
- Fetch lewat `api-client` yang digenerate (jangan `fetch` manual). Form pakai RHF + Zod.

**Guru pembina (P1)** — panel kelola di portal guru: tabel materi/tugas/target + form tambah/edit, `PermissionGate`.

## 8. Acceptance Criteria (per fitur)

**BE-PROG-01 — API materi/target/tugas siswa [P0]**
- AC1: Siswa program X hanya melihat materi/target/tugas lingkup program & kelasnya.
- AC2: Siswa program lain / reguler → 403 pada endpoint khusus program yang tak sesuai.
- AC3: Tanpa token → 401.
- AC4: Submit tugas dua kali dengan `Idempotency-Key` sama → satu submission (idempoten).
- AC5: Response daftar berpaginasi & tak ada N+1 (uji query count).

**BE-PROG-02 — API kelola oleh guru pembina [P1]**
- AC1: Guru hanya bisa menulis untuk siswa/kelas binaannya; di luar itu → 403 (anti-IDOR, dicek di service).
- AC2: Setiap create/update/delete menulis `audit_logs`.
- AC3: Validasi DTO (`material_type`/`domain`/`status` sesuai enum) → 400 bila invalid.

**FE-PROG-01 — Tampilan siswa [P0]**
- AC1: Ketiga program menampilkan Materi, Tugas, Target (bukan hanya nilai).
- AC2: State loading/kosong/error tampil benar (uji komponen).
- AC3: Submit tugas sukses memperbarui status tanpa reload penuh.

**GURU-PROG-01 — Panel kelola [P1]**
- AC1: Guru pembina dapat menambah materi/tugas/target dan melihatnya muncul di sisi siswa.
- AC2: `PermissionGate` menyembunyikan aksi tanpa permission (backend tetap otoritas).

## 9. Keamanan & Privasi (wajib, ref doc 09)
- Deny-by-default tiap endpoint; cek **permission + cakupan data**.
- Data inklusi (mood, PPI) & catatan pembinaan bersifat sensitif: tidak masuk log/notifikasi; akses terbatas pembina + pihak berwenang; audit baca untuk data sensitif.
- Unggahan tugas/materi: validasi tipe/ukuran/magic bytes; simpan via mekanisme `files` existing.
- Tidak ada data pribadi nyata di seed/fixture/test (pakai data fiktif).

## 10. Pengujian (ref doc 10)
- Nama test berawalan ID fitur (mis. `BePROG01...`, `fe-prog-01...`).
- Minimal tiap fitur: happy path, validasi gagal, 401, 403, satu aturan bisnis batas (mis. submit setelah `due_at`).
- Jangan turunkan coverage; jangan nonaktifkan test untuk lolos.

## 11. Fase & Dependensi
- **P0:** migrasi tabel + endpoint baca siswa + submit tugas + perluasan 3 widget & 3 halaman detail.
- **P1:** panel kelola guru + promosi riwayat mutaba'ah/mood + (opsional) tampilan progres untuk ortu/wali kelas.
- Dependensi: tabel `files`, `students`, `classes`, `student_program_profiles`, modul auth/permission — semuanya sudah ada.

## 12. Pertanyaan untuk Sekolah (AGENTS.md §8 — jangan diputuskan engineering)
1. **Tahfidz:** skala predikat setoran (Mumtaz/Jayyid/…?), definisi "mutqin", target juz per tingkat.
2. **ICP:** skor target Cambridge Checkpoint per mapel & seri ujian resmi; daftar jenis materi yang sah.
3. **Inklusi:** daftar domain PPI resmi & format milestone; kebijakan kerahasiaan catatan GPK.
4. Retensi data program (berapa lama disimpan) & siapa boleh melihat riwayat.
5. Apakah ortu boleh melihat progres program (P1)?

## 13. Risiko & Asumsi
- Asumsi: `program_type` siswa sudah ter-set (via `students.program_type`/`classes.program_type`) — sudah ada di migrasi `...000041`.
- Risiko: model `meta` JSONB fleksibel tapi bisa jadi tidak konsisten → kurangi dengan validasi DTO per `program_type`.
- Risiko scope: godaan menjadikannya LMS penuh → tahan di batas materi/tugas/target.
