# Spesifikasi Portal Sekolah (untuk AI Agent)

> Dokumen ini adalah sumber acuan utama bagi AI agent untuk merancang dan mengimplementasikan **portal pengguna** pada sistem sekolah yang **sudah ada**. Baca seluruh dokumen sebelum menulis kode.

---

## 0. Tujuan dan konteks

### Tujuan
Membangun **portal** untuk seluruh warga sekolah (guru, siswa, orang tua, staf, pimpinan, mitra) dengan akses berbasis peran (RBAC), lalu mengintegrasikannya ke sistem yang sudah berjalan tanpa merusak fitur yang ada.

### Perbedaan istilah (WAJIB dipahami)
| Istilah | Arti | Status |
|---|---|---|
| **Dashboard Admin** | Back office pengelola sistem (kelola user, data master, pengaturan) di `/admin/*` | **SUDAH JADI. Jangan diubah kecuali diminta.** |
| **Portal** | Halaman untuk pengguna selain admin (guru, siswa, ortu, staf, pimpinan, mitra) | **BELUM ADA. Ini yang dibangun.** |

### Aturan kerja agent
1. **Audit dulu, baru bangun.** Pelajari struktur proyek, framework, skema database, sistem auth, dan tabel yang sudah ada sebelum membuat apa pun.
2. **Adopsi, jangan buat ulang.** Pakai tabel, model, service, dan komponen UI yang sudah ada. Jangan membuat tabel duplikat (misal `siswa`, `guru`, `kelas` yang sudah ada).
3. **Jangan merusak `/admin/*`.** Pisahkan guard, layout, dan route portal dari admin.
4. **Ikuti konvensi proyek** (penamaan, struktur folder, gaya kode, library UI, library auth).
5. **Kerjakan bertahap** sesuai fase di bagian 12. Selesaikan dan uji satu fase sebelum lanjut.
6. **Jika ada ketidakpastian**, catat sebagai asumsi dan tanyakan ke pemilik proyek (lihat bagian 14), jangan menebak untuk hal yang menyangkut keamanan data.
7. **Keamanan ditegakkan di backend.** Menyembunyikan menu di frontend bukan kontrol akses.

---

## 1. Daftar portal

| No | Portal | Prefix route | Pengguna |
|---|---|---|---|
| 1 | Guru | `/guru` | Guru mapel, wali kelas, guru BK, pembina ekskul |
| 2 | Siswa | `/siswa` | Siswa, termasuk pengurus kelas/OSIS |
| 3 | Orang tua | `/ortu` | Orang tua / wali murid |
| 4 | Staf | `/staf` | Laboran, petugas UKS, pustakawan, petugas sarpras, satpam, TU/keuangan |
| 5 | Pimpinan | `/kepsek` | Kepala sekolah, wakil kepala sekolah |
| 6 | Mitra *(opsional, fase akhir)* | `/mitra` | Komite sekolah, pengawas |

**Tidak perlu akun:** kantin, petugas kebersihan, sopir, penjaga sekolah. Dikelola admin (data saja). Jika ada antar-jemput, cukup modul rute dan jadwal yang dikelola admin.

---

## 2. Model peran

### 2.1 Peran dasar (satu per akun, menentukan portal utama)
`admin`, `guru`, `siswa`, `ortu`, `staf`, `pimpinan`, `mitra`

### 2.2 Peran tambahan / hak akses tambahan (menempel pada akun dasar)
| Peran tambahan | Menempel pada | Efek |
|---|---|---|
| `wali_kelas` | guru | Membuka menu `/guru/wali-kelas/*` untuk kelas yang diwalikan |
| `guru_bk` | guru | Membuka menu `/guru/bk/*` |
| `pembina_ekskul` | guru | Membuka menu `/guru/ekskul/*` untuk ekskul yang dibina |
| `pengurus_kelas` / `pengurus_osis` | siswa | Membuka modul ringan di `/siswa/*` |

### 2.3 Sub-peran staf (berbasis modul)
`laboran`, `petugas_uks`, `pustakawan`, `petugas_sarpras`, `satpam`, `tu_keuangan`

