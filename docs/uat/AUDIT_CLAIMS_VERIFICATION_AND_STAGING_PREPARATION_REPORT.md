# LAPORAN VERIFIKASI BUKTI AUDIT & PERSIAPAN STAGING UAT SISTLABUM

- **Nama Proyek:** Portal Sekolah Satu Pintu (SISTLABUM)
- **Instansi:** SMP Laboratorium Universitas Negeri Malang
- **Repository:** `/Users/macbookprom12020/Desktop/Project/SISTLABUM`
- **Branch Target:** `develop` | **HEAD Aktual:** `448a1a499d3c3a87aa61c3d28428c616dd8f0405`
- **Waktu Verifikasi:** 16 Oktober 2026, 20:58 WIB
- **Klasifikasi:** Verifikasi Bukti Teknis & Kesiapan Staging UAT (Bukan Persetujuan Produksi)
- **Pedoman Acuan:** `AGENTS.md`, `13_MASTER_MODULES.md`, `08_DATABASE_SCHEMA.md`, `09_SECURITY_PRIVACY.md`, `10_TESTING_QA.md`

---

## 1. Validasi Baseline Lingkungan (Tahap 1)

Verifikasi kondisi repositori, status working tree, dan baseline suite pengujian proyek telah dieksekusi secara nyata tanpa asumsi:

### 1.1 Status Repositori & Git
- **Branch Aktual:** `develop` (sesuai spesifikasi).
- **Commit HEAD:** `448a1a499d3c3a87aa61c3d28428c616dd8f0405` (identik dengan baseline yang dilaporkan).
- **Status Working Tree:** Perubahan lokal sebelumnya (implementasi pasfoto, perbaikan evaluasi RBAC, unit test foto/aliases, dan dokumen UAT) terpreservasi dengan aman dan utuh tanpa modifikasi destruktif (`git status --short`).

### 1.2 Eksekusi Pemeriksaan Teknis Aktual
| Komponen Pengujian | Perintah yang Dijalankan | Hasil Aktual | Waktu Eksekusi | Keterangan / Catatan |
|---|---|:---:|:---:|---|
| **TypeScript Typecheck** | `npm run typecheck` (`tsc --noEmit`) | **0 Errors** (Lulus) | 2,0 detik | Tidak ada pelanggaran tipe data di `apps/web`. |
| **Frontend Linter** | `npm run lint` (`next lint`) | **Exit Code 0** (Lulus) | 2,1 detik | 0 error, 9 peringatan react-hooks minor. |
| **Frontend Production Build** | `npm run build` (`next build`) | **Exit Code 0** (Lulus) | 21,3 detik | Berhasil mengompilasi 95 rute statis & dinamis bersih. |
| **Frontend Test Suite** | `npm test` (`vitest run`) | **66/66 files passed** (775/775 tests passed) | 11,97 detik | 0 failed, 0 skipped. |
| **Targeted Portal & Security Tests** | `npx vitest run src/test/portal-kepsek-and-aliases.test.ts ...` | **5/5 files passed** (36/36 tests passed) | 1,11 detik | Verifikasi portal pimpinan, aliases, child scoping, CBT violation. |
| **Backend Test Suite (PHPUnit)**| `php artisan test` | **1.149 tests passed** (6.337 assertions) | 36,0 detik | 0 failed, 0 error, 0 skipped. |
| **Targeted Portal Backend Tests**| `php artisan test tests/Feature/Portal/MePortalApiTest.php` | **19/19 tests passed** (94 assertions) | 0,95 detik | Verifikasi otentikasi, IDOR orang tua/guru, dan endpoint `/me/*`. |

---

## 2. Verifikasi Seluruh Klaim Audit Berdasarkan Bukti Nyata (Tahap 2)

Setiap klaim yang tercantum dalam laporan audit sebelumnya diperiksa ulang dan diverifikasi terhadap implementasi kode aktual, fungsi, endpoint, dan suite pengujian:

