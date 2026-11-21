# Laporan Audit Fungsional Menyeluruh: Tahap 5E-2 (Persona Portals, Keuangan & PPDB)

**Portal Sekolah Satu Pintu (SISTLABUM Labschool UM)**  
**Tanggal Audit:** 26 Oktober 2026  
**Status:** LULUS (Semua Portal Persona, Alur Keuangan & PPDB Terverifikasi 100%)  
**Spesifikasi Pengujian E2E:** [`apps/web/e2e/19-audit-5e2-portals-finance-ppdb.spec.ts`](file:///Users/macbookprom12020/Desktop/SISTLABUM/apps/web/e2e/19-audit-5e2-portals-finance-ppdb.spec.ts)

---

## 1. Ringkasan Eksekutif

Tahap **5E-2** membuktikan berfungsinya seluruh modul front-facing berbasis persona pengguna dan alur transaksi krusial:
1. **Portal Guru & Wali Kelas (`/portal/guru/*`):** Menampilkan dashboard, jadwal mengajar, pengisian presensi siswa, jurnal kelas, buku nilai/penilaian formatif-sumatif, materi ajar, konsol wali kelas, dan layanan pribadi staf (presensi pegawai, pengajuan izin cuti, slip gaji).
2. **Portal Siswa (`/portal/siswa/*`):** Menampilkan profil siswa (`Ahmad Pratama`, Kelas 7-A), jadwal pelajaran, penugasan belajar, nilai akademik/rapor, informasi tagihan biaya pendidikan bersumber seeder, dan layanan janji temu konseling BK.
3. **Portal Orang Tua / Wali Murid (`/portal/ortu/*`):** Menguji dashboard perwalian resmi, selector profil anak, serta halaman mendalam anak (`/portal/ortu/anak/:id`) yang mencakup tab presensi, rapor, catatan karakter, perkembangan, dan keuangan.
4. **Portal Pimpinan Eksekutif / Kepala Sekolah (`/portal/kepsek/*`):** Dashboard KPI pimpinan (tingkat kehadiran siswa 93.73%, kehadiran guru 100%, capaian akademik jenjang VII 86.4/100, target PPDB) dan konsol antrean persetujuan (approvals).
5. **Modul Keuangan Sekolah (`/admin/finance/*`):** Faktur & piutang tagihan siswa, antrean verifikasi slip bukti bayar pihak ketiga, laporan rekapitulasi tunggakan siswa, dan laporan realisasi pemasukan kas.
6. **Sistem PPDB Online (`/ppdb/*` & `/admin/ppdb/*`):** Halaman publik PPDB, autentikasi masuk calon siswa, konfigurasi gelombang/jalur pendaftaran, verifikasi berkas pendaftar, dan perankingan seleksi kelulusan.

---

## 2. Hasil Eksekusi Uji End-to-End (Playwright)

```bash
npx --prefix apps/web playwright test apps/web/e2e/19-audit-5e2-portals-finance-ppdb.spec.ts --config=apps/web/playwright.config.ts
```

### Log Ringkasan Suite:
```
Running 6 tests using 1 worker

  ✓  1. Portal Guru: Dashboard, Jadwal, Presensi, Jurnal, Nilai, Materi, Wali Kelas & Layanan Pribadi (11.5s)
  ✓  2. Portal Siswa: Dashboard, Jadwal, Tugas, Nilai, Tagihan & Konseling (7.0s)
  ✓  3. Portal Orang Tua: Dashboard Wali & Tab Profil Anak (2.8s)
  ✓  4. Portal Eksekutif Kepala Sekolah & Antrean Persetujuan (Approvals) (5.7s)
  ✓  5. Modul Keuangan: Dashboard, Tagihan Siswa, Verifikasi Slip & Tunggakan (19.0s)
  ✓  6. PPDB Publik & Shell Admin PPDB: Gelombang, Pendaftar & Seleksi (19.3s)

6 passed (1.1m)
```

---

## 3. Matriks Verifikasi Fitur per Persona

| Persona | Rute Halaman Teruji | Komponen & Data Kunci Terverifikasi | Status |
|---|---|---|:---:|
| **Guru & Wali Kelas** | `/portal/guru`<br>`/portal/guru/jadwal`<br>`/portal/guru/presensi`<br>`/portal/guru/jurnal`<br>`/portal/guru/nilai`<br>`/portal/guru/materi`<br>`/portal/guru/wali-kelas`<br>`/portal/guru/presensi-saya`<br>`/portal/guru/izin-cuti`<br>`/portal/guru/slip-gaji` | - Sesi guru aktif (`guru.indo` / Rina Marlina, M.Pd.)<br>- Jadwal KBM per hari<br>- Presensi siswa rombel aktif<br>- Jurnal mengajar & materi ajar<br>- Konsol wali kelas & roster kelas<br>- Layanan pribadi staf (presensi mandiri, form cuti, slip gaji) | **LULUS (100%)** |
| **Siswa** | `/portal/siswa`<br>`/portal/siswa/jadwal`<br>`/portal/siswa/tugas`<br>`/portal/siswa/nilai`<br>`/portal/siswa/tagihan`<br>`/portal/siswa/konseling` | - Sesi siswa aktif (`siswa_001` / Ahmad Pratama, Kelas 7-A)<br>- Jadwal kelas 7-A<br>- Daftar tugas belajar aktif<br>- Capaian nilai akademik<br>- Faktur tagihan aktif (`INV/2026/10/QUEUE-803`)<br>- Form permohonan konseling BK rahasia | **LULUS (100%)** |
| **Orang Tua** | `/portal/ortu`<br>`/portal/ortu/anak/:id` | - Sesi wali murid (`ortu_001`)<br>- Kartu identitas anak terhubung (`Ahmad Pratama`)<br>- Rekapitulasi kehadiran ananda<br>- Tab capaian rapor & catatan karakter<br>- Tab tagihan & unggah bukti transfer | **LULUS (100%)** |
| **Kepala Sekolah** | `/portal/kepsek`<br>`/portal/kepsek/approvals` | - Sesi pimpinan (`kepsek_demo`)<br>- Ringkasan KPI: Presensi siswa 93.73%, Guru 100%, Akademik 86.4<br>- Konsol persetujuan pengajuan (approvals queue) | **LULUS (100%)** |
| **Bendahara** | `/admin/finance`<br>`/admin/finance/invoices`<br>`/admin/finance/payments`<br>`/admin/finance/arrears`<br>`/admin/finance/reports` | - Sesi keuangan (`bendahara_demo`)<br>- Daftar faktur & status pembayaran tagihan<br>- Antrean verifikasi slip transfer bank<br>- Laporan tunggakan piutang per rombel<br>- Laporan realisasi arus kas pemasukan | **LULUS (100%)** |
| **PPDB** | `/ppdb`<br>`/ppdb/masuk`<br>`/admin/ppdb/waves`<br>`/admin/ppdb/applicants`<br>`/admin/ppdb/selection` | - Landing page publik PPDB SMP Laboratorium UM<br>- Form login calon peserta didik baru<br>- Panel admin gelombang & jalur pendaftaran<br>- Verifikasi berkas administrasi calon siswa<br>- Konsol seleksi nilai & perankingan kuota | **LULUS (100%)** |

---

## 4. Temuan, Catatan & Keamanan

1. **Privasi & Scope Data Ortu-Anak:** Akses anak diuji langsung menggunakan relasi resmi `guardians -> students`. Siswa yang tidak berada di bawah perwalian wali terkait ditolak secara ketat pada level backend (`ChildDetailPolicy` / `GuardianStudentAccess`).
2. **Pemisahan Jalur Civitas & Admin:** Sesi civitas (`guru.indo`, `siswa_001`, `ortu_001`) tidak memiliki cookie `sistlabum_admin_session=1`, sehingga setiap upaya membuka `/admin/*` langsung menghasilkan respon HTTP 404 sungguhan.
3. **Data Nyata Seeder Skala 1:** Seluruh halaman portal menampilkan data dinamis dari database `sistlabum` yang terisi dari skenario seeder (tagihan Rp 200.000, 502 siswa, kehadiran guru 100%, dll.) tanpa menggunakan data mock di sisi browser.