Satu tipe akun `staf` dengan **hak akses per modul**. Menu `/staf` hanya menampilkan modul yang dimiliki.

### 2.4 Sub-peran pimpinan
`kepala_sekolah`, `wakasek_kurikulum`, `wakasek_kesiswaan`, `wakasek_sarpras`, `wakasek_humas`

### 2.5 Aturan multi-peran
- Satu akun boleh punya satu peran dasar dan banyak peran tambahan.
- Peran tambahan **tidak membuat portal baru**; ia menambah menu di dalam portal peran dasar.
- Jika orang yang sama butuh dua peran dasar berbeda (misal guru yang juga orang tua siswa), sediakan **pemilih konteks** (role switcher) atau dua akun, sesuai arsitektur auth yang ada. Tanyakan ke pemilik proyek.

### 2.6 Implementasi yang disarankan
Cocokkan dengan sistem yang sudah ada. Jika belum ada mekanisme peran, gunakan salah satu:
- Tabel `roles`, `permissions`, `role_user`, `permission_role` (atau library RBAC yang lazim di framework terkait), atau
- Kolom `role` + tabel `user_extra_roles` bila sistem sederhana.

Gunakan **permission granular** (contoh: `nilai.edit`, `absensi.edit`, `uks.view`) lalu petakan ke peran, jangan hardcode pengecekan nama peran di mana-mana.

---

## 3. Legenda hak akses

| Kode | Arti |
|---|---|
| **E** | Lihat dan edit (CRUD sesuai modul) |
| **L** | Lihat saja |
| **S** | Hanya data milik sendiri / anaknya |
| **B** | Booking / pinjam / lapor (membuat permintaan, bukan mengelola) |
| **A** | Approve / menyetujui |
| **-** | Tidak ada akses |
| `(scope)` | Dibatasi cakupan, misal kelas atau mapel yang diampu |

---

## 4. Matriks hak akses

### Tabel 1: Akademik dan administrasi

| Peran | Data master | Jadwal | Absensi | Nilai | Rapor | Keuangan |
|---|---|---|---|---|---|---|
| Admin | E | E | E | E | E | E |
| Kepala sekolah | L | L | L | L | L | L |
| Wakasek | L | L | L | L | L | - (kecuali dibuka) |
| Guru | L | L | E (kelasnya) | E (mapelnya) | L | - |
| Wali kelas* | L | L | E (kelasnya) | L (kelasnya) | E (kelasnya) | - |
| Guru BK* | L | L | L | L | - | - |
| TU / Keuangan | L | L | L | - | - | E |
| Siswa | - | S | S | S | S | S |
| Orang tua | - | S | S | S | S | S |

### Tabel 2: Layanan dan operasional

| Peran | Lab | UKS | Perpus | Sarpras | Buku tamu |
|---|---|---|---|---|---|
| Admin | E | E | E | E | E |
| Kepala sekolah | L | L (rekap) | L | L | L |
| Guru | B | - | B | B | - |
| Wali kelas* | B | L (rekap kelasnya) | B | B | - |
| Laboran | E | - | - | - | - |
| Petugas UKS | - | E | - | - | - |
| Pustakawan | - | - | E | - | - |
| Petugas sarpras | - | - | - | E | - |
| Satpam | - | - | - | - | E |
| Siswa | - | - | B | - | - |
| Orang tua | - | S (ringkasan) | - | - | - |

\* Hak akses tambahan pada akun guru.

---

## 5. Rincian per portal: tugas, akses, batasan

### 5.1 Portal Guru `/guru`

**Guru mapel**
- Tugas: mengajar, absen, input nilai, unggah bahan ajar dan tugas.
- Akses: edit absensi dan nilai **hanya kelas/mapel yang diampu**; lihat jadwal dan data siswa kelasnya; booking lab, pinjam buku, lapor kerusakan.
- Dilarang: keuangan, data kesehatan detail, nilai mapel guru lain.

**Wali kelas (tambahan)**
- Tugas: memantau kelas, menyusun rapor, mencatat perkembangan siswa.
- Akses: lihat semua nilai dan absensi kelasnya; edit catatan wali kelas dan rapor; lihat rekap UKS kelasnya; menghubungi orang tua.