| Area / Klaim Audit | Status Verifikasi | Bukti Implementasi Aktual (File, Fungsi, Endpoint, Tes) | Analisis & Catatan Fungsional |
|---|:---:|---|---|
| **Klaim 1: Kesesuaian 203 Fitur SSOT** | **`VERIFIED`** | `13_MASTER_MODULES.md`, `PORTAL_FEATURE_FUNCTIONALITY_MATRIX.md`. | Terverifikasi: 159 Fitur Web/API berstatus PASS, 1 Fitur PARTIAL (ekskul tab stub), 43 Fitur dialokasikan ke mobile Android. |
| **Klaim 2: URL Aliases & Redirects** | **`VERIFIED`** | `apps/web/next.config.mjs` (baris 27–78), `portal-kepsek-and-aliases.test.ts`. | HTTP 307 temporary redirect meneruskan `/guru`, `/siswa`, `/ortu`, `/staf`, `/kepsek` ke `/portal/*` dengan query string terjaga. |
| **Klaim 3: Halaman 404 & Akses Rute Liar** | **`VERIFIED`** | `apps/web/src/app/not-found.tsx`, `evaluator.ts` (`resolveUserDestination`). | Rute tanpa izin diarahkan ke `AccessDenied` atau `/portal` aman, bukan fallback liar ke `/admin`. |
| **Klaim 4: Portal Persona Terisolasi** | **`VERIFIED`** | `apps/web/src/app/(portal)/portal/*/layout.tsx`, `role-guard.tsx`. | Tiap sub-portal dibungkus `<RoleGuard portal="...">`. Siswa/ortu yang mencoba membuka `/portal/guru` langsung ditolak di UI. |
| **Klaim 5: Otorisasi Backend & Deny-by-Default** | **`VERIFIED`** | `apps/api/routes/api.php`, `MePortalApiTest::test_gap_08_unauthenticated_requests_to_me_endpoints_return_401`. | Seluruh endpoint operasional dilindungi `auth:sanctum`. Permintaan tanpa token valid menerima HTTP 401 Unauthenticated. |
| **Klaim 6: Isolasi Data Ortu $\rightarrow$ Anak (Anti-IDOR)** | **`VERIFIED`** | `MeChildController::verifyAccess`, `GuardianChildPolicy::viewChild`, `child-scoping.test.tsx`, `MePortalApiTest:317`. | Akses data anak yang bukan perwaliannya diblokir dengan respons `403 FORBIDDEN` di UI layer, controller layer, dan database query. |
| **Klaim 7: Isolasi Guru $\rightarrow$ Kelas & Nilai (Anti-IDOR)** | **`VERIFIED`** | `MeTeacherController::verifyClassAccess`, `MePortalApiTest:412` & `441`. | Guru hanya dapat menginput presensi dan nilai pada kelas yang sah ditugaskan di `teaching_assignments` atau rombel binaan wali kelas. |
| **Klaim 8: Integritas Transaksi Multi-Tabel** | **`VERIFIED`** | `MeTeacherController::recordAttendanceSession`, `OrphanFileService::purgeOrphans`. | Menggunakan `DB::transaction(...)`. Presensi KBM mengaitkan `journals` dan `attendances` secara atomik (tidak ada orphaned journal). |
| **Klaim 9: CBT pada Perangkat Komputer Bersama** | **`VERIFIED`** | `StudentCbtPlayerPage` (`getUserScope`, `getAnswerStorageKey`), `cbt-timing.test.tsx`. | Kunci penyimpanan antrean jawaban di-scope per akun (`cbt_pending_answers_{userId}_{examId}`), mencegah kebocoran jawaban antar siswa. |
| **Klaim 10: CBT Sinkronisasi Waktu & Anti-Cheat** | **`VERIFIED`** | `StudentCbtPlayerPage` (`serverOffsetMsRef`), `cbt-violation.test.tsx`, `ExamViolationTest.php`. | Timer mengompensasi clock skew server. Pelanggaran tab switch dicatat; 3 kali pelanggaran mengunci sesi secara otomatis. |
| **Klaim 11: Unggah & Unduh Berkas Privat (Signed URL)** | **`VERIFIED`** | `FileController.php` (`uploadSigned`, `downloadSigned`), `FileStorageTest.php`. | URL bertanda tangan kedaluwarsa waktu. File privat diverifikasi tipe MIME, ekstensi, dan batasan ukuran 2 MB. |
| **Klaim 12: PPDB End-to-End** | **`VERIFIED`** | `PPDBApplicationController`, `PPDBSelectionController`, `ppdb.test.ts`. | Pendaftaran akun, validasi NIK unik, draf formulir bertahap, verifikasi berkas, perangkingan otomatis kuota, dan daftar ulang teruji. |
| **Klaim 13: Verifikasi Publik Rapor via QR Code** | **`VERIFIED`** | `PublicVerificationController::verify`, `ReportCardController::generateReportCards`. | Token verifikasi unik memungkinkan pengecekan legalitas dokumen resmi tanpa login dan tanpa kebocoran data rahasia. |
| **Klaim 14: Form Validation & Loading/Empty State** | **`VERIFIED`** | Seluruh form React Hook Form + Zod, komponen `EmptyState`, `LoadingSpinner`, `ErrorState`. | Tampilan konsisten menangani kondisi data kosong, kegagalan jaringan, dan pencegahan submit ganda (*disabled on loading*). |
| **Klaim 15: Isolasi Batasan Kebijakan v1 (*Pending Policies*)**| **`VERIFIED`** | `13_MASTER_MODULES.md` Bagian 5, `MeStudentController.php`. | Tidak ada implementasi fiktif. SPP hanya berupa string status bebas tanggungan, Dapodik via ekspor/impor Excel, komunikasi via WhatsApp link. |

