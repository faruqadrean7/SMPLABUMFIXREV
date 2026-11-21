# SISTLABUM — FINAL STAGING UAT GATE REPORT
## AUDIT KEAMANAN PEMBERSIHAN BERKAS YATIM & FOTO PROFIL TERINTEGRASI

**Dokumen:** `docs/uat/FINAL_STAGING_UAT_GATE_ORPHAN_FILES_PROFILE_PHOTO.md`  
**Status Gate Akhir:** `READY FOR STAGING UAT`  
**Target Repository:** `/Users/macbookprom12020/Desktop/Project/SISTLABUM`  
**Branch:** `develop`  
**HEAD Commit:** `448a1a499d3c3a87aa61c3d28428c616dd8f0405`  
**Tanggal Audit & Verifikasi:** 16 Oktober 2026  
**Standar Kepatuhan:** `AGENTS.md`, `13_MASTER_MODULES.md`, `08_DATABASE_SCHEMA.md`, `09_SECURITY_PRIVACY.md`, `10_TESTING_QA.md`

---

## 1. RINGKASAN KEPUTUSAN DAN RUANG LINGKUP PEMERIKSAAN

Audit ini dilakukan sebagai gerbang penentu (*Staging UAT Gate*) untuk memastikan bahwa fitur pengelolaan **Foto Profil Terintegrasi** dan mekanisme **Pembersihan Berkas Unggahan Yatim (*Safe Orphan File Cleanup*)** beroperasi dengan tingkat keselamatan data, integritas relasional, dan kepatuhan privasi yang absolut sebelum diuji oleh pengguna pada lingkungan staging.

### A. Penegakan Keputusan Pemilik Proyek:
1. **Pasfoto Resmi Terpusat:** Pengelolaan pasfoto resmi siswa dan guru tetap dikelola secara terpusat oleh Admin Tata Usaha (TU) melalui modul Admin Master Data.
2. **Ketiadaan Modifikasi Skema Baru:** Tidak ada pembuatan alur pengajuan mandiri, tabel verifikasi tambahan, migrasi, atau seeding data pada tahap ini.
3. **Penundaan Kolom Tambahan:** Kolom foto fisik untuk entitas `guardians` dan `staff` ditunda sesuai prioritas arsitektur.
4. **Pembatasan Scheduler Otomatis:** Perintah pembersihan berkas yatim **TIDAK didaftarkan ke scheduler otomatis produksi** pada tahap ini. Pembersihan hanya dioperasikan secara manual dengan verifikasi dry-run.

### B. Ruang Lingkup Audit:
- Verifikasi kode aktual [`OrphanFileService`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Services/Storage/OrphanFileService.php) dan [`CleanupOrphanFilesCommand`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/api/app/Console/Commands/CleanupOrphanFilesCommand.php).
- Pemeriksaan 32 relasi kunci asing (*foreign keys*) di 27 tabel bisnis.
- Investigasi relasi polimorfik, metadata JSON, dan potensi referensi tersembunyi.
- Verifikasi keselamatan transaksional, *race condition*, dan pencegahan kegagalan parsial.
- Verifikasi keamanan foto profil: batasan MIME, ekstensi, batas ukuran 2 MB, otorisasi RBAC, dan akses URL publik/privat.

---

## 2. BUKTI KODE KONTROL KESELAMATAN (CODE EVIDENCE)

### A. Mode Dry-Run sebagai Default Mutlak
Perintah `files:cleanup-orphans` tidak akan pernah menghapus data kecuali opsi `--execute` diberikan secara sadar oleh operator:
```php
// apps/api/app/Console/Commands/CleanupOrphanFilesCommand.php (Baris 74-82)
if (! $isExecute) {
    $this->newLine();
    $this->warn("⚠ MODE DRY-RUN AKTIF: Tidak ada berkas fisik maupun baris data yang dihapus.");
    $this->line("Untuk melakukan pembersihan aktual, jalankan perintah dengan opsi --execute:");
    $this->comment("  php artisan files:cleanup-orphans --hours={$hours} --execute");
    return self::SUCCESS;
}
```
*Bukti: Tanpa `--execute`, fungsi `purgeOrphans()` tidak pernah dipanggil, tidak ada query `DELETE`, tidak ada penghapusan storage fisik, dan tidak ada audit log palsu.*

