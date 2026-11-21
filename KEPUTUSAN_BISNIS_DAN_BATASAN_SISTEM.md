# Registri Keputusan Bisnis, Batasan Kebijakan & Audit Sistem (SISTLABUM)

> **Status Dokumen: RESMI & DISETUJUI OLEH PEMILIK SISTEM**  
> Tanggal Pembaruan: **11 Oktober 2026**  
> Dokumen ini disusun dan dikunci berdasarkan aturan **`AGENTS.md` Bagian 8 (Hal yang HARUS Ditanyakan, Bukan Diputuskan Sendiri oleh AI)** sebagai sumber kebenaran (*single source of truth*) implementasi seluruh modul sistem.

---

## 1. Ringkasan Hasil Audit Teknis Sistem Terkini

Sistem backend Laravel API telah menyelesaikan **4 modul inti (29 fitur)** dengan status pengujian:

- **Total Skenario Uji**: **271 tests** (1.768 assertions) — **100% Lulus (0 Gagal)**
- **Kepatuhan Gaya Kode**: **Laravel Pint Clean (0 violation)**
- **Audit Modul Selesai**:
  1. **AUTH (`BE-AUTH-01` s/d `05`)**: Login multi-identifier (Username, Email, NIS, NIP), rate limiter anti-brute force, device sessions, RBAC 12 role sistem, audit log append-only.
  2. **MST (`BE-MST-01` s/d `08`)**: Tahun ajaran & semester, data siswa (NIK enc, status history, CSV import/export), guru & staf, wali murid & IDOR protection, rombel/kelas & kapasitas, mata pelajaran, inventaris ruang, pratinjau kenaikan kelas & kelulusan idempoten.
  3. **CMS (`BE-CMS-01` s/d `07`)**: Pengaturan profil situs, berita & kategori (views counter), kalender agenda, direktori publik ter-sanitasi, galeri & buletin, pusat unduhan, halaman statis & pohon navigasi dinamis.
  4. **PPDB (`BE-PPDB-01` s/d `09`)**: Gelombang & jalur kuota, akun pendaftar (OTP 6 digit), draf formulir autosave, unggah berkas privat (max 2MB), verifikasi berkas & revisi, perankingan otomatis, pengumuman hasil 2FA, daftar ulang & konversi idempoten ke siswa/wali/rombel, bukti pendaftaran PDF native & statistik agregat.

---

## 2. Keputusan Resmi Pemilik Sistem & Batasan Kebijakan Sekolah

Berikut adalah ketetapan resmi yang telah disetujui untuk menjadi pedoman pengembangan teknis:

### 1. Rumus Seleksi PPDB
* **Ketetapan**:  
  **Nilai Rapor 60% + Nilai Tes Seleksi 40%**. Bobot dapat dikonfigurasi secara dinamis oleh admin sekolah per gelombang/jalur sesuai kebijakan penerimaan siswa. Jika pada jalur tertentu tidak ada tes seleksi, sistem fleksibel menggunakan nilai rapor murni atau kriteria lain yang ditetapkan sekolah.
* **Implementasi Teknis**: Disimpan dan dieksekusi melalui kolom JSONB `score_formula` pada tabel `ppdb_tracks`.

### 2. Aturan Pemecah Seri (Tie-Breaker) PPDB
* **Ketetapan**:  
  Peringkat nilai akhir diutamakan $\rightarrow$ Kriteria tambahan yang ditetapkan sekolah $\rightarrow$ Waktu pendaftaran lebih awal sebagai penentu akhir jika nilai tetap sama. Usia digunakan sebagai kriteria tambahan hanya jika sesuai dengan jenjang dan ketentuan PPDB yang berlaku.
* **Implementasi Teknis**: Diselaraskan pada query perankingan `PPDBSelectionService`.

### 3. Kebijakan Promosi Cadangan (Waitlist)
* **Ketetapan**:  
  Promosi cadangan dijalankan **otomatis bersyarat** jika calon siswa yang lolos tidak melakukan daftar ulang hingga batas waktu yang ditentukan. Sistem mengirimkan notifikasi kepada panitia dan mempromosikan kandidat cadangan berdasarkan urutan peringkat **setelah mendapat persetujuan panitia** (*committee approval*).
* **Implementasi Teknis**: Cron job mengecek tenggat daftar ulang $\rightarrow$ mengirim draf eskalasi notifikasi panitia $\rightarrow$ panitia mengonfirmasi dengan 1 klik untuk mempromosikan kandidat.

### 4. Batas Waktu Daftar Ulang & Konsekuensi
* **Ketetapan**:  
  **3 hari kerja** setelah pengumuman hasil seleksi dipublikasikan. Batas waktu dapat disesuaikan oleh admin sekolah, dengan sistem pengiriman notifikasi pengingat sebelum tenggat berakhir.

