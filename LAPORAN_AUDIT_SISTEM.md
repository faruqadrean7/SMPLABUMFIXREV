# Laporan Audit Temuan Sistem — SISTLABUM (Portal Sekolah Satu Pintu)

- **Repositori**: `SISTLABUM` — SMP Laboratorium Universitas Negeri Malang
- **Tanggal audit**: 27 Oktober 2026
- **Cakupan**: Backend Laravel (`apps/api`), Frontend Next.js (`apps/web`), Skema Database (migrasi), dokumen perencanaan.
- **Metode**: Analisis statik kode (rute, controller, service, migrasi) + penyisiran pola pada frontend. Audit ini melengkapi `FINAL_SECURITY_GATE_REPORT.md` (12 Okt 2026) dan menguji ulang klaimnya terhadap kode terkini (migrasi s/d 26 Okt 2026).

> **Catatan kejujuran cakupan**: audit ini **belum 100% menyeluruh**. Sebagian halaman frontend terdalam (mis. `admin/academic/grades`, `admin/cbt/exams`, `admin/finance/invoices`, `portal/siswa/cbt`, `portal/guru/nilai`) **tidak dapat ditarik** lewat jembatan file karena lebih dari 7 folder di bawah folder terhubung, dan shell di komputer masih menyiapkan lingkungan saat audit ditulis. Temuan frontend didasarkan pada ±12 halaman besar yang berhasil disisir. Rekomendasi penuntasan ada di bagian akhir.

---

## 1. Ringkasan Eksekutif

Kualitas rekayasa sistem ini **tinggi**: arsitektur modular, UUID PK, audit log, enkripsi PII, suite test besar (klaim 827 lulus), dan skema database yang rapi. Namun audit menemukan **satu kelemahan arsitektural keamanan yang sistemik** dan sejumlah masalah kualitas/konsistensi. Tidak ditemukan tombol "mati" (dead button) pada halaman yang disisir, tetapi ada masalah **konsistensi UX** yang nyata.

| Severity | Jumlah | Area |
|---|:--:|---|
| 🔴 Tinggi | 1 | Otorisasi (deny-by-default tidak ditegakkan di layer rute) |
| 🟠 Sedang | 4 | Error-handling, god-object, telemetry audit, UX dialog |
| 🟡 Rendah | 5 | Duplikasi rute, IDOR self-service (perlu verifikasi), kolom cache, migrasi finance |
| 🔵 Kebijakan | 1 | Modul Finance dibangun padahal "Menunggu Keputusan" |
| ✅ Baik | — | Skema DB, money-as-integer, FK onDelete, scoping whistleblowing |

---

## 2. Temuan Keamanan & Otorisasi

### 🔴 SEC-01 (TINGGI) — Deny-by-default tidak ditegakkan di layer rute; otorisasi bergantung sepenuhnya pada service
**Lokasi**: `apps/api/routes/api.php`, grup besar `Route::middleware('auth:sanctum')->group(...)` baris **693–1457**.

Ratusan endpoint yang **mengubah state** berada di dalam satu grup `auth:sanctum` **tanpa** middleware `permission:` per-rute. Otorisasi didelegasikan sepenuhnya ke layer service (service melempar `HttpException(403)`). Contoh endpoint sensitif tanpa guard rute:

- `GET/PATCH /complaints`, `/complaints/{id}/status` (aspirasi & whistleblowing) — baris 758–760
- `POST /notifications/broadcast` (broadcast massal) — baris 1357
- `POST /notification-templates/send`, `GET /notification-logs` (berisi PII pesan terkirim) — baris 1368–1372
- `GET /dashboard/executive`, `GET /reports/{type}` & `/export` — baris 1396–1402
- `POST/PUT/DELETE /point-types`, `/point-thresholds` (konfigurasi sanksi) — baris 1088–1099
- CRUD `/extracurriculars`, `/competitions`, `/achievements`, `/trips` — baris 1114–1179
- `/report-cards/generate|approve|lock`, `/progress-reports/.../approve`, `/grade-periods/{id}/lock` (penerbitan & penguncian rapor) — baris 915–944
- `/exams/{id}/results`, `/exams/{id}/item-analysis`, `/exam-sessions/{id}/manage`, `/exams/{id}/monitor` — baris 1048–1068
- Modul BK sensitif: `/counseling/cases`, `/early-warnings` — baris 1185–1206

