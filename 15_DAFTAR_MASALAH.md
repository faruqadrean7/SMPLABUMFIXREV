# 15. Daftar Masalah (Issue Register) — SISTLABUM

> Rekap seluruh masalah yang ditemukan pada audit arsitektur & database (2026-10-29).
> Status: **FIXED** = sudah diperbaiki sesi remediasi ini · **PENDING** = perlu tindak lanjut · **N/A-develop** = tidak berlaku lagi di `develop` (sudah benar).
> Detail perbaikan ada di `16_FASE_PERBAIKAN.md`.

## Ringkasan Severity
| Severity | Jumlah | Keterangan |
|---|---|---|
| 🔴 Kritis | 4 | Integritas data / keamanan / tidak bisa jalan |
| 🟠 Tinggi | 6 | Risiko data salah / drift / fragmentasi |
| 🟡 Sedang | 6 | Best-practice / performa / kejelasan |

---

## A. Masalah Proses & Baseline

### ISS-01 — Analisis awal berbasis branch basi (`main`) 🟡 — FIXED
- **Bukti:** `main` tertinggal 59 commit dari `develop`; audit pertama menilai "tanpa controller/logika" padahal itu kondisi `main`, bukan `develop`.
- **Dampak:** Kesimpulan awal keliru (backend dianggap kosong).
- **Perbaikan:** Re-baseline ke `develop` sebelum eksekusi. Di `develop` backend sudah matang (100+ controller, 115 berkas test).

### ISS-02 — ~64 commit kerja tim belum ter-merge 🟠 — FIXED
- **Bukti:** `fix/fase-8-portal-adoption` (42), `fix/teacher-gaps` (21), `feat/web-home-01` (1) ahead dari `develop` (oleh "Erik", 27 Okt).
- **Dampak:** Risiko hilang bila branch dihapus tanpa merge.
- **Perbaikan:** Ketiga branch di-merge ke `develop` (konflik diselesaikan) sebelum penghapusan.

---

## B. Arsitektur & Konfigurasi

### ISS-03 — Dua tech stack bertabrakan (NestJS vs Laravel) 🟠 — FIXED
- **Bukti:** `02_TECH_STACK.md`/`03_ARCHITECTURE.md` mewajibkan NestJS+Prisma; implementasi nyata `apps/api` = Laravel 13.
- **Dampak:** Dokumen mengikat tidak sesuai kode; membingungkan developer/agent.
- **Perbaikan:** Keputusan final **Laravel API utama**; didokumentasikan di `14_REMEDIATION.md §1`.

### ISS-04 — Sumber skema DB ganda (Prisma + migration Laravel) 🔴 — FIXED
- **Bukti:** `prisma/schema.prisma` (100 KB) + `prisma/native_extensions_and_constraints.sql` hidup berdampingan dengan 44 migration Laravel.
- **Dampak:** Dijamin drift; dua definisi tabel yang sama.
- **Perbaikan:** Folder `prisma/` dihapus; migration Laravel jadi sumber tunggal.

### ISS-05 — `.env.example` format NestJS 🟠 — N/A-develop
- **Bukti (main):** `NODE_ENV`, `PORT=3000`, `DATABASE_URL` Prisma, `JWT_*`.
- **Status:** Di `develop` sudah format Laravel (`APP_KEY`, `DB_CONNECTION=pgsql`, Sanctum, CORS). Tidak perlu tindakan.

### ISS-06 — Default koneksi DB `sqlite` padahal skema butuh PostgreSQL 🟠 — FIXED
- **Bukti:** `apps/api/config/database.php` → `env('DB_CONNECTION','sqlite')`; skema pakai `jsonb`, partial index, GiST, `pg_trgm`.
- **Dampak:** Out-of-the-box mentarget sqlite → fitur PostgreSQL pecah.
- **Perbaikan:** Default diubah ke `pgsql`.

---

## C. Integritas Database

### ISS-07 — Constraint integritas kritis hilang di jalur deploy 🔴 — FIXED
- **Bukti:** GiST anti double-booking, partial-unique status-aktif-tunggal, presensi gerbang harian, indeks trigram **hanya** ada di `prisma/native_extensions_and_constraints.sql`, tidak di migration Laravel. `composer setup` memakai `artisan migrate` → constraint tak pernah terpasang.
- **Dampak:** Booking fasilitas bisa dobel; >1 tahun ajaran/semester/gelombang PPDB aktif; sesi CBT ganda; presensi gerbang dobel.
- **Perbaikan:** Dipindah ke migration `2026_10_29_000031_add_native_db_constraints.php` (guard driver pgsql).

### ISS-08 — Bentrok jadwal guru/ruang tidak dikunci 🟠 — FIXED
- **Bukti:** `schedules` unik `(semester,class,day,slot)` saja; guru & ruang hanya index biasa.
- **Dampak:** Guru/ruang bisa dijadwalkan di dua tempat pada slot sama.
- **Perbaikan:** Tambah unique `(semester,teacher,day,slot)` & `(semester,room,day,slot)` (partial, room not null).