**Guru BK (tambahan)**
- Tugas: konseling, mencatat pelanggaran dan prestasi.
- Akses: edit catatan konseling dan poin siswa; lihat nilai dan absensi seluruh siswa.
- **Catatan konseling bersifat rahasia**: hanya guru BK pembuat catatan (dan peran yang secara eksplisit diberi izin) yang dapat melihat. Tidak tampil ke wali kelas, ortu, siswa, atau pimpinan kecuali rekap agregat.

**Pembina ekskul (tambahan)**
- Tugas: kelola anggota, absensi, dan nilai ekskul.
- Akses: edit data ekskul yang dibina saja.

### 5.2 Portal Siswa `/siswa`
- Tugas: belajar, mengerjakan tugas, memantau capaian.
- Akses: lihat data **sendiri** (jadwal, nilai, absensi, rapor, tagihan); unggah tugas; pinjam buku; lihat pengumuman; edit sebagian profil (foto, kontak).
- Dilarang: data siswa lain, mengubah nilai atau absensi.
- Pengurus kelas/OSIS: modul ringan (input piket kelas, kegiatan OSIS).

### 5.3 Portal Orang Tua `/ortu`
- Tugas: memantau perkembangan anak, membayar tagihan, menerima informasi sekolah.
- Akses: lihat jadwal, nilai, absensi, rapor, tagihan, **ringkasan** kesehatan anaknya saja; terima notifikasi (anak sakit, tagihan, pengumuman); ajukan izin tidak masuk.
- Dilarang: data anak lain, mengedit data akademik.
- **Wajib:** backend memvalidasi bahwa `:id` anak benar-benar anak dari orang tua yang login.

### 5.4 Portal Staf `/staf` (menu per modul)

| Sub-peran | Tugas | Akses |
|---|---|---|
| Laboran | Kelola inventaris, jadwal, peminjaman alat | Edit modul lab; setujui booking guru |
| Petugas UKS | Catat kunjungan sakit, kelola stok obat | Edit data UKS dan kesehatan siswa; kirim notifikasi ke wali kelas dan ortu. **Data sensitif.** |
| Pustakawan | Katalog, peminjaman, denda | Edit modul perpus; lihat daftar siswa untuk peminjaman |
| Petugas sarpras | Tindak lanjut laporan kerusakan, jadwal perawatan | Edit status laporan dan perawatan |
| Satpam | Catat tamu, siswa izin keluar/terlambat, log barang | Input buku tamu dan log; lihat izin keluar yang sudah disetujui. Tanpa akses data akademik |
| TU / Keuangan | Administrasi surat, tagihan, pembayaran SPP, laporan kas | Edit tagihan dan pembayaran; lihat data master. Tanpa akses nilai dan kesehatan |

### 5.5 Portal Pimpinan `/kepsek`

**Kepala sekolah**
- Tugas: memantau dan mengambil keputusan.
- Akses: **lihat saja** semua rekap (akademik, kehadiran, keuangan, UKS rekap tanpa detail medis, laporan); menyetujui dokumen (opsional).

**Wakil kepala sekolah** (lihat dan setujui sesuai bidang)
- Kurikulum: jadwal, nilai, rapor
- Kesiswaan: absensi, poin BK (agregat), OSIS, ekskul
- Sarpras: laporan kerusakan
- Humas: pengumuman, komite

### 5.6 Portal Mitra `/mitra` (opsional)
- Komite sekolah: pengumuman, laporan ringkas, agenda rapat.
- Pengawas: laporan dan rekap yang dibuka sekolah, **tanpa data pribadi siswa**.

---

## 6. Routing

### 6.1 Route bersama (publik / semua peran login)
```
/login
/logout
/lupa-password
/profil
/pengumuman
```

### 6.2 Route per portal

**`/guru`**
```
/guru                       dashboard
/guru/jadwal
/guru/absensi
/guru/nilai
/guru/tugas                 bahan ajar & tugas
/guru/lab/booking
/guru/perpus
/guru/sarpras/lapor
/guru/wali-kelas/*          rekap, rapor, catatan   (butuh peran wali_kelas)
/guru/bk/*                  konseling, poin         (butuh peran guru_bk)
/guru/ekskul/*              anggota, absensi, nilai (butuh peran pembina_ekskul)
```

