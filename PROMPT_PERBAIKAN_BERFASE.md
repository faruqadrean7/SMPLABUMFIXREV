# Prompt Perbaikan Berfase — SISTLABUM

Dokumen ini berisi **prompt siap-pakai** untuk AI agent (Antigravity / Claude Code / Cursor) guna menindaklanjuti temuan di `LAPORAN_AUDIT_SISTEM.md`. Kerjakan **berurutan per fase**. Jangan lompat fase sebelum fase sebelumnya lulus verifikasi.

## Aturan Global (berlaku untuk semua fase)
1. Patuhi `AGENTS.md` dan dokumen `00`–`13`. Konvensi: ID fitur, Conventional Commits, PR < 500 baris, test berawalan ID.
2. **Tulis "rencana singkat" (5–10 baris) sebelum coding** tiap tugas: file yang disentuh, perubahan DB/API, uji yang ditulis, risiko.
3. **Jangan memutuskan hal di AGENTS.md §8** (rumus/kebijakan sekolah, kontrak API lintas-klien, hapus/ubah kolom berisi data). Bila ketemu, **berhenti & tandai sebagai pertanyaan**, jangan menebak.
4. **Jangan menurunkan coverage, jangan `--no-verify`, jangan nonaktifkan test/lint.** Setiap logika baru wajib ada test (happy path, 401, 403, validasi gagal, 1 aturan bisnis batas).
5. Setelah tiap fase: jalankan `php artisan test`, `vendor/bin/pint --test` (backend) dan `npm run test` + `npm run build` (web). Semua harus hijau sebelum commit.
6. Branch per fase: `fix/fase-N-<slug>` dari `develop`. Jangan push ke `main`/`develop` langsung.

---

## FASE 0 — Baseline & Verifikasi (wajib pertama)

```
Kerjakan FASE 0 (Baseline) untuk proyek SISTLABUM. Jangan ubah logika apa pun di fase ini.

Tugas:
1. Baca AGENTS.md, 00_README.md, 09_SECURITY_PRIVACY.md, dan LAPORAN_AUDIT_SISTEM.md.
2. Jalankan baseline dan catat hasil apa adanya ke file docs/audit/BASELINE_RUN.md:
   - Backend: `cd apps/api && php artisan test` dan `vendor/bin/pint --test`.
   - Web: `cd apps/web && npm run test` dan `npm run build`.
3. Verifikasi SEC-04: jalankan `git ls-files | grep -E "(^|/)\.env$"`. Jika .env ter-track,
   laporkan (JANGAN hapus file; cukup catat + usulkan `git rm --cached`).
4. Buat inventaris rute tanpa guard: daftar semua Route di apps/api/routes/api.php yang berada
   dalam grup `auth:sanctum` TANPA `permission:` per-rute. Tulis ke docs/audit/ROUTE_GUARD_MATRIX.md
   (kolom: method, path, controller@method, permission_saat_ini, permission_usulan).

Hasil: laporan baseline + matriks rute. Tidak ada perubahan kode. Jangan commit kode, hanya file docs.
```

---

## FASE 1 — Keamanan Kritis (P0)

