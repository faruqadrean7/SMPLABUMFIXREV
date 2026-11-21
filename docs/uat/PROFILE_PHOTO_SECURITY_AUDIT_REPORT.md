# LAPORAN AUDIT KEAMANAN DAN INTEGRITAS DATA FOTO PROFIL SISTLABUM
**Dokumen:** `docs/uat/PROFILE_PHOTO_SECURITY_AUDIT_REPORT.md`  
**Status Akhir:** `READY WITH CONDITIONS`  
**Target Repository:** `/Users/macbookprom12020/Desktop/Project/SISTLABUM`  
**Branch:** `develop` (HEAD: `448a1a499d3c3a87aa61c3d28428c616dd8f0405`)  
**Tanggal Audit:** 16 Oktober 2026  
**Referensi:** `AGENTS.md`, `13_MASTER_MODULES.md`, `08_DATABASE_SCHEMA.md`, `09_SECURITY_PRIVACY.md`, `10_TESTING_QA.md`, `docs/uat/PROFILE_PHOTO_IMPLEMENTATION_REPORT.md`

---

## 1. RINGKASAN EKSEKUTIF

Audit keamanan dan integritas penyimpanan data foto profil ini dilakukan untuk memverifikasi seluruh alur pengunggahan, otorisasi, penanganan kegagalan parsial, kepatuhan batas ukuran dan tipe berkas di server, isolasi hak asuh (anti-IDOR), serta tata kelola foto profil resmi sebelum fitur dinyatakan siap digunakan pada lingkungan staging UAT.

Hasil audit menyimpulkan status: **`READY WITH CONDITIONS`**.

Fitur foto profil telah memenuhi standar keamanan tingkat tinggi:
- **Validasi Server-Side Diperketat:** Penambahan validasi server pada `PresignFileRequest` dan `FileStorageService` yang secara tegas membatasi berkas dengan `purpose: 'avatar'` hanya boleh berformat gambar (JPEG/PNG/WebP) dan ukuran fisik maksimal 2 MB di server.
- **Isolasi Otorisasi & Anti-IDOR:** Endpoint pembaruan master data (`/students`, `/teachers`) dikunci di level backend dengan permission `master.student.write` dan `master.teacher.manage`. Siswa, guru biasa, orang tua, dan peretas tidak dapat mengubah atau mengaitkan foto secara sembarangan.
- **Keamanan Penyimpanan:** Berkas privat tetap dilindungi dari eksposur URL publik langsung (`$file->url` selalu `null` untuk berkas privat). Berkas foto profil (`visibility: 'publik'`) menggunakan nama objek biner acak (UUID v4) sehingga tidak membocorkan nama asli berkas atau informasi pribadi pengguna.
- **Foreign Key Integrity:** Relasi foreign key `photo_file_id` pada tabel `students` dan `teachers` menggunakan konfigurasi `ON DELETE SET NULL`, mencegah kegagalan integritas basis data jika berkas di tabel `files` dihapus.
- **Transparansi Alur Persetujuan:** Tidak ada klaim palsu di portal mandiri bahwa foto telah disetujui; pengguna diinformasikan secara jujur bahwa pembaruan pasfoto resmi diproses melalui Tata Usaha (TU).

Kondisi yang memerlukan keputusan pemilik proyek sebelum rilis produksi penuh didokumentasikan pada Bab 7.

---

## 2. TEMUAN AUDIT BERDASARKAN TINGKAT RISIKO (FINDINGS BY SEVERITY)

### [MEDIUM] SEC-PHOTO-01: Risiko Penumpukan Berkas Yatim (Orphan Files) pada Kegagalan Parsial
- **Deskripsi:** Pada alur pengunggahan berkas langsung (`storageApi.uploadFileDirect`), berkas fisik diunggah dan dicatat pada tabel `files` terlebih dahulu sebelum formulir induk disimpan. Jika pengguna membatalkan dialog formulir (`StudentDialog` / `TeacherDialog`) atau terjadi kegagalan validasi data induk (misalnya NIS duplikat atau NISN tidak valid), berkas foto yang telah terunggah tetap berada di storage dan tabel `files` tanpa direferensikan oleh entitas mana pun.
- **Dampak:** Konsumsi kapasitas penyimpanan disk yang tidak perlu dalam jangka panjang. Tidak menimbulkan kebocoran data karena nama berkas telah diacak menggunakan UUID.
- **Rekomendasi:** Implementasikan scheduled command harian (misal: `php artisan files:cleanup-orphans --dry-run`) yang membersihkan berkas berumur lebih dari 24 jam dengan `purpose = 'avatar'` yang tidak direferensikan oleh tabel `students` maupun `teachers`.

