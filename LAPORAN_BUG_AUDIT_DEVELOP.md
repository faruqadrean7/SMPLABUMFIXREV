# Laporan Audit Bug — Branch `develop` (SISTLABUM)

> Tanggal: 2026-11-03 · Metode: **analisis kode statis** (apps/api Laravel, apps/web Next.js).
> Catatan jujur: aplikasi **tidak dijalankan** di sesi ini (tanpa PHP/DB), jadi gejala runtime yang
> kamu laporkan saya konfirmasi **di tingkat kode** (penyebab yang konsisten dengan gejala), bukan
> reproduksi langsung. Item bertanda "perlu verifikasi runtime" belum saya buktikan berjalan.

## Ringkasan prioritas
| # | Area | Severity | Status bukti |
|---|---|---|---|
| 1 | Login admin 2FA tertolak | **P0 blocker** | Konfirmasi kode |
| 2 | Fabrikasi data di portal **ortu** (nilai 87.4, poin 45, wali kelas palsu) | **P0** | Konfirmasi kode |
| 3 | Dataset mock masih tersebar (ortu/murid/guru/staf/eksekutif) | P1 | Konfirmasi kode |
| 4 | `lms-api` fallback mock menutupi error HTTP (dead code, laten) | P1 | Konfirmasi kode |
| 5 | "Beberapa hal gagal di-input" | P1 | Perlu verifikasi runtime |

---

## BUG 1 — Login admin gagal: kode 2FA selalu ditolak (P0)

**Gejala kamu:** admin tidak bisa login karena kode autentikasi 2FA tidak bisa masuk.

**Penyebab di kode** (`apps/api`):

**1a. Replay-guard "membakar" time-step saat enrollment, lalu login langsung ditolak.**
- `TwoFactorAuthController@verify` memanggil `verifyKeyWithUser()`
  ([TwoFactorAuthController.php:98](apps/api/app/Http/Controllers/Api/V1/TwoFactorAuthController.php:98)).
- `verifyKeyWithUser()` mencatat `two_factor_last_step = timeSlice` dan menolak kode bila
  `timeSlice <= two_factor_last_step`
  ([TwoFactorAuthService.php:91-96](apps/api/app/Services/TwoFactorAuthService.php:91)).
- Akibat: kode 6 digit yang **masih sama** di layar authenticator, bila dipakai lagi di langkah
  login (`/2fa/challenge` → `verifyTwoFactorAndLogin` → `verifyKeyWithUser`), **ditolak
  `INVALID_2FA_CODE`** selama ~30 detik sampai muncul kode baru. UI tidak menjelaskan ini — pengguna
  mengira "kode salah". Keberadaan command bantuan `php artisan admin:2fa-code`
  ([Admin2faCodeCommand.php](apps/api/app/Console/Commands/Admin2faCodeCommand.php)) menandakan tim
  memang sering tersandung hal ini.

**1b. Toleransi waktu hanya ±1 window (±30 detik).** `verifyKeyWithUser($user,$code,discrepancy=1)`
([:84](apps/api/app/Services/TwoFactorAuthService.php:84)). Jika jam server VPS atau HP pengguna
meleset >~60 detik (NTP mati), **semua** kode gagal. UI enrollment bahkan memberi petunjuk ini
("Pastikan waktu jam perangkat Anda sinkron" — [admin-login-form.tsx:169](apps/web/src/features/auth/admin-login-form.tsx:169)).

**1c. Hipotesis operasional (perlu cek data):** memori sesi mencatat 2FA superadmin+kepsek pernah
di-enroll lewat skrip QA (secret di scratchpad), sehingga authenticator **manusia** tidak pernah
berisi secret itu → kode manusia tidak akan pernah cocok. Solusi cepat: `php artisan admin:2fa-code <user>`
untuk kode valid, atau pakai recovery code, atau disable+enroll ulang via HP sendiri.

**Rekomendasi:**
- Enrollment `verify` sebaiknya **tidak** memakai replay-guard (pakai `verifyKey()` biasa) ATAU langsung
  menerbitkan sesi login setelah verify sukses (hilangkan langkah login kedua).
- Perbesar toleransi ke ±1–2 window + pastikan **NTP aktif di server**.
- Pesan error 2FA bedakan "kode kedaluwarsa, tunggu kode baru" vs "kode salah".
- Sediakan jalur reset 2FA admin yang jelas.

---

## BUG 2 — Fabrikasi data di portal ORTU (P0)

**Gejala kamu:** dashboard/sidebar menampilkan nilai/angka (mis. nilai, poin) padahal datanya kosong.

**Penyebab:** [guardian-context.tsx](apps/web/src/lib/guardian-context.tsx) mengisi nilai **karangan**
sebagai fallback yang tampil ke orang tua:
- **Rata-rata nilai default `87.4`** bila `gradesData` kosong
  ([:183](apps/web/src/lib/guardian-context.tsx:183), dipakai di [:216](apps/web/src/lib/guardian-context.tsx:216)).
  Anak tanpa nilai → ortu tetap melihat "87.4".
- **Poin hardcoded `points_balance: 45`, `points_prestasi: 45`** — tidak diambil dari API mana pun
  ([:217-218](apps/web/src/lib/guardian-context.tsx:217)). Setiap anak selalu "45 poin".
- **Wali kelas palsu**: fallback `'Nur Hayati, S.Pd.'`, `NIP. 19840214 200902 2 003`,
  `0812-3456-7890` ([:201-203](apps/web/src/lib/guardian-context.tsx:201)). Identitas fiktif tampil
  seolah nyata.
