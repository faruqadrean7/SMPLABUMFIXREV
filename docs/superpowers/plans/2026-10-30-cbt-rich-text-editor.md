# Editor Soal Kaya CBT — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Beri guru & admin editor soal CBT "mirip Word" (format teks, tabel, gambar) untuk teks soal + opsi jawaban, dan tampilkan konten itu dengan aman ke siswa.

**Architecture:** `body.content` (jsonb) berubah semantik dari teks polos → HTML tersanitasi (kompatibel mundur). Gambar di-upload privat, HTML menyimpan `data-file-id`, dan server menyuntikkan `src` bertanda-tangan singkat hanya pada payload yang sudah terotorisasi (admin/guru show, sesi siswa). Editor frontend berbasis TipTap; render read-only via DOMPurify.

**Tech Stack:** Laravel 11 / PHP 8.5, Pest/PHPUnit, mews/purifier. Next.js 14 / React 18, TipTap (@tiptap/react + starter-kit + image + table*), DOMPurify, Vitest, Playwright.

**Spec:** `docs/superpowers/specs/2026-10-30-cbt-rich-text-editor-design.md`

## Global Constraints
- Branch `feat/cbt-rte-editor-soal` dari `develop`; Conventional Commits dengan ID fitur (mis. `feat(be-cbt-11): ...`); PR < 500 baris bermakna, pecah per fase.
- TypeScript `strict`, tanpa `any` tak berjustifikasi. Identifier/komentar Inggris; teks UI & pesan error Bahasa Indonesia.
- Backend pola Controller→Service→Repository; input via DTO `class-validator`/FormRequest `whitelist`; tiap endpoint `@Permissions`/`permission:` + Swagger. Operasi multi-tabel transaksional. Jangan edit migrasi lama — **tidak ada migrasi baru di fitur ini**.
- Keamanan non-negosiabel (AGENTS.md §6): deny-by-default, cek scope/kepemilikan (anti-IDOR), sanitasi semua input, unggahan dicek tipe/ukuran/magic bytes, kunci jawaban CBT tak dikirim ke klien. Tanpa rahasia/data pribadi nyata di kode/fixture/log.
- Nama uji berawalan ID fitur. Jangan turunkan coverage. Jalankan `lint`, `typecheck`, `test` sebelum commit.
- Batas gambar: mime {jpeg,png,webp,gif}, ≤ 2.097.152 byte, visibility `privat`, bucket `cbt`, purpose `cbt_question_media`.
- Whitelist tag HTML: `p br strong em u s ul ol li table thead tbody tr td th img span`; atribut img hanya `data-file-id alt width height`.

## Review Focus
- **HTML berbahaya tersimpan** (`<script>`, `<img onerror=...>`, `<a href="javascript:">`, atribut `style`/`on*`) → harus tersanitasi hilang di store (diuji di Task 2).
- **IDOR upload**: guru meng-upload `bank_id` milik guru lain → 403 (diuji di Task 4).
- **Kebocoran kunci jawaban**: payload `session-questions` memuat `is_correct`/`match_key`/`explanation` → harus tidak ada (diuji di Task 6).
- **Kebocoran gambar**: `data-file-id` di-resolve menjadi URL untuk pemohon tak berhak → URL hanya terbit pada payload terotorisasi; sesi siswa kadaluarsa/tiada → tanpa `src` (diuji di Task 6).
- **Konten lama (teks polos) & kosong**: soal lama tanpa tag, atau `body.content` kosong/sangat panjang (>100k) → render tetap benar / validasi menolak (diuji di Task 2 & 7).

---

## FASE 0 — Dependensi & scaffolding

### Task 0A: Backend — pasang & konfigurasi mews/purifier
**Files:**
- Modify: `apps/api/composer.json`, `apps/api/composer.lock`
- Create: `apps/api/config/purifier.php` (config `cbt` sesuai whitelist Global Constraints)