```
Kerjakan FASE 1 (Keamanan Kritis) SISTLABUM. Rujuk ROUTE_GUARD_MATRIX.md dari Fase 0.

Tugas:
1. [SEC-01] Terapkan defense-in-depth: tambahkan middleware `permission:<...>` di LAYER RUTE untuk
   SETIAP endpoint manajemen/mutasi di apps/api/routes/api.php yang kini hanya `auth:sanctum`.
   - Gunakan nama permission yang sudah ada (cek seeder RBAC & RbacService). Jangan mengarang permission baru
     tanpa menambahkannya ke seeder + test.
   - JANGAN hapus pengecekan otorisasi yang sudah ada di service (tetap dua lapis).
   - Prioritas tertinggi dulu: /notifications/broadcast, /notification-templates/send, /notification-logs,
     /dashboard/executive, /reports/{type} & export, /point-types, /point-thresholds,
     /report-cards (generate|approve|lock), /progress-reports approve, /grade-periods/{id}/lock,
     /exams/{id}/results, /exams/{id}/item-analysis, /exam-sessions/{id}/manage, /exams/{id}/monitor,
     /counseling/* dan /early-warnings/* (data BK sensitif).
   - Untuk tiap endpoint: tambahkan test "403 bila role tanpa izin" (mis. siswa) dan "200 bila berwenang".
2. [SEC-02] Batasi `POST /audit-logs` (storeTelemetry): tandai entri sebagai source=client,
   batasi field yang boleh ditulis via FormRequest, dan beri `throttle`. Jangan biarkan menulis
   action/entity sembarang yang menyamar sebagai audit resmi. Tambah test.
3. [SEC-03] Audit IDOR unduh slip gaji (`/salary-slips/{slipId}/download`, `/teacher/me/...`) dan
   struk pembayaran (`/me/payments/{id}/receipt`): pastikan kepemilikan diverifikasi di service.
   Tambahkan test "user A tidak bisa mengunduh milik user B (403)".

Batasan: jangan ubah skema DB; jangan ubah kontrak response. 
DoD: semua test lama + baru hijau, Pint clean. Commit per kelompok modul (feat/fix(be-sec-xx)).
```

---

## FASE 2 — Konsistensi & Integritas Backend (P1)

```
Kerjakan FASE 2 (Backend Hardening) SISTLABUM.

Tugas:
1. [BE-01] Seragamkan error-handling di SEMUA controller API: tangkap `HttpException` SEBELUM `Throwable`,
   petakan 401/403/404/422 ke kode & status yang benar (ikuti pola ComplaintController/ReportCardController).
   Perbaiki minimal controller yang hanya `catch (Throwable)` → 500 (contoh: StudentController index/export).
   Lebih baik: pasang exception handler global di bootstrap/app.php agar konsisten. Tambah test yang
   memastikan 403/422 tidak lagi muncul sebagai 500.
2. [BE-04] Pastikan `invoices.paid_amount` & `invoices.status` direkalkulasi di dalam DB::transaction yang
   sama saat payment verify/reject/void di FinanceService. Tambah command `php artisan finance:reconcile`
   (idempoten) + test drift.

DoD: test hijau, Pint clean. Tanpa perubahan skema.
```

---

## FASE 3 — Frontend: Tombol & UX (P0/P1)

```
Kerjakan FASE 3 (Frontend UX) SISTLABUM di apps/web.

Tugas:
1. [FE-01] Ganti SEMUA `window.confirm(...)` dan `alert(...)` dengan komponen aplikasi:
   - Konfirmasi hapus/aksi destruktif -> komponen ConfirmDialog yang sudah ada (lihat confirm-dialog.test.tsx).
   - Notifikasi sukses/error/validasi -> sistem toast konsisten (buat satu hook useToast bila belum ada).
   - Lokasi diketahui: admin/cms (hapus berita/agenda/unduhan/album/media), admin/public-relations,
     admin/facilities, admin/reports, portal/siswa. Sisir juga file lain dengan:
     `grep -rnE "window\.(confirm|prompt|alert)|[^.]alert\(" apps/web/src/app`.
   - Pastikan aksesibilitas: fokus, label, ESC, kontras.
2. [FE-02] Tuntaskan audit tombol/handler halaman yang belum tersisir (yang 8 folder dalam):
   admin/academic/grades, admin/cbt/exams, admin/cbt/monitor, admin/finance/invoices & payments,
   portal/siswa/cbt/[id] (workspace ujian), portal/guru/nilai & presensi, master/students & teachers.
   Cari: tombol tanpa handler, handler tanpa pemanggilan API, form tanpa submit, state loading/empty/error
   yang hilang, dan tombol yang tetap aktif saat request berjalan (double-submit). Perbaiki yang ditemukan.
   Tulis temuan tambahan ke docs/audit/FRONTEND_BUTTON_FINDINGS.md.

DoD: `npm run test` + `npm run build` hijau; tidak ada lagi window.confirm/alert di src/app.
```

