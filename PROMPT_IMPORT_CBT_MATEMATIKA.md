# Prompt Tugas: BE-CBT-IMPORT-MTK-01 — Impor Paket PAS Matematika Kelas 9 ke Bank Soal & Ujian CBT

> Semua rute, kolom, enum, dan logika penilaian di bawah **sudah diverifikasi langsung dari
> kode** pada 2026-11-02 (branch `develop`). JANGAN mengarang endpoint/kolom (AGENTS.md §6.6, §9).
> Sumber paket soal: folder lokal `docs/mtk/` (kisi-kisi, naskah soal, kunci & penskoran, template).
>
> **Ruang lingkup:** mendigitalkan 1 paket ujian nyata (PAS/Sumatif Akhir Semester Matematika
> kelas 9 Genap, 30 butir) menjadi **1 bank soal + 26 record soal + 1 ujian SAS** yang siap
> dipakai di CBT, dengan penilaian otomatis penuh. BUKAN membuat fitur CBT baru — hanya memakai
> jalur yang sudah ada (BE-CBT-01 import massal + BE-CBT-02 pembuatan ujian).

---

## 0. Keputusan yang SUDAH DITETAPKAN (patuhi; jangan tanya ulang)
Pemberi tugas sudah menyetujui pendekatan ini. Yang masih butuh input sekolah ada di Bagian 10.

- **D1. Mekanisme impor = artisan command + manifest JSON lokal (gitignored).** Bukan seeder yang
  di-commit, bukan entri manual satu per satu. Alasan di Bagian 6 (menjaga isi soal & kunci asli
  tetap di luar git). Jalur API murni (tanpa kode baru) didokumentasikan sebagai alternatif.
- **D2. Isi soal & kunci asli TIDAK boleh masuk git.** `docs/mtk/` dan manifest JSON di-`.gitignore`.
  File prompt ini & command TIDAK memuat teks soal atau nilai kunci apa pun.
- **D3. Jenis ujian = `SAS`** (Sumatif Akhir Semester = PAS pada Kurikulum Merdeka).
- **D4. Bobot soal menjumlah tepat 100** (lihat Bagian 2) sehingga skor mentah = persentase.
  `passing_grade = 75` (SKM pada kisi-kisi).
- **D5. Author/owner bank & ujian = user guru nyata yang di-resolve dari DB berdasarkan
  email/role**, BUKAN nama di-hardcode. Nama guru/kepsek pada dokumen asli tidak ditulis ke kode.
- **D6. Ruang lingkup berhenti di: bank soal + soal + ujian (status `draft`) + plotting soal.**
  Penjadwalan ruang, token, peserta, dan jadwal live = di luar tiket ini (sudah ada endpointnya,
  dikerjakan sekolah/operator saat mau menggelar ujian).

---

## 1. Fakta terverifikasi dari kode (pakai ini, jangan audit ulang dari nol)

### 1.1 Rute (prefix `/api/v1`, `auth:sanctum`) — `apps/api/routes/api/cbt.php`
| Aksi | Method + Path | Permission | Controller |
|---|---|---|---|
| Buat bank soal | `POST /question-banks` | `cbt.bank.write` | `QuestionBankController@store` |
| Import massal soal | `POST /questions/import` | `cbt.bank.write` | `QuestionController@import` |
| Buat soal satuan | `POST /questions` | `cbt.bank.write` | `QuestionController@store` |
| Buat ujian | `POST /exams` | `cbt.exam.manage` | `ExamController@store` |
| Plot soal ke ujian | `POST /exams/{id}/questions` | `cbt.exam.manage` | `ExamController@assignQuestions` |
| Master mapel | `GET /subjects` | — | `master.php:151` |
| Master jenjang | `GET /grade-levels` | — | `master.php:127` |
| Upload berkas | `POST /files/presign` → `/files/upload-signed/{token}` → `/files/confirm` → `GET /files/{id}/url` | — | `Storage/FileController` |