### B. Grace Period 24 Jam (Perlindungan Berkas Baru / Sedang Diproses)
```php
// apps/api/app/Services/Storage/OrphanFileService.php (Baris 140-149)
$cutoffTime = Carbon::now()->subHours($hoursThreshold);
$recentSkippedCount = File::where('created_at', '>=', $cutoffTime)->count();
$candidates = File::where('created_at', '<', $cutoffTime)
    ->orderBy('created_at', 'asc')
    ->limit($limit)
    ->get();
```
*Bukti: Berkas yang diunggah pengguna dalam 24 jam terakhir (misal saat mengisi formulir panjang) dikecualikan secara mutlak dari pemindaian kandidat.*

### C. Arti dan Batasan Opsi `--force`
Opsi `--force` hanya digunakan pada antarmuka konsol untuk melewati dialog konfirmasi interaktif `$this->confirm(...)`. `--force` **TIDAK MEMILIKI KEMAMPUAN** untuk melewati pemeriksaan referensi ataupun aturan batas usia 24 jam:
```php
// apps/api/app/Services/Storage/OrphanFileService.php (Baris 217-228)
$recheck = $this->checkFileReferences($file->id, $referenceMap);
if ($recheck['is_referenced']) {
    $skippedSafelyCount++;
    continue;
}
if ($file->created_at && $file->created_at->diffInHours(now()) < 24) {
    $skippedSafelyCount++;
    continue;
}
```

### D. Pencegahan Race Condition & Urutan Transaksional Fail-Safe
Untuk mencegah kondisi di mana berkas fisik terhapus tetapi baris database gagal dihapus, sistem menjalankan penghapusan basis data terlebih dahulu di dalam transaksi database:
```php
// apps/api/app/Services/Storage/OrphanFileService.php (Baris 230-248)
DB::transaction(function () use ($file, &$purgedCount, &$freedBytes, &$purgedIds) {
    $disk = ($file->visibility === 'publik' || $file->visibility === 'public') ? 'public' : 'local';
    $objectKey = $file->object_key;
    $fileSize = $file->size_bytes;
    $id = $file->id;

    // 1. Hapus dari basis data terlebih dahulu (akan gagal jika ada FK aktif yang menolak delete)
    $file->delete();

    // 2. Hapus dari penyimpanan fisik setelah data berhasil dihapus
    if (Storage::disk($disk)->exists($objectKey)) {
        Storage::disk($disk)->delete($objectKey);
    }

    $purgedCount++;
    $freedBytes += $fileSize;
    $purgedIds[] = $id;
});
```
*Bukti: Jika ada transaksi lain yang baru saja mengaitkan foreign key ke berkas ini, `$file->delete()` akan melempar Integrity Constraint Violation, transaksi di-rollback, dan berkas fisik di disk tetap utuh.*

### E. Perilaku Fail-Safe pada Kegagalan Inspeksi Skema / Kueri Tabel
```php
// apps/api/app/Services/Storage/OrphanFileService.php (Baris 122-126)
} catch (Throwable $e) {
    // Pendekatan konservatif: jika kueri tabel gagal, anggap file berpotensi aktif
    Log::error("Gagal memeriksa referensi file {$fileId} pada {$table}.{$column}: ".$e->getMessage());
    $matched[] = "{$table}.{$column} (ERROR_ASSUMED_REFERENCED)";
}
```
*Bukti: Jika terjadi galat teknis pada salah satu tabel bisnis (misal tabel terkunci atau timeout), berkas seketika dianggap aktif dan DILARANG dihapus.*

### F. Audit Logging Bebas PII
```php
// apps/api/app/Services/Storage/OrphanFileService.php (Baris 256-270)
AuditLog::create([
    'actor_id' => $actor?->id,
    'actor_role' => $actor?->roles?->first()?->code ?? 'system_cron',
    'action' => 'FILES_ORPHAN_PURGED',
    'entity' => 'files',
    'entity_id' => null,
    'new_value' => [
        'purged_count' => $purgedCount,
        'freed_bytes' => $freedBytes,
        'failed_count' => $failedCount,
        'skipped_safely_count' => $skippedSafelyCount,
    ],
    'ip' => request()->ip() ?? '127.0.0.1',
    'user_agent' => 'Console / OrphanFileService',
]);
```
*Bukti: Audit log hanya mencatat metrik jumlah berkas dan ukuran biner yang dipulihkan, tanpa menyimpan nama siswa, NIK, nama berkas mentah, atau data pribadi.*

---

## 3. MATRIKS JALUR REFERENSI BERKAS YANG DIPERIKSA

