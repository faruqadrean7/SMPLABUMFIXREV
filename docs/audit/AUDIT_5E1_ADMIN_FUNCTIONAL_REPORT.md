# Laporan Audit Fungsional Menyeluruh: Tahap 5E-1 (Admin Shell)

**Portal Sekolah Satu Pintu (SISTLABUM Labschool UM)**  
**Tanggal Audit:** 26 Oktober 2026  
**Status:** LULUS (Semua Kontrol, Detektor & Matriks CRUD Berhasil Diverifikasi)  
**Tautan Berkas Inventaris Kontrol:** [`docs/audit/AUDIT_5E1_ADMIN_CONTROLS_INVENTORY.csv`](file:///Users/macbookprom12020/Desktop/SISTLABUM/docs/audit/AUDIT_5E1_ADMIN_CONTROLS_INVENTORY.csv)

---

## 1. Ringkasan Eksekutif

Tahap **5E-1** memverifikasi seluruh komponen fungsional pada Shell Admin (portal administrasi sentral):
1. **Langkah 1 (Inventaris Kontrol):** Mendata seluruh 804 elemen interaktif (tombol aksi, dialog form, link menu sidebar, paginasi, filter, dan tab) ke dalam CSV terstruktur.
2. **Langkah 2 (Detektor Kontrol Mati):** Menjalankan crawler interaktif berbasis Playwright (`17-audit-5e1-admin-controls-detector.spec.ts`) pada 55 halaman admin, memastikan tidak ada tombol mati, tidak ada loop loading tak berujung, dan tidak ada HTTP 5xx.
3. **Langkah 3 (Matriks CRUD & Integritas Referensial):** Menguji operasi C/R/U/D, validasi negatif form, proteksi duplikasi, penolakan penghapusan entitas bereferensi aktif (integritas data), otorisasi 403 civitas, dan pencatatan jejak audit log (`18-audit-5e1-admin-crud-matrix.spec.ts`).

---

## 2. Inventaris Kontrol (Langkah 1)

Dari hasil ekstraksi statis dan inspeksi layout UI shell admin:
- **Total Kontrol Terdata:** 804 kontrol
- **Cakupan Halaman Admin:** 55 halaman (Dashboard, Master Data, KBM & Presensi, Penilaian & E-Rapor, CBT, Kesiswaan & Konseling, Sarana & Prasarana, Humas, CMS, Keuangan, Pengaturan & Audit).
- **Cakupan Dialog Form Modal:** 46 dialog
- **Cakupan Navigasi Sidebar:** 57 tautan menu dan sub-menu
- **Status Integrasi:** 100% kontrol tersambung ke backend API atau mutasi form reaktif (tidak ada mock data sisa).

Detail per halaman dan aksi terdokumentasi lengkap di CSV [`docs/audit/AUDIT_5E1_ADMIN_CONTROLS_INVENTORY.csv`](file:///Users/macbookprom12020/Desktop/SISTLABUM/docs/audit/AUDIT_5E1_ADMIN_CONTROLS_INVENTORY.csv).

---

## 3. Detektor Kontrol Mati (Langkah 2)

Pengujian interaktif otomatis dijalankan via Playwright: `apps/web/e2e/17-audit-5e1-admin-controls-detector.spec.ts`.

### Hasil Eksekusi:
```
  ✓  1. Detektor Kontrol Mati: Navigasi Sidebar & Header Shell Admin (26.6s)
  ✓  2. Detektor Kontrol Mati: Input Pencarian & Filter pada Seluruh Tabel Master & Operasional (20.3s)
  ✓  3. Detektor Kontrol Mati: Tombol Paginasi & Pengatur Limit pada Tabel Admin (17.5s)
  ✓  4. Detektor Kontrol Mati: Pembukaan & Penutupan Dialog Form Modal (25.1s)

  4 passed (1.5m)
```

### Temuan Bug Nyata & Perbaikan:
- **Bug P1:** `StaffAttendanceService::listAttendances` mengalami crash SQLSTATE[42703] (HTTP 500) saat menerima parameter pencarian `?q=...`. Penyebab: klausa query mencari kolom `nip` pada relasi `staff`, sedangkan tabel `staff` tidak memiliki kolom `nip` (hanya `full_name`, `position`, `unit`).
- **Uji Gagal:** Ditambahkan unit test di `apps/api/tests/Feature/Academic/StaffAttendanceTest.php` (`test_be_acd_05_list_staff_attendances_with_search_keyword_works_without_sql_error`).
- **Perbaikan Minimal:** Mengubah pencarian relasi `staff` ke kolom `position` pada `apps/api/app/Services/StaffAttendanceService.php:199`.
- **Hasil Verifikasi:** 18/18 pengujian `StaffAttendanceTest` lulus, dan uji Playwright berjalan mulus tanpa error HTTP 5xx.

---

## 4. Matriks CRUD, Validasi, Izin & Integritas Referensial (Langkah 3)

Pengujian matriks operasi UI end-to-end dijalankan via Playwright: `apps/web/e2e/18-audit-5e1-admin-crud-matrix.spec.ts`.

| Modul | Entitas | Create (C) | Read (R) | Update (U) | Delete (D) | Validasi Form & Duplikasi | Proteksi Integritas Referensial |
|---|---|:---:|:---:|:---:|:---:|:---:|:---:|
| Master | Tahun Ajaran | ✅ UI Form | ✅ Tabel & Filter | ✅ Modal Edit | ✅ Hapus Draf | ✅ Validasi nama wajib & format tahun | ✅ Tahun aktif tidak dapat dihapus (tombol hapus dicegah) |
| Master | Mata Pelajaran | ✅ UI Form | ✅ Pencarian Kode | ✅ Ubah Nama | ✅ Hapus Mapel | ✅ Penolakan kode duplikat (422) | ✅ Mapel dengan tugas ajar terproteksi |
| Master | Rombel & Kelas | ✅ Buat Rombel | ✅ Pencarian Nama | ✅ Form Edit | ✅ Hapus Rombel Kosong | ✅ Validasi tingkat, rombel, kapasitas | ✅ Penolakan penghapusan kelas yang memiliki siswa aktif (HTTP 409 & dialog warning) |
| Keuangan | Pos / Jenis Biaya | ✅ Tambah Biaya | ✅ Filter Kategori | ✅ Edit Tarif | ✅ Konfirmasi Hapus | ✅ Validasi tarif tidak negatif | ✅ Pembatasan pos biaya ber-invoice |
| CMS | Berita & Rilis | ✅ Buat Berita | ✅ Tab Berita | ✅ Editor Konten | ✅ Hapus Berita | ✅ Validasi judul & isi wajib diisi | ✅ Slug unik per berita |

### Hasil Eksekusi Suite:
```
  ✓  1. CRUD Master Data Tahun Ajaran & Proteksi Integritas Referensial (8.1s)
  ✓  2. CRUD Master Mata Pelajaran & Validasi Duplikasi Kode (8.3s)
  ✓  3. CRUD Master Rombel & Kelas serta Penolakan Hapus Kelas Berisi Siswa (7.2s)
  ✓  4. CRUD Keuangan Jenis Biaya & CMS Berita Sekolah (7.7s)
  ✓  5. Verifikasi Otorisasi Penolakan (403) Civitas & Jejak Audit Log Admin (1.0s)

  5 passed (33.7s)
```

### Bukti Keamanan & Audit:
1. **Otorisasi 403 Deny-by-Default:** Token civitas siswa (`siswa_001`) yang mencoba memanggil mutasi admin (`POST /api/v1/academic-years`) ditolak keras dengan status HTTP `403 Forbidden` (`FORBIDDEN`).
2. **Audit Logging:** Setiap mutasi admin (`create`, `update`, `delete`) tercatat secara real-time pada tabel `audit_logs` dengan informasi aktor `admin_demo`, IP address, model yang diubah, serta payload before/after.
3. **Kebersihan Lingkungan:** Seluruh data uji berprefiks `[E2E]` dibersihkan otomatis pada hook `afterAll` (0 baris residu di database).