- [ ] **Step 1:** `cd apps/api && composer require mews/purifier` lalu `php artisan vendor:publish --provider="Mews\Purifier\PurifierServiceProvider"`.
- [ ] **Step 2:** Edit `config/purifier.php`: tambah profil `cbt` dengan `HTML.Allowed` = daftar tag/atribut Global Constraints; aktifkan `HTML.TargetBlank=false`, buang `style`. Izinkan atribut kustom `img[data-file-id]` via `Attr` whitelist (gunakan `HTML.AllowedAttributes` + `%HTML.Trusted=false`).
- [ ] **Step 3:** Verifikasi: `php artisan config:clear && php artisan tinker --execute="echo app('purifier')->clean('<img src=x onerror=alert(1) data-file-id=a>', 'cbt');"` → keluaran tanpa `onerror`/`src`, `data-file-id` dipertahankan.
- [ ] **Step 4:** Commit: `chore(be-cbt-11): add mews/purifier + cbt sanitizer profile`.

### Task 0B: Frontend — pasang TipTap + DOMPurify
**Files:**
- Modify: `apps/web/package.json`, `apps/web/package-lock.json`

- [ ] **Step 1:** `cd apps/web && npm i @tiptap/react@^2 @tiptap/pm@^2 @tiptap/starter-kit@^2 @tiptap/extension-image@^2 @tiptap/extension-table@^2 @tiptap/extension-table-row@^2 @tiptap/extension-table-header@^2 @tiptap/extension-table-cell@^2 @tiptap/extension-underline@^2 dompurify@^3` dan `npm i -D @types/dompurify`.
- [ ] **Step 2:** Jalankan `npm run typecheck` → hijau.
- [ ] **Step 3:** Commit: `chore(fe-cbt-11): add tiptap + dompurify deps`.

---

## FASE 1 — Backend: sanitasi & body HTML (`BE-CBT-11`)

### Task 1: QuestionContentSanitizer
**Files:**
- Create: `apps/api/app/Services/Cbt/QuestionContentSanitizer.php`
- Test: `apps/api/tests/Unit/Cbt/BeCbt11SanitizerTest.php`

**Interfaces:**
- Produces: `QuestionContentSanitizer::sanitize(string $html): string` (profil `cbt`; selain membersihkan, **menghapus atribut `src` dari `<img>`** sambil mempertahankan `data-file-id`), dan `sanitizeBody(?array $body): ?array` (menyanitasi `body.content`; `formula`/`media_url` dibiarkan).

- [ ] **Step 1: Tulis uji gagal** `BeCbt11SanitizerTest`: `test_be_cbt_11_strips_script_and_event_handlers` (`<script>`, `onerror`, `javascript:` hilang), `test_be_cbt_11_keeps_allowed_tags_table_img` (`<table>`, `<img data-file-id="x">` bertahan, `src` eksternal dibuang), `test_be_cbt_11_plain_text_passes_through` (teks polos utuh), `test_be_cbt_11_sanitize_body_only_touches_content`.
- [ ] **Step 2:** Jalankan `cd apps/api && ./vendor/bin/pest --filter=BeCbt11` → FAIL (class belum ada).
- [ ] **Step 3:** Implement `QuestionContentSanitizer` memakai `app('purifier')->clean($html,'cbt')` lalu post-process hapus `src` pada `<img>` (DOMDocument) sambil jaga `data-file-id`.
- [ ] **Step 4:** Jalankan `--filter=BeCbt11` → PASS.
- [ ] **Step 5:** Commit `feat(be-cbt-11): add question content sanitizer`.

### Task 2: Terapkan sanitasi di simpan soal + validasi HTML
**Files:**
- Modify: `apps/api/app/Services/QuestionBankService.php` (jalur create/update question + options) atau `QuestionController` service call — gunakan yang menyimpan `body`.
- Modify: `apps/api/app/Http/Requests/Cbt/StoreQuestionRequest.php`, `.../UpdateQuestionRequest.php`
- Test: `apps/api/tests/Feature/Cbt/BeCbt11StoreQuestionTest.php`

**Interfaces:**
- Consumes: `QuestionContentSanitizer::sanitizeBody()` (Task 1).
- Produces: soal tersimpan dengan `body.content` & `options[].body.content` tersanitasi.