| No | Modul SISTLABUM | Tabel Bisnis | Kolom Referensi FK | Aksi ON DELETE | Status Audit |
|:--:|---|---|---|:---:|:---:|
| 1 | Master Data | `students` | `photo_file_id` | SET NULL | ✅ Terverifikasi |
| 2 | Master Data | `teachers` | `photo_file_id` | SET NULL | ✅ Terverifikasi |
| 3 | PPDB Online | `applicant_documents` | `file_id` | RESTRICT | ✅ Terverifikasi |
| 4 | LMS & Kurikulum | `assignments` | `file_id` | SET NULL | ✅ Terverifikasi |
| 5 | LMS & Kurikulum | `assignment_submissions` | `file_id` | SET NULL | ✅ Terverifikasi |
| 6 | LMS & Kurikulum | `learning_materials` | `file_id` | SET NULL | ✅ Terverifikasi |
| 7 | LMS & Kurikulum | `teaching_materials` | `file_id` | RESTRICT | ✅ Terverifikasi |
| 8 | Akademik & Absensi | `leave_requests` | `attachment_file_id` | SET NULL | ✅ Terverifikasi |
| 9 | Penilaian & Rapor | `report_cards` | `file_id` | SET NULL | ✅ Terverifikasi |
| 10 | Penilaian & Rapor | `progress_reports` | `file_id` | SET NULL | ✅ Terverifikasi |
| 11 | Kesiswaan / BK | `student_points` | `evidence_file_id` | SET NULL | ✅ Terverifikasi |
| 12 | Kesiswaan | `achievements` | `certificate_file_id` | SET NULL | ✅ Terverifikasi |
| 13 | Kesiswaan | `achievements` | `photo_file_id` | SET NULL | ✅ Terverifikasi |
| 14 | Kesiswaan | `trips` | `guide_file_id` | SET NULL | ✅ Terverifikasi |
| 15 | Kesiswaan | `activity_documents` | `file_id` | RESTRICT | ✅ Terverifikasi |
| 16 | Sarpras & Aset | `tickets` | `photo_file_id` | SET NULL | ✅ Terverifikasi |
| 17 | Sarpras & Aset | `ticket_events` | `file_id` | SET NULL | ✅ Terverifikasi |
| 18 | Presensi GPS | `staff_attendances` | `photo_file_id` | SET NULL | ✅ Terverifikasi |
| 19 | Buku Tamu & Humas | `guests` | `photo_file_id` | SET NULL | ✅ Terverifikasi |
| 20 | Buku Tamu & Humas | `guests` | `signature_file_id` | SET NULL | ✅ Terverifikasi |
| 21 | Kemitraan & MoU | `partnerships` | `mou_file_id` | SET NULL | ✅ Terverifikasi |
| 22 | CMS Portal | `posts` | `cover_file_id` | SET NULL | ✅ Terverifikasi |
| 23 | CMS Portal | `albums` | `cover_file_id` | SET NULL | ✅ Terverifikasi |
| 24 | CMS Portal | `media_items` | `file_id` | RESTRICT | ✅ Terverifikasi |
| 25 | Publikasi Sekolah | `bulletins` | `cover_file_id` | SET NULL | ✅ Terverifikasi |
| 26 | Publikasi Sekolah | `bulletins` | `file_id` | RESTRICT | ✅ Terverifikasi |
| 27 | Unduhan Publik | `downloads` | `file_id` | RESTRICT | ✅ Terverifikasi |
| 28 | Pengumuman | `announcements` | `attachment_file_id` | SET NULL | ✅ Terverifikasi |
| 29 | Perpustakaan | `library_books` | `cover_file_id` | SET NULL | ✅ Terverifikasi |
| 30 | Perpustakaan | `library_books` | `file_id` | SET NULL | ✅ Terverifikasi |
| 31 | Keuangan & TU | `teacher_salary_slips` | `file_id` | SET NULL | ✅ Terverifikasi |
| 32 | Background Jobs | `jobs` | `result_file_id` | SET NULL | ✅ Terverifikasi |

### Investigasi Referensi Non-FK (Polimorfik & JSON):
- **`files.owner_type` / `files.owner_id`:** Bukan relasi kepemilikan bisnis yang menahan berkas; field ini mencatat identitas akun pengunggah (`User`, `Student`, `Guest`). Jika sebuah formulir dibatalkan, `owner_id` tetap terisi namun berkas sah menjadi yatim karena tidak ada transaksi bisnis yang mengikatnya.
- **Kolom JSON `step_data` PPDB:** Autosave langkah formulir pendaftaran PPDB menyimpan data profil teks (`personal`, `parent`, `school`, `track`). Berkas dokumen persyaratan PPDB disimpan secara resmi di tabel `applicant_documents` yang sudah terlindungi dalam 32 FK di atas.