- **`DEFAULT_CHILD`** ([:50-93](apps/web/src/lib/guardian-context.tsx:50)) dikembalikan saat tak ada
  anak cocok ([:152-154](apps/web/src/lib/guardian-context.tsx:152)) — membawa rekap hadir 96%, nilai
  87.4, poin 45 (semua karangan) di balik nama "Memuat data siswa...".
- Profil ortu juga fallback ke `'Adrean Pratama, S.T.'` / email / telepon fiktif
  ([:227-229](apps/web/src/lib/guardian-context.tsx:227)).

**Melanggar AGENTS.md §6.2 & §9** (dilarang data karangan / tampil seolah berjalan). Catatan: sebagian
sudah diperbaiki (rekap presensi & lokasi gerbang kini dari data nyata), tapi nilai/poin/wali kelas
belum. Sudah ada `PROMPT_FIX_ORTU_FABRIKASI.md` namun fabrikasi masih ada → **belum selesai/merge**.

**Rekomendasi:** ganti semua fallback angka/identitas dengan state kosong/strip ("—", "Belum ada
data") + state loading/empty, persis pola yang sudah benar di [guardian-dashboard-view.tsx:50](apps/web/src/features/portal/guardian-dashboard-view.tsx:50).

---

## BUG 3 — Dataset mock masih tersebar di seluruh portal (P1)

Masih ada berkas data fiktif besar yang di-bundle ke frontend:
`lib/mock-guardian-data.ts`, `lib/mock-student-data.ts`, `lib/mock-lms-data.ts`,
`features/portal/portal-mock-data.ts`, `guru/guru-mock-data.ts`, `staf/staf-mock-data.ts`,
`shared/shared-portal-mock-data.ts`, `features/executive/executive-mock-data.ts` — berisi nama guru,
NIP, nilai fiktif (mis. [mock-guardian-data.ts:88-109](apps/web/src/lib/mock-guardian-data.ts:88)).

**Kabar baik (sudah aman):** jalur `features/*/*-api.ts` memakai `executeWithMockFallback`
([mock-mode.ts](apps/web/src/lib/api/mock-mode.ts)) yang: mati total di production, hanya aktif bila
`NEXT_PUBLIC_USE_MOCK=true` di dev, dan **tidak pernah** menutupi error HTTP (401/403/404/5xx) — desain
ini benar. Mock loader juga `NODE_ENV !== 'production'` ([guru-api.ts:34](apps/web/src/features/portal/guru/guru-api.ts:34)).

**Risiko tersisa:** (a) kebocoran bila `NEXT_PUBLIC_USE_MOCK` tak sengaja `true` di staging;
(b) dataset fiktif menggemukkan bundle & bikin bingung saat audit; (c) sebagian dipakai sebagai **tipe**
sekaligus **data** dari file yang sama. **Rekomendasi:** pisahkan tipe ke `types.ts`, pindah mock ke
folder khusus yang jelas dev-only, pastikan guard env di CI.

---

## BUG 4 — `lms-api.ts` fallback mock menutupi error HTTP (P1, laten)

[lib/lms-api.ts](apps/web/src/lib/lms-api.ts) punya toggle `useMockFallback` dan `catch` yang
mengembalikan mock pada **semua** error (termasuk 401/403/404/5xx) bila aktif — berbeda dari
`mock-mode.ts` yang melempar error HTTP. Contoh [:53-58](apps/web/src/lib/lms-api.ts:53).

**Saat ini tidak berbahaya:** `setMockFallback()` **tidak pernah dipanggil** di seluruh `apps/web/src`
→ `useMockFallback` selalu `false` → cabang mock = **dead code**. Tapi laten: siapa pun yang
menyalakannya akan menyembunyikan error server dengan data palsu. **Rekomendasi:** hapus cabang mock
`lms-api` atau samakan dengan kontrak `mock-mode.ts` (jangan telan error HTTP), lalu hapus
`mock-lms-data.ts` bila tak terpakai.

---

## BUG 5 — "Beberapa hal gagal di-input" (P1 — perlu verifikasi runtime)

Belum bisa saya pastikan tanpa menjalankan app. Form yang saya lihat (submit tugas program
[program-tasks-card.tsx](apps/web/src/features/portal/student-programs/program-tasks-card.tsx), login
admin) sudah terhubung ke endpoint nyata + Idempotency-Key + state error. Kandidat penyebab yang paling
mungkin saat input gagal: (a) endpoint target balas 404/403 (fitur backend belum ada / permission
kurang / cakupan data), (b) data master belum di-seed sehingga dropdown/relasi kosong, (c) validasi DTO
backend menolak field. **Perlu:** aku jalankan web+api lokal, reproduksi 1–2 input yang kamu maksud,
dan baca respons jaringan. Sebutkan layar/tombol spesifiknya biar langsung saya kejar.

---

## Catatan metode & batasan
- Analisis statis; **belum** menjalankan Playwright/stack. Gejala runtime (hitungan "2 tugas",
  input gagal) dikonfirmasi lewat penyebab kode, bukan reproduksi.
- Belum menyisir **seluruh** modul (fokus: auth/2FA, portal ortu/murid/guru yang kamu sebут). Modul
  lain (keuangan, PPDB, CBT runtime, admin shell) belum diaudit di sesi ini.
- Langkah lanjut yang saya sarankan: (1) perbaiki BUG 1 & BUG 2 dulu (blocker + fabrikasi), (2) saya
  jalankan app untuk BUG 5 & verifikasi perbaikan. Perbaikan = tugas/PR terpisah, bukan di laporan ini.