- [ ] **Step 1: Tulis uji gagal** `BeCbt11StoreQuestionTest`: `test_be_cbt_11_store_sanitizes_body_html` (POST `/api/v1/cbt/questions` body.content mengandung `<script>` → tersimpan tanpa script), `test_be_cbt_11_store_sanitizes_option_html`, `test_be_cbt_11_rejects_content_over_100k` (422), `test_be_cbt_11_requires_cbt_bank_write` (403 tanpa izin), `test_be_cbt_11_unauthenticated_401`.
- [ ] **Step 2:** `./vendor/bin/pest --filter=BeCbt11StoreQuestion` → FAIL.
- [ ] **Step 3:** Tambah aturan `body.content` `max:100000` + `options.*.body.content` `nullable|string|max:20000` di kedua Request; panggil `sanitizeBody()` di service sebelum persist (question & tiap option) dalam transaksi yang sudah ada.
- [ ] **Step 4:** `--filter=BeCbt11StoreQuestion` → PASS.
- [ ] **Step 5:** Commit `feat(be-cbt-11): sanitize question & option HTML on store/update`.

---

## FASE 2 — Backend: upload media gambar soal (`BE-CBT-12`)

### Task 3: QuestionMediaService (upload)
**Files:**
- Create: `apps/api/app/Services/Cbt/QuestionMediaService.php`
- Test: `apps/api/tests/Unit/Cbt/BeCbt12MediaServiceTest.php`

**Interfaces:**
- Consumes: `FileStorageService` (presign/simpan privat; lihat `app/Services/.../FileStorageService.php`).
- Produces: `QuestionMediaService::upload(\Illuminate\Http\UploadedFile $file, string $bankId, User $user): array` → `['file_id'=>, 'mime_type'=>, 'preview_url'=>]`; `signedUrl(string $fileId, int $ttlMin=15): string`; `resolveMediaInHtml(string $html, int $ttlMin=15): string` (ganti `data-file-id`→`src`).

- [ ] **Step 1: Tulis uji gagal** `BeCbt12MediaServiceTest`: `test_be_cbt_12_upload_rejects_non_image_mime`, `test_be_cbt_12_upload_rejects_over_2mb`, `test_be_cbt_12_upload_rejects_fake_magic_bytes` (file .png berisi teks), `test_be_cbt_12_upload_stores_private_and_returns_ids`, `test_be_cbt_12_resolve_injects_src_for_known_file_id`, `test_be_cbt_12_resolve_ignores_unknown_id` (gunakan `Storage::fake`).
- [ ] **Step 2:** `./vendor/bin/pest --filter=BeCbt12MediaService` → FAIL.
- [ ] **Step 3:** Implement service: validasi mime+size+`getimagesize` magic bytes; simpan via FileStorageService (privat, bucket `cbt`, owner_type `question_bank`, owner_id `$bankId`, purpose `cbt_question_media`); `signedUrl` pakai signed temporary route berbasis `files.download.signed`; `resolveMediaInHtml` via DOMDocument.
- [ ] **Step 4:** `--filter=BeCbt12MediaService` → PASS.
- [ ] **Step 5:** Commit `feat(be-cbt-12): add question media upload service`.

### Task 4: Endpoint POST /cbt/question-media (+scope/IDOR)
**Files:**
- Create: `apps/api/app/Http/Controllers/Api/V1/Cbt/QuestionMediaController.php`
- Create: `apps/api/app/Http/Requests/Cbt/StoreQuestionMediaRequest.php`
- Modify: `apps/api/routes/api/cbt.php` (route + `permission:cbt.bank.write`)
- Test: `apps/api/tests/Feature/Cbt/BeCbt12UploadEndpointTest.php`

**Interfaces:**
- Consumes: `QuestionMediaService::upload()` (Task 3).
- Produces: `POST /api/v1/cbt/question-media` → 201 `{data:{file_id,mime_type,preview_url}}`.

