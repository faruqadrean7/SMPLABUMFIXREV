# Prompt Tugas: BE-CBT-13 — Impor Soal dari DOCX (Mandiri Admin/Guru)

> Kerjakan **BE-CBT-13** sesuai PRD `PRD_BE-CBT-13_IMPOR_DOCX.md`. Ikuti `AGENTS.md`.
> **Tulis rencana singkat dulu, tunggu persetujuan sebelum coding.**
> Semua rute/kolom/enum di Bagian 1 **sudah diverifikasi dari kode** (`apps/api`, branch `develop`,
> 2026-11-03) dan dari `PROMPT_IMPORT_CBT_MATEMATIKA.md` (2026-11-02). JANGAN mengarang (AGENTS.md §6.6, §9).
> Stack API = **Laravel 13 + PostgreSQL + Redis**. Queue default = **driver `database`**.

## Konteks
- Dependensi siap: BE-CBT-01 (bank soal + import massal) & BE-FILE-01 (presign upload) sudah ada.
- Satu tugas = satu ID. **Jangan** menyentuh fitur lain. PR < 500 baris bermakna; pecah bila perlu.

## 0. Keputusan yang SUDAH DITETAPKAN (default; tandai "KONFIRMASI" ke client, jangan tanya ulang ke saya)
- **D1. Reuse jalur import yang ada.** DOCX → draf payload → pratinjau → `commit` memanggil
  `QuestionBankService::importQuestions()`. TIDAK membuat jalur persist baru, TIDAK mengubah kontrak
  `POST /questions/import`.
- **D2. Wajib pratinjau + koreksi manusia** sebelum soal masuk bank. Tidak ada auto-commit.
- **D3. Parsing berjalan di queue** (Job `database`), bukan di request.
- **D4. Format sumber = template baku `docs/mtk/*FORMAT SOAL*`.** Isi soal/kunci asli & file DOCX
  TIDAK masuk git/log (`.gitignore` + bucket privat).
- **D5. Permission reuse `cbt.bank.write`.** Guru hanya bank soal miliknya (cek cakupan di service).
- **D6. Rilis pertama tipe soal: `pg`, `bs`, `isian`, `uraian`.** `pg_kompleks`/`menjodohkan` menyusul
  (butuh konfirmasi format template — Bagian 8).

## 1. Fakta terverifikasi dari kode (pakai ini)

### 1.1 Rute CBT — `routes/api/cbt.php` (prefix `/api/v1`, `auth:sanctum`)
| Aksi | Method + Path | Permission | Controller |
|---|---|---|---|
| Buat bank soal | `POST /question-banks` | `cbt.bank.write` | `QuestionBankController@store` |
| Import massal | `POST /questions/import` | `cbt.bank.write` | `QuestionController@import` |

### 1.2 Rute File — `routes/api/reports.php` & `routes/api/auth.php`
| Aksi | Method + Path | Akses |
|---|---|---|
| Presign | `POST /files/presign` | auth:sanctum |
| Konfirmasi | `POST /files/confirm` | auth:sanctum |
| Signed URL | `GET /files/{id}/url` | auth:sanctum |

### 1.3 Tabel `files` — `database/migrations/2026_10_07_000001_create_storage_files_table.php`
`id(uuid)`, `school_id?`, `owner_id?`, `owner_type(50)?` (polymorphic), `bucket(100)`,
`object_key(500) unique`, `mime_type(100)`, `size_bytes`, `visibility(20)` default `privat`,
`checksum?`, timestamps.

### 1.4 Payload & service import (jangan ubah) — `ImportQuestionsRequest`, `QuestionBankService::importQuestions()` (`app/Services/QuestionBankService.php:350`)
- enum `question_type`: `pg, pg_kompleks, bs, menjodohkan, isian, uraian`.
- `cognitive_level`: `LOTS|MOTS|HOTS`; `difficulty` 1–5; `weight` 0.1–100.
- `body` jsonb (`body.content` wajib string); `options[]`: `label, body, is_correct, match_key`.
- `importQuestions()` membungkus transaksi, menulis `audit_logs` (`question.import`), set `status='aktif'`,
  mengembalikan `{ bank_id, imported_count, questions[] }`.
- Logika kunci (`ExamGradingService`): `pg`/`bs` = tepat 1 `is_correct`; B/S = 2 opsi; `isian` kunci + alternatif.

## 2. Komponen yang DIBUAT

### 2.1 Dependency baru (catat alasan di PR — AGENTS.md §6.10)
- `phpoffice/phpword` (baca `.docx`). Paket aktif dipelihara.