### 1.2 Skema tabel — `apps/api/database/migrations/2026_10_07_000006_create_cbt_tables.php`
- `question_banks`: `id(uuid)`, `school_id?`, `subject_id`(FK subjects), `grade_level_id?`(FK), `owner_id`(FK users), `name(200)`.
- `questions`: `id`, `bank_id`(FK), `question_type(30)`, **`body jsonb`** (konten+formula+media), `cp_code?`, `cognitive_level(20)` default `MOTS` (`LOTS|MOTS|HOTS`), `difficulty` default 3 (1–5), `weight decimal(5,2)` default 1, `explanation?`, `version` default 1, `status(30)` default `draft`.
- `question_options`: `id`, `question_id`(FK), `label(10)`, **`body jsonb`**, `is_correct bool`, **`match_key(100)?`** (untuk menjodohkan).
- `exams`: `subject_id`, `created_by`, `title(200)`, `exam_type(30)`, `duration_min`, `start_at`, `end_at`, `passing_grade` default 75, `shuffle_questions` default true, `shuffle_options` default true, `package_mode` default `single`, `show_result_mode` default `after_release`, `status` default `draft`.
- `exam_questions`: `exam_id`, `question_id`, `sort_order`, `weight_override?`, `package_code?` — unique `[exam_id, question_id, package_code]`.

### 1.3 Enum valid (dari Form Request)
- `question_type` (`StoreQuestionRequest`/`ImportQuestionsRequest`): `pg, pg_kompleks, bs, menjodohkan, isian, uraian`.
- `cognitive_level`: `LOTS, MOTS, HOTS`. `difficulty`: integer 1–5. `weight`: 0.1–100.
- `exam_type` (`StoreExamRequest.php:20`): `kuis, harian, STS, SAS, ASAT, remedial, pengayaan`.
- `show_result_mode`: `never, after_submit, after_release`. `package_mode`: `single, multi`.

### 1.4 Payload import massal — `ImportQuestionsRequest.php`
```jsonc
POST /api/v1/questions/import
{
  "bank_id": "<uuid bank>",                 // wajib, exists:question_banks
  "questions": [                            // wajib, min 1
    {
      "question_type": "pg",                // enum 1.3
      "body": { "content": "teks soal" },   // wajib; body.content wajib string
      "cp_code": "RATIO-PROP",              // opsional (pakai kode materi dari kisi)
      "cognitive_level": "MOTS",            // opsional (peta dari ranah Bloom, lihat 2.3)
      "difficulty": 2,                      // opsional 1–5 (peta dari Easy/Medium/Hard, lihat 2.3)
      "weight": 3,                          // opsional (lihat bobot 2.2)
      "explanation": "pembahasan",          // opsional
      "options": [
        { "label": "A", "body": { "text": "..." }, "is_correct": false, "match_key": null }
      ]
    }
  ]
}
```
> `QuestionBankService::importQuestions()` (`apps/api/app/Services/QuestionBankService.php:350`)
> menyimpan tiap soal via `Question::create` + tiap opsi via `QuestionOption::create`, membungkus
> dalam transaksi, menulis `audit_logs` (`question.import`), dan **menandai `status = 'aktif'`
> otomatis**. Mengembalikan `{ bank_id, imported_count, questions[] }`.

### 1.5 Logika penilaian otomatis — `apps/api/app/Services/Cbt/ExamGradingService.php`
Verifikasi penting agar struktur opsi benar:
- **`pg` & `bs`** (baris 447): butuh **tepat satu** opsi `is_correct = true`. Jawaban siswa
  dicocokkan ke `option.id` / `option.label` / teks `body.text`. → B/S dimodelkan sebagai 2 opsi
  (`Benar`/`Salah`), satu ditandai benar.
- **`menjodohkan`** (baris 498): skor = `(jumlah pasangan benar / jumlah opsi ber-`match_key`) ×
  bobot`. Tiap opsi = satu premis; `match_key` = jawaban benar premis itu. → 5 pasangan dalam
  **satu** soal `menjodohkan` berbobot 20 ⇒ 4 poin per pasangan benar.
