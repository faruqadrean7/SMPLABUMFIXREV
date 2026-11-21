# 14. Remediasi & Penyesuaian Arsitektur — SISTLABUM

> Dokumen ini merekam **keputusan final, perbaikan yang diterapkan, cara menjalankan, dan roadmap** hasil penyelarasan arsitektur. Menjadi acuan tunggal untuk setup & kelanjutan proyek. Dibuat: 2026-10-29.

---

## 0. Catatan Baseline (penting)

Analisis awal sempat dilakukan terhadap branch `main` yang **tertinggal jauh** (±59 commit) dari `develop`. Kondisi nyata proyek ada di **`develop`**: backend Laravel sudah matang (100+ controller, puluhan service, 44 migrasi, 115 berkas test, koleksi Bruno 14 modul), dan `apps/web` sudah menjadi aplikasi Next.js terpadu. Dokumen ini berbasis `develop`.

---

## 1. Keputusan Arsitektur Final

| Aspek | Keputusan | Konsekuensi |
|---|---|---|
| Backend | **Laravel 13 REST API** (`apps/api`) sebagai satu-satunya backend | NestJS/Prisma ditinggalkan |
| Sumber skema DB | **Migrasi Laravel** satu-satunya sumber kebenaran | Folder `prisma/` **dihapus** (anti dual-source/drift) |
| Database | **PostgreSQL 16** (+ Redis untuk cache/queue/sesi CBT) | `jsonb`, partial index, GiST, `pg_trgm` dipakai |
| Auth | **Laravel Sanctum** (bearer token) + rotasi refresh token kustom | Sudah terimplementasi di `AuthService` |
| Frontend | **Satu** aplikasi **Next.js 14 (App Router)** di `apps/web` | Website publik + PPDB + Portal (siswa/guru/ortu/staf/kepsek) + Admin dalam satu app, **satu port** |
| Mobile | **Ditunda** (`apps/mobile` Flutter dipertahankan sebagai kerangka) | Masuk roadmap, bukan scope rilis saat ini |
| Menjalankan | **2 perintah**: `npm run api` (backend), `npm run web` (frontend) | Tidak ada lagi port terpisah untuk web vs portal vs admin |

---

## 2. Cara Menjalankan (2 Perintah)

### Prasyarat (sekali pasang)
- **PHP 8.3+**, **Composer**
- **PostgreSQL 16** (buat database, mis. `sistlabum_db`)
- **Redis 7** (opsional di dev; `QUEUE_CONNECTION=sync` membuat job jalan inline tanpa worker)
- **Node.js 20+** (sudah tersedia)

### Setup awal (sekali)
```bash
# dari root repo
npm run setup
# setara dengan:
#  apps/api : composer install, copy .env, key:generate, migrate --seed
#  apps/web : npm install, copy .env.local
```
Sesuaikan `apps/api/.env` (kredensial PostgreSQL/Redis) dan `apps/web/.env.local` (`NEXT_PUBLIC_API_BASE_URL`).

### Menjalankan harian (hanya 2 perintah, 2 terminal)
```bash
npm run api    # Backend Laravel -> http://127.0.0.1:8000
npm run web    # Frontend Next.js -> http://localhost:3000
```
Frontend terpadu melayani:
- `/` dan `/profil /akademik /berita /galeri /kontak /ppdb` — **website publik + PPDB**
- `/masuk` — **login**
- `/portal/...` — **portal** siswa/guru/ortu/staf/kepsek (alias `/guru`, `/siswa`, `/ortu` di-redirect ke `/portal/*`)
- `/admin/...` — **sistem admin**

> Produksi: set `QUEUE_CONNECTION=redis` dan jalankan `php artisan queue:work` sebagai service terpisah; `php artisan schedule:run` via cron.

---

## 3. Perubahan yang Diterapkan (sesi remediasi ini)

### 3.1 Integritas Database (celah nyata — dulu HILANG di jalur deploy)
Seluruh aturan integritas sebelumnya hanya ada di `prisma/native_extensions_and_constraints.sql` dan **tidak pernah** ikut `php artisan migrate`. Kini dipindahkan ke migrasi Laravel:

**`apps/api/database/migrations/2026_10_29_000031_add_native_db_constraints.php`** (khusus PostgreSQL, guard driver agar aman di SQLite test):
- Ekstensi `uuid-ossp`, `btree_gist`, `pg_trgm`.
- **Anti double-booking fasilitas** — GiST exclusion `no_overlapping_approved_bookings` pada `bookings` (status `disetujui`, `tstzrange(start_at,end_at)`).
- **Status aktif tunggal** (partial unique): 1 tahun ajaran, 1 semester, 1 gelombang PPDB, 1 sesi CBT aktif/siswa, 1 presensi gerbang/siswa/hari.
- **Anti bentrok jadwal**: unique guru `(semester, teacher, day, slot)` & ruang `(semester, room, day, slot)`.
- **Dedup NIK**: partial-unique pada `students.nik_hash` & `applicants.nik_hash`.
- **Indeks trigram** untuk pencarian cepat nama siswa/guru, judul post/buku.
- **CHECK**: `grades.value` 0–100, `questions.difficulty` 1–5, `schedules.day_of_week` 1–7, `students.gender ∈ {L,P}`.