**Status verifikasi**: pola otorisasi-di-service **terbukti ada dan benar** pada contoh yang diperiksa — `ComplaintService::verifyComplaintAccess()` membatasi akses ke `super_admin`/`principal`/`counselor` dan menyembunyikan identitas pelapor (`ComplaintService.php:289–328`); `ReportCardService` & `StudentService` juga melempar 403. Jadi ini **bukan lubang yang pasti ter-eksploitasi**, melainkan **risiko arsitektural**:

1. **Tidak ada jaring pengaman (defense-in-depth).** Dengan ~75 service, cukup **satu** metode lupa memanggil pengecekan → eskalasi hak akses diam-diam, tanpa lapisan kedua yang menangkapnya.
2. **Melanggar AGENTS.md §6.3**: *"Setiap endpoint deny-by-default: tanpa guard permission = tidak boleh dirilis."*
3. **Tidak konsisten**: modul **Finance** (baris 1423–1456) dan **Master/CMS/PPDB** justru memakai `permission:` di rute. Jadi standar keamanan berbeda antar-modul dalam satu basis kode.

**Rekomendasi**: tambahkan `permission:<...>` pada **setiap** rute manajemen (defense-in-depth, service tetap mengecek). Prioritaskan verifikasi guard-service pada daftar endpoint berisiko tinggi di atas (broadcast, executive dashboard, reports, BK, approve/lock rapor) karena di situ dampak kebocoran paling besar.

### 🟠 SEC-02 (SEDANG) — Endpoint telemetry audit terbuka untuk semua user terautentikasi
**Lokasi**: `routes/api.php:333–335` → `POST /audit-logs` (`AuditLogController::storeTelemetry`) hanya di bawah `auth:sanctum`.
Pengguna mana pun yang login dapat menulis entri ke jalur audit. Berpotensi mem-"banjiri" atau mengotori audit trail (yang menurut KEPUTUSAN §10 harus tepercaya). **Rekomendasi**: batasi field yang boleh ditulis, tandai `source=client`, beri rate-limit, dan pisahkan dari `audit_logs` resmi bila perlu.

### 🟡 SEC-03 (RENDAH, perlu verifikasi) — IDOR pada endpoint self-service berbasis ID
**Lokasi**: `routes/api.php` — `GET /salary-slips/{slipId}/download` (196–197), `GET /me/payments/{id}/receipt` (230), `GET /me/children/{child}/*` (203–212).
Keamanannya bergantung pada pengecekan kepemilikan di controller/service. `FINAL_SECURITY_GATE_REPORT` mengklaim IDOR kartu peserta CBT & dashboard wali sudah ditutup, tetapi **unduh slip gaji berdasarkan `slipId`** dan **struk pembayaran berdasarkan `id`** belum terkonfirmasi dalam audit ini. **Rekomendasi**: pastikan setiap handler memverifikasi `slip.teacher_id == user.teacher.id` dan `payment.invoice.student ∈ user.children`.

### 🔵 SEC-04 (VERIFIKASI) — Berkas `.env`
Terdapat `.env` di root dan di `apps/api` (ukuran identik dengan `.env.example`). `.gitignore` ada. **Rekomendasi**: pastikan `.env` **tidak** ter-track git (`git ls-files | grep .env`) dan tidak memuat rahasia nyata (AGENTS.md §6.1).

---

## 3. Temuan Logika & Kualitas Backend

### 🟠 BE-01 (SEDANG) — Error-handling menyamarkan 401/403/422 menjadi 500
**Lokasi contoh**: `StudentController::index` & `export` (`StudentController.php:31–57`) hanya `catch (Throwable)` → selalu mengembalikan **HTTP 500**.
Bila service melempar `HttpException(403/422)`, error itu tertangkap blok `Throwable` dan dikembalikan sebagai 500 generik ("Gagal mengambil daftar siswa"), bukan kode yang benar. Ini merusak kontrak API dan menyembunyikan kegagalan otorisasi. Bandingkan dengan `ComplaintController`/`ReportCardController` yang memetakan `HttpException` dengan benar. **Rekomendasi**: seragamkan — tangkap `HttpException` lebih dulu lalu `Throwable`, atau pasang exception handler global.