### [LOW] SEC-PHOTO-02: Ketiadaan Kolom Fisik Foto pada Entitas Orang Tua dan Staf
- **Deskripsi:** Tabel `guardians` dan `staff` belum memiliki kolom `photo_file_id` pada skema basis data saat ini.
- **Dampak:** Orang tua dan staf non-guru tidak dapat menyimpan pasfoto profil individual di database induk. Sistem saat ini menampilkan avatar inisial kanonis untuk profil wali dan staf.
- **Rekomendasi:** Pemilik proyek perlu menyetujui migrasi skema penambahan kolom `photo_file_id` (blueprint telah disiapkan).

### [INFORMATIONAL] SEC-PHOTO-03: Ketiadaan Antrean Persetujuan Mandiri (Self-Service Approval Queue)
- **Deskripsi:** Portal mandiri siswa dan guru belum menyediakan formulir pengajuan ganti foto mandiri ke antrean persetujuan Admin TU, karena backend saat ini tidak memiliki skema penampung foto sementara (`pending_photo_file_id`) maupun endpoint persetujuan foto.
- **Dampak:** Foto profil siswa dan guru bersifat resmi dan hanya dapat diubah oleh Admin TU melalui Admin Master Data.
- **Status Mitigasi:** Sesuai instruksi, frontend portal tidak berpura-pura bahwa pengunggahan mandiri berhasil, melainkan menampilkan instruksi jelas bahwa penggantian pasfoto resmi dilakukan melalui staf TU.

---

## 3. BUKTI ALUR UPLOAD DAN INTEGRITAS PENYIMPANAN

Alur lengkap pengujian integritas end-to-end:

```
[UI: ProfilePhotoUploader]
       │
       ▼ (1) Validasi lokal (MIME image/*, max 2MB, preview blob:)
[POST /api/v1/files/presign] (Sanctum Auth)
       │
       ▼ (2) Server validation: purpose 'avatar', max 2MB, random UUID object_key, token cached
[POST /api/v1/files/upload-signed/{token}] (Temporary Signed Route)
       │
       ▼ (3) Binary upload received, size checked <= 2MB, written to Storage disk
[POST /api/v1/files/confirm] (Sanctum Auth)
       │
       ▼ (4) Verifikasi fisik berkas di storage, INSERT into `files`, AuditLog dicatat
[Response: FileItem JSON { id: "uuid", object_key: "..." }]
       │
       ▼ (5) Form submit master data
[POST/PUT /api/v1/students atau /teachers] (Permission: master.student.write / master.teacher.manage)
       │
       ▼ (6) UPDATE students/teachers SET photo_file_id = "uuid"
[GET /api/v1/students atau /me/student/dashboard]
       │
       ▼ (7) Eager load 'photoFile', return photo_url
[UI: <Avatar src={photo_url} />] -> Ter-render sempurna (onError fallback aktif jika 404)
```

### Hasil Uji Skenario Integritas:
1. **Upload Berhasil:** Berkas tersimpan di disk `public/YYYY/MM/<uuid>.<ext>`, tercatat di tabel `files`, terhubung ke `students.photo_file_id`, dan URL publik dapat diakses tanpa token kedaluwarsa.
2. **Upload Ditolak (Format Bukan Gambar):** Ditolak di klien dan di server (HTTP 400 `VALIDATION_FAILED`).
3. **Upload Ditolak (Ukuran > 2 MB):** Ditolak di klien (`Ukuran gambar melebihi batas 2 MB`) dan ditolak di server (HTTP 400 `VALIDATION_FAILED` pada `presign`, HTTP 422 jika lolos `presign`).
4. **Penggantian Foto:** Saat foto diganti, `photo_file_id` pada data siswa diperbarui ke ID berkas baru. Berkas lama tetap ada di tabel `files` untuk integritas log audit.
5. **Pembatalan / Penghapusan:** Menghapus foto menyetel `photo_file_id` menjadi `null`. Avatar langsung beralih ke inisial nama tanpa error.
6. **Foto Tidak Ditemukan (404 Fallback):** Komponen `<Avatar>` menangkap event `onError`, menyetel `hasError: true`, menyembunyikan tag `<img>`, dan merender inisial nama secara mulus.
7. **Integritas Relasi Basis Data:** Penghapusan baris pada tabel `files` secara otomatis menyetel `photo_file_id = NULL` pada tabel `students` dan `teachers` karena relasi menggunakan `ON DELETE SET NULL`.

