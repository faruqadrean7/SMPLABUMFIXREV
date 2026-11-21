# 16. Fase Perbaikan (Remediation Phases) — SISTLABUM

> Rencana & status pengerjaan perbaikan atas masalah di `15_DAFTAR_MASALAH.md`.
> Keputusan arsitektur & cara jalan ada di `14_REMEDIATION.md`.
> Legenda status: ✅ SELESAI · 🔄 BERJALAN · ⏳ MENUNGGU (user/prasyarat) · 🗺️ ROADMAP.

## Peta Fase
| Fase | Judul | Status | Isu terkait |
|---|---|---|---|
| 0 | Re-baseline ke `develop` | ✅ | ISS-01 |
| 1 | Integritas DB & konfigurasi | ✅ | ISS-04, 06, 07, 08, 10, 11 |
| 2 | Fondasi tenant & dedup NIK | ✅ | ISS-09, 12 |
| 3 | Keamanan audit log | ✅ | ISS-13 |
| 4 | Verifikasi AUTH | ✅ | — |
| 5 | Konsolidasi frontend | ✅ | ISS-16 |
| 6 | Runner 2-perintah | ✅ | ISS-17 |
| 7 | Merge & rapikan branch | 🔄 | ISS-02, ISS-20 |
| 8 | Verifikasi runtime | ✅ | ISS-21 |
| 9 | Tenant penuh, CHECK enum, finance | ✅ | ISS-09, ISS-10, ISS-12, ISS-18 |
| 10 | Performa & hardening | ✅ | ISS-14, ISS-15 |
| 11 | Mobile | 🗺️ | ISS-19 |

---

## Fase 0 — Re-baseline ke `develop` ✅
- **Tujuan:** Pastikan perbaikan berbasis kondisi nyata, bukan `main` yang basi.
- **Langkah:** `git checkout develop`; verifikasi 44 migration, 100+ controller, 115 berkas test, struktur `apps/web` terpadu.
- **Hasil:** Semua eksekusi berikutnya di `develop`.

## Fase 1 — Integritas DB & Konfigurasi ✅
- **Tujuan:** Pulihkan jaminan integritas yang hilang + stack DB benar.
- **Langkah:**
  1. Pindahkan seluruh constraint native ke migration Laravel.
  2. Set default koneksi `pgsql`.
  3. Hapus folder `prisma/` (sumber ganda).
- **File:**
  - `apps/api/database/migrations/2026_10_29_000031_add_native_db_constraints.php` (baru): ekstensi `uuid-ossp`/`btree_gist`/`pg_trgm`; GiST `no_overlapping_approved_bookings`; partial-unique (tahun/semester/gelombang/sesi CBT/presensi gerbang); unik anti-bentrok jadwal guru & ruang; indeks trigram; CHECK nilai/difficulty/hari/gender; partial-unique `nik_hash`.
  - `apps/api/config/database.php` (default → `pgsql`).
  - `prisma/` (dihapus).
- **Verifikasi:** `php artisan migrate:fresh` di PostgreSQL harus menjalankan 000031 tanpa error; constraint tampak di `\d+ bookings` / `\di`.

## Fase 2 — Fondasi Tenant & Dedup NIK ✅
- **Tujuan:** Siapkan multi-sekolah & deteksi NIK ganda tanpa menyimpan plaintext.
- **File:**
  - `2026_10_29_000029_create_schools_table.php` + `app/Models/School.php` (baru).
  - `2026_10_29_000030_add_nik_hash_to_students_and_applicants.php` (kolom `nik_hash`).
  - `app/Traits/HasNikBlindIndex.php` (baru) dipasang di `Student` & `Applicant` — isi `nik_hash = HMAC-SHA256(nik)` otomatis saat `nik_enc` berubah (key `NIK_HASH_KEY`/`APP_KEY`).
- **Catatan:** FK `school_id → schools.id` & global scope penuh sengaja **ditunda** ke Fase 9 agar 115 test tetap hijau.
- **Verifikasi:** Simpan Student baru → `nik_hash` terisi; dua Student NIK sama → ditolak unique (PostgreSQL).

## Fase 3 — Keamanan Audit Log ✅
- **Tujuan:** Hentikan kebocoran field sensitif ke `audit_logs`.
- **File:** `app/Traits/Auditable.php` — `auditSafe()` meredaksi `$hidden` + daftar sensitif menjadi `[REDACTED]`.
- **Verifikasi:** Ubah password user → `audit_logs.new_value.password_hash` berisi `[REDACTED]`.

## Fase 4 — Verifikasi AUTH ✅
- **Tujuan:** Pastikan AUTH lengkap tanpa membangun ulang.
- **Temuan:** Sudah ada `AuthController` + `AuthService` (login/refresh/logout/me/forgot/reset/change-password/sessions), rute `/api/v1/auth/*`, seeder RBAC (permission + role + super admin). Tidak ada perubahan diperlukan.

## Fase 5 — Konsolidasi Frontend ✅
- **Tujuan:** Satu app, satu port (web + PPDB + portal + admin).
- **Langkah:** Jadikan `apps/web` kanonik; tarik `apps/admin` (stub) & `apps/portal` (port 3001); arsipkan prototipe ke `legacy/portal-prototype/` (sesuai blueprint "jangan dihapus").
- **Verifikasi:** `npm run web` menyajikan `/`, `/ppdb`, `/masuk`, `/portal/*`, `/admin/*` pada satu port.

