# Dokumen Eskalasi Tata Kelola: Keputusan Modul Keuangan Sekolah (GOV-01)

**Nomor Dokumen:** `GOV-01-FINANCE-DECISION`  
**Institusi:** SMP Laboratorium Universitas Negeri Malang  
**Status:** ✅ **RESOLVED (v1: Rilis Bertahap, Rekening Giro Mitra + Verifikasi Manual TU)**  
**Referensi Regulasi:** `AGENTS.md §8 (Hal yang HARUS Ditanyakan, Bukan Diputuskan Sendiri)` & `13_MASTER_MODULES.md §5`  
**Tanggal Resolusi:** 29 Oktober 2026  

---

## 1. Latar Belakang & Pernyataan Eskalasi

Berdasarkan audit tata kelola sistem (`GOV-01`), ditemukan bahwa fungsionalitas **Modul Keuangan Sekolah (Finance & SPP)** telah dibangun pada kode backend Laravel (`apps/api`) dan frontend Next.js (`apps/web`), padahal dokumen arsitektur [`13_MASTER_MODULES.md`](file:///Users/macbookprom12020/Desktop/SISTLABUM/13_MASTER_MODULES.md) dan [`docs/finance-module-assessment.md`](file:///Users/macbookprom12020/Desktop/SISTLABUM/docs/finance-module-assessment.md) secara eksplisit menetapkan status modul ini sebagai **"Menunggu Keputusan Sekolah"**.

Sesuai ketentuan **`AGENTS.md §8`**, keputusan mengenai:
1. Skema keuangan sekolah, rekening penampung, dan payment gateway,
2. Regulasi penagihan dan denda keterlambatan terhadap siswa,
3. Keterkaitan status tunggakan SPP dengan hak akses akademik (e-rapor dan ujian CBT),

**TIDAK BOLEH** diputuskan secara mandiri oleh tim pengembang atau AI agent. Kode yang sudah terbangun **tidak boleh dihapus**, melainkan dipagari (*guarded*) dan dieskalasikan ke pihak pimpinan sekolah/yayasan melalui dokumen ini.

---

## 2. Inventaris Komponen Modul Finance yang Sudah Terbangun

Berikut adalah inventaris seluruh artefak teknis modul Keuangan yang saat ini berada di dalam repositori:

### 2.1 Skema Basis Data & Model Eloquent
- **Tabel & Relasi**:
  - `fee_types`: Master jenis biaya (SPP, daftar ulang PPDB, seragam, kegiatan).
  - `payment_provider_settings`: Konfigurasi gateway pembayaran pihak ketiga (Midtrans/Xendit/manual).
  - `invoices`: Master tagihan siswa per periode/tahun ajaran.
  - `invoice_items`: Rincian item biaya dalam satu faktur tagihan.
  - `payments`: Pencatatan transaksi pembayaran, status verifikasi (`pending`, `verified`, `rejected`, `void`), dan unggahan bukti transfer.
- **Model Backend**:
  - [`App\Models\FeeType`](file:///Users/macbookprom12020/Desktop/SISTLABUM/apps/api/app/Models/FeeType.php)
  - [`App\Models\Invoice`](file:///Users/macbookprom12020/Desktop/SISTLABUM/apps/api/app/Models/Invoice.php)
  - [`App\Models\InvoiceItem`](file:///Users/macbookprom12020/Desktop/SISTLABUM/apps/api/app/Models/InvoiceItem.php)
  - [`App\Models\Payment`](file:///Users/macbookprom12020/Desktop/SISTLABUM/apps/api/app/Models/Payment.php)
  - [`App\Models\PaymentProviderSetting`](file:///Users/macbookprom12020/Desktop/SISTLABUM/apps/api/app/Models/PaymentProviderSetting.php)

### 2.2 Endpoint REST API Backend (`apps/api/routes/api/finance.php`)
- **Master Tarif**: `GET|POST|PUT|DELETE /api/v1/finance/fee-types` (Permission: `finance.fee.manage`)
- **Pengaturan Provider**: `GET|POST|PUT|DELETE /api/v1/finance/settings/providers` (Permission: `finance.fee.manage`)
- **Manajemen Tagihan**: `GET|POST /api/v1/finance/invoices`, `/api/v1/finance/invoices/generate-bulk`, `/api/v1/finance/invoices/{id}/cancel` (Permission: `finance.invoice.manage`)
- **Verifikasi Pembayaran**: `GET|POST /api/v1/finance/payments/{id}/verify`, `/reject`, `/void`, `/receipt` (Permission: `finance.payment.verify`)
- **Laporan Keuangan**: `GET /api/v1/finance/reports/arrears`, `GET /api/v1/finance/reports/income` (Permission: `finance.report.view`)
- **Self-Service Siswa/Wali**: `GET /api/v1/me/finance/invoices`, `POST /api/v1/me/finance/payments`

### 2.3 Antarmuka Web Next.js (`apps/web`)
- **Admin Shell**:
  - `/admin/finance/fee-types`: Manajemen komponen tarif SPP.
  - `/admin/finance/invoices`: Penerbitan dan pembuatan massal faktur siswa.
  - `/admin/finance/payments`: Verifikasi slip transfer bukti bayar.
  - `/admin/finance/arrears`: Rekapitulasi tunggakan siswa per kelas.
  - `/admin/finance/reports`: Rekap penerimaan kas masuk.
- **Portal Siswa**:
  - `/portal/siswa/tagihan`: Tampilan kartu SPP, histori pembayaran, dan upload slip pembayaran.

---

## 3. Aspek yang Memerlukan Persetujuan Resmi Sekolah & Yayasan

Sebelum modul ini dioperasikan penuh pada lingkungan produksi (*live*), pemangku kepentingan wajib menyepakati aspek-aspek berikut:

1. **Rekening Penampung & Payment Gateway:**
   - Apakah pembayaran menggunakan rekening giro bank resmi sekolah/yayasan (misal Bank Jatim / BNI / Mandiri / BRI) dengan verifikasi manual slip transfer oleh staf TU?
   - Atau menggunakan aggregator payment gateway (Virtual Account otomatis melalui Midtrans / Xendit)? Jika ya, siapa penanggung biaya admin transaksi (*fee per transaction*)?
2. **Kategori Tarif & Afirmasi Siswa:**
   - Apakah tarif SPP bersifat tunggal (flat) untuk seluruh siswa per angkatan, atau terdapat skema berjenjang (reguler, anak guru/dosen UM, penerima beasiswa KIP/afirmasi)?
3. **Keterkaitan Keuangan dengan Akses Akademik:**
   - Apakah siswa yang menunggak SPP diblokir dari keikutsertaan Ujian CBT atau ditahan penerbitan e-rapornya? *(Catatan: Perlu diperhatikan etika pendidikan dan regulasi Kemendikbudristek)*.
4. **Pemisahan Dana Komite vs Dana BOS:**
   - Pencatatan keuangan di portal sistem ini harus dipastikan terpisah secara legal dari pembukuan Bantuan Operasional Sekolah (BOS) yang memiliki aplikasi pelaporan tersendiri dari kementerian (ARKAS/BOS Salur).

---

## 4. Daftar Pertanyaan Kunci untuk Pemilik Sistem (Product Owner / Kepala Sekolah)

| No | Pertanyaan Kebijakan | Opsi Jawaban | Rekomendasi Engineering | Keputusan Final |
|:--:|---|---|---|:---:|
| 1 | Bagaimana status aktivasi Modul Keuangan pada rilis v1? | A. Diaktifkan penuh<br/>B. Ditunda (hanya pencatatan manual di TU)<br/>C. Aktif parsial (hanya info tagihan tanpa bayar online) | **Opsi C** (Rilis fitur bertahap) | **Opsi C (Rilis Bertahap)**: Kartu/tagihan SPP + unggah slip transfer manual, verifikasi oleh TU. Jalur bayar online dinonaktifkan di v1. |
| 2 | Kanal pembayaran mana yang akan digunakan? | A. Transfer manual ke Giro Sekolah + Unggah Slip<br/>B. Virtual Account otomatis (Midtrans/Xendit)<br/>C. Loket kasir fisik sekolah | **Opsi A** untuk tahap awal, dilanjutkan Opsi B | **Opsi A (Giro Mitra + Verifikasi Manual)**: Memakai rekening giro resmi sekolah. Seluruh logika ditaruh di balik adapter interface `PaymentChannel` sehingga VA dapat ditambah di Fase 2 tanpa refactoring. |
| 3 | Apakah tunggakan SPP membatasi hak ujian CBT siswa? | A. Ya, sistem otomatis mengunci ujian CBT<br/>B. Tidak, penagihan murni persuasif ke orang tua | **Opsi B** (Hak belajar & evaluasi siswa tetap terjamin) | **Opsi B**: Hak evaluasi siswa dijamin, penagihan murni persuasif ke orang tua / wali murid. |
| 4 | Siapa pihak yang memiliki hak otorisasi verifikasi pembayaran? | A. Bendahara Sekolah<br/>B. Kepala Tata Usaha<br/>C. Staf Administrasi Umum | **Opsi A & B** (Role `admin` dan `staff` tertentu) | **Opsi A & B**: Bendahara Sekolah dan Staf TU yang memegang permission `finance.payment.verify`. |

---

## 5. Tindakan Teknis Pasca-Keputusan (Resolved)

1. **Adapter Pattern Terpasang:** Arsitektur pembayaran modular telah diterapkan via `PaymentChannelInterface`, `ManualGiroPaymentChannel`, dan `PaymentChannelManager` di backend (`apps/api/app/Services/Finance/Channels/`).
2. **Kanal v1 Dibatasi ke Giro Mitra:** Pengajuan pembayaran mewajibkan unggah slip bukti transfer bank giro sekolah dan nomor referensi unik, kemudian diverifikasi oleh staf TU sebelum menerbitkan kuitansi resmi.
3. **Pintu Masuk VA Fase 2 Terbuka:** Ketika sekolah siap bermitra dengan agregator payment gateway (Midtrans/Xendit), integrasi cukup menambahkan class adapter baru yang mengimplementasikan `PaymentChannelInterface` tanpa mengubah alur tagihan atau database.
4. **Keamanan & Guarding:** Hak akses tetap dipagari secara ketat di bawah permission `finance.*` dan tidak dibuka ke publik sebelum alur operasional TU teruji menyeluruh.