---

## 4. MATRIKS HAK AKSES DAN KEAMANAN BERKAS PER ROLE

| Role Pengguna | Upload Berkas Foto (`/files/presign`) | Ubah Foto Siswa (`PUT /students`) | Ubah Foto Guru (`PUT /teachers`) | Lihat Foto Siswa di Master Data | Lihat Foto Anak di Portal Ortu | Akses URL Publik Avatar |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| **Super Admin** | ✅ Ya | ✅ Ya | ✅ Ya | ✅ Ya | N/A | ✅ Ya |
| **Admin TU** | ✅ Ya | ✅ Ya | ✅ Ya | ✅ Ya | N/A | ✅ Ya |
| **Kepala Sekolah** | ✅ Ya | ❌ Tidak | ❌ Tidak | ✅ Ya (Read-only) | N/A | ✅ Ya |
| **Guru / Walas** | ✅ Ya | ❌ Tidak (403) | ❌ Tidak (403) | ✅ Ya (Kelas Sendiri) | N/A | ✅ Ya |
| **Siswa** | ✅ Ya | ❌ Tidak (403) | ❌ Tidak (403) | ❌ Tidak (403) | N/A | ✅ Ya |
| **Orang Tua / Wali** | ✅ Ya | ❌ Tidak (403) | ❌ Tidak (403) | ❌ Tidak (403) | ✅ Ya (Anak Sendiri) | ✅ Ya |
| **Staf Sarpras / TU** | ✅ Ya | ❌ Tidak (403) | ❌ Tidak (403) | ❌ Tidak (403) | N/A | ✅ Ya |
| **Anonim / Unauth** | ❌ Tidak (401) | ❌ Tidak (401) | ❌ Tidak (401) | ❌ Tidak (401) | ❌ Tidak (401) | ✅ Ya (Hanya CDN/Static) |

---

## 5. PERBAIKAN PENGUATAN KEAMANAN YANG DILAKUKAN (TANPA PERUBAHAN SKEMA)

Untuk memenuhi seluruh kriteria keamanan pada dokumen audit tanpa memodifikasi skema basis data:
1. **Penguatan `PresignFileRequest.php`:**
   - Menambahkan aturan validasi khusus saat `purpose === 'avatar'`:
     - Tipe MIME wajib berupa `image/jpeg`, `image/png`, atau `image/webp`.
     - Ekstensi berkas wajib berupa `.jpg`, `.jpeg`, `.png`, atau `.webp`.
     - Ukuran berkas (`size_bytes`) dibatasi maksimal 2.097.152 bytes (2 MB).
2. **Penguatan `FileStorageService.php` (`uploadSigned`):**
   - Menambahkan pemeriksaan fisik berkas saat upload langsung: jika `purpose === 'avatar'` dan ukuran konten melebihi 2 MB, request dibatalkan dengan HTTP 422.
3. **Penguatan Aksesibilitas `<Avatar>`:**
   - Memastikan elemen wrapper tidak memiliki atribut `role="img"` yang bentrok saat tag `<img>` aktif, menjaga kepatuhan penuh terhadap standar WAI-ARIA dan pengujian Testing Library.

---

## 6. HASIL UJI REGRESI DAN VERIFIKASI AKTUAL