---

## FASE 4 — Maintainability (P2)

```
Kerjakan FASE 4 (Refactor Maintainability) SISTLABUM. Refactor TANPA mengubah perilaku (behavior-preserving).

Tugas:
1. [BE-02] Pecah apps/api/routes/api.php menjadi beberapa file per modul (routes/api/auth.php,
   master.php, academic.php, cbt.php, dst) yang di-require dari api.php. Jangan ubah path/permission.
2. [BE-02] Pecah MeTeacherController (±108KB) menjadi beberapa controller per sub-domain
   (dashboard, attendance, grading, lms, exam, leave, homeroom). Pindahkan logika ke service bila masih
   ada di controller. Jaga signature route tetap sama (atau perbarui route + test seiring).
3. [BE-03] Rapikan duplikasi/alias rute (GET /me 3x; /me/notifications vs /notifications;
   notification-preferences ganda; alias learning-modules/question-packages/grades-batch). Satukan handler
   atau samakan middleware-nya secara eksplisit. Pastikan alias punya permission yang SAMA dengan aslinya.

DoD: SELURUH test existing tetap hijau tanpa diubah logikanya (bukti refactor aman). Pint clean.
```

---

## FASE 5 — Tata Kelola & Dokumentasi

```
Kerjakan FASE 5 (Dokumentasi) SISTLABUM. Hanya file dokumen, tanpa kode.

Tugas:
1. [GOV-01] HENTIKAN & TANYAKAN: modul Finance sudah dibangun padahal 13_MASTER_MODULES.md & 
   docs/finance-module-assessment.md menandainya "Menunggu Keputusan" (AGENTS.md §8). Jangan hapus kodenya.
   Buat docs/DECISION_PENDING_FINANCE.md yang merangkum: apa yang sudah terbangun, apa yang butuh persetujuan
   sekolah, dan pertanyaan yang harus dijawab pemilik sistem.
2. Sinkronkan dokumen dengan kode (catat di CHANGELOG tiap dokumen):
   - Perbaiki klaim backend "NestJS" di 02_TECH_STACK.md/03_ARCHITECTURE.md menjadi Laravel (atau tandai
     bahwa alternatif Laravel yang dipakai).
   - Perbarui status modul Finance di 13_MASTER_MODULES.md setelah keputusan.
   - Perbarui FINAL_SECURITY_GATE_REPORT dengan jumlah test terbaru pasca-Fase 1–4.

DoD: dokumen konsisten dengan kode; daftar "pending decision" jelas.
```

---

## FASE 6 — Verifikasi Akhir (Regression Gate)

```
Kerjakan FASE 6 (Regression Gate) SISTLABUM.

Tugas:
1. Jalankan penuh: apps/api -> `php artisan test` + `vendor/bin/pint --test`;
   apps/web -> `npm run test`, `npm run build`, dan e2e Playwright bila ada.
2. Bandingkan dengan BASELINE_RUN.md (Fase 0). Jumlah test tidak boleh turun; tidak ada regresi.
3. Buat LAPORAN_PERBAIKAN.md: per temuan audit (SEC-01..GOV-01) -> status (Selesai/Sebagian/Ditunda),
   file yang diubah, test yang ditambahkan, dan sisa risiko.
4. Siapkan ringkasan PR gabungan sesuai format AGENTS.md §11.

DoD: semua gate hijau, laporan perbaikan lengkap.
```

---

## Catatan Eksekusi
- Jika suatu permission belum ada di seeder RBAC, tambahkan ke seeder + test (jangan hardcode di rute saja).
- Jika sebuah perbaikan menyentuh kontrak API yang dipakai web **dan** mobile, perlakukan sebagai item AGENTS.md §8: hentikan dan tanyakan dulu.
- Semua teks UI/pesan error untuk pengguna dalam Bahasa Indonesia; identifier & komentar teknis Bahasa Inggris.