- **`pg_kompleks`/`isian`/`uraian`**: tidak dipakai di paket ini (semua 30 butir objektif ⇒ **100%
  auto-grade**, tanpa koreksi manual).

### 1.6 Keamanan penyajian ke siswa (SUDAH benar — jangan rusak) — `ExamSessionService.php`
- Opsi yang dikirim ke siswa **di-whitelist** ke `id, label, body{text,content,media_url,image_url}`
  (baris 251–281). **`is_correct` & `match_key` TIDAK pernah dikirim ke klien** ⇒ memenuhi §6.6.
- Body soal di-whitelist ke `content, text, formula, media_url, media, image_url, audio_url`
  (baris 286–309). Field lain di `body` TIDAK sampai ke siswa.
- Menjodohkan: server membangun `body.targets` = daftar `match_key` unik yang **diacak** (baris
  317–326), dikirim sebagai kolom pilihan **tanpa membocorkan pasangan benar**.
  ⚠️ **Keterbatasan:** distraktor (pilihan jawaban yang tidak berpasangan, mis. opsi "8 cm" pada
  naskah asli) **tidak didukung** saat `match_key` terisi — kolom target = persis 5 kunci. Lihat
  keputusan Bagian 5.

---

## 2. Pemetaan paket soal → model CBT

### 2.1 Komposisi (dari kisi-kisi & kunci di `docs/mtk/`)
| Blok | Nomor | `question_type` | Jumlah record | Bobot/record | Subtotal |
|---|---|---|---|---|---|
| A. Pilihan Ganda | 1–20 | `pg` | 20 | **3** | 60 |
| B. Menjodohkan | 21–25 | `menjodohkan` (1 soal, 5 opsi ber-`match_key`) | **1** | **20** | 20 |
| C. Benar/Salah | 26–30 | `bs` (tiap pernyataan 1 soal, 2 opsi) | 5 | **4** | 20 |
| **Total** | | | **26 record** | | **100** |

> Menjodohkan 21–25 berbagi **satu stimulus** (tabel + diagram batang-daun tanaman) ⇒ 1 record
> soal berbobot 20. B/S 26–30 berbagi stimulus (gambar 3 bangun A/B/C) tetapi tiap pernyataan
> dinilai independen ⇒ 5 record `bs` berbobot 4 (grading `bs` hanya mendukung 1 kunci/soal).

### 2.2 Aturan bobot
- PG `weight: 3`, B/S `weight: 4`, Menjodohkan `weight: 20`. Jumlah = 100.
- Saat plotting ke ujian boleh pakai `weight_override` bila perlu, tapi default: biarkan `weight`
  soal = bobot final. **Verifikasi** apakah skor akhir sesi = jumlah mentah skor jawaban
  (`refreshSessionTotalScore`) atau dinormalkan; karena total = 100, skor mentah = persentase
  sehingga `passing_grade 75` valid di kedua kasus.

### 2.3 Peta metadata dari kisi-kisi (`1. KISI`)
- `difficulty`: Easy→2, Medium→3, Hard→4 (skala 1–5; ambil kolom "Tingkat Kesulitan" per nomor).
- `cognitive_level`: C2→`LOTS`, C3→`MOTS`, C4→`MOTS`, C5→`HOTS` (ambil kolom "Ranah" per nomor).
- `cp_code`: pakai slug materi dari kolom "Materi" (mis. `RATIO-PROP`, `COORD-GEOM`, `STAT-STEMLEAF`,
  `VOL-CUBOID`). Konsisten huruf besar, tanpa mengarang kode resmi kurikulum.
- `explanation`: isi dari langkah penyelesaian bila tersedia; boleh kosong.

### 2.4 Struktur opsi per tipe
- **PG (1–20):** 4 opsi `label` A–D, `body.text` = teks pilihan, **satu** `is_correct: true`
  sesuai kunci. `match_key: null`.
- **Menjodohkan (21–25 → 1 soal):** 5 opsi; tiap opsi `body.text` = premis (mis. "The number of a"),
  `match_key` = jawaban benar premis (mis. "7 cm"). `label` = 1–5. `is_correct` diabaikan.