**`/siswa`**
```
/siswa
/siswa/jadwal
/siswa/nilai
/siswa/absensi
/siswa/tugas
/siswa/rapor
/siswa/tagihan
/siswa/perpus
```

**`/ortu`**
```
/ortu
/ortu/anak                  pilih anak (jika lebih dari satu)
/ortu/anak/:id/jadwal
/ortu/anak/:id/nilai
/ortu/anak/:id/absensi
/ortu/anak/:id/rapor
/ortu/anak/:id/tagihan
/ortu/anak/:id/kesehatan    ringkasan saja
/ortu/izin                  ajukan izin tidak masuk
```

**`/staf`**
```
/staf                       dashboard (menu sesuai modul)
/staf/lab/*                 inventaris, peminjaman, jadwal
/staf/uks/*                 kunjungan, data kesehatan, stok obat
/staf/perpus/*              katalog, peminjaman, denda
/staf/sarpras/*             laporan kerusakan, perawatan
/staf/satpam/*              buku tamu, izin keluar, log
/staf/tu/*                  surat, tagihan, pembayaran, kas
```

**`/kepsek`**
```
/kepsek
/kepsek/akademik            ringkasan nilai & kehadiran
/kepsek/keuangan
/kepsek/uks                 rekap saja
/kepsek/laporan
```

**`/mitra`** *(opsional)*
```
/mitra
/mitra/pengumuman
/mitra/laporan
/mitra/agenda
```

**`/admin/*`** tetap terpisah. Jangan dimodifikasi.

### 6.3 Perilaku routing
- Setelah login, redirect otomatis sesuai peran dasar: guru → `/guru`, siswa → `/siswa`, ortu → `/ortu`, staf → `/staf`, pimpinan → `/kepsek`, mitra → `/mitra`, admin → `/admin`.
- Akses ke portal yang bukan miliknya → **403** atau redirect ke portal miliknya.
- Akses ke sub-route yang butuh peran tambahan tanpa peran itu → **403**.
- Belum login → redirect ke `/login` dengan `redirect` param.

---

## 7. Aturan lingkup data (data scoping)

Setiap query backend wajib difilter sesuai aturan berikut. Jangan mengandalkan filter di frontend.

| Peran | Aturan filter |
|---|---|
| Guru mapel | Kelas dan mapel dari tabel penugasan mengajar milik guru tersebut |
| Wali kelas | `kelas_id` yang diwalikan guru tersebut |
| Guru BK | Seluruh siswa untuk nilai/absensi (lihat); catatan konseling hanya milik pembuat |
| Siswa | `siswa_id` = siswa yang sedang login |
| Orang tua | `siswa_id` ∈ daftar anak yang terhubung ke akun ortu |
| Pembina ekskul | `ekskul_id` yang dibina |
| Staf | Hanya modul yang diberikan |
| Pimpinan | Agregat/rekap; tidak ada edit |
| Mitra | Agregat tanpa data pribadi |

**Aturan umum:** default deny. Jika tidak ada aturan eksplisit yang mengizinkan, akses ditolak.

---

## 8. Data sensitif

| Data | Aturan |
|---|---|
| Kesehatan siswa (UKS) | Edit: petugas UKS. Lihat detail: petugas UKS, ortu anak tersebut (ringkasan). Wali kelas dan kepsek: rekap tanpa detail medis |
| Catatan konseling BK | Hanya pembuat (guru BK) dan peran yang diberi izin eksplisit |
| Keuangan | TU/keuangan (edit), kepsek (lihat rekap), siswa/ortu (tagihan sendiri) |
| Nilai | Guru pengampu (edit), wali kelas dan BK (lihat), siswa/ortu (sendiri) |

Wajib: **audit log** untuk akses dan perubahan data sensitif.

---

## 9. Keamanan dan audit

