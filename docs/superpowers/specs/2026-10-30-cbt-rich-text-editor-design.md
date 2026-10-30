# Editor Soal Kaya CBT — Design Spec

**Feature IDs:** `BE-CBT-11` (sanitasi + body HTML), `BE-CBT-12` (media gambar soal), `BE-CBT-13` (render aman ke pembaca), `FE-CBT-11` (komponen editor + renderer), `FE-CBT-12` (integrasi admin), `FE-CBT-13` (integrasi guru), `FE-CBT-14` (integrasi siswa).
**Status:** Disetujui (brainstorming 2026-10-30). **Pemilik keputusan:** user (faruqadrean7).

## 1. Tujuan
Guru dan admin dapat menyunting **teks soal dan opsi jawaban** CBT dengan editor "mirip Word": format teks (tebal/miring/garis-bawah, daftar), **tabel**, dan **gambar** (upload tombol + drag-drop + paste), lalu siswa melihat konten tersebut dengan benar saat ujian/review. Rumus matematika TIDAK termasuk (fase lain).

## 2. Keputusan yang sudah dikunci
- **Lingkup field:** teks soal (`body.content`) + tiap opsi jawaban (`options[].body.content`). Pembahasan (`explanation`) tetap teks biasa.
- **Pipeline gambar:** Opsi A (aman). Gambar disimpan `privat`; HTML menyimpan `data-file-id`; URL bertanda-tangan singkat disuntikkan server-side saat dibaca oleh konsumen yang sudah terotorisasi.
- **Dependency baru disetujui (3):** FE `@tiptap/*` + `dompurify`; BE `mews/purifier`.
- **Rumus:** ditunda.

## 3. Model data (tanpa migrasi destruktif)
`questions.body` & `question_options.body` tetap `jsonb`. Semantik berubah:
- `body.content`: **HTML tersanitasi** (sebelumnya teks polos; teks lama tetap valid HTML → kompatibel mundur).
- `body.formula`, `body.media_url`: dibiarkan apa adanya (legacy; renderer tetap menampilkannya bila ada).
- Gambar di HTML berbentuk `<img data-file-id="{uuid}" alt="...">` **tanpa** atribut `src` saat disimpan.
Tidak ada perubahan kolom DB. Tidak ada migrasi.

## 4. Kontrak API
### 4.1 Perubahan validasi (kompatibel)
`StoreQuestionRequest` / `UpdateQuestionRequest`:
- `body.content`: `required|string|max:100000` (kini boleh HTML).
- `options.*.body.content`: `nullable|string|max:20000`.
Body tetap bertipe `string` → klien lama (mobile placeholder) tidak rusak.

### 4.2 Endpoint baru (upload media) — `BE-CBT-12`
`POST /api/v1/cbt/question-media` — middleware `permission:cbt.bank.write`.
- Request (multipart): `file` (gambar), `bank_id` (uuid, exists, pemohon harus boleh menulis bank itu → cegah IDOR).
- Validasi: mime ∈ {image/jpeg, image/png, image/webp, image/gif}; ukuran ≤ 2 MB (2097152 byte); verifikasi **magic bytes** (`getimagesize`), bukan sekadar header mime.
- Simpan via `FileStorageService` (visibility `privat`, bucket `cbt`, owner_type `question_bank`, owner_id `bank_id`, purpose `cbt_question_media`).
- Response 201: `{ data: { file_id, mime_type, preview_url } }` (`preview_url` = URL bertanda-tangan TTL 15 mnt untuk pratinjau langsung di editor).

### 4.3 Resolusi media saat dibaca — `BE-CBT-13`
Server menyuntikkan `src` bertanda-tangan ke `<img data-file-id>` **hanya** pada payload yang sudah terotorisasi:
- `QuestionController@show`, `@index` (pembaca: pemilik/izin `cbt.bank.write`).
- `ExamSessionController@questions` dan jalur review (pembaca: siswa dengan sesi aktif pada ujian itu).
Karena tiap endpoint sudah mengautorisasi "soal mana yang boleh dilihat", URL gambar hanya pernah diterbitkan untuk pembaca yang berhak. Tidak ada endpoint resolusi media untuk siswa.

## 5. Keamanan (AGENTS.md §6 — wajib)
- **Sanitasi backend** (`QuestionContentSanitizer`, mews/purifier config `cbt`): whitelist tag `p, br, strong, em, u, s, ul, ol, li, table, thead, tbody, tr, td, th, img, span`; atribut `img` hanya `data-file-id, alt, width, height`; buang `script/style/on*`/`src` eksternal. Deny-by-default.
- **Render frontend**: `RichContent` memakai DOMPurify (pertahanan lapis dua) sebelum `dangerouslySetInnerHTML`.
- **Kunci jawaban**: payload siswa tetap TANPA `is_correct`, `match_key`, `explanation` (invarian lama dipertahankan & diuji ulang).
- **IDOR**: upload dicek kepemilikan bank; guru hanya bank miliknya/berizin.
- **Upload**: tipe+ukuran+magic bytes; nama objek acak (UUID); privat.

## 6. Permukaan frontend
- Baru: `src/features/cbt/rich-editor/RichQuestionEditor.tsx` (TipTap + toolbar), `use-image-upload.ts` (upload+drag+paste), `RichContent.tsx` (render read-only tersanitasi).
- Ubah: `admin/cbt/questions/page.tsx`, `guru/ujian/page.tsx`, `siswa/cbt/[id]/page.tsx` (+ review), `features/cbt/cbt-api.ts`, `types/cbt.ts`.

## 7. Mobile (di luar lingkup sekarang)
Layar CBT mobile masih placeholder. `body.content` menjadi HTML; kontrak tetap `string` sehingga tidak rusak, namun mobile akan menampilkan tag mentah hingga ditambah renderer HTML. Dicatat untuk tim mobile sebagai tindak lanjut.

## 8. Definition of Done
Lint+typecheck+test+build hijau; uji berawalan ID fitur; OpenAPI + dokumen diperbarui (endpoint media baru); permission+scope+sanitasi diterapkan; tanpa rahasia/data pribadi nyata; state UI (loading/kosong/error) lengkap pada editor.
