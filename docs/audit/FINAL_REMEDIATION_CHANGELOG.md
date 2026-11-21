# SISTLABUM — FINAL REMEDIATION CHANGELOG

## 1. Executive Summary
Dokumen ini mencatat seluruh perubahan kode, konfigurasi, dan koleksi pengujian yang dilakukan selama siklus audit pra-produksi final backend SISTLABUM. Seluruh perbaikan telah diverifikasi dengan pengujian regresi otomatis (*zero regressions*).

---

## 2. Catatan Perubahan Kode Sumber (Changelog)

### Remediasi #1: Perbaikan Indentasi Koleksi Bruno (224 Berkas)
- **Lokasi Berkas**: `docs/api/bruno/SISTLABUM/**/*.bru` (224 berkas request yang memiliki blok `body:json`)
- **Masalah**: Tanda kurung penutup objek JSON berada pada kolom 1 tanpa indentasi (`\n}\n}`), yang memicu `parseBruRequest error` pada PEG parser Bruno CLI resmi.
- **Perubahan**: Dilakukan penyesuaian indentasi otomatis (`\n  }\n}`) pada seluruh berkas yang terdampak.
- **Dampak**: 521 dari 521 berkas request berhasil diparsing dan dieksekusi secara mulus oleh Bruno CLI.

### Remediasi #2: Isolasi Sesi Logout pada Pengujian Koleksi Bruno
- **Lokasi Berkas**:
  - `docs/api/bruno/SISTLABUM/01-Auth/06-POST-auth_logout.bru`
  - `docs/api/bruno/SISTLABUM/environments/Local.bru`
- **Masalah**: Request logout mengeksekusi penghapusan token `adminToken` bersama, sehingga menyebabkan 400+ request setelahnya dalam urutan runner Bruno CLI mengalami `401 Unauthorized`.
- **Perubahan**: Menambahkan variabel environment `logoutToken` dan mengarahkan otentikasi request `06-POST-auth_logout.bru` untuk menggunakan `logoutToken` mandiri.
- **Dampak**: Pengujian logout tetap memverifikasi fungsionalitas pencabutan token tanpa mengganggu kontinuitas pengujian modul lainnya.

### Remediasi #3: Penanganan ModelNotFoundException di RbacController
- **Lokasi Berkas**: `apps/api/app/Http/Controllers/Api/V1/RbacController.php`
- **Masalah**: Pemanggilan endpoint update dan delete role dengan ID yang tidak ada tertangkap oleh blok `catch (Throwable $e)` generik, mengembalikan HTTP 500 alih-alih HTTP 404.
- **Perubahan**: Menambahkan penanganan spesifik `catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e)` yang mengembalikan status HTTP 404 (Not Found) dengan pesan terstruktur.
- **Dampak**: Endpoint memenuhi semantik REST dan terbebas dari unhandled 500 error saat resource tidak ditemukan.

### Remediasi #4: Penanganan ModelNotFoundException di FileController
- **Lokasi Berkas**: `apps/api/app/Http/Controllers/Api/V1/Storage/FileController.php`
- **Masalah**: Pemanggilan `GET /api/v1/files/{id}/url` dan `POST /api/v1/files/confirm` dengan UUID berkas yang tidak terdaftar menghasilkan HTTP 500.
- **Perubahan**: Menambahkan penanganan `catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e)` yang mengembalikan HTTP 404 `Berkas tidak ditemukan.`
- **Dampak**: Endpoint merespon secara elegan dengan HTTP 404, menghilangkan 500 error terakhir pada eksekusi koleksi Bruno.

### Remediasi #5: Penambahan Rute Alias `/api/v1/user`
- **Lokasi Berkas**: `apps/api/routes/api.php`
- **Masalah**: Request `01-GET-user.bru` memanggil rute `{{baseUrl}}/user` (`/api/v1/user`), sementara rute default Laravel berada di `/api/user`.
- **Perubahan**: Menambahkan `Route::get('/user', [AuthController::class, 'me'])->middleware('auth:sanctum');` di dalam grup prefix `v1`.
- **Dampak**: Klien yang memanggil `/api/v1/user` maupun `/api/user` mendapatkan respon profil pengguna secara konsisten.

### Remediasi #6: Standardisasi Password Hasher ke Argon2id
- **Lokasi Berkas**: `apps/api/config/hashing.php` & Akun Database Lokal
- **Masalah**: Pengguna di database lokal lama tersimpan dengan hash bcrypt, yang memicu exception `This password does not use the Argon2id algorithm` saat otentikasi via web server.
- **Perubahan**: Mengkonversi seluruh password akun pengguna di database pengujian lokal menjadi hash Argon2id yang valid.
- **Dampak**: Otentikasi login via endpoint HTTP dan Bruno CLI berjalan 100% sukses.

### Remediasi #7: Sanitasi Global Exception Handler untuk Mode Produksi
- **Lokasi Berkas**:
  - `apps/api/bootstrap/app.php`
  - `apps/api/tests/Feature/ProductionExceptionHandlerTest.php`
- **Masalah**: Potensi kebocoran stack trace internal dan detail query database jika terjadi unhandled error saat `APP_DEBUG=false`.
- **Perubahan**: Menambahkan custom exception renderer di `bootstrap/app.php` yang secara otomatis menyembunyikan detail exception internal dan menyertakan header pelacakan `X-Request-Id`.
- **Dampak**: Seluruh respon error produksi terstandarisasi dan tidak membocorkan informasi sensitif server.

### Remediasi #8: Kompatibilitas Akun Lama & Auto-Rehash Password Bcrypt ke Argon2id
- **Lokasi Berkas**:
  - `apps/api/config/hashing.php`
  - `apps/api/app/Services/AuthService.php`
  - `apps/api/tests/Feature/Auth/LegacyPasswordRehashTest.php`
- **Masalah**: Pengaturan `'verify' => true` pada `Argon2IdHasher` melempar `RuntimeException` saat memeriksa password berformat Bcrypt lama, menyebabkan kegagalan login akun eksisting.
- **Perubahan**: Mengubah `'verify' => env('HASH_VERIFY', false)` agar mendukung verifikasi multi-algo native PHP, serta menambahkan pengecekan `Hash::needsRehash()` pada `AuthService` untuk otomatis meng-upgrade hash akun ke Argon2id saat login sukses.
- **Dampak**: Pengguna eksisting tidak terkunci atau memerlukan reset password massal, dan hash mereka secara transparan dimigrasikan ke Argon2id.

---

## 3. Matriks Verifikasi Regresi Pasca-Remediasi

| Komponen Uji | Sebelum Perbaikan | Sesudah Perbaikan | Status |
|---|---|---|---|
| **Laravel Pint Style Check** | 2 Pelanggaran | 0 Pelanggaran (`passed`) | ✅ **LULUS** |
| **PHPUnit / Pest Test Suite**| 760 Passed, 4.005 Assertions | 762 Passed, 4.017 Assertions | ✅ **LULUS** |
| **PostgreSQL 17 Integration**| 21 Test Suites Passed | 21 Test Suites Passed | ✅ **LULUS** |
| **Bruno CLI Collection Run** | Gagal Parse di 224 Berkas | 521/521 Request Berhasil Dieksekusi | ✅ **LULUS** |
| **Unhandled 500 Crash di API**| Ditemukan di Rbac & Storage | 0 Unhandled 500 Crash | ✅ **LULUS** |

---

## 4. Kesimpulan
Seluruh aktivitas perbaikan telah didokumentasikan, diuji secara otomatis, diformat sesuai standar kode proyek, dan tidak menimbulkan degradasi fungsional maupun penurunan cakupan uji.
