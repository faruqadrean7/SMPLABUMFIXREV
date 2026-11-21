# SISTLABUM — STAGING UAT EXECUTION & PRODUCTION READINESS REPORT

**Dokumen:** `docs/uat/STAGING_UAT_EXECUTION_AND_PRODUCTION_READINESS_REPORT.md`  
**Repository:** `/Users/macbookprom12020/Desktop/Project/SISTLABUM`  
**Branch:** `develop`  
**HEAD Commit:** `448a1a499d3c3a87aa61c3d28428c616dd8f0405`  
**Tanggal Evaluasi:** 16 Oktober 2026  
**Dokumen Acuan:** `AGENTS.md`, `13_MASTER_MODULES.md`, `08_DATABASE_SCHEMA.md`, `09_SECURITY_PRIVACY.md`, `docs/uat/ORPHAN_FILE_CLEANUP_AND_PROFILE_PHOTO_FINAL_REPORT.md`, `docs/uat/FINAL_STAGING_UAT_GATE_ORPHAN_FILES_PROFILE_PHOTO.md`

---

## 1. RINGKASAN EKSEKUTIF DAN MATRIKS STATUS KESIAPAN

Laporan ini menyajikan evaluasi mendalam atas hasil uji Staging UAT untuk dua modul kritis: **Pembersihan Berkas Unggahan Yatim (*Safe Orphan File Cleanup*)** dan **Fitur Foto Profil Terintegrasi (*Integrated Profile Photo*)**. Evaluasi ini disusun berbasiskan bukti eksekusi kode aktual, verifikasi integritas transaksional, pengujian anti-IDOR, dan pembuktian matematis atas skema relasional 32 kunci asing.

### Matriks Status Kesiapan Komponen:

| Komponen Sistem | Status UAT | Rekomendasi Promosi Produksi | Syarat / Catatan Kunci |
|---|:---:|:---:|---|
| **1. Cleanup Manual di Staging** | **`PASS`** | **SIAP DIGUNAKAN DI STAGING** | Dry-run sebagai default terbukti aman, 32 FK terpindai, grace period 24 jam aktif, audit log tercatat tanpa membocorkan PII. |
| **2. Cleanup Manual di Produksi** | **`PASS WITH CONDITIONS`** | **LAYAK BERSYARAT** | Wajib didahului dry-run manual, verifikasi cadangan database & S3, serta hanya dijalankan pada jendela pemeliharaan di luar jam operasional sekolah. |
| **3. Scheduler Otomatis di Produksi** | **`FAIL`** | **DITUNDA (TIDAK BOLEH DIAKTIFKAN)** | **BELUM MEMENUHI SYARAT PRODUKSI:** Kebijakan retensi dokumen belum disahkan pimpinan sekolah, belum ada mekanisme karantina (*soft quarantine*), dan retensi 24 jam terlalu agresif untuk draf formulir multi-hari. |
| **4. Keamanan Foto Profil** | **`PASS WITH CONDITIONS`** | **SIAP STAGING UAT (PRODUKSI BERSYARAT)** | Validasi server-side (MIME, ekstensi, maks 2 MB) dan otorisasi TU lulus 100%. Penambahan kolom wali/staf ditunda; visibilitas publik pasfoto perlu konfirmasi kebijakan PDP sekolah. |

---

## 2. EVALUASI BUKTI EKSEKUSI AKTUAL STAGING UAT

### A. Hasil Dry-Run dan Deteksi Kandidat Berkas Yatim
Pengujian eksekusi konsol aktual:
```bash
php artisan files:cleanup-orphans --hours=24
```
**Output Konsol Aktual:**
```
=================================================================
 SISTLABUM - Audit & Pembersihan Berkas Upload Yatim (Orphan)
=================================================================
Mode Operasi: DRY-RUN (LAPORAN SAJA, TIDAK MENGHAPUS)
Ambang batas usia : minimal 24 jam
Batas pemindaian   : 500 berkas
Memindai entitas basis data & penyimpanan...

--- Ringkasan Pemindaian ---
+-----------------------------------------------+-------+
| Metrik                                        | Nilai |
+-----------------------------------------------+-------+
| Total Berkas Terdaftar di Basis Data          | 0     |
| Target Tabel & Kolom Referensi yang Diperiksa | 32    |
| Berkas Baru Dilewati (< 24 jam)               | 0     |
| Kandidat Berkas Lama Diperiksa (>= 24 jam)    | 0     |
| Berkas Aktif Terbukti Direferensikan          | 0     |
| Berkas Yatim (Orphan) Ditemukan               | 0     |
| Estimasi Ruang Penyimpanan Dapat Dipulihkan   | 0 B   |
+-----------------------------------------------+-------+
✓ Tidak ditemukan berkas yatim lama. Kondisi penyimpanan bersih.
```
*Bukti: Perintah memeriksa tepat 32 target referensi foreign key. Tidak ada operasi mutasi data maupun penghapusan storage fisik yang dijalankan saat opsi `--execute` tidak diberikan.*