- **B/S (26–30 → 5 soal):** tiap soal 2 opsi `label` `Benar`/`Salah` (`body.text` sama),
  `is_correct: true` pada yang sesuai kunci.

---

## 3. Mekanisme impor (D1)

### 3.1 Manifest JSON lokal (gitignored) — sumber kebenaran isi
Buat file lokal **di luar git**, mis. `storage/app/import/mtk-pas-9-genap-2526.json`, berisi:
```jsonc
{
  "bank": { "name": "Matematika Kelas 9 — PAS Genap 2025/2026",
            "subject_match": "Mathematics", "grade_level": "9" },
  "owner": { "email": "<email guru matematika yang ADA di DB>" },
  "exam": { "title": "PAS Matematika Kelas 9 — Genap 2025/2026",
            "exam_type": "SAS", "duration_min": 90, "passing_grade": 75,
            "start_at": null, "end_at": null, "status": "draft",
            "shuffle_questions": true, "shuffle_options": true,
            "package_mode": "single", "show_result_mode": "after_release" },
  "questions": [ /* 26 objek sesuai payload 1.4 + sort_order + media_url */ ]
}
```
Isi `questions[]` disalin dari `docs/mtk/` (naskah + kunci) oleh manusia/agent saat digitalisasi.
**Teks soal & nilai kunci hanya ada di file ini (gitignored), tidak di repo.**

### 3.2 Artisan command baru (tipis, membungkus service yang ADA)
Buat `apps/api/app/Console/Commands/ImportCbtExamPackage.php`:
`php artisan cbt:import-package {manifest} {--fresh}`

Langkah di dalam command (pakai service, jangan query mentah di command):
1. Baca & validasi manifest (struktur + enum sesuai Bagian 1.3/1.4). Gagal cepat bila tak valid.
2. Resolve `subject_id` (cari `subjects` yang namanya cocok `subject_match`), `grade_level_id`
   (cocok `grade_level`), dan `owner` (user by `email`). Bila salah satu tak ketemu → berhenti
   dengan pesan jelas (jangan buat data master baru di sini).
3. Idempoten: cari bank dengan `name`+`subject_id`+`grade_level_id`. Ada → pakai (atau recreate
   bila `--fresh`); tidak ada → `QuestionBankService::createQuestionBank`.
4. Import 26 soal via `QuestionBankService::importQuestions(['bank_id'=>…, 'questions'=>…])`.
   (Jangan tulis ulang logika simpan opsi — service sudah menangani + audit log.)
5. Buat ujian via `ExamService`/`ExamController@store` path (`exam_type: SAS`, dst). Idempoten by
   title+subject.
6. Plot semua 26 soal ke ujian via `assignQuestions` dengan `sort_order` sesuai nomor asli
   (1..20 PG, 21 menjodohkan, 22..26 B/S) dan `weight_override` bila manifest menetapkan.
7. Cetak ringkasan: bank_id, jumlah soal terimpor, exam_id, total bobot (harus 100).

> Command memakai user `owner` sebagai `request->user()` untuk audit (resolve via `Auth::login`
> pada konteks CLI atau teruskan actor ke service). Verifikasi service menerima actor saat dipanggil
> dari CLI; bila perlu, tambah util kecil tanpa mengubah kontrak HTTP.

### 3.3 Alternatif tanpa kode baru (opsional)
Bila sekolah menolak command baru: login sebagai guru ber-`cbt.bank.write`, lalu kirim manifest
langsung — `POST /question-banks` → `POST /questions/import` → `POST /exams` → `POST /exams/{id}/questions`
via skrip HTTP/Postman. Hasil akhirnya sama; hanya kehilangan idempotensi & kemudahan ulang.

---

## 4. Penanganan gambar/media (bagian tersulit — jangan diabaikan)