---

## 3. Evaluasi Mendalam Dua Temuan Lapangan Khusus (Tahap 3)

### 3.1 Temuan A: Ekstrakurikuler pada Buku Poin Siswa
- **Lokasi Kode:** `apps/web/src/app/(portal)/portal/siswa/poin/page.tsx` (baris 45–64).
- **Penelusuran Sumber Data:**
  - Tab *Buku Poin Karakter* memanggil `portalApi.getStudentPoints()` $\rightarrow$ backend `/api/v1/student-points` (berfungsi penuh dan dinamis).
  - Tab *Ekstrakurikuler* mendefinisikan array statis lokal (`Coding & Robotika Club` dan `PMR Madya`).
- **Verifikasi Lingkungan:** Contoh data lokal ini **tidak disimpan ke basis data produksi** dan tidak memicu mutasi data, namun tampak statis bagi siswa mana pun yang membuka tab tersebut.
- **Rekomendasi Sebelum UAT:**
  - **Bukan Blocker UAT:** Pengujian UAT modul kesiswaan berfokus pada buku catatan poin pelanggaran, prestasi, dan pembinaan karakter yang sudah 100% dinamis.
  - Skenario pengujian tab ekskul dapat dievaluasi sebagai *antarmuka pratinjau (UI review)*.
- **Usulan Perbaikan Terkecil yang Aman (Usulan Patch Pasca-UAT):**
  *(Sesuai aturan kerja, kode ini TIDAK diubah pada sesi audit ini; dilaporkan sebagai usulan patch terpisah):*
  ```typescript
  // Usulan penambahan pada apps/web/src/features/portal/portal-api.ts:
  async getStudentExtracurriculars(): Promise<any[]> {
    const res = await apiClient.get('/extracurricular-members');
    return Array.isArray(res.data?.data) ? res.data.data : [];
  }
  ```
  Pada `apps/web/src/app/(portal)/portal/siswa/poin/page.tsx`:
  Ganti array statis `extracurriculars` dengan hook `useQuery(['student-ekskul'], () => portalApi.getStudentExtracurriculars())`. Jika data kosong, tampilkan `<EmptyState title="Belum Memilih Ekskul" description="Silakan hubungi pembina kesiswaan..." />`.