### ISS-09 — NIK terenkripsi tak bisa dicek duplikat 🔴 — FIXED
- **Bukti:** `students.nik_enc`/`applicants.nik_enc` cast `encrypted` (non-deterministik), tanpa unique.
- **Dampak:** Satu orang bisa daftar PPDB berkali-kali; tak bisa deteksi NIK ganda.
- **Perbaikan:** Kolom `nik_hash` (HMAC-SHA256) + trait `HasNikBlindIndex` + partial-unique index.

### ISS-10 — Enum berupa string bebas tanpa CHECK 🟡 — FIXED (sebagian)
- **Bukti:** `status`, `gender`, `exam_type`, dll string + komentar, tanpa CHECK/enum type.
- **Dampak:** Nilai tak valid lolos di level DB.
- **Perbaikan:** CHECK ditambah untuk `students.gender ∈ {L,P}`; CHECK enum status lain = roadmap (butuh audit nilai aktual dulu, lihat `16 §Fase 9`).

### ISS-11 — Nilai numerik tanpa batas 🟡 — FIXED
- **Bukti:** `grades.value decimal(5,2)` dikomentari 0–100 tapi tanpa CHECK.
- **Perbaikan:** CHECK `value 0..100`, `questions.difficulty 1..5`, `schedules.day_of_week 1..7`.

### ISS-12 — Multi-tenant hanya kosmetik 🟠 — FIXED (fondasi)
- **Bukti:** Semua tabel punya `school_id` tapi tak ada tabel `schools`, nullable, tanpa FK, tanpa global scope.
- **Dampak:** Isolasi antar-sekolah tidak dijamin saat ekspansi multi-sekolah.
- **Perbaikan:** Tabel `schools` + model `School` dibuat (fondasi). Penegakan FK + global scope penuh = roadmap (`16 §Fase 8`) agar 115 test tetap hijau.

---

## D. Keamanan

### ISS-13 — Audit log membocorkan field sensitif 🔴 — FIXED
- **Bukti:** `Auditable::updated` memakai `getDirty()` yang tak menghormati `$hidden` → `password_hash` bisa tercatat di `audit_logs.new_value`.
- **Perbaikan:** Trait meredaksi `$hidden` + daftar sensitif (`password_hash`, `token_hash`, `nik_enc`, `nik_hash`, `fcm_token`, dll) jadi `[REDACTED]`.

### ISS-14 — Audit log ditulis sinkron 🟡 — PENDING
- **Bukti:** Event model menulis `AuditLog::create` langsung tiap aksi.
- **Dampak:** Beban berat pada operasi massal (import Excel, seed nilai).
- **Tindak lanjut:** Pindahkan ke queue (roadmap `16 §Fase 10`).

### ISS-15 — Cek permission berpotensi lambat (N+1) 🟡 — PENDING
- **Bukti:** `RequirePermission` query `whereHas` relasi permissions tiap request tanpa cache.
- **Tindak lanjut:** Cache permission per user (roadmap).

---

## E. Fitur & Frontend

### ISS-16 — Frontend terfragmentasi (port terpisah) 🟠 — FIXED
- **Bukti:** `apps/web` (publik), `apps/portal` (port 3001), `apps/admin` (stub) — app terpisah.
- **Dampak:** Web & portal beda port; duplikasi.
- **Perbaikan:** `apps/web` jadi satu app (route group `(public)/(auth)/(portal)/(admin)`), satu port. `apps/portal`/`apps/admin` ditarik; prototipe diarsipkan `legacy/portal-prototype/`.

### ISS-17 — Belum ada perintah jalan terpadu 🟡 — FIXED
- **Bukti:** Tidak ada root `package.json`; backend/portal/web dijalankan terpisah.
- **Perbaikan:** Root `package.json` → `npm run api` & `npm run web` (2 perintah).

### ISS-18 — Tidak ada modul keuangan/SPP 🟡 — PENDING (keputusan)
- **Bukti:** Tak ada model pembayaran/invoice/SPP; PPDB daftar-ulang tanpa pembayaran. Ada `docs/finance-module-assessment.md` & `docs/DECISION_PENDING_FINANCE.md`.
- **Tindak lanjut:** Putuskan in-scope vs integrasi eksternal (roadmap `16 §Fase 9`).

### ISS-19 — Scope Mobile belum final 🟡 — RESOLVED (ditunda)
- **Keputusan:** Mobile (`apps/mobile` Flutter) ditunda; kerangka dipertahankan, masuk roadmap.

---

## F. Operasional

### ISS-20 — Tidak ada kredensial GitHub untuk push 🟠 — PENDING (user)
- **Bukti:** Mac tanpa credential helper/`gh`/SSH key; `git push` gagal.
- **Tindak lanjut:** User setup auth (`gh auth login` / PAT), lalu push `develop` + hapus branch.

### ISS-21 — Belum terverifikasi runtime 🟠 — PENDING
- **Bukti:** Mac belum ada PHP/Composer/PostgreSQL/Redis → migrasi & test belum dijalankan.
- **Tindak lanjut:** `php artisan migrate:fresh --seed` lalu `php artisan test` (lihat `14 §6`).