### 4.1 Soal yang WAJIB gambar (diagram/grafik/foto, tak bisa jadi teks)
No. **4** (grafik garis), **5** (segitiga koordinat M/P/B), **9** (prisma), **11** (kolam renang),
**13** (foto kubah), **17** (scatter A/B 1–2), **18** (opsi a–d berupa scatter — **gambar pada
opsi**), **19–20** (diagram batang-daun), **21–25** (tabel + diagram batang-daun tanaman),
**26–30** (gambar 3 bangun A/B/C).

### 4.2 Soal yang sebaiknya TABEL TEKS, bukan gambar (lebih bersih & bisa dicari)
No. **7** (tabel gradien/intercept), **14/15** (daftar data + tabel frekuensi kosong), **16**
(tabel massa–frekuensi). Render sebagai HTML/teks di `body.content`.

### 4.3 Alur unggah (pakai yang sudah ada)
Untuk tiap gambar: ekstrak dari `docs/mtk/` → `POST /files/presign` → unggah ke URL/`upload-signed`
→ `POST /files/confirm` (dapat `file id`) → `GET /files/{id}/url`. Taruh URL hasil ke
`body.media_url` (soal) atau `options[].body.media_url` / `image_url` (opsi, untuk No. 18).
- ⚠️ **Verifikasi kontrak** `PresignFileRequest`/`ConfirmFileRequest` & bentuk respons (field
  `id`/`url`) di `apps/api/app/Http/Controllers/Api/V1/Storage/FileController.php` sebelum dipakai.
- ⚠️ **Durabilitas URL:** `body.media_url` disimpan sebagai string sekali tulis dan diteruskan apa
  adanya ke siswa. Jika storage privat mengembalikan **signed URL ber-TTL**, URL akan kedaluwarsa.
  → Simpan gambar soal di disk **publik** dan pakai URL publik stabil, ATAU perpanjang
  `ExamSessionService` agar me-resolve `file_id`→URL saat penyajian. Putuskan di Bagian 10 (K2).

### 4.4 Hak cipta
No. 13 memakai foto "Source: republika.com". Untuk dipublikasikan di sistem, ganti dengan foto
kubah milik sekolah/bebas lisensi atau pertahankan atribusi sesuai izin. Jangan commit gambar pihak
ketiga ke repo. (Keputusan konten sekolah — catat, jangan putuskan sendiri; AGENTS.md §8.)

---

## 5. Menjodohkan & B/S — detail + keterbatasan jujur
- **Menjodohkan (1 soal, 5 opsi):** kolom pilihan untuk siswa = `match_key` unik yang diacak
  (otomatis dari server). Pada naskah asli ada **6 pilihan untuk 5 premis** (1 distraktor "8 cm").
  Model saat ini **tidak menampung distraktor** bila `match_key` terisi.
  → **Keputusan (Bagian 10, K1):** (a) terima tanpa distraktor (5 target = 5 premis; sedikit lebih
  mudah dari kertas), atau (b) jadikan **tiket backend terpisah** menambah dukungan distraktor
  (mis. `body.extra_targets[]` digabung ke `targets` di `ExamSessionService`). **Jangan** memaksa
  distraktor lewat cara yang merusak penilaian.
- **B/S (5 soal terpisah):** tiap pernyataan 26–30 jadi 1 soal `bs` + 2 opsi (`Benar`/`Salah`),
  satu `is_correct`. Stimulus gambar bangun A/B/C diulang (`body.media_url`) pada tiap dari 5 soal.

---

## 6. Keamanan & kepatuhan AGENTS.md (WAJIB)
1. **§6.6 kunci jawaban tidak ke klien** — sudah dijamin whitelist di `ExamSessionService`
   (1.6). Impor hanya menulis `is_correct`/`match_key` ke tabel `question_options` (server-side).
   **Jangan** menambah field kunci ke respons sesi/soal siswa.
2. **§6.2/§9 data nyata & kerahasiaan** — isi soal + kunci asli hanya di manifest JSON + `docs/mtk/`
   yang **di-`.gitignore`**. File prompt ini & command TIDAK memuat teks soal/kunci. Tambahkan ke
   `.gitignore`: `docs/mtk/` dan `storage/app/import/`.