### A. Backend Security & Storage Tests (`apps/api`)
Perintah eksekusi:
```bash
php artisan test --filter="FileStorageTest"
```
Hasil:
```json
{"tool":"phpunit","result":"passed","tests":16,"passed":16,"assertions":67,"duration_ms":741}
```
**Status: 16/16 LULUS (Termasuk 4 uji keamanan avatar baru)**:
- `test_be_file_01_unauthenticated_request_returns_401` ✅
- `test_be_file_01_user_cannot_presign_dangerous_executable_file` ✅
- `test_be_file_01_user_cannot_presign_oversized_file` ✅
- `test_be_file_01_user_can_presign_valid_file_with_randomized_key` ✅
- `test_be_file_01_end_to_end_upload_confirm_and_audit_logging` ✅
- `test_be_file_01_confirm_fails_if_file_not_uploaded_to_storage` ✅
- `test_be_file_01_confirm_fails_with_invalid_or_expired_token` ✅
- `test_be_file_01_owner_can_get_short_lived_signed_url_for_private_file` ✅
- `test_be_file_01_anti_idor_other_student_cannot_access_private_file_url` ✅
- `test_be_file_01_admin_and_guardian_can_access_authorized_private_file` ✅
- `test_be_file_01_public_file_provides_public_access_url` ✅
- `test_be_file_01_download_signed_verifies_signature_and_streams_file` ✅
- `test_be_photo_sec_avatar_rejects_non_image_mime_or_extension` ✅
- `test_be_photo_sec_avatar_rejects_oversized_file_above_2mb` ✅
- `test_be_photo_sec_avatar_accepts_valid_image_and_completes_upload` ✅
- `test_be_photo_sec_student_cannot_assign_photo_to_student_master` ✅

### B. Backend Master Data & Portal Tests Filter (`apps/api`)
Perintah eksekusi:
```bash
php artisan test --filter="StudentTest|TeacherStaffTest|FileStorageTest|MePortalApiTest|UserControllerTest"
```
Hasil:
```json
{"tool":"phpunit","result":"passed","tests":74,"passed":74,"assertions":399,"duration_ms":1829}
```
**Status: 74/74 LULUS (100%)**

### C. Frontend Typecheck (`apps/web`)
Perintah eksekusi:
```bash
npm run typecheck
```
Hasil:
```
> sistlabum-web@1.0.0 typecheck
> tsc --noEmit
Exit code: 0 (Clean)
```

### D. Frontend Unit & Integration Tests (`apps/web`)
Perintah eksekusi:
```bash
npx vitest run src/test/profile-photo.test.tsx
```
Hasil:
```
Test Files  1 passed (1)
     Tests  10 passed (10)
  Duration  852ms
Exit code: 0
```

### E. Frontend Full Test Suite (`apps/web`)
Perintah eksekusi:
```bash
npm test
```
Hasil:
```
Test Files  66 passed (66)
     Tests  775 passed (775)
  Duration  11.62s
Exit code: 0
```

---

## 7. GAP DAN KEPUTUSAN YANG DIBUTUHKAN DARI PEMILIK PROYEK

Sebelum melanjutkan implementasi ke tahap migrasi database produksi:

1. **Persetujuan Migrasi Tabel `guardians`:**
   - *Dampak:* Menambahkan kolom `photo_file_id UUID NULL` dan foreign key ke `files.id` pada tabel `guardians`.
   - *Kebutuhan:* Memungkinkan orang tua/wali memiliki foto profil individual.

2. **Persetujuan Migrasi Tabel `staff`:**
   - *Dampak:* Menambahkan kolom `photo_file_id UUID NULL` dan foreign key ke `files.id` pada tabel `staff`.
   - *Kebutuhan:* Memungkinkan staf non-guru memiliki pasfoto resmi master.

3. **Keputusan Alur Persetujuan Mandiri Siswa/Guru (Self-Service Verification):**
   - *Pilihan A (Sesuai Implementasi Saat Ini - Rekomendasi):* Foto profil bersifat dokumen identitas resmi yang **hanya dikelola terpusat oleh Bagian Tata Usaha** via Admin Master Data untuk menjaga validitas rapor dan ijazah.
   - *Pilihan B (Alur Pengajuan & Verifikasi):* Pengguna mengunggah foto di portal mandiri dengan status `pending_verification`, kemudian Admin TU meninjau dan menyetujui di antrean approval sebelum foto resmi digantikan. (Memerlukan penambahan tabel/kolom pengajuan foto baru).