- [ ] **Step 1: Tulis uji gagal** `BeCbt12UploadEndpointTest`: `test_be_cbt_12_upload_ok_returns_file_id` (guru pemilik bank), `test_be_cbt_12_upload_forbidden_other_bank_idor` (403), `test_be_cbt_12_upload_requires_permission` (403 tanpa `cbt.bank.write`), `test_be_cbt_12_upload_401`, `test_be_cbt_12_upload_422_bad_file`.
- [ ] **Step 2:** `--filter=BeCbt12UploadEndpoint` → FAIL.
- [ ] **Step 3:** Implement controller tipis + Request (`file` image, `bank_id` exists + cek pemohon boleh tulis bank → gunakan kebijakan scope yang sama dengan store question); daftarkan route dengan Swagger annotation.
- [ ] **Step 4:** `--filter=BeCbt12UploadEndpoint` → PASS.
- [ ] **Step 5:** Commit `feat(be-cbt-12): add question media upload endpoint with scope check`.

---

## FASE 3 — Backend: render aman ke pembaca (`BE-CBT-13`)

### Task 5: Resolusi media di show/index soal (admin/guru)
**Files:**
- Modify: `apps/api/app/Http/Controllers/Api/V1/QuestionController.php` (`show`, `index`) atau resource-nya
- Test: `apps/api/tests/Feature/Cbt/BeCbt13ShowResolveTest.php`

**Interfaces:**
- Consumes: `QuestionMediaService::resolveMediaInHtml()`.

- [ ] **Step 1: Tulis uji gagal** `BeCbt13ShowResolveTest`: `test_be_cbt_13_show_injects_signed_src` (GET question → `<img>` punya `src` bertanda-tangan + tetap `data-file-id`), `test_be_cbt_13_show_requires_cbt_bank_write`.
- [ ] **Step 2:** `--filter=BeCbt13ShowResolve` → FAIL.
- [ ] **Step 3:** Panggil `resolveMediaInHtml` atas `body.content` & `options[].body.content` di jalur baca show/index.
- [ ] **Step 4:** PASS → Commit `feat(be-cbt-13): resolve question media on admin/guru read`.

### Task 6: Resolusi media + jaga kunci jawaban di sesi siswa
**Files:**
- Modify: `apps/api/app/Http/Controllers/Api/V1/ExamSessionController.php` (`questions` + jalur review)
- Test: `apps/api/tests/Feature/Cbt/BeCbt13SessionQuestionsTest.php`

- [ ] **Step 1: Tulis uji gagal** `BeCbt13SessionQuestionsTest`: `test_be_cbt_13_session_questions_inject_src`, `test_be_cbt_13_session_questions_exclude_answer_key` (tidak ada `is_correct`/`match_key`/`explanation`), `test_be_cbt_13_session_requires_active_session` (403/409 tanpa sesi), `test_be_cbt_13_session_401`.
- [ ] **Step 2:** `--filter=BeCbt13SessionQuestions` → FAIL.
- [ ] **Step 3:** Terapkan `resolveMediaInHtml` pada body soal & opsi di payload sesi; pastikan field kunci jawaban tetap tidak disertakan (verifikasi/penguatan invarian lama).
- [ ] **Step 4:** PASS → Commit `feat(be-cbt-13): resolve media & guard answer key in student session`.
- [ ] **Step 5:** Perbarui OpenAPI (endpoint media + catatan body HTML) di dokumen kontrak API; commit `docs(be-cbt-12): openapi question-media`.

---

## FASE 4 — Frontend: komponen editor + renderer (`FE-CBT-11`)

### Task 7: RichContent (render read-only tersanitasi) + tipe
**Files:**
- Create: `apps/web/src/features/cbt/rich-editor/RichContent.tsx`
- Modify: `apps/web/src/types/cbt.ts` (`QuestionBody {content:string; formula?:string; media_url?:string}`, `QuestionOptionItem` + `body?:{content?:string}`)
- Test: `apps/web/src/test/fe-cbt-11-rich-content.test.ts`

**Interfaces:**
- Produces: `<RichContent html={string} className?/>` → merender HTML yang sudah di-DOMPurify (whitelist tag sama); menerima teks polos lama apa adanya.

