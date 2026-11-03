# PROMPT — Dashboard Program Khusus · Fase P0 (Tampilan Siswa)

> Sumber PRD: `docs/prd/PRD_DASHBOARD_PROGRAM_KHUSUS.md`
> Fitur yang dikerjakan: **BE-PROG-01** (API materi/target/tugas siswa) + **FE-PROG-01** (tampilan siswa)
> Prioritas: P0 · Backend: Laravel (`apps/api`) · Web: Next.js (`apps/web`)

---

Kerjakan **BE-PROG-01** dan **FE-PROG-01** dari `docs/prd/PRD_DASHBOARD_PROGRAM_KHUSUS.md`.

## Konteks
- Program khusus: `tahfidz`, `icp`, `inklusi`. `program_type` siswa sudah ada (migrasi `...000041`, tabel `student_program_profiles`).
- Saat ini dashboard hanya menampilkan penilaian; belum ada Materi/Tugas/Target terstruktur. Mood & mutaba'ah sudah ada di `MeStudentController` (pertahankan).
- Dependensi SUDAH ADA: tabel `files`, `students`, `classes`, `student_program_profiles`, modul auth/permission.

## Batasan
- **Jangan** ubah kontrak/endpoint modul nilai, CBT, atau LMS umum (`assignments`/`teaching_materials`). Buat entitas khusus program.
- **Jangan** edit migrasi lama — tambahkan migrasi baru, kompatibel mundur satu versi.
- **Jangan** mengarang konten kurikulum (predikat, skor target, domain PPI) — lihat "Pertanyaan untuk Sekolah" di PRD §12; jika belum dijawab, pakai seed data fiktif & tandai sebagai placeholder.
- Satu PR per lapisan logis (migrasi+BE, lalu FE) — idealnya < 500 baris bermakna.

## Yang harus dibangun
1. **Migrasi** tabel baru (lihat PRD §5): `program_materials`, `program_targets`, `program_tasks`, `program_task_submissions` (UUID PK, `school_id`, soft delete, timestamps; diskriminasi `program_type` + `meta` JSONB).
2. **Endpoint siswa** (prefix `/api/v1`): `GET /me/program/materials`, `GET /me/program/targets`, `GET /me/program/tasks`, `POST /me/program/tasks/{id}/submit` (dukung `Idempotency-Key`).
3. Pola Controller(tipis) → Service(bisnis + cek cakupan) → Repository/Model. DTO + `class-validator`/FormRequest, `whitelist`. Dokumentasi Swagger + kode error baku (PRD/doc 04 §1.2). Semua tulis → `audit_logs`. Hindari N+1 (`with`/`select` terarah), daftar berpaginasi.
4. **Frontend siswa**: perluas `tahfidz-widget.tsx` / `icp-widget.tsx` / `inklusi-widget.tsx` dengan blok ringkas Materi & Tugas + Target; lengkapi halaman detail `/siswa/hafalan`, `/siswa/cambridge`, `/siswa/ppi` dengan daftar penuh. State **loading/kosong/error** wajib. Fetch via `api-client` yang digenerate (jangan `fetch` manual). Form submit: RHF + Zod.
5. **Seed** data fiktif untuk tiga program agar dashboard tampil terisi (data fiktif, bukan data nyata).

## Keamanan (wajib, PRD §9 + doc 09)
- Deny-by-default; cek **permission + cakupan data** (siswa hanya miliknya; anti-IDOR di service).
- Siswa program lain / reguler → 403 pada endpoint khusus yang tak sesuai.
- Unggahan tugas: validasi tipe/ukuran/magic bytes via mekanisme `files` existing.
- Data inklusi/pembinaan sensitif: jangan masuk log/notifikasi.

## Acceptance Criteria yang harus lulus
- **BE-PROG-01** AC1–AC5 (cakupan per program, 403 lintas program, 401 tanpa token, submit idempoten, paginasi tanpa N+1).
- **FE-PROG-01** AC1–AC3 (ketiga program menampilkan Materi/Tugas/Target, state lengkap, submit memperbarui status tanpa reload penuh).

## Pengujian (doc 10)
- Nama test berawalan ID (`BePROG01...`, `fe-prog-01...`). Minimal: happy path, validasi gagal, 401, 403, satu aturan batas (mis. submit setelah `due_at`).
- Jalankan sebelum commit: `lint`, `typecheck`, `test`, dan `build` bila ada perubahan struktural. Jangan turunkan coverage.

## Definition of Done
- AC di atas terpenuhi satu per satu (sebutkan AC yang diuji) · lint/typecheck/test/build lulus · test ditambah · OpenAPI/klien/dokumen diperbarui · permission + cakupan + audit diterapkan · tak ada rahasia/data pribadi nyata · state UI lengkap · ringkasan PR siap (format AGENTS.md §11).

---
**Ikuti AGENTS.md. Tulis rencana singkat dulu (file, tabel, endpoint, uji, risiko), tunggu persetujuan sebelum coding.**