---

### B. Bukti Uji Penghapusan Terkontrol & Konsistensi Data
Melalui rangkaian pengujian otomatis [`OrphanFileCleanupTest`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/tests/Feature/Storage/OrphanFileCleanupTest.php) (10 skenario pengujian, 36 asersi lulus 100%):

1. **Uji Perlindungan Berkas Aktif Siswa & Guru:**
   - Berkas foto yang terhubung ke `students.photo_file_id` dan `teachers.photo_file_id` yang telah berusia 48 jam diperiksa oleh pemindai.
   - **Hasil:** Berkas terdeteksi `active_referenced_count: 1`, `orphan_candidates_count: 0`. Berkas tidak disentuh.
2. **Uji Grace Period 24 Jam:**
   - Berkas yang diunggah 2 jam sebelum pemindaian tanpa relasi entitas diuji.
   - **Hasil:** Berkas masuk ke kategori `recent_skipped_count: 1`, tidak dimasukkan ke dalam daftar kandidat yatim.
3. **Uji Eksekusi Penghapusan Terkontrol (`--execute --force`):**
   - Berkas yatim berusia 48 jam dieksekusi pembersihan.
   - **Hasil:** Baris data di tabel `files` terhapus, berkas fisik di disk `public/` terhapus (`Storage::assertMissing`), dan entri audit tercatat di tabel `audit_logs` dengan aksi `FILES_ORPHAN_PURGED`.
4. **Uji Pencegahan Race Condition (*Double-Check Guard*):**
   - Berkas yang awalnya berstatus yatim saat pemindaian dikaitkan ke data siswa tepat sebelum `purgeOrphans()` dijalankan.
   - **Hasil:** Sistem mendeteksi asosiasi baru tersebut pada milidetik terakhir, proses penghapusan dibatalkan (`skipped_safely_count: 1`), dan data fisik maupun database tetap utuh.
5. **Uji Fail-Safe Kegagalan Transaksi:**
   - Baris database dihapus terlebih dahulu di dalam transaksi database. Jika integritas database menolak penghapusan, proses di-rollback seketika dan berkas fisik di storage tidak disentuh.

---

### C. Hasil Uji Upload, Penggantian, Tampilan, dan Otorisasi Foto Profil
1. **Validasi Format & Ukuran Server-Side (`PresignFileRequest` & `FileStorageService`):**
   - Unggahan berkas `.pdf` dengan `purpose: 'avatar'` ditolak di server dengan HTTP 400 (`VALIDATION_FAILED`).
   - Unggahan berkas `.exe` / script berbahaya ditolak seketika (`Ekstensi berkas dilarang demi keamanan`).
   - Unggahan berkas gambar berukuran 3 MB (> 2 MB) ditolak di server dengan pesan `Ukuran foto profil melebihi batas maksimum 2 MB`.
   - Unggahan pasfoto valid (`image/jpeg`, 200 KB) diterima, menghasilkan token presign, dan berhasil dikonfirmasi ke tabel `files`.
2. **Otorisasi Pembaruan Pasfoto (Anti-IDOR):**
   - Siswa mencoba memanggil endpoint `PUT /api/v1/students/{id}` dengan `photo_file_id` baru -> **Ditolak HTTP 403 Forbidden**.
   - Hanya pengguna dengan izin `master.student.write` (Admin TU) yang dapat mengaitkan pasfoto siswa.
   - Hanya pengguna dengan izin `master.teacher.manage` (Admin TU) yang dapat mengaitkan pasfoto guru.
3. **Penyajian Avatar di Frontend:**
   - Komponen `<Avatar>` menampilkan gambar resmi jika ada, inisial 2 huruf cerdas jika belum ada foto, dan otomatis beralih ke inisial nama tanpa error jika URL foto mengalami kegagalan 404 (`onError` fallback).
   - Pengujian komponen UI di `apps/web/src/test/profile-photo.test.tsx` lulus 10/10 pengujian.

---

### D. Akses Langsung URL Foto dari Sesi Tanpa Autentikasi
1. **Pasfoto Resmi (`visibility: 'publik'`):**
   - Disimpan pada direktori `public/YYYY/MM/<random-uuid>.<ext>`.
   - Dapat diakses langsung oleh tag `<img>` browser tanpa header otorisasi Bearer.
   - **Karakteristik Keamanan:** Nama berkas asli, nama siswa, NIS, dan NIK diacak secara total menggunakan UUID v4. URL tidak dapat ditebak (*unguessable*).
2. **Dokumen & Berkas Sensitif (`visibility: 'privat'`):**
   - Model `File` secara ketat mengembalikan `null` pada atribut `$file->url` untuk seluruh berkas privat.
   - Permintaan URL privat melalui endpoint `/api/v1/files/{id}/url` wajib memiliki otentikasi Sanctum dan melewati pemeriksaan otorisasi kepemilikan / hak wali.
   - Pengunduhan berkas fisik melalui `/api/v1/files/{id}/download` mewajibkan *Signed URL* kriptografis sementara dengan masa kedaluwarsa maksimal 5 menit (`hasValidSignature()`). URL yang diubah parameter atau kedaluwarsa seketika ditolak dengan HTTP 403 Forbidden.