### 3.2 Blind Index NIK (deteksi ganda tanpa plaintext)
- **`2026_10_29_000030_add_nik_hash...`** menambah kolom `nik_hash` pada `students` & `applicants`.
- Trait **`app/Traits/HasNikBlindIndex.php`**: mengisi `nik_hash = HMAC-SHA256(nik)` otomatis saat `nik_enc` berubah (key dari `NIK_HASH_KEY`/`APP_KEY`). Dipasang pada model `Student` & `Applicant`.
- Alasan: `nik_enc` memakai cast `encrypted` (non-deterministik) sehingga tak bisa dicari/di-unik-kan.

### 3.3 Fondasi Multi-Sekolah (tenant)
- **`2026_10_29_000029_create_schools_table.php`** + model **`app/Models/School.php`**.
- v1 single-school; seluruh tabel sudah punya `school_id`. Penegakan FK & global scope penuh = roadmap (lihat §5) agar tidak memutus 115 test hijau.

### 3.4 Keamanan Audit Log
- **`app/Traits/Auditable.php`** kini meredaksi field sensitif (`password_hash`, `remember_token`, `token_hash`, `nik_enc`, `nik_hash`, `fcm_token`, dan semua `$hidden`) dari `old_value`/`new_value` sebelum ditulis ke `audit_logs`. Sebelumnya event `updated` bisa membocorkan hash via `getDirty()`.

### 3.5 Konfigurasi
- `apps/api/config/database.php`: default koneksi `sqlite` → **`pgsql`**.
- `apps/api/.env.example` sudah format Laravel (APP_KEY, DB pgsql, Sanctum, CORS) — dipertahankan.

### 3.6 Konsolidasi Frontend (satu app, satu port)
- `apps/admin` (stub kosong) dihapus. `apps/portal` (aplikasi Next.js terpisah lama, port 3001) ditarik dari `apps/` dan dihapus dari repo (riwayat tetap tersimpan di git history).
- `apps/web` menjadi satu-satunya frontend; route group `(public)`, `(auth)`, `(portal)`, `(admin)` sudah mencakup seluruh peran. Routing lama `/guru /siswa /ortu` tetap jalan via redirect ke `/portal/*`.

### 3.7 Root Runner
- **`package.json`** (root) baru: skrip `api`, `web`, dan `setup`/`api:setup`/`web:setup`.

---

## 4. Struktur Repo Final
```
SISTLABUM/
├─ apps/
│  ├─ api/     # Laravel 13 REST API (backend utama)
│  ├─ web/     # Next.js 14 — website publik + PPDB + portal + admin (SATU app)
│  └─ mobile/  # Flutter (DITUNDA — kerangka dipertahankan)
├─ docs/       # kontrak API (Bruno), desain auth, rbac-matrix, dll
├─ package.json  # root: 2 perintah (api, web)
├─ 00..13_*.md   # paket PRD
└─ 14_REMEDIATION.md  # dokumen ini
```

---

## 5. Roadmap / Rekomendasi Lanjutan
1. **Tenant scope penuh**: tambah FK `school_id → schools.id` bertahap + global scope (`BelongsToSchool`) berbasis konteks user, dengan memperbarui factory/seeder test agar 115 test tetap hijau.
2. **Isi `nik_hash` data lama**: backfill satu kali untuk siswa/pendaftar eksisting sebelum mengandalkan unik.
3. **CHECK enum lanjutan**: setelah audit nilai status aktual, tambah CHECK untuk kolom status (PPDB, exam, booking, dll) secara bertahap.
4. **Modul Keuangan/SPP**: tindak lanjuti `docs/finance-module-assessment.md` — putuskan in-scope atau integrasi eksternal (halaman `ortu/biaya` lama di portal adalah mock dan sudah ditarik).
5. **Mobile (Flutter)**: aktifkan kembali `apps/mobile` sebagai fase berikutnya; kontrak API sudah tersedia (Bruno).
6. **Worker produksi**: Redis + `queue:work` + scheduler untuk PDF rapor, notifikasi massal, tutup gelombang PPDB, lock nilai.

---

## 6. Status Verifikasi
Perubahan ditulis & konsisten secara statis. **Belum dijalankan/di-migrate** karena mesin kerja saat ini tidak memiliki PHP/Composer/PostgreSQL/Redis. Langkah verifikasi setelah prasyarat terpasang:
```bash
cd apps/api && php artisan migrate:fresh --seed   # pastikan migrasi 000029-000031 jalan di PostgreSQL
cd apps/api && php artisan test                    # pastikan 115 test tetap hijau
```
