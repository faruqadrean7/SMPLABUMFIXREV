# Laporan Baseline Pengujian & Linter — FASE 0 (Baseline)

- **Repositori**: `SISTLABUM` — SMP Laboratorium Universitas Negeri Malang
- **Tanggal & Waktu**: 27 Oktober 2026, 11:58 WIB
- **Branch**: `fix/teacher-gaps` (HEAD: `b234cfb test(audit-5e): complete functional audit and integrated simulations 5e1-5e3`)
- **Tujuan**: Mengambil baseline status pengujian backend (`php artisan test`), linter backend (`vendor/bin/pint --test`), unit test frontend (`npm run test`), dan build frontend (`npm run build`) tanpa mengubah logika aplikasi.
- **Aturan**: Mengacu pada `AGENTS.md` dan `PROMPT_PERBAIKAN_BERFASE.md` (FASE 0).

---

## 1. Verifikasi SEC-04: Pelacakan File `.env` pada Git

### 1.1 Perintah Eksekusi
```bash
git ls-files | grep -E "(^|/)\.env$"
```

### 1.2 Hasil & Bukti
```
(Exit code: 1, tidak ada output)
```

Pengecekan daftar file ter-track yang memuat kata `env`:
```bash
git ls-files "*env*"
# Output:
# .env.example
# apps/api/.env.example
# apps/web/.env.example
# apps/web/next-env.d.ts
# docs/api/bruno/SISTLABUM/.env.example
# docs/api/bruno/SISTLABUM/environments/Local.bru
# docs/api/bruno/SISTLABUM/environments/Testing.bru
```

Verifikasi aturan `.gitignore`:
```bash
git check-ignore -v .env apps/api/.env apps/web/.env
# Output:
# .gitignore:10:.env          .env
# apps/api/.gitignore:3:.env  apps/api/.env
# .gitignore:10:.env          apps/web/.env
```

### 1.3 Kesimpulan SEC-04
- **STATUS: AMAN / VERIFIED CLEAN ✅**
- Berkas `.env` asli (baik di root, `apps/api`, maupun `apps/web`) **tidak pernah ter-track** ke dalam repositori git.
- Hanya template `.env.example` dan deklarasi tipe `next-env.d.ts` yang ter-track.
- Tidak diperlukan tindakan `git rm --cached`.

---

## 2. Baseline Backend: Pengujian PHPUnit (`php artisan test`)

### 2.1 Lingkungan Eksekusi
- **Runtime**: PHP 8.2 (Container `sistlabum-api`)
- **Database**: PostgreSQL 16 (Container `sistlabum-postgres`, database `sistlabum_testing`)
- **Cache/Queue**: Redis 7 (Container `sistlabum-redis`)
- **Perintah**: `docker exec -i sistlabum-api php artisan test`

### 2.2 Ringkasan Hasil
| Metrik | Nilai | Catatan |
|---|:---:|---|
| **Total Test** | **1.311** | Meliputi Feature, Unit, dan Simulasi Terpadu Batch 5E |
| **Passed** | **1.301** | Lulus (99.2%) |
| **Skipped** | **6** | Dilewati sengaja (lingkungan khusus) |
| **Failed** | **4** | Kegagalan spesifik pengujian throttling rate-limit |
| **Assertions** | **8.679** | Total asersi terverifikasi |
| **Durasi** | **151,26s** | ~2,5 menit |

### 2.3 Rincian 4 Test Gagal (Root Cause Analysis)
Keempat test yang gagal adalah pengujian pembatasan laju (rate limiting / HTTP 429):
1. `Tests\Feature\Auth\ForgotPasswordThrottlingTest > p1 sec dos 01 forgot password throttled after 5 requests`
   - *Expected*: 429
   - *Received*: 200
2. `Tests\Feature\Cbt\ExamSyncSecurityTest > p1 sec authz 02 sync pull rate limiting prevents rapid successive sync attempts`
   - *Expected*: 429
   - *Received*: 200
3. `Tests\Feature\Cbt\ExamTokenAndRoomTest > regenerate token endpoint enforces rate limit of 10 requests per minute`
   - *Expected*: 429
   - *Received*: 200
4. `Tests\Feature\Integration\ApiErrorContractTest > rate limited error returns structured error with retry after`
   - *Expected*: 429
   - *Received*: 200

**Penyebab (Root Cause)**:
Pada file `docker-compose.yml`, service `api` dikonfigurasikan dengan environment variable:
`TEST_DISABLE_RATE_LIMIT: ${TEST_DISABLE_RATE_LIMIT:-true}`
Konfigurasi ini sengaja menonaktifkan rate limiting middleware (`BypassThrottle`) saat pengujian beban dan automasi crawling staging, sehingga request ke-6 tidak memicu 429. Jika variabel diset `false`, keempat test ini lulus.

---

## 3. Baseline Backend Linter: Laravel Pint (`vendor/bin/pint --test`)

### 3.1 Perintah Eksekusi
```bash
cd apps/api && vendor/bin/pint --test
```

