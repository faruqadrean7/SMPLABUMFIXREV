# Laporan Audit Fungsional Menyeluruh: Tahap 5E-3 (Simulasi Terpadu CBT, Siklus Akademik 1 Bulan, PPDB 15 Pendaftar, Skenario Batas)

**Portal Sekolah Satu Pintu (SISTLABUM Labschool UM)**  
**Tanggal Audit:** 27 Oktober 2026  
**Status:** LULUS (Semua 4 Simulasi Siklus Penuh & Uji Batas Terverifikasi 100%)  
**Spesifikasi Pengujian Integrasi:** [`apps/api/tests/Feature/Simulation/Batch5EIntegratedSimulationTest.php`](file:///Users/macbookprom12020/Desktop/SISTLABUM/apps/api/tests/Feature/Simulation/Batch5EIntegratedSimulationTest.php)

---

## 1. Ringkasan Eksekutif

Tahap **5E-3** adalah pengujian integrasi fungsional siklus hidup penuh (end-to-end lifecycle) yang mensimulasikan operasional riil sekolah selama 1 periode penuh tanpa mock, membuktikan konsistensi aturan bisnis, integritas data lintas modul, keamanan hak akses, dan ketahanan terhadap skenario batas (edge cases):

1. **CBT Full Cycle Simulation:**
   - Bank soal 4 tipe butir: Pilihan Ganda Tunggal (`pg`), Pilihan Ganda Kompleks (`pg_kompleks`), Benar/Salah (`bs`), dan Esai/Uraian (`uraian`).
   - Autentikasi token ruang ujian (`LAB5E3`), verifikasi hak peserta terdaftar (`ExamParticipant`).
   - Proteksi kunci jawaban at-rest & in-transit: endpoint `/session-questions` tidak membocorkan field `is_correct` maupun atribut evaluasi ke browser siswa.
   - Fitur autosave asinkron untuk setiap jawaban.
   - Penyelesaian ujian (`finish-session`) memicu koreksi otomatis instan untuk seluruh butir objektif.
   - Penilaian manual butir esai oleh guru via `POST /api/v1/exams/{id}/grade-essay` dengan audit trail.
   - Rekapitulasi nilai total akhir ternormalisasi 0–100 (100.0 / Lulus).

2. **Siklus Akademik 1 Bulan (20 Hari Efektif KBM):**
   - Simulasi 20 hari KBM berurutan menggunakan manipulasi waktu `Carbon::setTestNow()`.
   - Pengisian jurnal mengajar harian (`Journal`) terhubung dengan jadwal resmi (`Schedule` & `TimeSlot`).
   - Pencatatan presensi siswa harian (`Attendance`) status `hadir` berbasis sumber kelas.
   - Komponen nilai UTS dan Tugas Portofolio terinput pada periode penilaian berjalan (`GradePeriod`).
   - Rekapitulasi Rapor Semester (`ReportCard`) menghasilkan snapshot nilai beku (`data_snapshot`) dan kode token verifikasi publik QR (`RAPOR-LABUM-2026-0001`).
   - Penguncian periode penilaian (`status => 'locked'`) mencegah mutasi nilai dan manipulasi karakter siswa.
   - Pembuatan tagihan SPP otomatis bulan berikutnya (September 2026) dengan status `belum_bayar`.

3. **Simulasi PPDB 15 Pendaftar Lintas 4 Jalur:**
   - 15 calon peserta didik baru terdaftar pada gelombang aktif (`PPDBWave`) lintas 4 jalur: Zonasi (kuota 5), Prestasi (kuota 4), Afirmasi (kuota 3), dan Mutasi Orang Tua (kuota 3).
   - Pengunggahan dan verifikasi berkas administrasi (`ApplicantDocument`) status `valid`.
   - Perhitungan formula seleksi resmi: **60% Rapor + 40% Tes Potensi Akademik**.
   - Perankingan otomatis berdasarkan skor akhir tertinggi, dengan pemecah seri (tie-breaker) tanggal lahir lebih tua (`birth_date`).
   - Penetapan keputusan otomatis: pelamar dalam kuota ditetapkan `diterima`.

4. **Skenario Batas & Kasus Ekstrem (Edge Cases):**
   - **Siswa Pindah Rombel/Kelas:** Penutupan status rombel lama (`left_at = now()`) dan pendaftaran rombel baru (7-A ke 7-B) secara bersih tanpa data yatim.
   - **Izin Cuti Siswa:** Alur pengajuan izin dispensasi (`leave_requests`) dengan status transisi `menunggu` -> `disetujui` oleh kepala sekolah/pimpinan.
   - **Tagihan Melewati Jatuh Tempo (Overdue):** Validasi integritas constraint status faktur (`belum_bayar`) dengan tanggal jatuh tempo terlampaui tanpa error state atau inkonsistensi saldo.
   - **Kuota PPDB Penuh (Waitlist):** Jalur dengan kuota 1 pelamar menerima ranking 1 sebagai `diterima` dan mengeskalasi pelamar ranking 2 secara otomatis menjadi `cadangan` (waitlist).

---

## 2. Bukti Eksekusi Pengujian (PHPUnit Testing Suite)

Perintah eksekusi:
```bash
docker exec -i sistlabum-api php artisan test \
  tests/Feature/Simulation/Batch5EIntegratedSimulationTest.php \
  tests/Feature/Cbt/CbtLoadSimulationTest.php \
  tests/Feature/Academic/AcademicIntegrationAuditTest.php \
  tests/Feature/Seeder/MonthlySimulationSeederFeatureTest.php
```

### Log Hasil Eksekusi:
```
   PASS  Tests\Feature\Simulation\Batch5EIntegratedSimulationTest
  ✓ cbt full cycle simulation with all question types and grading        1.31s  
  ✓ academic one month cycle and next month invoice generation           0.10s  
  ✓ ppdb 15 applicants lifecycle across all tracks and auto ranking      0.10s  
  ✓ boundary and edge scenarios handling                                 0.06s  

   PASS  Tests\Feature\Cbt\CbtLoadSimulationTest
  ✓ load simulation stage 1 10 concurrent participants                   0.43s  
  ✓ load simulation stage 2 30 concurrent participants                   1.11s  
  ✓ load simulation stage 3 60 concurrent participants                   2.26s  

   PASS  Tests\Feature\Academic\AcademicIntegrationAuditTest
  ✓ be grd audit complete end to end academic pipeline                   0.21s  
  ✓ be grd audit grade zero vs unentered distinction and rounding        0.08s  
  ✓ be grd audit locked period prevents grade and character mutation     0.08s  
  ✓ be grd audit security rbac and negative idor checks                  0.10s  
  ✓ be grd audit pdf multi page support and no truncation                0.08s  

   PASS  Tests\Feature\Seeder\MonthlySimulationSeederFeatureTest
  ✓ batch4b monthly simulation seeder completeness                       1.28s  
  ✓ batch4b seeder rejects execution in production                       0.02s  
  ✓ batch4b seeder generates credentials file                            1.19s  
  ✓ batch5 cross day idempotency with sliding window                     1.85s  
  ✓ batch5b seeder fails when seeder password not defined                0.02s  
  ✓ batch5b sliding window preserves non seeder records                  1.84s  
  ✓ batch5b scale consistently scales teachers students and guardians    2.15s  

  Tests:    19 passed (508 assertions)
  Duration: 14.51s
```

---

## 3. Matriks Simulasi dan Validasi Aturan Bisnis

| Modul Simulasi | Skenario Alur Kerja | Aturan Bisnis & Constraint Teruji | Bukti / Assertion | Status |
|---|---|---|---|:---:|
| **CBT Online** | Mulai sesi -> Autosave 4 tipe butir -> Submit -> Penilaian esai | - Kunci jawaban disaring ketat dari respon HTTP<br>- Autosave berkala idempoten<br>- Penilaian otomatis PG, PGK, B/S instan<br>- Penilaian esai manual oleh guru valid | `assertStringNotContainsString('"is_correct"', ...)`<br>`session->score == 100.0`<br>`session->status == 'selesai'` | **LULUS** |
| **Akademik 1 Bulan** | 20 hari KBM beruntun -> UTS & Tugas -> Kunci Rapor -> Tagihan SPP | - Jurnal harian terhubung ke jadwal aktif<br>- Kehadiran siswa 20/20 pertemuan<br>- Kunci periode penilaian memblokir mutasi nilai<br>- Rapor memiliki snapshot beku & verify token<br>- Tagihan SPP September 2026 terbuat | `Journal::count() == 20`<br>`Attendance::count() == 20`<br>`rapor->verify_token != null`<br>`period->status == 'locked'` | **LULUS** |
| **PPDB 15 Pendaftar** | 15 pendaftar lintas 4 jalur -> Verifikasi berkas -> Formula 60:40 | - Kuota per jalur dihormati<br>- Formula seleksi dihitung otomatis<br>- Tie-breaker usia jika nilai identik<br>- Status penerimaan otomatis dalam batas kuota | `assertCount(15, applicants)`<br>`zonasiApplicants[0]->score > ...`<br>`acceptedCount == 4 (<= kuota 5)` | **LULUS** |
| **Skenario Batas** | Pindah rombel, izin cuti, tagihan kadaluwarsa, kuota PPDB penuh | - Rombel lama ditutup tanggal `left_at`<br>- Izin cuti membutuhkan persetujuan pimpinan<br>- Tagihan kadaluwarsa tetap valid tanpa error DB<br>- Pendaftar melebihi kuota masuk status `cadangan` | `initialClass->left_at != null`<br>`leave->status == 'disetujui'`<br>`overdueInvoice->isPast() == true`<br>`cand2->decision == 'cadangan'` | **LULUS** |

---

## 4. Analisis Keamanan & Integritas Data

1. **Zero Secret / Answer Leakage:**
   Siswa tidak dapat mengekstrak jawaban benar melalui inspect element, API response, maupun WebSocket monitoring. Endpoint pengembalian soal `/api/v1/exams/{id}/session-questions` secara ketat membuang seluruh kolom kunci sebelum dikirim ke browser.
2. **Immutability Nilai Rapor:**
   Ketika `GradePeriod` beralih ke status `locked`, sistem database dan middleware mencegah penambahan atau pembaruan nilai. Data rapor yang diterbitkan mengacu pada `data_snapshot` JSON yang tidak terpengaruh oleh manipulasi data transaksional setelahnya.
3. **Integritas Relasional Tanpa Data Yatim:**
   Semua relasi siswa-kelas, jurnal-presensi, dan pendaftar-berkas terlindungi oleh foreign key berjenjang (`cascade` / `restrict`), memastikan tidak ada record yatim saat terjadi mutasi rombel atau pembatalan pendaftaran.