### 🟠 BE-02 (SEDANG, maintainability) — God object
- `app/Http/Controllers/Api/V1/MeTeacherController.php` ≈ **108 KB** (controller terbesar).
- `routes/api.php` ≈ **1.464 baris / 96 KB** dalam satu file.
Melanggar prinsip AGENTS.md "controller tipis, logika di service" dan mempersulit review (aturan PR < 500 baris). **Rekomendasi**: pecah `MeTeacherController` per sub-domain; pecah `routes/api.php` menjadi beberapa file per modul (`routes/api/academic.php`, dst) via `Route::group`.

### 🟡 BE-03 (RENDAH) — Duplikasi & alias rute
- `GET /me` didefinisikan **3×** (baris 120, 175, 195).
- `/me/notifications*` (198–200) menduplikasi `/notifications*` (1350–1354); `/notification-preferences` ada di 216–217 **dan** 1389–1390.
- Alias: `/learning-modules` = `/teaching-materials` (838–843), `/question-packages` = `/question-banks` (963–968), `/grades/bulk` = `/grades/batch`.
Risiko: alias dapat **menyimpang permission-nya**. Contoh: `/teaching-materials` (832–836) **tanpa** `permission:`, sementara fitur setara bisa saja dilindungi di tempat lain — perbedaan ini mudah luput saat perubahan. **Rekomendasi**: satukan handler, kurangi alias, atau samakan middleware secara eksplisit.

### 🟡 BE-04 (RENDAH) — Kolom agregat ter-cache berisiko drift
`invoices.paid_amount` (cache total terbayar) & `invoices.status` harus selalu dihitung ulang transaksional saat `payment.verify/reject/void`. **Rekomendasi**: pastikan `FinanceService` memperbaruinya dalam `DB::transaction` yang sama dan sediakan command rekonsiliasi.

---

## 4. Temuan Database (Skema)

**Penilaian umum: BAIK.** UUID PK konsisten, **setiap foreign key memiliki `onDelete`** (cascade/restrict/set null sesuai konteks), nilai/geo pakai `decimal` yang tepat, status CBT dikunci lewat check-constraint (migrasi 000029 & 000034), serta enkripsi NIK (PII) sesuai KEPUTUSAN §10.

- ✅ **Uang sebagai integer**: `fee_types.default_amount`, `invoices.total_amount/paid_amount`, `invoice_items.amount/discount`, `payments.amount` semuanya `unsignedBigInteger` (rupiah bulat) — sesuai aturan tech stack "Uang: Integer, bukan float".
- ✅ **Index & unik**: `invoice_number`, `receipt_number`, dan partial unique index `payments_external_ref_active_unique` (hanya untuk status aktif) — desain bagus.

### 🟡 DB-01 (RENDAH) — Migrasi finance menambah kolom NOT NULL tanpa default ke tabel berisi data
**Lokasi**: `2026_10_26_000031_revise_finance_to_third_party_slip.php:31` → `->string('external_reference', 150)` ditambahkan **tanpa `nullable()` dan tanpa `default`** ke tabel `payments` yang sudah ada. Aman hanya jika tabel kosong; akan **gagal** bila ada baris. **Rekomendasi**: pola `nullable()` → backfill → set NOT NULL.

---

## 5. Temuan Frontend (Tombol & UX)

> Berdasarkan penyisiran halaman besar: `admin` dashboard, `admin/cms`, `admin/counseling`, `admin/facilities`, `admin/library`, `admin/tickets`, `admin/public-relations`, `admin/reports`, `portal/siswa`, `portal/ortu`, `ppdb/daftar`, home publik, `middleware.ts`, `auth-provider`.

### ✅ FE-00 (BAIK) — Tidak ada tombol mati pada sampel
Tidak ditemukan `href="#"`, `onClick={() => {}}` kosong, maupun placeholder "coming soon / segera hadir / belum diimplementasi". Tombol pada halaman yang disisir tersambung ke handler/API.

### 🟠 FE-01 (SEDANG, konsistensi & a11y) — Dialog native browser dipakai untuk aksi kritis
Alih-alih komponen `ConfirmDialog`/toast milik aplikasi (yang sudah ada — lihat `confirm-dialog.test.tsx`), beberapa halaman memakai dialog bawaan browser:

- **`window.confirm(...)`** untuk konfirmasi hapus di `admin/cms/page.tsx` — hapus berita (`:444`), agenda (`:481`), unduhan (`:517`), album (`:548`), media (`:627`).
- **`alert(...)`** untuk notifikasi/validasi/error di `admin/public-relations/page.tsx` (`:217,229,243,254,277,289,336,352,366`), `admin/facilities/page.tsx` (`:320,402`), `admin/reports/page.tsx` (`:148`), `portal/siswa/page.tsx` (`:208`).

