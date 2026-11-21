# Kajian Kesiapan & Usulan Modul Keuangan (Bendahara Sekolah)

**Status:** Kajian Kesiapan / Scope Assessment (FASE 4)  
**Institusi:** SMP Laboratorium Universitas Negeri Malang  
**Penulis:** Tim Engineering SISTLABUM  

---

## 1. Temuan Kondisi Saat Ini (Existing State)

Berdasarkan audit menyeluruh terhadap kode backend Laravel (`apps/api`) dan antarmuka web Next.js (`apps/web`), berikut status komponen keuangan:

1. **Slip Gaji Pegawai & Guru (`salary-slips`)**:
   - **Status**: ✅ **Tersedia**.
   - **Backend**: Didukung oleh `TeacherSalarySlip.php` dan `TeacherController::mySalarySlips` / `downloadMySalarySlip`.
   - **Frontend**: Telah terintegrasi penuh pada Portal Guru (`/portal/guru/slip-gaji`) dan Portal Staf (`/portal/staf/slip-gaji`).
2. **Daftar Ulang Siswa Baru PPDB (`PPDBReRegistration`)**:
   - **Status**: ✅ **Tersedia Sebagian (Status Administrasi)**.
   - **Backend**: Tabel `ppdb_re_registrations` mencatat status verifikasi daftar ulang calon siswa baru.
   - **Kekurangan**: Belum terhubung dengan payment gateway otomatis atau penerbitan Virtual Account (VA) bank.
3. **Sistem Penagihan SPP & Buku Kas Bendahara**:
   - **Status**: ❌ **Belum Ada (Not Implemented)**.
   - **Temuan**: Tidak ditemukan tabel `tuition_fees`, `invoices`, `payments`, `ledgers`, atau `cash_books`.
   - **Konfirmasi**: Sesuai instruksi AGENTS.md Bagian 8 dan Roadmap Fase 4, modul keuangan ini **TIDAK** dibangun pada fase ini.

---

## 2. Usulan Scope Modul Keuangan Masa Depan (Future Scope Proposal)

Bila sekolah memutuskan untuk membangun Modul Keuangan & Bendahara Terpadu pada rilis mendatang, diusulkan arsitektur modular berikut:

### 2.1. Manajemen Tarif & Pos Anggaran (Billing Setup)
- **Komponen Tarif**:
  - SPP Bulanan berjenjang (Reguler vs Beasiswa/KIP).
  - Biaya Daftar Ulang Siswa Baru (Seragam, Buku Paket, Kegiatan Tahunan).
  - Biaya Ujian / Laboratorium / Ekskul Khusus.
- **Tahun Ajaran & Periode**: Pengaitan tarif dengan tahun ajaran aktif dan semester berjalan.

### 2.2. Invoice & Payment Gateway (Virtual Account Terpadu)
- **Otomatisasi Tagihan**: Penagihan otomatis diterbitkan tiap tanggal 1 setiap bulan.
- **Channel Pembayaran**: Integrasi Virtual Account (Bank Mandiri, BRI, BNI, atau Bank Jatim) melalui payment gateway (Midtrans / Xendit / BNI Direct).
- **Rekonsiliasi Real-Time**: Webhook notifikasi pembayaran yang otomatis mengubah status tagihan menjadi lunas tanpa perlu unggah bukti transfer manual.

### 2.3. Dashboard Bendahara Sekolah
- **Penerimaan Kas Harian/Bulanan**: Grafik total uang masuk vs tunggakan siswa per kelas.
- **Monitoring Tunggakan SPP**: Filter kelas dan daftar nama siswa yang menunggak beserta fitur kirim pengingat WhatsApp otomatis ke orang tua/wali murid.
- **Pencairan & Pengeluaran Operasional**: Buku kas keluar untuk operasional sekolah, honor ekstrakurikuler, dan pembelian inventaris sarpras.

### 2.4. Integrasi Portal Orang Tua (`/portal/ortu`)
- Wali murid dapat melihat riwayat pembayaran ananda, mengunduh bukti kwitansi resmi ber-QR code, dan menyalin nomor Virtual Account pembayaran langsung dari HP.

---

## 3. Rekomendasi & Langkah Selanjutnya
- Menunggu keputusan pimpinan yayasan dan sekolah terkait mekanisme perbankan mitra (rekening giro sekolah yang ditunjuk).
- Memastikan kepatuhan terhadap standar pelaporan keuangan pendidikan dan BOS (Bantuan Operasional Sekolah).