### 2.2 File baru
- Migration `..._create_question_import_jobs_table.php` (Bagian 3). **Jangan edit migrasi lama.**
- `app/Models/QuestionImportJob.php`
- `app/Http/Controllers/Api/V1/Cbt/QuestionImportController.php` (`store`, `show`, `update`, `commit`, `template`)
- `app/Services/Cbt/DocxQuestionParser.php` (DOCX → array soal + error per butir; murni, mudah diuji)
- `app/Services/Cbt/QuestionImportService.php` (orkestrasi: simpan job, panggil parser, `commit` → `importQuestions()`)
- `app/Jobs/ParseQuestionImportJob.php` (queued; set status `parsing`→`parsed`/`failed`)
- Requests: `StartQuestionImportRequest`, `UpdateImportDraftRequest`, `CommitQuestionImportRequest`
  (semua `class-validator` setara FormRequest, `whitelist`/strict).
- Tambah rute di `routes/api/cbt.php`.

### 2.3 Alur
`store` (multipart docx + bank_id) → validasi mime/magic/ukuran → simpan file privat (owner_type=`question_import`)
→ buat job `queued` → dispatch `ParseQuestionImportJob` → worker parse → `parsed` + `parsed_payload`/`parse_errors`
→ `show` pratinjau → `update` koreksi draf → `commit` (Idempotency-Key) → `importQuestions()` → `committed`.

## 3. Migrasi DB (baru, kompatibel mundur)
`question_import_jobs`: `id uuid`, `bank_id uuid` (FK question_banks), `uploaded_by uuid` (FK users),
`source_file_id uuid` (FK files), `status varchar(20)` (`queued|parsing|parsed|committed|failed`),
`parsed_payload jsonb?`, `parse_errors jsonb?`, `imported_count int?`, timestamps.

## 4. Endpoint baru (perbarui OpenAPI + klien tergenerate; error baku §1.2)
| Method + Path | Permission |
|---|---|
| `GET /question-imports/template?format=docx` | `cbt.bank.write` |
| `POST /question-imports` (multipart) | `cbt.bank.write` |
| `GET /question-imports/{id}` | `cbt.bank.write` |
| `PATCH /question-imports/{id}` | `cbt.bank.write` |
| `POST /question-imports/{id}/commit` (Idempotency-Key) | `cbt.bank.write` |

## 5. Keamanan (WAJIB)
- Validasi `.docx` via MIME + **magic bytes** + batas ukuran; file tidak dieksekusi; pindai virus (P1).
- `is_correct`/`match_key` hanya di pratinjau penyusun (`cbt.bank.write`); tak pernah ke klien ujian (§6.6).
- DOCX & `parsed_payload` di bucket privat/DB; **tidak** masuk git; log tanpa teks soal/kunci.
- `commit` idempoten via `Idempotency-Key`; `audit_logs` bertambah (reuse `question.import`).
- Guru impor ke bank milik orang lain → `403` (cek cakupan di service, bukan hanya guard).

## 6. Uji (nama berprefiks `BE-CBT-13`; AGENTS.md §5, doc 10)
- happy path: upload→parse→preview→commit (soal tersimpan, `imported_count` benar).
- validasi: non-docx/magic salah/oversize → `400 VALIDATION_FAILED`.
- 401 tanpa token; 403 bank bukan milik guru.
- aturan bisnis: `pg` tanpa tepat 1 kunci → butir ditandai error, tidak tersimpan.
- idempotensi: dua `commit` Idempotency-Key sama → tidak dobel.
- unit `DocxQuestionParser` dari fixture DOCX **fiktif** (bukan data nyata, AGENTS.md §6.2).

## 7. Definition of Done (AGENTS.md §10)
AC PRD §8 terpenuhi satu per satu · lint/typecheck/test/build lulus · uji berprefiks ID ·
OpenAPI/klien/dokumen diperbarui · permission+cakupan+audit · tanpa rahasia/data pribadi ·
ringkasan PR siap (sebut dependency baru).

## 8. Tunggu keputusan sekolah/client (jangan tebak — AGENTS.md §8)
Lihat PRD §9: D1 template final · D2 rumus (LaTeX vs gambar/OMML) · D3 tipe soal · D4 media/gambar.
Jika belum dijawab: kerjakan hanya `pg/bs/isian/uraian` teks tanpa rumus; tandai sisanya TODO.

## 9. Laporan akhir — pakai format AGENTS.md §11.
