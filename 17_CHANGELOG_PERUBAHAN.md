# 17. Changelog Perubahan Pasca-Remediasi — SISTLABUM

> Catatan perubahan nyata pada `develop` sejak baseline remediasi (`7d8343f`) s.d. analisis ulang 2026-10-29.
> Diverifikasi terhadap kode, bukan sekadar pesan commit. Rentang: `7d8343f..HEAD` — 132 berkas, +48.476/−2.784 baris.

## Ringkasan
Agent mengeksekusi Fase 8, 9, 10 (`16_FASE_PERBAIKAN.md`) + keputusan finance, plus adopsi desain portal & dokumen presentasi. Jumlah berkas test: **115 → 140** (+25). **Belum di-push** ke origin (develop 61 commit di depan `origin/develop`).

---

## A. Backend (`apps/api`)

### Fase 8 — Verifikasi runtime (commit `57914b9`, `be-ops-08`)
- Perbaikan constraint pgsql & seeder agar `migrate:fresh --seed` + test berjalan. Status Fase 8 → ✅.

### Fase 9 — Tenant, dedup NIK, CHECK enum (commit `e0ea463`, `be-ops-09`)
- **Dedup NIK:** `app/Console/Commands/BackfillNikHashCommand.php` (baru) — backfill `nik_hash` data lama.
- **Tenant scope (Opsi A):** `app/Scopes/SchoolScope.php` + `app/Traits/BelongsToSchool.php` (baru) + canonical default school `00000000-0000-0000-0000-000000000001`. **Tanpa hard FK** ke `schools` (Opsi A terjaga — diverifikasi: tidak ada migrasi FK school_id).
  - ⚠️ **Catatan/gap:** `BelongsToSchool` **baru terpasang di 1 dari 51 model** ber-`school_id` (hanya `GradeLevel.php`). Isolasi tenant belum berlaku menyeluruh — 50 model lain belum di-scope.
- **CHECK enum:** `2026_10_29_000032_add_status_enum_check_constraints.php` (baru) — 8 CHECK untuk kolom status (`bookings`, `facilities`, `ppdb_waves`, `leave_requests`, `applicants`, dll).

### Fase 10 — Performa & hardening (commit `e0ea463`)
- **Audit async:** `app/Jobs/RecordAuditLogJob.php` (baru) — penulisan `audit_logs` dipindah ke queue (menjawab ISS-14).
- **Cache permission:** `RequirePermission` kini `cache()->remember("user_perm_cache:{id}", 300, …)` — TTL 5 menit, mengurangi N+1 (ISS-15).
- **Worker scripts:** root `package.json` tambah `npm run worker` (`queue:work`) & `npm run schedule` (`schedule:work`).

### Finance/SPP (commit `e4cf8fc`, `be-fin-01`) — sesuai keputusan v1
- **Tabel:** `fee_types`, `school_bank_accounts`, `invoices`, `invoice_items`, `payments` (migrasi `2026_10_26_000030/000031`).
- **Adapter pembayaran:** `app/Services/Finance/Channels/` — `PaymentChannelInterface`, `ManualGiroPaymentChannel`, `PaymentChannelManager`. VA (Xendit/Midtrans) dapat ditambah di fase 2 tanpa refactor.
- **Keputusan:** `docs/DECISION_PENDING_FINANCE.md` → ✅ RESOLVED (v1: rilis bertahap, kartu SPP + slip giro manual, verifikasi TU; bayar online nonaktif). Dipagari permission `finance.*`.

---

## B. Frontend (`apps/web`) — 101 berkas
- Adopsi desain & komponen portal prototipe Erik ke `apps/web` (commit `cec515e`).
- Pulihkan rute portal peran terkonsolidasi: siswa, ortu, guru, staf (commit `2888206`).
- Perbaikan hero publik ganda/menumpuk (commit `7eee39a`).

## C. Dokumentasi
- `docs/DOKUMENTASI_SCREEN_SISTLABUM.md` + `.pdf` — direktori 30 layar/antarmuka + presentasi klien (branding Karya Amanah & SMP Lab UM).
- `docs/images/` — logo.
- `15_DAFTAR_MASALAH.md` & `16_FASE_PERBAIKAN.md` diperbarui (Fase 8 & 9 ✅; ISS-12 "FIXED fondasi+scope"; ISS-18 RESOLVED).

---

## D. Status Isu Terbaru (ringkas)
| Isu | Status pasca-perubahan | Catatan |
|---|---|---|
| ISS-09 NIK dedup | ✅ + backfill command | perlu jalankan backfill di data nyata |
| ISS-10 CHECK enum | ✅ (000032, 8 check) | audit nilai existing sebelum migrate prod |
| ISS-12 Tenant scope | 🟠 **SEBAGIAN** | scope baru di 1/51 model — belum menyeluruh |
| ISS-14 Audit async | ✅ | butuh worker aktif (QUEUE_CONNECTION=redis) |
| ISS-15 Permission cache | ✅ | invalidasi cache saat role/permission berubah perlu dicek |
| ISS-18 Finance | ✅ RESOLVED | v1 bertahap, giro manual |