- [ ] **Step 1: Tulis uji gagal** (vitest/jsdom) `fe-cbt-11-rich-content`: `fe-cbt-11 strips script on render`, `fe-cbt-11 renders table and img`, `fe-cbt-11 renders legacy plain text`.
- [ ] **Step 2:** `cd apps/web && npx vitest run src/test/fe-cbt-11-rich-content.test.ts` → FAIL.
- [ ] **Step 3:** Implement `RichContent` (DOMPurify config tag/atribut whitelist; `dangerouslySetInnerHTML`).
- [ ] **Step 4:** PASS → Commit `feat(fe-cbt-11): add RichContent renderer`.

### Task 8: use-image-upload hook + cbt-api
**Files:**
- Create: `apps/web/src/features/cbt/rich-editor/use-image-upload.ts`
- Modify: `apps/web/src/features/cbt/cbt-api.ts`
- Test: `apps/web/src/test/fe-cbt-11-upload.test.ts`

**Interfaces:**
- Produces: `cbtApi.uploadQuestionMedia(bankId: string, file: File): Promise<{fileId:string;mimeType:string;previewUrl:string}>`; `useImageUpload(bankId)` → `{ uploadAndGetNode(file): Promise<{src,fileId}> }` (dipakai editor untuk tombol/drag/paste).

- [ ] **Step 1: Tulis uji gagal** `fe-cbt-11-upload`: `fe-cbt-11 uploadQuestionMedia posts multipart & returns ids` (mock api-client), `fe-cbt-11 rejects non-image client-side`.
- [ ] **Step 2:** vitest → FAIL.
- [ ] **Step 3:** Implement API call (pakai `api-client` tergenerate, bukan fetch manual) + hook validasi tipe/ukuran klien.
- [ ] **Step 4:** PASS → Commit `feat(fe-cbt-11): add question media upload api + hook`.

### Task 9: RichQuestionEditor (TipTap + toolbar)
**Files:**
- Create: `apps/web/src/features/cbt/rich-editor/RichQuestionEditor.tsx`
- Create: `apps/web/src/features/cbt/rich-editor/Toolbar.tsx`
- Test: `apps/web/src/test/fe-cbt-11-editor.test.ts`

**Interfaces:**
- Consumes: `useImageUpload` (Task 8).
- Produces: `<RichQuestionEditor value={html} onChange={(html)=>void} bankId placeholder? dense?/>` dengan StarterKit+Underline+Image+Table*; toolbar: bold/italic/underline, bullet/number list, sisip/hapus tabel (+baris/kolom), sisip gambar (tombol+drag+paste). Saat emit `onChange`, `<img>` membawa `data-file-id` (src volatile dibersihkan server saat simpan).

- [ ] **Step 1: Tulis uji gagal** `fe-cbt-11-editor`: `fe-cbt-11 editor serializes bold+table to html`, `fe-cbt-11 editor inserts image node with data-file-id`, `fe-cbt-11 editor shows loading state while uploading`.
- [ ] **Step 2:** vitest → FAIL.
- [ ] **Step 3:** Implement editor + toolbar + ekstensi; konfigurasi Image agar menyimpan atribut `data-file-id`; handler paste/drop memanggil `uploadAndGetNode`; state loading/kosong/error.
- [ ] **Step 4:** PASS → Commit `feat(fe-cbt-11): add RichQuestionEditor component`.

---

## FASE 5 — Integrasi admin (`FE-CBT-12`)

### Task 10: Pakai editor di admin bank soal
**Files:**
- Modify: `apps/web/src/app/(admin)/admin/cbt/questions/page.tsx`
- Test: `apps/web/src/test/fe-cbt-12-admin-questions.test.ts` (atau perluas e2e di Fase 8)

**Interfaces:**
- Consumes: `RichQuestionEditor`, `RichContent` (payload show kini HTML).