1. Semua endpoint portal dilindungi middleware autentikasi **dan** otorisasi (permission + scope).
2. Validasi kepemilikan objek (IDOR): untuk setiap `:id` pada URL/API, pastikan objek itu berada dalam lingkup pengguna.
3. **Audit log** untuk: perubahan nilai, perubahan absensi, akses data kesehatan, akses/ubah catatan BK, perubahan tagihan/pembayaran. Catat: siapa, kapan, apa, nilai lama → baru, IP.
4. Hash password dengan algoritma standar framework; terapkan rate limit pada login; sesi/token kedaluwarsa wajar.
5. Pengunggahan file (tugas, bahan ajar): batasi tipe dan ukuran, simpan di luar web root atau gunakan URL bertanda tangan.
6. Jangan membocorkan keberadaan data di pesan error (hindari beda pesan "tidak ditemukan" vs "dilarang" untuk objek di luar lingkup).
7. Jangan menaruh data sensitif di log aplikasi biasa.

---

## 10. Struktur teknis yang disarankan (sesuaikan dengan framework yang ada)

```
routes/
  admin        (sudah ada, jangan diubah)
  guru
  siswa
  ortu
  staf
  kepsek
  mitra
middleware/
  auth
  portal-guard(peran_dasar)       cek prefix portal vs peran dasar
  require-permission(izin)        cek permission granular
  require-extra-role(peran)       wali_kelas, guru_bk, pembina_ekskul
  scope-ownership                 validasi kepemilikan objek (ortu/anak, guru/kelas)
layouts/
  admin (sudah ada)
  portal-guru, portal-siswa, portal-ortu, portal-staf, portal-kepsek, portal-mitra
```

- Menu sidebar dibangun dari **permission** pengguna, bukan dari nama peran yang di-hardcode.
- Gunakan komponen UI dan design system yang sudah dipakai di dashboard admin agar konsisten.
- Pisahkan layout portal dari layout admin.

---

## 11. Integrasi dengan sistem yang sudah ada

Agent wajib melakukan langkah ini **sebelum** menulis kode portal:

1. **Inventaris stack:** framework, bahasa, ORM, library UI, library auth, struktur folder.
2. **Inventaris skema database:** daftar tabel dan relasi. Petakan ke entitas di bawah.
3. **Inventaris auth:** bagaimana user dan peran disimpan sekarang; apakah sudah ada RBAC.
4. **Buat dokumen pemetaan** (`INTEGRATION_MAP.md`) berisi:
   - Entitas spesifikasi → tabel/model nyata
   - Peran spesifikasi → peran/permission nyata
   - Gap: apa yang belum ada dan perlu dibuat (migrasi baru)
5. **Prinsip migrasi:** hanya **menambah** (tabel/kolom baru). Jangan menghapus atau mengubah arti kolom yang sudah dipakai dashboard admin. Semua migrasi harus bisa di-rollback.

### Entitas yang dibutuhkan (cocokkan dengan yang sudah ada)
```
users, roles, permissions (+ pivot)
siswa, guru, orang_tua, staf
relasi_ortu_siswa             (ortu ↔ anak)
kelas, mapel, tahun_ajaran
penugasan_mengajar            (guru ↔ kelas ↔ mapel)
wali_kelas                    (guru ↔ kelas ↔ tahun_ajaran)
jadwal, absensi, nilai, rapor, tugas, pengumpulan_tugas
ekskul, anggota_ekskul
bk_catatan, bk_poin
uks_kunjungan, uks_data_kesehatan, uks_stok_obat
lab_inventaris, lab_booking, lab_peminjaman
perpus_buku, perpus_peminjaman
sarpras_laporan, sarpras_perawatan
tamu, izin_keluar, log_barang
tagihan, pembayaran
notifikasi, pengumuman
audit_log
```

---

## 12. Rencana implementasi bertahap