### 5. Pola Nomor Induk Siswa (NIS) Resmi
* **Ketetapan**:  
  Format: **Tahun Masuk + Nomor Urut**, contoh: `202600001`. Nomor urut dibuat otomatis, unik, dan tidak boleh digunakan kembali, serta dapat disesuaikan dengan format resmi sekolah jika diinput manual. NISN tetap disimpan terpisah dari NIS internal sekolah.
* **Implementasi Teknis**: Telah diimplementasikan pada `PPDBEnrollmentService` dengan format sequence tahunan dan constraint unique di tabel `students`.

### 6. Pembagian Akun Orang Tua / Wali Murid
* **Ketetapan**:  
  Aktivasi mandiri melalui portal menggunakan **tautan aktivasi unik** yang dikirim ke nomor WhatsApp atau email wali yang telah diverifikasi. Sistem menyediakan opsi pengiriman ulang (*resend activation link*) dan bantuan aktivasi oleh admin sekolah. **Kredensial tidak boleh dikirim dalam bentuk kata sandi terbuka**.
* **Implementasi Teknis**: Akun dibuat berstatus `active` dengan token aktivasi mandiri via link aman (bukan plain-text password).

### 7. Toleransi Kapasitas Rombongan Belajar (Kelas)
* **Ketetapan**:  
  Batas standar maksimal **36 siswa per rombongan belajar**, dengan pengaturan konfigurasi berdasarkan jenjang dan ketentuan yang berlaku. **Override hanya dapat dilakukan oleh pejabat berwenang**, dengan menyertakan alasan tertulis, persetujuan, dan pencatatan audit log lengkap.
* **Implementasi Teknis**: Enforced di `ClassController` dan `PromotionService` dengan pencatatan audit log.

### 8. Kurikulum Akademik & Penilaian
* **Ketetapan**:  
  Menggunakan **Kurikulum Merdeka dengan KKTP (Kriteria Ketercapaian Tujuan Pembelajaran)**. Sistem mendukung pengaturan mata pelajaran, tujuan pembelajaran (TP), asesmen, deskripsi capaian kompetensi, dan cetak e-rapor. Struktur akademik dibuat fleksibel agar dapat menyesuaikan perubahan kurikulum dan kebijakan sekolah di masa depan.
* **Implementasi Teknis**: Menjadi landasan utama modul `ACD` (Akademik) dan `GRD` (Penilaian & Rapor).

### 9. Disposisi Buku Tamu & Layanan Aspirasi/Pengaduan
* **Ketetapan**:  
  - **Buku Tamu**: Data kunjungan diteruskan kepada guru atau pejabat tujuan melalui notifikasi sistem secara instan.
  - **Aspirasi/Aduan**: Dapat diakses oleh **Kepala Sekolah dan Guru BK** sesuai kewenangan, klasifikasi kasus, serta tingkat kerahasiaan. Setiap proses memiliki pelacakan status, riwayat tindak lanjut, dan batas akses ketat berdasarkan peran pengguna.
* **Implementasi Teknis**: Menjadi spesifikasi langsung untuk modul `GUEST` (`BE-GUEST-01`, `BE-GUEST-02`).

### 10. Prinsip Arsitektur & Keamanan Khusus
* **Ketetapan**:  
  - Seluruh sistem wajib menerapkan **Role-Based Access Control (RBAC)** dan prinsip *deny-by-default*.
  - Pencatatan log audit sistem untuk setiap aktivitas penting (perubahan status siswa, nilai, kenaikan kelas, akun).
  - Perlindungan data pribadi (PII): enkripsi NIK at-rest, masking pada tampilan audit/log.
  - Integrasi menyeluruh antara PPDB $\rightarrow$ Daftar Ulang $\rightarrow$ Penetapan NIS $\rightarrow$ Rombel $\rightarrow$ Akademik $\rightarrow$ Layanan Pengaduan.
  - Setiap keputusan otomatis yang berdampak pada status siswa tetap dapat ditelusuri riwayatnya dan memerlukan verifikasi/persetujuan pejabat berwenang.

---

## 3. Rencana Aksi Lanjutan

Berdasarkan keputusan resmi di atas, roadmap implementasi berikutnya:

1. **Memulai Modul 6: GUEST (Buku Tamu Digital & Aspirasi/Whistleblowing)**:
   - `BE-GUEST-01`: Buku tamu digital dengan notifikasi tujuan guru/pejabat.
   - `BE-GUEST-02`: Sistem aspirasi & pengaduan publik (tiket pelacak, akses terbatas Kepala Sekolah & BK, opsi anonim tanpa pencatatan IP).
2. **Melanjutkan ke Modul 7: ACD (Akademik & LMS)** dan **Modul 8: GRD (Penilaian Kurikulum Merdeka & KKTP)** sesuai ketetapan Poin 8.