## E. Yang Masih Terbuka
1. **ISS-12 (tenant scope) belum menyeluruh** — pasang `BelongsToSchool` pada 50 model sisanya + uji tidak memecah 140 test. **Prioritas tinggi** (klaim "selesai" masih berlebihan).
2. **Fase 7 (push & rapikan branch) 🔄** — develop 61 commit di depan origin, branch tim belum dihapus; menunggu auth git di Mac (ISS-20).
3. **Verifikasi runtime dari mesin ini** — belum bisa (tanpa PHP/PostgreSQL/Redis); jumlah berkas test 140 tetapi hijau/merah belum terkonfirmasi lokal. Jalankan `php artisan test`.
4. **Invalidasi cache permission** — pastikan `user_perm_cache:{id}` di-flush saat peran/izin user berubah, agar tidak stale 5 menit.

---

## 2026-10-30 — Login Admin: "Percayai Perangkat 30 Hari" (pengganti 2FA wajib tiap login)

**Latar:** 2FA/TOTP wajib di tiap login admin terasa merepotkan saat pengembangan & operasional harian. Keputusan: pertahankan 2FA (tetap aman) tapi tambahkan opsi *trusted device* — sekali verifikasi 2FA di satu browser, 30 hari berikutnya browser itu melewati 2FA.

**Backend (`apps/api`)**
- Migration `2026_10_30_000040_create_admin_trusted_devices_table` — tabel `admin_trusted_devices` (user_id, token_hash sha256, expires_at, last_used_at, ip, user_agent). Token disimpan sebagai **hash**, bukan plaintext.
- `App\Models\AdminTrustedDevice`.
- `App\Services\TrustedDeviceService` — `issue()` menerbitkan token (expiry 30 hari, dapat diatur `ADMIN_TRUSTED_DEVICE_DAYS`), `isTrusted()` memvalidasi + bump `last_used_at`, `revokeAll()`.
- `AdminAuthController`:
  - Saat verifikasi password, jika `trusted_device_token` valid untuk user tsb → **lewati 2FA**, langsung login (audit `method=trusted_device`). Token terikat ke user; token milik user lain / kedaluwarsa tetap diminta 2FA.
  - Saat verifikasi 2FA sukses dengan `remember_device=true` → terbitkan token, kembalikan di `data.trusted_device_token` (+ `trusted_device_expires_at`).
- Command `php artisan admin:2fa-code <username> [--uri]` — cetak kode TOTP yang berlaku saat ini (untuk login pertama sebelum trusted device aktif) dan opsional otpauth URI.
- Test `tests/Feature/Auth/AdminTrustedDeviceTest.php` (5 kasus): remember menerbitkan token (tersimpan hash), token valid melewati 2FA, token invalid/kedaluwarsa/milik user lain tetap minta 2FA.

**Frontend (`apps/web`)**
- `admin-login-form.tsx` — checkbox "Percayai perangkat ini selama 30 hari" (default tercentang) di layar verifikasi 2FA; kirim `remember_device`.
- `auth-provider.tsx` — otomatis melampirkan token tersimpan (`localStorage: sistlabum_tdt`) pada login admin, dan menyimpan token baru dari server.
- `types/auth.ts` — `LoginPayload` + `remember_device`, `trusted_device_token`.

**Catatan keamanan:** token bersifat rahasia (setara bearer) dan hanya melewati 2FA pada browser yang sama; jangan centang di komputer publik. Ganti password / insiden → panggil `TrustedDeviceService::revokeAll()` untuk mencabut semua perangkat.

---

## 2026-10-30 — Detail Pendaftar PPDB (admin): biodata/ortu kosong & dokumen tak bisa dibuka

**Gejala:** Di halaman verifikasi pendaftar admin, NISN/NIK/tempat lahir/asal sekolah/alamat tampil "-", nama ayah/ibu hilang, gender salah (default "Perempuan"), dan dokumen hanya "File: dokumen" tanpa tombol buka — padahal data sudah diisi.

**Akar masalah (bukan data hilang, tapi pemetaan):** Endpoint detail `GET /ppdb/applications/{id}` mengembalikan **model mentah**, sedangkan:
- Field biodata (nisn, nik, birth_place, gender, address) disimpan di kolom `step_data` (jsonb), bukan kolom atas → FE membaca `applicant.nisn` dkk = kosong.
- Nama ortu ada di kolom `name` + `parent_type`, tetapi FE membaca `full_name` + `relationship`.
- Relasi `file` pada dokumen tidak di-eager-load → tombol "Buka File" tersembunyi (butuh `doc.file.id`).

**Perbaikan (`apps/api`, tanpa migrasi — langsung aktif):**
- `PPDBApplicationService::getApplication()` kini eager-load `documents.file` + `documents.verifier`.
- Tambah `PPDBApplicationService::presentDetail()` — memetakan model ke kontrak FE: biodata dari `step_data.personal`, asal sekolah dari `step_data.school.origin_school`, ortu `name→full_name` & `parent_type→relationship`, dokumen menyertakan objek `file` (`id`, `filename` dari `object_key`, `mime_type`, `size`).
- `PPDBApplicationController::show()` mengembalikan hasil `presentDetail()`.
- Test unit `tests/Unit/Ppdb/ApplicantDetailMappingTest.php` (3 kasus) mengunci pemetaan biodata, nama+relasi ortu, dan objek file dokumen.