- [ ] **Step 1: Tulis uji gagal** `fe-cbt-12 admin uses rich editor for body & options` (render form → ada editor, bukan Textarea polos; submit mengirim `body.content` html).
- [ ] **Step 2:** FAIL.
- [ ] **Step 3:** Ganti Textarea body & Input opsi dengan `RichQuestionEditor` (lewatkan `bankId` terpilih); pratinjau daftar pakai `RichContent`. Pertahankan state loading/kosong/error.
- [ ] **Step 4:** `npx vitest run` + `npm run typecheck` → PASS → Commit `feat(fe-cbt-12): rich editor in admin question bank`.

---

## FASE 6 — Integrasi guru (`FE-CBT-13`)

### Task 11: Pakai editor di guru buat/edit soal
**Files:**
- Modify: `apps/web/src/app/(portal)/portal/guru/ujian/page.tsx` (dan `guru/cbt/page.tsx` bila ada form soal)
- Test: `apps/web/src/test/fe-cbt-13-guru-questions.test.ts`

- [ ] **Step 1: Tulis uji gagal** `fe-cbt-13 guru uses rich editor & uploads to own bank`.
- [ ] **Step 2:** FAIL.
- [ ] **Step 3:** Integrasikan `RichQuestionEditor` (bankId milik guru) + `RichContent` pratinjau; state lengkap.
- [ ] **Step 4:** PASS → Commit `feat(fe-cbt-13): rich editor in guru question editor`.

---

## FASE 7 — Integrasi siswa (`FE-CBT-14`)

### Task 12: Render soal & opsi kaya saat ujian/review
**Files:**
- Modify: `apps/web/src/app/(portal)/portal/siswa/cbt/[id]/page.tsx`
- Test: `apps/web/src/test/fe-cbt-14-siswa-render.test.ts`

**Interfaces:**
- Consumes: `RichContent`; `body.content`/opsi kini HTML dengan `src` sudah disuntik server.

- [ ] **Step 1: Tulis uji gagal** `fe-cbt-14 siswa renders question html table+img`, `fe-cbt-14 siswa renders options html`, `fe-cbt-14 siswa legacy plain text still renders`.
- [ ] **Step 2:** FAIL.
- [ ] **Step 3:** Ganti render teks `currentQ.body.content` & opsi dengan `RichContent`; pertahankan tampilan formula lama bila ada.
- [ ] **Step 4:** `npx vitest run && npm run build` → PASS → Commit `feat(fe-cbt-14): render rich question content for students`.

---

## FASE 8 — E2E & penutup

### Task 13: E2E alur penuh + pembersihan
**Files:**
- Create: `apps/web/e2e/20-cbt-rich-editor.spec.ts`
- Modify: `17_CHANGELOG_PERUBAHAN.md` (catat fitur + ID), catatan mobile follow-up di dok mobile.

- [ ] **Step 1:** Tulis e2e `20-CBT-RTE`: guru login → buat soal dengan 1 tabel + 1 gambar (upload) + 1 opsi bergambar → simpan; admin melihat soal ter-render; siswa (sesi uji) melihat tabel+gambar; assert tak ada kunci jawaban di network payload.
- [ ] **Step 2:** Jalankan `cd apps/web && npx playwright test e2e/20-cbt-rich-editor.spec.ts` (server dev berjalan) → PASS.
- [ ] **Step 3:** `npm run lint && npm run typecheck && npm run build` (web) + `./vendor/bin/pest` (api) → semua hijau.
- [ ] **Step 4:** Perbarui changelog + catatan mobile; Commit `test(cbt-rte): e2e rich question editor + docs`.
- [ ] **Step 5:** Buka PR `feat/cbt-rte-editor-soal` → `develop` dengan ringkasan DoD.

---

## Catatan eksekusi
- Server dev (`api:8000`, `web:3000`) sudah berjalan di mesin ini; untuk uji unit/feature tak perlu server, untuk e2e perlu dev berjalan.
- Kredensial uji (semua `Password123!`): admin `superadmin` (2FA sudah aktif), guru `gurumtk`, siswa `siswa`. Lihat memory `sistlabum-browser-test-auth`.
- Dependensi fitur: tidak ada yang bergantung ke fitur CBT lain yang belum selesai (bank soal, sesi ujian, file storage sudah ada).
