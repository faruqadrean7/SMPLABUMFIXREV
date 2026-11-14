# PRD — BE-CBT-13: Impor Soal dari DOCX (Mandiri oleh Admin/Guru)

> **Status: USULAN / DRAFT — belum disetujui.** Ditulis sebagai catatan sesuai AGENTS.md §2.2
> (usulan baru = catatan, bukan implementasi). Belum boleh dikerjakan sebelum keputusan di
> Bagian 9 dijawab sekolah/client dan tiket ini di-ACC.
>
> Fakta rute/kolom/enum di bawah **diverifikasi dari kode** (`apps/api`, branch `develop`) dan dari
> `PROMPT_IMPORT_CBT_MATEMATIKA.md` (diverifikasi 2026-11-02). Stack API = **Laravel + PostgreSQL**.
> Jangan mengarang endpoint/kolom (AGENTS.md §6.6, §9).

---

## 1. Latar & ruang lingkup

**Keinginan client:** admin atau guru mengunggah naskah soal berbentuk **.docx**, lalu sistem
mengubahnya menjadi soal CBT siap pakai.

**Masalah inti:** DOCX bebas (format tiap guru beda) tidak bisa di-parse andal. Sistem berisi data
akademik anak → ketepatan di atas kecepatan (AGENTS.md §0). Karena itu fitur ini **wajib**:
1. Memakai **template DOCX baku** (sudah ada di `docs/mtk/` — "FORMAT SOAL", "KISI", "KUNCI DAN
   PENSKORAN") sebagai satu-satunya format yang dijamin.
2. Menyertakan **langkah pratinjau + koreksi oleh manusia** sebelum soal disimpan.

**Bukan fitur CBT baru.** Ini memperluas jalur yang sudah ada:
- `BE-CBT-01` bank soal + `POST /questions/import` (persist massal).
- `BE-FILE-01` upload bertanda tangan (presign → upload → confirm).
Yang baru: lapisan **parsing DOCX → draf JSON impor → pratinjau → konfirmasi**.

Pola ini sejajar dengan `ADM-*` ImportDialog yang sudah ada untuk Excel:
*Unggah → pratinjau → laporan error per baris → konfirmasi* (06_PRD_SYSTEM_ADMIN.md §52).

**Di luar lingkup:** membuat ujian, plotting ruang/token/peserta (sudah ada endpoint-nya; dikerjakan
operator saat menggelar ujian). Konversi rumus matematika sempurna (lihat Bagian 9-D2).

---

## 2. Aktor & permission

| Aktor | Boleh |
|---|---|
| Admin CBT / operator | impor ke bank soal mana pun dalam cakupannya |
| Guru | impor **hanya** ke bank soal yang ia miliki (`owner_id = user`) / mapel yang diampu |

- Permission dipakai ulang: **`cbt.bank.write`** (sama dengan `POST /questions/import` saat ini).
- Cakupan data diperiksa di service, bukan hanya guard (AGENTS.md §6.4): guru hanya bank soal
  miliknya. Deny-by-default (AGENTS.md §6.3).

---

## 3. Alur (pipeline)

```
(1) Guru unduh template DOCX baku
      │
(2) POST /question-imports  (multipart: file .docx + bank_id)
      │   validasi tipe/ukuran/magic bytes → simpan file (bucket privat) → buat job status=queued
      ▼
(3) Queue worker: parse DOCX  (BE kerja berat → antrean, tidak blok request — AGENTS.md backend)
      │   DOCX → struktur soal → petakan ke payload import (lihat §4) → status=parsed | failed
      ▼
(4) GET /question-imports/{id}  → pratinjau: daftar soal hasil parse + daftar error per butir
      │   guru mengoreksi butir yang salah (PATCH draf)
      ▼
(5) POST /question-imports/{id}/commit  (Idempotency-Key)
      │   panggil QuestionBankService::importQuestions() yang sudah ada → simpan + audit
      ▼
(6) status=committed ; mengembalikan { bank_id, imported_count }
```

Pratinjau berakhir di `draft`; guru wajib menekan commit. Tidak ada soal masuk bank tanpa
persetujuan manusia.

---

## 4. Pemetaan DOCX → payload impor (yang sudah ada)

Target = payload `ImportQuestionsRequest` yang **sudah ada** (jangan ubah kontrak):

```jsonc
POST /api/v1/questions/import         // dipanggil internal oleh commit
{
  "bank_id": "<uuid>",
  "questions": [
    {
      "question_type": "pg",          // enum: pg | pg_kompleks | bs | menjodohkan | isian | uraian
      "body": { "content": "teks soal" },
      "cp_code": "...",               // dari kolom materi di KISI (opsional)
      "cognitive_level": "MOTS",      // LOTS|MOTS|HOTS (peta dari ranah/level di KISI)
      "difficulty": 2,                // 1–5
      "weight": 3,                    // bobot dari "KUNCI DAN PENSKORAN"
      "explanation": "pembahasan",
      "options": [
        { "label": "A", "body": { "text": "..." }, "is_correct": false, "match_key": null }
      ]
    }
  ]
}
```

Aturan pemetaan yang mengikuti logika penilaian terverifikasi (`ExamGradingService`):
- **pg / bs** → tepat **satu** opsi `is_correct = true`. B/S dimodelkan 2 opsi (Benar/Salah).
- **pg_kompleks** → boleh >1 opsi `is_correct = true`.
- **menjodohkan** → pasangan lewat `match_key`.
- **isian** → kunci (+ alternatif) dari "KUNCI DAN PENSKORAN".
- **uraian** → tanpa kunci otomatis; dinilai guru (rubrik) — parser hanya mengisi `body`+`weight`.

---

## 5. Perubahan DB (migrasi baru, kompatibel mundur)

Tabel baru `question_import_jobs` (tidak mengubah tabel lama):

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | uuid | |
| `bank_id` | uuid FK question_banks | tujuan impor |
| `uploaded_by` | uuid FK users | pelapor/pengunggah |
| `source_file_id` | uuid FK files | DOCX asli (bucket privat) |
| `status` | varchar(20) | `queued\|parsing\|parsed\|committed\|failed` |
| `parsed_payload` | jsonb (nullable) | hasil parse untuk pratinjau/koreksi |
| `parse_errors` | jsonb (nullable) | error per butir |
| `imported_count` | int (nullable) | diisi saat commit |
| `created_at/updated_at` | timestamptz | |

Migrasi via `prisma migrate dev`/migration Laravel baru — **jangan edit migrasi lama** (AGENTS.md §4).

---

## 6. Keamanan & privasi (WAJIB)

1. **Kunci jawaban tidak bocor ke klien ujian.** `is_correct`/`match_key` hanya tampil pada
   pratinjau ke **penyusun** (guru/admin pemilik) — tidak pernah ke endpoint yang diakses siswa
   (AGENTS.md §6.6). Pratinjau memakai permission `cbt.bank.write`.
2. **Isi soal & kunci asli jangan masuk git/log.** DOCX & payload parse di bucket privat / DB,
   bukan repo; log tidak memuat teks soal/kunci (sejalan D2 pada impor MTK).
3. **Validasi unggahan:** MIME + **magic bytes** + batas ukuran; hanya `.docx`
   (`application/vnd.openxmlformats-officedocument.wordprocessingml.document`); file tidak
   dieksekusi; pindai virus (P1) — 09_SECURITY_PRIVACY.md §54.
4. **Idempotensi:** `commit` pakai header `Idempotency-Key` → upload/commit dobel tidak menggandakan
   soal (AGENTS.md backend; §1.3 standar API).
5. **Audit:** `commit` menulis `audit_logs` (`question.import` sudah ada); unggah & gagal-parse
   dicatat juga.
6. **Transaksi:** persist soal+opsi dalam satu transaksi (sudah dijamin `importQuestions()`).

---

## 7. Endpoint (baru)

| Aksi | Method + Path | Permission |
|---|---|---|
| Unduh template | `GET /question-imports/template?format=docx` | `cbt.bank.write` |
| Unggah & mulai parse | `POST /question-imports` (multipart) | `cbt.bank.write` |
| Lihat status + pratinjau | `GET /question-imports/{id}` | `cbt.bank.write` |
| Koreksi butir draf | `PATCH /question-imports/{id}` | `cbt.bank.write` |
| Konfirmasi simpan | `POST /question-imports/{id}/commit` (Idempotency-Key) | `cbt.bank.write` |

Kontrak OpenAPI + klien tergenerate diperbarui sebelum merge (AGENTS.md §4). Kode error pakai baku
§1.2 (`VALIDATION_FAILED`, `BUSINESS_RULE_VIOLATION`, dst.).

---

## 8. Acceptance Criteria

- **AC1** Unggah DOCX sesuai template → job `queued` → worker menghasilkan `parsed` dengan daftar
  soal + error per butir.
- **AC2** DOCX bukan `.docx` / melebihi ukuran / magic bytes salah → `400 VALIDATION_FAILED`, job tidak dibuat.
- **AC3** Pratinjau menampilkan tiap butir + `is_correct` **hanya** untuk penyusun; tanpa login/permission → `401/403`.
- **AC4** Guru mengoreksi butir salah lewat `PATCH`, lalu `commit` → soal tersimpan via jalur import
  yang ada; `imported_count` benar; `audit_logs` bertambah.
- **AC5** `commit` dengan `Idempotency-Key` sama dua kali → soal tidak dobel.
- **AC6** Guru mencoba impor ke bank soal milik orang lain → `403 FORBIDDEN` (cakupan data).
- **AC7** pg/bs hasil parse selalu punya tepat satu `is_correct`; pelanggaran → butir ditandai error, bukan tersimpan diam-diam.

## 9. Keputusan yang HARUS diputuskan sekolah/client (AGENTS.md §8, jangan ditebak)

- **D1 — Template.** Pakai persis format `docs/mtk/*FORMAT SOAL*` sebagai template baku? Perlu
  sampel ter-ACC untuk tiap tipe soal.
- **D2 — Rumus matematika.** DOCX Matematika memakai equation (OMML). Konversi OMML→LaTeX tidak
  100% andal. Opsi: (a) wajib ketik rumus pakai sintaks LaTeX di template, atau (b) rumus disisipkan
  sebagai gambar. Mana yang dipilih?
- **D3 — Tipe soal yang didukung di rilis pertama** (mulai dari pg/bs/isian/uraian saja?).
- **D4 — Gambar/media** dalam DOCX: diekstrak otomatis ke bucket & ditautkan, atau diunggah terpisah?
- **D5 — Siapa boleh impor:** admin saja, atau guru juga (default usulan: keduanya, guru dibatasi bank miliknya)?

## 10. Uji (nama berprefiks ID — AGENTS.md §5)
Minimal: happy path (AC1,AC4), validasi gagal (AC2), 401/403 (AC3,AC6), aturan bisnis batas
(AC7 pg satu kunci), idempotensi (AC5). Jangan turunkan coverage.

## 11. Risiko
- Parsing DOCX rapuh terhadap variasi format → dimitigasi template baku + pratinjau manusia.
- OMML→LaTeX (lihat D2) adalah bagian tersulit; sarankan dibatasi di rilis pertama.