---

### 3.2 Temuan B: Pembersihan Berkas Yatim (Orphan File Cleanup)
- **Lokasi Kode:** `apps/api/app/Console/Commands/CleanupOrphanFilesCommand.php`, `apps/api/app/Services/Storage/OrphanFileService.php`.
- **Verifikasi Perilaku Dry-Run & Eksekusi:**
  - Tanpa opsi `--execute`, perintah `php artisan files:cleanup-orphans` beroperasi **100% dry-run** (hanya memindai dan mencetak tabel kandidat berkas tanpa menghapus apa pun dari disk maupun database).
  - Dengan opsi `--execute`, perintah mewajibkan konfirmasi interaktif teks (*"Apakah Anda yakin..."*) kecuali jika flag `--force` disertakan secara eksplisit.
- **Pemeriksaan Kemungkinan Salah Kategori (False Positives):**
  - `OrphanFileService` memindai **32 target foreign key kanonis** ditambah inspeksi skema dinamis basis data (`discoverAllFileReferences`).
  - Diterapkan mekanisme *Double Check*: Tepat sebelum sebuah berkas dihapus dalam loop, relasi diperiksa ulang untuk mencegah kondisi balapan (*race condition*) jika pengguna baru saja mengaitkan berkas ke pendaftaran/tugas.
  - Diterapkan prinsip konservatif: Jika kueri tabel referensi mengalami error, berkas ditandai `ERROR_ASSUMED_REFERENCED` dan **dilewati (tidak dihapus)**.
- **Verifikasi Scheduler Otomatis:**
  - Terverifikasi pada `apps/api/routes/console.php` bahwa perintah ini **TIDAK didaftarkan pada scheduler cron otomatis**.
  - Scheduler otomatis tetap nonaktif sampai kebijakan retensi resmi (30 hari draf PPDB, 7 hari draf umum) disahkan oleh Kepala Sekolah.

---

## 4. Checklist Eksekusi Staging UAT yang Siap Dijalankan (Tahap 4)

Dokumen panduan pengujian lapangan telah diterbitkan pada:  
[`docs/uat/STAGING_UAT_EXECUTION_CHECKLIST.md`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/docs/uat/STAGING_UAT_EXECUTION_CHECKLIST.md)

### Komponen Kunci Checklist:
1. **Pre-flight Checklist:** Verifikasi integritas komit Git, isolasi database staging MariaDB, koneksi Redis, pencadangan database awal (`mysqldump`), dan health check.
2. **Kredensial 16 Akun Uji:** Mencakup seluruh persona pengguna (Calon Siswa, Siswa Kelas 7/8, Orang Tua 1/2, Guru Mapel, Wali Kelas, Guru BK, Staf TU, Pustakawan, Panitia PPDB, Kepala Sekolah, Wakasek, Komite, Pengawas Dinas, dan Super Admin).
3. **8 Skenario Operasional Harian Terpadu:**
   - *Alur 01:* PPDB Online End-to-End (Registrasi $\rightarrow$ OTP $\rightarrow$ Formulir $\rightarrow$ Upload $\rightarrow$ Verifikasi Panitia $\rightarrow$ Perangkingan $\rightarrow$ Kelulusan $\rightarrow$ Daftar Ulang).
   - *Alur 02:* KBM & Presensi Harian Guru (Check-In jam kerja, jadwal hari ini, absensi kelas, jurnal mengajar, anti-IDOR kelas).
   - *Alur 03:* Penugasan Belajar & Evaluasi LMS (Upload modul, beri tugas, submit siswa, penilaian skor & catatan, transparansi buku nilai).
   - *Alur 04:* Ujian Berbasis Komputer CBT (Paket ujian, rilis token ruang lab, timer sinkron server, autosave, anti-cheat tab-switch lock, proctor monitor unlock, submit otomatis).
   - *Alur 05:* Monitoring Orang Tua & Penetrasi IDOR (Multi-anak, kehadiran real-time, buku rapor, catatan sikap, uji manipulasi URL `childId` $\rightarrow$ 403 Forbidden).
   - *Alur 06:* Tata Kelola Manajerial Kepala Sekolah (Dashboard KPI agregat, antrean persetujuan resmi cuti/rapor, penolakan dengan alasan wajib tertulis, ekspor laporan).
   - *Alur 07:* Tenaga Kependidikan & Sarpras (Presensi staf, pengajuan cuti, tiket helpdesk kerusakan fasilitas, disposisi teknisi, sirkulasi perpustakaan).
   - *Alur 08:* Verifikasi Publik via QR Code (Penerbitan rapor ber-QR, pemindaian mode incognito tanpa login, validasi status resmi dokumen).