3. **Nama guru/kepsek** pada dokumen asli = data pribadi staf → **jangan di-hardcode**. `owner`
   di-resolve dari user DB via email/role (D5).
4. **§6.1 rahasia** — tak ada kredensial di command/manifest; email owner bukan rahasia tapi tetap
   lewat manifest lokal, bukan commit.
5. **Audit** — `importQuestions` sudah menulis `audit_logs`; pastikan pembuatan ujian juga.
6. **Permission** — command berjalan sebagai user ber-`cbt.bank.write` + `cbt.exam.manage`.
   Jalur API wajib login role tsb.

---

## 7. Langkah eksekusi berurutan (untuk pelaksana)
1. Tambah `docs/mtk/` & `storage/app/import/` ke `.gitignore` (bila belum).
2. Digitalkan 30 butir dari `docs/mtk/` ke manifest JSON (3.1), termasuk kunci & match_key.
   Validasi bobot menjumlah 100 & tiap PG/BS tepat 1 `is_correct`.
3. Unggah semua gambar (Bagian 4), isi `media_url` ke manifest.
4. Tulis command `cbt:import-package` (3.2) + uji unit kecil (lihat Bagian 9).
5. Jalankan di lingkungan dev/staging; verifikasi hasil (Bagian 8/9).
6. Serahkan: bank_id + exam_id + ringkasan. Penjadwalan ruang/peserta = di luar tiket (D6).

## 8. Acceptance Criteria
- **AC1** Terbentuk 1 `question_bank` (subject=Mathematics, grade=9, owner=user DB nyata) berisi
  **26 soal** berstatus `aktif`: 20 `pg` + 1 `menjodohkan` + 5 `bs`.
- **AC2** Tiap PG punya 4 opsi dengan **tepat satu** `is_correct`; tiap BS 2 opsi (`Benar`/`Salah`)
  satu benar; soal menjodohkan punya 5 opsi ber-`match_key`.
- **AC3** Total bobot = **100** (20×3 + 1×20 + 5×4). Terbentuk 1 ujian `exam_type=SAS`,
  `passing_grade=75`, `duration_min=90`, status `draft`, dengan 26 `exam_questions` ber-`sort_order`.
- **AC4** Simulasi 1 sesi siswa menjawab semua benar → skor = 100 & lulus; salah sebagian →
  skor proporsional (PG 3/soal, BS 4/soal, menjodohkan 4/pasangan). (Bukti via test/log.)
- **AC5** Respons `GET /exams/{id}/session-questions` untuk siswa **tidak** memuat `is_correct`
  maupun `match_key` (cek whitelist tetap utuh).
- **AC6** Tidak ada teks soal/kunci asli atau nama guru/kepsek yang ter-commit ke git; `docs/mtk/`
  & manifest ter-`.gitignore`.
- **AC7** Command idempoten: menjalankan ulang tanpa `--fresh` tidak menggandakan bank/soal/ujian.

## 9. Verifikasi sebelum klaim selesai
- `cd apps/api && php artisan test --filter=Cbt` (atau tambah test `BeCbtImportMtk01Test`
  berawalan ID fitur: validasi jumlah soal, bobot=100, 1 is_correct/PG, penilaian menjodohkan &
  bs proporsional, dan whitelist siswa bersih dari kunci).
- Jalankan command di DB dev → query jumlah `questions`/`question_options`/`exam_questions`.
- Panggil `session-questions` sebagai siswa percobaan → grep respons memastikan tak ada
  `is_correct`/`match_key`.
- `git status` + `git check-ignore docs/mtk storage/app/import` memastikan tak ikut ter-commit.

## 10. Keputusan untuk pemberi tugas (§8 — jangan diputuskan sendiri)
- **K1. Distraktor menjodohkan:** terima tanpa distraktor (default) ATAU buat tiket backend
  menambah dukungan distraktor. (Bagian 5)
- **K2. Hosting gambar:** disk publik + URL stabil (default) ATAU tambah resolver `file_id`→URL di
  `ExamSessionService` agar bisa pakai storage privat. (Bagian 4.3)
