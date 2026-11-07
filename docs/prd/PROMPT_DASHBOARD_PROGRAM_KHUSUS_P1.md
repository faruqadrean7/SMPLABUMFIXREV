# PROMPT — Dashboard Program Khusus · Fase P1 (Panel Kelola Guru)

> Sumber PRD: `docs/prd/PRD_DASHBOARD_PROGRAM_KHUSUS.md`
> Fitur yang dikerjakan: **BE-PROG-02** (API kelola guru pembina) + **GURU-PROG-01** (panel kelola)
> Prioritas: P1 · Backend: Laravel (`apps/api`) · Web: Next.js (`apps/web`)

---

Kerjakan **BE-PROG-02** dan **GURU-PROG-01** dari `docs/prd/PRD_DASHBOARD_PROGRAM_KHUSUS.md`.

## Konteks
- **Dependensi: Fase P0 (BE-PROG-01 + FE-PROG-01) HARUS sudah selesai** — tabel `program_materials/targets/tasks/program_task_submissions` dan endpoint siswa sudah ada.
- Fase ini menambah sisi **guru pembina** (ustadz tahfidz / guru ICP / GPK inklusi) untuk mengisi & menilai, plus promosi riwayat.

## Batasan
- Bangun di atas entitas P0; **jangan** membuat tabel materi/tugas/target duplikat.
- Promosi `meta_data` (mutaba'ah/mood) ke tabel riwayat harus **kompatibel mundur**: baca `meta_data` lama hingga migrasi data beres.
- Migrasi baru saja; jangan edit migrasi lama.

## Yang harus dibangun
1. **Endpoint guru** (prefix `/api/v1`): `GET|POST /program/materials`, `PATCH|DELETE /program/materials/{id}`; `GET|POST /program/targets`, `PATCH /program/targets/{id}`; `GET|POST /program/tasks`, `PATCH /program/tasks/{id}`; `GET /program/tasks/{id}/submissions`, `PATCH /program/submissions/{id}` (nilai + feedback).
2. **Riwayat (opsional sesuai PRD §5.4)**: tabel `mutabaah_logs`, `mood_logs`; milestone PPI sebagai baris `program_targets`.
3. **Panel kelola** di portal guru: tabel materi/tugas/target + form tambah/edit (RHF + Zod), `PermissionGate`. State loading/kosong/error wajib.

## Keamanan (wajib)
- **Cakupan data ketat (anti-IDOR):** guru hanya menulis untuk siswa/kelas binaannya; di luar itu → 403, diperiksa di service (bukan hanya permission).
- Permission: `program.material.write`, `program.task.write`, `program.target.write`, `program.submission.grade`. Deny-by-default.
- Setiap create/update/delete → `audit_logs`. Data sensitif (inklusi) tidak masuk log/notifikasi.

## Acceptance Criteria yang harus lulus
- **BE-PROG-02** AC1–AC3 (menulis hanya untuk binaan → selain itu 403; audit tiap tulis; validasi enum → 400).
- **GURU-PROG-01** AC1–AC2 (guru tambah materi/tugas/target → muncul di sisi siswa; `PermissionGate` sembunyikan aksi tanpa permission, backend tetap otoritas).

## Pengujian (doc 10)
- Nama test berawalan ID (`BePROG02...`, `guru-prog-01...`). Minimal: happy path, validasi gagal, 401, 403 (lintas binaan), satu aturan batas. Jangan turunkan coverage.

## Definition of Done
Sama seperti AGENTS.md §11 + §10: AC terpenuhi, lint/typecheck/test/build lulus, test ditambah, OpenAPI/klien/dokumen diperbarui, permission+cakupan+audit, tak ada data pribadi nyata, state UI lengkap, ringkasan PR siap.

---
**Ikuti AGENTS.md. Pastikan P0 selesai dulu. Tulis rencana singkat, tunggu persetujuan sebelum coding.**