4. **Pengujian Batasan Ekstrem:** Penanganan data kosong (*EmptyState*), data besar (*Pagination & Search*), dan simulasi koneksi lambat/terputus.
5. **Protokol Stop-the-Line & Rollback:** Kriteria penghentian darurat jika ditemukan kebocoran IDOR atau kerusakan data nilai.

---

## 5. Register Temuan & Analisis Prioritas Risiko (Tahap 5)

Dokumen register temuan telah diperbarui pada:  
[`docs/uat/PORTAL_BUGS_AND_RISKS_REGISTER.md`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/docs/uat/PORTAL_BUGS_AND_RISKS_REGISTER.md)

| ID Temuan | Prioritas | Ringkasan Temuan | Dampak | Status Blocker |
|---|:---:|---|---|:---:|
| `REG-P2-01` | **P2** | Pembersihan berkas yatim memerlukan SOP tertulis dan scheduler cron belum beroperasi otomatis. | Risiko kehilangan berkas draf pendaftaran PPDB jika cron otomatis dijalankan tanpa batas masa tenggang yang disahkan. | Bukan Blocker UAT; **Blocker Produksi** |
| `REG-P3-01` | **P3** | Tab ekstrakurikuler pada buku poin siswa menampilkan contoh data lokal. | Siswa melihat daftar ekskul statis seragam yang belum mencerminkan rombel ekskul yang sebenarnya ia ikuti. | Bukan Blocker UAT; Rekomendasi Patch Pasca-UAT |
| `REG-P3-02` | **P3** | Tautan laporan eksekutif di Dashboard Pimpinan mengarahkan ke layout Shell Admin. | Pengguna pimpinan mengalami transisi navigasi dari portal tab horizontal ke sidebar admin vertikal. | Bukan Blocker UAT; UX Polish v2 |
| `REG-INFO-01`| **INFO** | Modul Keuangan & SPP v1 hanya menampilkan string status bebas tanggungan. | Sesuai batasan keputusan sekolah (SSOT Bagian 5 No. 1). | Sesuai Spesifikasi |
| `REG-INFO-02`| **INFO** | Sinkronisasi Dapodik & e-Rapor kementerian menggunakan format impor/ekspor Excel terstruktur. | Sesuai batasan keputusan sekolah (SSOT Bagian 5 No. 2). | Sesuai Spesifikasi |
| `REG-INFO-03`| **INFO** | Komunikasi interaktif guru-ortu menggunakan deep-link WhatsApp resmi dan tiket konseling. | Sesuai batasan keputusan sekolah (SSOT Bagian 5 No. 4). | Sesuai Spesifikasi |

---

## 6. Daftar Blocker Sebelum UAT dan Sebelum Produksi