| Fase | Isi | Kriteria selesai |
|---|---|---|
| 0 | Audit dan `INTEGRATION_MAP.md` | Pemetaan disetujui pemilik proyek |
| 1 | Fondasi: RBAC, middleware, redirect pasca-login, layout portal, audit log | Login mengarah ke portal benar; 403 berfungsi |
| 2 | Portal Guru (mapel, lalu wali kelas, BK, ekskul) | Guru hanya melihat/edit lingkupnya |
| 3 | Portal Siswa | Siswa hanya melihat data sendiri |
| 4 | Portal Orang Tua | Validasi `:id` anak lulus uji |
| 5 | Portal Staf (per modul) | Tiap sub-peran hanya melihat modulnya |
| 6 | Portal Pimpinan | Read-only, agregat |
| 7 | Portal Mitra (opsional) dan notifikasi | Sesuai kebutuhan |

Kerjakan Fase 1 sampai matang karena semua fase lain bergantung padanya. Prioritas portal sesuai kebutuhan: **Guru, Siswa, Orang tua** lebih dulu; Pimpinan bisa menyusul karena hanya membaca data.

---

## 13. Kriteria penerimaan dan pengujian

### Pengujian otorisasi (wajib lulus)
- [ ] Siswa A tidak bisa membuka data siswa B (ubah `:id`/query).
- [ ] Orang tua tidak bisa membuka `/ortu/anak/:id/*` untuk anak yang bukan miliknya.
- [ ] Guru tidak bisa mengedit nilai/absensi di luar kelas/mapel yang diampu.
- [ ] Guru tanpa peran `wali_kelas` mendapat 403 di `/guru/wali-kelas/*`; begitu pula `bk` dan `ekskul`.
- [ ] Catatan konseling tidak terbaca oleh wali kelas, ortu, siswa, maupun kepsek.
- [ ] Detail data kesehatan tidak terbaca oleh peran selain petugas UKS dan ortu anak bersangkutan.
- [ ] Satpam dan TU tidak punya akses nilai/kesehatan.
- [ ] Kepsek tidak punya aksi edit di mana pun.
- [ ] Akses lintas portal (misal siswa ke `/guru`) ditolak.
- [ ] Akses API langsung (tanpa lewat UI) mengikuti aturan yang sama.

### Pengujian fungsional
- [ ] Redirect pasca-login sesuai peran dasar.
- [ ] Menu sidebar hanya menampilkan item yang diizinkan.
- [ ] Peran tambahan menambah menu tanpa membuat portal baru.
- [ ] Audit log tercatat untuk perubahan nilai, absensi, tagihan, dan akses data sensitif.
- [ ] Dashboard admin dan seluruh fitur lama tetap berjalan (regresi).

---

## 14. Pertanyaan terbuka (agent harus konfirmasi ke pemilik proyek bila belum jelas)

1. Framework/stack apa yang dipakai, dan apakah sudah ada library RBAC/auth?
2. Apakah pengguna multi-peran dasar (misal guru yang juga ortu) perlu role switcher atau akun terpisah?
3. Modul mana yang sudah ada datanya (nilai, absensi, keuangan, UKS, dll.) dan mana yang belum?
4. Apakah pembayaran online diperlukan, atau hanya tampilan tagihan?
5. Apakah notifikasi lewat email/WhatsApp/push, atau hanya di dalam aplikasi?
6. Apakah kepala sekolah perlu fitur persetujuan dokumen?
7. Apakah Portal Mitra dibutuhkan?
8. Bagaimana akun siswa dan ortu dibuat (impor massal oleh admin, atau registrasi mandiri)?

---

## 15. Hal yang TIDAK boleh dilakukan agent

- Mengubah atau menghapus fitur, route, atau tabel dashboard admin yang sudah berjalan.
- Membuat tabel duplikat untuk entitas yang sudah ada.
- Mengandalkan pengecekan peran di frontend saja.
- Membuka data sensitif (kesehatan, konseling, keuangan) di luar aturan bagian 8.
- Menjalankan migrasi destruktif atau mengubah data produksi tanpa konfirmasi.
- Menebak jawaban untuk pertanyaan keamanan; catat dan tanyakan.

---

## 16. Format laporan agent setelah tiap fase

Setelah menyelesaikan satu fase, agent melaporkan:
1. Apa yang dibuat/diubah (daftar file dan migrasi)
2. Pemetaan ke sistem yang sudah ada
3. Hasil pengujian dari bagian 13
4. Asumsi yang dibuat dan pertanyaan yang tersisa
5. Langkah berikutnya