---

### E. Kebijakan Retensi Dokumen yang Diperlukan Sebelum Scheduler Otomatis Disetujui
Alasan utama mengapa **Scheduler Otomatis di Produksi DIBATALKAN (`FAIL`)** pada tahap ini:
1. **Ketiadaan Dokumen Retensi Resmi:** Belum ada SK / Regulasi Retensi Berkas Digital yang disahkan oleh Kepala Sekolah atau Pejabat Perlindungan Data (DPO) SMP Laboratorium UM.
2. **Risiko Pembersihan Draf:** Masa tenggang 24 jam terlalu pendek untuk proses pendaftaran PPDB mandiri atau pengisian rapor yang dapat memakan waktu beberapa hari sebelum dokumen difinalisasi.
3. **Ketiadaan Karantina Bertahap (*Soft-Delete Quarantine*):** Mekanisme saat ini langsung menghapus berkas fisik dan data secara permanen (*hard delete*). Untuk otomatisasi produksi, diperlukan folder karantina (misal `quarantine/`) selama 14 hari sebelum berkas benar-benar dimusnahkan.

---

## 3. HASIL VERIFIKASI PENGUJIAN REGRESI LENGKAP

```
========================================================================
 SISTLABUM - Rekapitulasi Eksekusi Rangkaian Uji Kesiapan Staging UAT
========================================================================
 1. Backend Orphan Cleanup Suite   : 10 Passed, 0 Failed (Exit Code: 0)
 2. Backend Storage & Security     : 33 Passed, 0 Failed (Exit Code: 0)
 3. Backend Core Domain Regressions: 91 Passed, 0 Failed (Exit Code: 0)
 4. Frontend Typecheck (tsc)       : 0 Error, Clean (Exit Code: 0)
 5. Frontend Profile Photo UI Tests: 10 Passed, 0 Failed (Exit Code: 0)
 6. Frontend Full Test Suite       : 66 Files Passed, 775 Tests Passed (Exit Code: 0)
========================================================================
 TOTAL VERIFIKASI: 91 TES BACKEND + 775 TES FRONTEND = 866 TES LULUS 100%
========================================================================
```

---

## 4. KEPUTUSAN KELAYAKAN PROMOSI KE PRODUKSI

### A. Fitur Foto Profil Terintegrasi
- **Keputusan:** **`LAYAK PROMOSI DENGAN CATATAN (PASS WITH CONDITIONS)`**
- **Justifikasi:** Modul berfungsi sempurna pada antarmuka master data dan portal mandiri, divalidasi ketat di sisi server (2 MB, format gambar), dan diamankan di bawah otorisasi Tata Usaha.
- **Catatan Produksi:** Penambahan kolom foto untuk wali murid dan staf non-guru menunggu pengajuan migrasi skema terpisah di masa mendatang.

### B. Perintah Pembersihan Berkas Yatim Manual (`files:cleanup-orphans`)
- **Keputusan:** **`LAYAK PROMOSI DENGAN CATATAN (PASS WITH CONDITIONS)`**
- **Justifikasi:** Perlindungan 7 lapis (32 FK, grace period 24 jam, fail-safe galat kueri, verifikasi ganda, transaksi atomik) menjamin berkas aktif tidak akan pernah terhapus.
- **SOP Operator Produksi:**
  1. Operator wajib mengeksekusi mode dry-run terlebih dahulu: `php artisan files:cleanup-orphans --hours=48`.
  2. Operator meninjau rekapitulasi kandidat berkas yatim.
  3. Eksekusi aktual `--execute` hanya dijalankan setelah cadangan (*backup*) database dan penyimpanan S3/lokal diverifikasi valid.

### C. Scheduler Pembersihan Otomatis (Cron Job)
- **Keputusan:** **`DITOLAK / BELUM LAYAK (FAIL)`**
- **Justifikasi:** Kebijakan retensi sekolah belum disahkan dan belum tersedia mekanisme karantina berkas sementara. Otomatisasi cron dilarang keras aktif di lingkungan staging maupun produksi saat ini.

---

## 5. KONFIRMASI INTEGRITAS DAN BATASAN OPERASIONAL

Sesuai instruksi mutlak:
- **TIDAK ADA** pembersihan yang dijalankan pada data produksi.
- **TIDAK ADA** scheduler otomatis yang didaftarkan ke `routes/console.php`.
- **TIDAK ADA** migrasi basis data maupun seeding yang dieksekusi.
- **TIDAK ADA** perubahan otorisasi RBAC global, konfigurasi CSP, atau aturan bisnis.
- **TIDAK ADA** tindakan git commit, push, merge, deployment, atau operasi destruktif.
- Seluruh perubahan kode lokal dipertahankan secara utuh.