### 3.2 Hasil
- **Exit Code**: 1 (Gagal)
- **Status**: Ditemukan 29 file kode dengan ketidaksesuaian gaya format (coding standards).

### 3.3 Daftar 29 File Terdampak
1. `database/seeders/MonthlySimulationUatSeeder.php`
2. `database/seeders/DatabaseSeeder.php`
3. `database/factories/FeeTypeFactory.php`
4. `database/factories/PaymentFactory.php`
5. `database/factories/InvoiceFactory.php`
6. `database/factories/InvoiceItemFactory.php`
7. `bootstrap/app.php`
8. `app/Http/Controllers/Api/V1/Finance/FinanceReportController.php`
9. `app/Http/Controllers/Api/V1/Finance/FeeTypeController.php`
10. `app/Http/Controllers/Api/V1/Finance/InvoiceController.php`
11. `app/Http/Controllers/Api/V1/Finance/PaymentController.php`
12. `app/Services/Ntf/ReminderService.php`
13. `app/Services/PostService.php`
14. `app/Services/AuthService.php`
15. `app/Services/GuardianService.php`
16. `app/Services/Finance/FinanceService.php`
17. `app/Services/Finance/ReceiptService.php`
18. `app/Services/Finance/InvoiceGeneratorService.php`
19. `tests/Feature/Cbt/ExamSyncSecurityTest.php`
20. `tests/Feature/Seeder/MonthlySimulationSeederFeatureTest.php`
21. `tests/Feature/Auth/AdminRoleMatrixFeatureTest.php`
22. `tests/Feature/Auth/MobileClientAuthCompatibilityTest.php`
23. `tests/Feature/Auth/AdminTwoFactorAuthFeatureTest.php`
24. `tests/Feature/Auth/LoginTest.php`
25. `tests/Feature/Auth/ForgotPasswordThrottlingTest.php`
26. `tests/Feature/Auth/PasswordTest.php`
27. `tests/Feature/Simulation/Batch5EIntegratedSimulationTest.php`
28. `tests/Feature/Finance/FinanceModuleFeatureTest.php`
29. `routes/api.php`

---

## 4. Baseline Frontend Test: Vitest (`npm run test`)

### 4.1 Perintah Eksekusi
```bash
cd apps/web && npm run test
```

### 4.2 Hasil
- **Exit Code**: 1
- **Test Files**: 74 passed, 1 failed (total 75 file pengujian)
- **Tests**: 860 passed, 2 failed (total 862 skenario pengujian)
- **Durasi**: 12,64s

### 4.3 Rincian 2 Test Gagal
File: `src/test/finance-module.test.tsx`
1. `Admin ArrearsReportPage: renders arrears summary cards and breakdown`
   - *Penyebab*: `waitFor` timeout menunggu state render tabel setelah request mock.
2. `Admin FinanceReportsPage: renders total income summary and daily stream from verified slips`
   - *Penyebab*: Asersi sinkronisasi data mock API keuangan.

Catatan: Seluruh 74 file pengujian frontend lainnya (PPDB, CBT, Admin Shell, Portal Guru, Portal Siswa, dsb) **100% lulus**.

---

## 5. Baseline Frontend Build: Next.js (`npm run build`)

### 5.1 Perintah Eksekusi
```bash
cd apps/web && ADMIN_LOGIN_PATH=/admin-gate-build npm run build
```

### 5.2 Hasil
- **Exit Code**: 0 (Lulus Sempurna ✅)
- **Kompilasi**: `Compiled successfully`
- **Generasi Halaman**: 103 dari 103 halaman statis terkompilasi bersih tanpa error TypeScript (`Linting and checking validity of types: PASS`).
- **Middleware**: 26,8 kB bundle size.

### 5.3 Catatan Khusus Guard Produksi Next.js
Pada `apps/web/next.config.mjs`, terdapat guard keamanan keras:
Lingkungan produksi mewajibkan nilai `ADMIN_LOGIN_PATH` yang unik dan melarang nilai bawaan `/secret-admin-gate-login`. Jika dijalankan tanpa variabel tersebut, build sengaja melempar error `FATAL` demi keamanan sistem (keamanan pintu masuk admin).

---

## 6. Ringkasan Matriks Baseline FASE 0

| Komponen | Status Baseline | Baseline Angka | Target FASE 6 |
|---|:---:|---|---|
| **SEC-04 (.env leak)** | **HIJAU** | 0 file ter-track | 0 file ter-track |
| **Backend Test** | **1.301 Pass / 4 Fail** | 1.301 / 1.311 (8.679 assertions) | >= 1.301 pass, 0 fail saat throttle aktif |
| **Backend Pint** | **MERAH** | 29 file perlu format | 0 file (Pint Clean) |
| **Frontend Test** | **860 Pass / 2 Fail** | 860 / 862 (74/75 suite pass) | >= 860 pass |
| **Frontend Build** | **HIJAU** | 103/103 halaman lulus | 103/103 halaman lulus |