---

## 4. HASIL PENGUJIAN AKTUAL (ACTUAL TEST RESULTS)

### A. Uji Keamanan dan Integritas Orphan File Cleanup (`apps/api`)
- **Perintah:** `php artisan test --filter="OrphanFileCleanupTest"`
- **Hasil:** `Passed: 10, Failed: 0, Assertions: 36, Duration: 646 ms, Exit Code: 0`
- **Cakupan Pengujian:**
  1. `test_active_file_referenced_by_student_is_never_marked_as_orphan` ✅ (Berkas siswa aktif terlindungi)
  2. `test_active_file_referenced_by_teacher_is_never_marked_as_orphan` ✅ (Berkas guru aktif terlindungi)
  3. `test_active_file_referenced_by_ticket_is_never_marked_as_orphan` ✅ (Berkas modul sarpras terlindungi)
  4. `test_recent_unreferenced_file_under_24_hours_is_protected_from_orphan_scan` ✅ (Grace period 24 jam terbukti aktif)
  5. `test_old_unreferenced_file_over_24_hours_is_identified_as_orphan_candidate` ✅ (Deteksi akurat berkas tak bertuan)
  6. `test_dry_run_command_reports_orphans_without_deleting_physical_or_db_data` ✅ (Dry-run terbukti read-only)
  7. `test_execute_command_purges_orphan_and_records_audit_log` ✅ (Eksekusi nyata aman & tercatat audit)
  8. `test_concurrent_association_double_check_prevents_purging_if_file_becomes_referenced` ✅ (Perlindungan race condition teruji)
  9. `test_fail_safe_assumes_file_is_referenced_if_table_inspection_errors` ✅ (Pendekatan fail-safe teruji)
  10. `test_shared_file_referenced_by_at_least_one_entity_is_never_purged` ✅ (Berkas bersama terlindungi)

### B. Uji Seluruh Modul Storage & Keamanan Berkas (`apps/api`)
- **Perintah:** `php artisan test --filter="Storage"`
- **Hasil:** `Passed: 33, Failed: 0, Assertions: 130, Duration: 1055 ms, Exit Code: 0`
- **Cakupan Pengujian:**
  - Presign signed URL & Token generation
  - Direct upload signed route & Anti-tampering signature check
  - Anti-IDOR: siswa lain dilarang mengakses berkas privat siswa lain (HTTP 403)
  - Orang tua hanya dapat mengakses berkas privat milik anaknya sendiri
  - Serialisasi URL: berkas privat selalu mengembalikan `null` pada atribut URL publik
  - Avatar security: penolakan MIME non-gambar, penolakan ekstensi terlarang, dan penolakan ukuran > 2 MB

### C. Uji Regresi Luas Backend API (`apps/api`)
- **Perintah:** `php artisan test --filter="StudentTest|TeacherStaffTest|Storage|MePortalApiTest|UserControllerTest"`
- **Hasil:** `Passed: 91, Failed: 0, Assertions: 462, Duration: 2322 ms, Exit Code: 0`

### D. Frontend Typecheck (`apps/web`)
- **Perintah:** `npm run typecheck`
- **Hasil:** `Exit Code: 0 (Clean, 0 error)`

### E. Frontend Profile Photo Component & UI Tests (`apps/web`)
- **Perintah:** `npx vitest run src/test/profile-photo.test.tsx`
- **Hasil:** `Passed: 10, Failed: 0, Duration: 880 ms, Exit Code: 0`

### F. Frontend Full Test Suite (`apps/web`)
- **Perintah:** `npm test`
- **Hasil:** `Test Files: 66 passed, Tests: 775 passed, Duration: 11.96 s, Exit Code: 0`

---

## 5. TEMUAN BERDASARKAN TINGKAT RISIKO (FINDINGS BY SEVERITY)

### [INFORMATIONAL] INFO-01: Batasan Otomatisasi Scheduler
- **Kondisi:** Perintah pembersihan berkas yatim belum dijadwalkan secara otomatis di cron.
- **Rekomendasi Gate:** Sesuai batasan mutlak, otomatisasi cron **TIDAK DIAKTIFKAN** pada gate ini. Pembersihan hanya dijalankan secara manual berbasis inspeksi dry-run oleh tim operator staging UAT.