**Dampak:** Admin kini melihat seluruh biodata & data ortu yang diisi pendaftar, gender benar, dan bisa menekan "Buka File" untuk mengunduh dokumen yang diunggah (endpoint download sudah ada).

---

## 2026-10-30 — Verifikasi PPDB: preview berkas tanpa unduh

Pada dialog verifikasi pendaftar, dokumen kini bisa **dipratinjau langsung** di modal (PDF via iframe, gambar via img) lewat tombol "Lihat", dengan tombol "Unduh" terpisah bila ingin menyimpan. Berkas tetap diambil via endpoint download terotentikasi (blob → object URL, di-revoke saat ditutup/unmount). Tombol lama "Buka File" (paksa unduh) diganti. File: `apps/web/src/features/ppdb/applicant-verification-dialog.tsx`.

---

## 2026-10-30 — CBT: Editor Soal Kaya (Rich Text Editor), Tabel, dan Media Gambar (BE-CBT-11/12/13 & FE-CBT-11/12/13/14)

**Tujuan & Lingkup:** Guru dan administrator dapat menyunting teks soal dan opsi jawaban evaluasi pembelajaran CBT dengan editor kaya (WYSIWYG format teks, tabel terstruktur, dan unggah gambar via tombol/drag-drop/paste). Siswa melihat tampilan soal dan opsi ter-render secara aman dengan URL gambar sementara bertanda-tangan (signed URL). Kunci jawaban tidak pernah dikirim ke klien siswa.

**Backend (`apps/api`):**
- `BE-CBT-11`: Sanitasi HTML deny-by-default via `App\Services\Cbt\QuestionContentSanitizer` dengan whitelist tag `p, br, strong, em, u, s, ul, ol, li, table, thead, tbody, tr, td, th, img, span`. Atribut `src` pada tag `img` dibersihkan pada penyimpanan basis data dan hanya atribut `data-file-id` yang dipertahankan.
- `BE-CBT-12`: Layanan `App\Services\Cbt\QuestionMediaService` dan endpoint `POST /api/v1/cbt/question-media` (permission `cbt.bank.write`). Verifikasi magic bytes berkas (`getimagesize`), batas ukuran 2 MB, tipe mime terbatas (JPEG, PNG, WebP, GIF), dan validasi anti-IDOR kepemilikan bank soal.
- `BE-CBT-13`: Resolusi media saat dibaca oleh pembaca terotorisasi (`QuestionBankService@getQuestion`, `listQuestions`, `ExamSessionService@getSessionQuestions`). Backend secara otomatis menyuntikkan signed URL ke atribut `src` pada `<img data-file-id>`. Payload sesi siswa diuji secara ketat bebas dari `is_correct`, `match_key`, dan `explanation`.

**Frontend (`apps/web`):**
- `FE-CBT-11`: Komponen renderer baca `RichContent` (DOMPurify lapis dua) dan `RichQuestionEditor` (TipTap + StarterKit + Table + CustomImage dengan dukungan `data-file-id`). Hook `useImageUpload` untuk unggah gambar secara mulus via picker berkas, drag-drop, dan tempel (paste).
- `FE-CBT-12`: Integrasi editor kaya pada modul bank soal administrator (`apps/web/src/app/(admin)/admin/cbt/questions/page.tsx`). Pratinjau soal dan opsi jawaban menggunakan `RichContent`.
- `FE-CBT-13`: Integrasi editor kaya pada modul bank soal mandiri guru (`apps/web/src/app/(portal)/portal/guru/ujian/page.tsx`).
- `FE-CBT-14`: Integrasi perenderan konten kaya pada ruang kerja pengerjaan CBT siswa (`apps/web/src/app/(portal)/portal/siswa/cbt/[id]/page.tsx`).

**Pengujian & Verifikasi:**
- Backend: Uji unit dan fitur berawalan ID lulus 100% (`BeCbt11SanitizerTest`, `BeCbt11StoreQuestionTest`, `BeCbt12MediaServiceTest`, `BeCbt12UploadEndpointTest`, `BeCbt13ShowResolveTest`, `BeCbt13SessionQuestionsTest` — 27 tests, 95 assertions).
- Frontend: Uji unit dan integrasi lulus 100% (`fe-cbt-11-rich-content.test.tsx`, `fe-cbt-11-upload.test.ts`, `fe-cbt-11-editor.test.tsx`, `fe-cbt-12-admin-questions.test.tsx`, `fe-cbt-13-guru-questions.test.tsx`, `fe-cbt-14-siswa-render.test.tsx`).
- E2E: Spesifikasi Playwright `apps/web/e2e/20-cbt-rich-editor.spec.ts`.