## Fase 6 — Runner 2-Perintah ✅
- **File:** `package.json` (root): `npm run api`, `npm run web`, plus `setup`/`api:setup`/`web:setup`.
- **Verifikasi:** Dari root, `npm run api` (Laravel :8000) & `npm run web` (Next.js :3000) berjalan terpisah.

## Fase 7 — Merge & Rapikan Branch 🔄
- **Tujuan:** Satukan kerja tim lalu sisakan `main` + `develop` di origin.
- **Sudah:** Merge `feat/web-home-01` (konflik hero/homepage), `fix/teacher-gaps` (konflik ppdb/header/test + arsip portal), `fix/fase-8` (bersih) ke `develop`. Semua jadi ancestor `develop`.
- **Menunggu (ISS-20):** User setup auth git di Mac → lalu:
  1. `git push origin develop` (49 commit).
  2. Hapus `feat/web-home-01-sync-portal-master-docs`, `fix/fase-8-portal-adoption`, `fix/teacher-gaps` di lokal & origin.
- **Hasil akhir:** origin hanya `main` + `develop`.

## Fase 8 — Verifikasi Runtime ✅
- **Prasyarat:** Pasang PHP 8.3+, Composer, PostgreSQL 16/17.
- **Hasil:**
  1. `npm run setup` / `apps/api` .env + generate app key.
  2. `migrate:fresh --seed` sukses di PostgreSQL 17 (constraint GiST exclusion `bookings` disesuaikan memakai `tsrange(start_at, end_at)` karena tipe kolom adalah `timestamp` tanpa timezone).
  3. `php artisan test` sukses 100%: 1.338 test lulus (8.866 assertions, 0 failure).
  4. Perbaikan `UatStagingSeeder` (resolusi variabel `$trackPrestasi` dan penyesuaian string guard lingkungan) & resolusi merge page PPDB/web typecheck.

## Fase 9 — Tenant Penuh, CHECK Enum, Finance ✅
- **(a) Backfill nik_hash (ISS-09):** Perintah `php artisan sistlabum:backfill-nik-hash` (--dry-run didukung) + automated test di `BackfillNikHashCommandTest.php` untuk mengisi HMAC-SHA256 pada data lama.
- **(b) Fondasi & Scope Tenant (ISS-12, Keputusan Opsi A):** Trait `BelongsToSchool` + global scope `SchoolScope` aktif (mengisolasi data per `school_id`, super_admin lintas sekolah, bypassable via `withoutSchoolScope()`). Canonical default school diseed otomatis di `DatabaseSeeder`. Sesuai Keputusan Eskalasi Opsi A, isolasi ditegakkan via `SchoolScope` dan kolom `school_id` tetap nullable tanpa hard FK agar 100% test tetap hijau. Hard FK dijadwalkan via alur: (a) seed canonical school di bootstrap test; (b) sweep fixture dari `Str::uuid()` ke ID canonical; (c) baru migrasi hard FK (guard pgsql).
- **(c) Audit Nilai Status & CHECK Enum (ISS-10):** Migrasi `2026_10_29_000032_add_status_enum_check_constraints.php` menambahkan PostgreSQL CHECK constraints untuk `bookings.status`, `facilities.status`, `ppdb_waves.status`, `leave_requests.status`, dan `applicants.status` mencakup nilai kanonis & alias.
- **(d) Status Modul Finance (ISS-18, RESOLVED):** Sesuai Keputusan Eskalasi, modul keuangan dirilis bertahap pada v1. Pembayaran menggunakan rekening giro mitra sekolah dengan verifikasi slip manual oleh TU. Logika pembayaran telah ditaruh di balik adapter interface `PaymentChannelInterface` (`ManualGiroPaymentChannel` & `PaymentChannelManager`) sehingga payment gateway VA (Midtrans/Xendit) dapat ditambahkan di Fase 2 tanpa refactoring. Dokumen `docs/DECISION_PENDING_FINANCE.md` telah berstatus RESOLVED.

## Fase 10 — Performa & Hardening ✅
- **Audit Log Asinkron (ISS-14):** `Auditable` trait kini men-dispatch `RecordAuditLogJob` (`ShouldQueue`) untuk eksekusi asinkron di queue worker, dengan fallback sinkron otomatis pada test suite.
- **Cache Permission (ISS-15):** Middleware `RequirePermission` meng-cache role dan permission per user selama 5 menit (`user_perm_cache:{user_id}`) dan menyediakan method `flushPermissionCache()`, menghilangkan N+1 query per HTTP request.
- **Worker & Scheduler:** Skrip `"worker"` (`php artisan queue:work`) dan `"schedule"` (`php artisan schedule:work`) ditambahkan ke `package.json` root. Environment produksi dikonfigurasi untuk `QUEUE_CONNECTION=redis`.

## Fase 11 — Mobile 🗺️
- Aktifkan kembali `apps/mobile` (Flutter) sebagai fase lanjutan; kontrak API tersedia (`docs/api/bruno`).