**Dampak**: UX tidak konsisten, **tidak aksesibel** (gagal kriteria a11y AGENTS.md), memblokir event-loop, dan dapat ditekan browser ("cegah halaman ini membuat dialog") sehingga **konfirmasi hapus bisa hilang** → penghapusan tak sengaja. **Rekomendasi**: ganti semua `window.confirm`/`alert` dengan `ConfirmDialog` + toast yang konsisten.

### 🔵 FE-02 (INFO) — Cakupan audit frontend belum tuntas
Halaman terdalam (grades, cbt/exams & workspace ujian siswa, finance invoices/payments, portal guru nilai/presensi, portal siswa cbt/tagihan, master students/teachers) belum disisir karena keterbatasan jembatan file. Area berisiko tinggi untuk "tombol salah" justru ada di sini (alur ujian CBT, input nilai massal, pembayaran). **Perlu penuntasan** (lihat bagian 7).

---

## 6. Temuan Kebijakan / Tata Kelola

### 🔵 GOV-01 — Modul Finance dibangun padahal berstatus "Menunggu Keputusan"
`13_MASTER_MODULES.md` (bagian 5) dan `docs/finance-module-assessment.md` menyatakan modul Keuangan/SPP (invoices, payments, VA) **TIDAK dibangun di v1** (menunggu keputusan yayasan; AGENTS.md §8). Namun migrasi **26 Okt 2026** membuat tabel `fee_types`, `invoices`, `invoice_items`, `payments`, `payment_provider_settings`, beserta controller, service, rute (`/finance/*`), dan halaman admin finance. 
**Dampak**: dokumentasi "sumber kebenaran" kini **kontradiktif & usang**, dan ada potensi pelanggaran proses §8 (keputusan yang seharusnya dari sekolah). **Rekomendasi**: konfirmasi persetujuan tertulis sekolah, lalu perbarui `13_MASTER_MODULES.md` & assessment agar sinkron dengan kode. (Sinkronisasi dokumen↔kode juga berlaku untuk klaim "NestJS" di `02/03` padahal backend memakai Laravel, dan tanggal audit 827-test yang lebih lama dari kode terkini.)

---

## 7. Rekomendasi Prioritas & Langkah Penuntasan

**Segera (P0):**
1. **SEC-01** — Tambahkan `permission:` di layer rute untuk semua endpoint manajemen (defense-in-depth), mulai dari daftar berisiko tinggi (broadcast, executive dashboard, reports, BK, approve/lock rapor, point-types).
2. **SEC-02 & SEC-04** — Kunci `POST /audit-logs`; verifikasi `.env` tidak ter-track git.
3. **FE-01** — Ganti `window.confirm`/`alert` dengan `ConfirmDialog`/toast (risiko hapus tak sengaja).

**Berikutnya (P1):**
4. **BE-01** — Seragamkan error-handling (`HttpException` sebelum `Throwable`) agar 403/422 tidak jadi 500.
5. **SEC-03** — Audit ulang IDOR unduh slip gaji & struk pembayaran.
6. **GOV-01** — Sinkronkan dokumen dengan kode (Finance, NestJS→Laravel, status test).

**Pemeliharaan (P2):**
7. **BE-02/BE-03** — Pecah `MeTeacherController` & `routes/api.php`; rapikan alias/duplikasi rute.
8. **DB-01** — Perbaiki pola migrasi kolom NOT NULL baru.

**Penuntasan audit (belum selesai):**
9. Sisir halaman frontend terdalam (CBT workspace siswa, input nilai guru, finance, master) — butuh **shell on-device aktif** atau menghubungkan folder `apps/web/src/app` langsung dari aplikasi desktop agar bisa ditarik.
10. Jalankan `php artisan test` + `vendor/bin/pint --test` untuk memverifikasi klaim "827 tests lulus" pada kode terkini (pasca-migrasi 26 Okt), dan `npm run test` (Vitest) + Playwright untuk frontend.

---

*Audit oleh asisten AI. Temuan "perlu verifikasi" ditandai karena belum dikonfirmasi langsung terhadap seluruh kode; gunakan sebagai peta prioritas, bukan vonis final. Tidak ada perubahan apa pun dilakukan terhadap kode selama audit ini.*