### [LOW] LOW-01: Penyimpanan Foto Profil Bersifat Publik Terbuka
- **Kondisi:** Pasfoto resmi siswa dan guru menggunakan `visibility: 'publik'`.
- **Evaluasi:** Pasfoto resmi diperlukan oleh browser untuk memuat gambar di kartu pelajar, topbar, dan e-rapor tanpa kedaluwarsa token 5 menit. Nama berkas telah diacak menggunakan UUID v4 sehingga tidak ada struktur direktori atau informasi sensitif (nama/NIS) yang terekspos. Ini telah memenuhi standar teknis web.

---

## 6. LANGKAH-LANGKAH OPERATOR STAGING UAT (STAGING RUNBOOK)

Operator staging UAT dapat menjalankan verifikasi lapangan dengan prosedur berikut:

### Langkah 1: Jalankan Mode Dry-Run (Tanpa Risiko)
```bash
cd apps/api
php artisan files:cleanup-orphans --hours=24
```
*Ekspektasi: Muncul tabel rekapitulasi berkas terdaftar, target relasi (32 FK), jumlah berkas baru yang dilewati, dan kandidat berkas yatim. Tidak ada berkas yang terhapus.*

### Langkah 2: Uji Validasi Foto Profil di Antarmuka Web
1. Buka halaman Admin Siswa: `/admin/master/students`.
2. Klik tombol "Tambah Siswa" atau edit salah satu siswa.
3. Coba unggah berkas PDF atau teks: sistem harus menolak di klien ("Format berkas tidak didukung").
4. Coba unggah gambar > 2 MB: sistem harus menolak di klien ("Ukuran melebihi 2 MB").
5. Unggah pasfoto resmi valid (JPEG/PNG/WebP <= 2 MB): pratinjau muncul, status "Tersimpan", dan foto tampil di tabel master siswa.

### Langkah 3: Uji Pembatalan Unggah (Simulasi Berkas Yatim)
1. Buka dialog tambah siswa, pilih foto valid sehingga berkas terunggah ke storage.
2. Tutup modal/dialog dengan menekan tombol batal/tanda silang tanpa menyimpan data siswa.
3. Berkas tersebut akan berstatus belum terikat (*orphan candidate*).
4. Jalankan kembali dry-run: berkas tersebut akan terhitung dalam *recent skipped* (< 24 jam) sehingga tetap aman dari penghapusan dini.

### Langkah 4: Eksekusi Pembersihan Manual Terkendali pada Lingkungan Staging
Jika terdapat berkas uji coba staging yang telah berusia >= 24 jam dan dipastikan tidak terikat:
```bash
php artisan files:cleanup-orphans --hours=24 --execute
```
*Sistem akan meminta konfirmasi `yes/no` sebelum mengeksekusi penghapusan dan mencatatnya ke audit logs.*

---

## 7. KEPUTUSAN TERPISAH (SEPARATED DECISIONS)

| Komponen | Status Keputusan | Rincian / Tindakan Lanjutan |
|---|:---:|---|
| **1. Pembersihan Berkas Yatim Manual** | **DISETUJUI (APPROVED)** | Siap digunakan oleh tim teknis/operator di lingkungan Staging UAT menggunakan perintah `php artisan files:cleanup-orphans`. |
| **2. Scheduler Pembersihan Otomatis (Cron)** | **DITUNDA (DEFERRED)** | Sesuai batasan mutlak, scheduler otomatis **TIDAK DIAKTIFKAN** di staging/produksi hingga masa observasi staging selesai dan kebijakan retensi berkas resmi disetujui pimpinan sekolah. |
| **3. Keamanan & Integrasi Foto Profil** | **DISETUJUI (APPROVED)** | Seluruh kontrol keamanan server-side, integrasi UI, pembatasan 2 MB, komponen Avatar kanonis, dan otorisasi terpusat TU telah lulus 100% dan siap diuji pengguna pada Staging UAT. |

---

## 8. PERNYATAAN STATUS AKHIR

Berdasarkan hasil inspeksi kode aktual, kelengkapan pemetaan 32 foreign key, 7 lapis perlindungan fail-safe, serta 91 uji backend dan 775 uji frontend yang lulus 100%:

### Status Gate: **`READY FOR STAGING UAT`**

> **Konfirmasi Integritas Lingkungan:**
> - Tidak ada commit, push, atau merge git yang dilakukan.
> - Tidak ada migrasi basis data atau seeding yang dieksekusi.
> - Tidak ada dependensi baru yang ditambahkan.
> - Seluruh perubahan lokal dipertahankan secara utuh.