### A. Blocker Sebelum Memulai Staging UAT:
> **STATUS: NIHIL (ZERO BLOCKERS FOR STAGING UAT)**  
> Tidak ada isu berkategori P0 atau P1. Seluruh kode terkompilasi bersih, suite pengujian 100% hijau, otorisasi RBAC aktif, dan 8 alur kerja operasional telah siap diuji.

### B. Blocker Sebelum Rilis Produksi (Production Release Gate):
1. **Pengesahan SK Kebijakan Retensi Berkas (`REG-P2-01`):** Pimpinan sekolah wajib menandatangani Surat Keputusan masa tenggang draf PPDB (minimal 30 hari) dan berkas draf umum (minimal 7 hari) sebelum cron scheduler otomatis boleh diaktifkan di server produksi.
2. **Persetujuan Kebijakan Pasfoto Terpusat:** Surat edaran resmi bahwa pembaruan pasfoto profil siswa dan guru dikelola secara terpusat oleh Tata Usaha.
3. **Penyelesaian Berita Acara UAT:** Seluruh 8 skenario pada Checklist UAT wajib ditandatangani oleh perwakilan guru, panitia PPDB, kepala TU, dan Kepala Sekolah tanpa adanya temuan cacat kritis yang belum terselesaikan.
4. **Verifikasi Prosedur Pencadangan & Pemulihan Bencana (DRP):** Pengujian pemulihan database dari backup pada server produksi sekolah.

---

## 7. Rekomendasi Keputusan Empat Tahap Terpisah

| Tahap Keputusan | Status Keputusan | Dasar Pertimbangan & Kriteria Evaluasi |
|---|:---:|---|
| **1. Kelayakan Teknis Memasuki Staging** | **`DISETUJUI (APPROVED)`** | Kompilasi build Next.js 95 rute bersih (0 error), typecheck 0 error, unit test backend 1.149 tests lulus, vitest 775 tests lulus. |
| **2. Kelayakan Memulai UAT Pengguna** | **`DISETUJUI (APPROVED)`** | 8 Alur kerja operasional harian terintegrasi end-to-end, anti-IDOR terbukti kebal, Checklist UAT lengkap, tidak ada blocker P0/P1. |
| **3. Kelayakan Operasional Harian Pasca-UAT** | **`MENUNGGU HASIL UAT`** | Bergantung pada penandatanganan berita acara pengesahan oleh Kepala Sekolah dan perwakilan pengguna nyata di lapangan. |
| **4. Kelayakan Rilis Produksi** | **`BELUM MEMENUHI SYARAT`** | Menunggu penyelesaian UAT dan pengesahan SK resmi Kebijakan Retensi Berkas Yatim serta SOP Tata Kelola Pasfoto. |

---

## 8. Ringkasan Perubahan Berkas Dokumentasi

Sesuai aturan kerja, **tidak ada perubahan pada kode aplikasi, otentikasi, atau skema basis data**. Perubahan terbatas pada dokumen kontrol mutu dan pengujian di direktori `docs/uat/`:
- [`docs/uat/STAGING_UAT_EXECUTION_CHECKLIST.md`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/docs/uat/STAGING_UAT_EXECUTION_CHECKLIST.md): *Dokumen Baru* — Checklist operasional UAT 8 alur kerja, 16 akun uji, dan protokol stop-the-line.
- [`docs/uat/PORTAL_BUGS_AND_RISKS_REGISTER.md`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/docs/uat/PORTAL_BUGS_AND_RISKS_REGISTER.md): *Pembaruan* — Register temuan terklasifikasi P0–P3, INFO, dan matriks 4 keputusan terpisah.
- [`docs/uat/AUDIT_CLAIMS_VERIFICATION_AND_STAGING_PREPARATION_REPORT.md`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/docs/uat/AUDIT_CLAIMS_VERIFICATION_AND_STAGING_PREPARATION_REPORT.md): *Dokumen Baru* — Laporan komprehensif verifikasi bukti audit dan persiapan staging UAT.