- **K3. Foto kubah No.13:** ganti dengan gambar milik sekolah/bebas lisensi atau pastikan izin
  republika.com. (Bagian 4.4)
- **K4. Owner/author ujian:** email guru matematika nyata yang dipakai sebagai pemilik bank & ujian.
- **K5. Jadwal nyata (`start_at`/`end_at`) + ruang + peserta:** kapan & di ruang mana ujian digelar
  (di luar tiket ini, tapi butuh keputusan sebelum ujian live).

---

## 11. Blok PROMPT siap pakai (salin untuk sesi/agent pelaksana)
```
Kerjakan BE-CBT-IMPORT-MTK-01: impor 1 paket PAS Matematika kelas 9 Genap (30 butir di docs/mtk/)
menjadi 1 bank soal + 26 record soal + 1 ujian SAS di CBT, dengan penilaian otomatis penuh.
Pakai HANYA jalur yang sudah ada (BE-CBT-01 import massal + BE-CBT-02 buat ujian). Ikuti AGENTS.md.
Baca dulu PROMPT_IMPORT_CBT_MATEMATIKA.md (fakta terverifikasi + keputusan terkunci). Tulis rencana
singkat dulu.

Mekanisme (terkunci): artisan command `cbt:import-package {manifest}` yang membungkus
QuestionBankService::importQuestions + pembuatan ujian; manifest JSON lokal (gitignored) jadi sumber
isi soal & kunci. JANGAN commit isi soal/kunci/nama guru. Tambah docs/mtk/ & storage/app/import/ ke
.gitignore.

Peta data: 20 PG (type pg, 4 opsi, 1 is_correct, weight 3) + 1 menjodohkan (5 opsi ber-match_key,
weight 20) + 5 B/S (type bs, 2 opsi Benar/Salah, 1 is_correct, weight 4). Total bobot = 100.
exam_type=SAS, passing_grade=75, duration_min=90, status draft, package single, show_result after_release.
Metadata difficulty/cognitive_level/cp_code dipetakan dari kisi-kisi (Bagian 2.3).

Gambar: soal 4,5,9,11,13,17,18,19,20,21-25,26-30 pakai gambar via alur presign→upload-signed→confirm
→GET /files/{id}/url, taruh URL di body.media_url (opsi No.18 pakai options[].body.image_url).
Soal 7,14,15,16 jadi tabel teks di body.content. Pastikan URL gambar durable (K2).

Keamanan: is_correct & match_key hanya di DB, TIDAK ke klien (whitelist ExamSessionService wajib tetap
utuh). Semua auto-grade (tak ada uraian). Owner bank/ujian = user guru nyata by email (jangan hardcode
nama).

Acceptance Criteria & verifikasi: ikuti Bagian 8 & 9 PROMPT_IMPORT_CBT_MATEMATIKA.md (jumlah soal,
bobot=100, 1 is_correct/PG, penilaian proporsional menjodohkan/bs, respons siswa bebas kunci,
git bersih, command idempoten). Tulis test berawalan BE-CBT-IMPORT-MTK-01.

Keputusan sekolah yang harus ditanyakan dulu bila belum ada jawabannya: K1 distraktor menjodohkan,
K2 hosting gambar, K3 foto kubah No.13, K4 email owner, K5 jadwal/ruang/peserta.

Format laporan akhir: ikuti AGENTS.md bagian 11.
```

---

## Catatan untuk Anda (pemberi tugas)
- **Base branch:** `develop`. **Commit command (nanti):** `feat(be-cbt-import-mtk-01): command impor paket ujian ke bank soal & ujian CBT`.
- Paket ini **cocok sempurna** dengan model CBT: semua tipe soal didukung & 100% dinilai otomatis.
- Pekerjaan nyata terbesar = **digitalisasi 30 butir + 11 gambar** ke manifest; kodenya tipis.
- Dua keterbatasan jujur yang perlu Anda putuskan: **distraktor menjodohkan (K1)** & **durabilitas
  URL gambar (K2)**. Sisanya keputusan konten sekolah (K3–K5).
