# Inventaris Desain dan Konten Website Publik
## SMP Laboratorium (P2LP Universitas Negeri Malang)

> **Status Dokumen:** Selesai (Fase Audit Langkah 1)  
> **Tanggal:** 15 Oktober 2026  
> **Lokasi Referensi:** `legacy/prototype/public/`  
> **Target Implementasi:** `apps/web/src/app/(public)/`  

---

## 1. Daftar Berkas Legacy yang Diperiksa

Pemeriksaan mendalam dilakukan terhadap seluruh berkas HTML dan stylesheet publik pada direktori `legacy/prototype/public/`:

| Nama Berkas | Ukuran / Baris | Peran & Isi Halaman |
|---|---|---|
| `index.html` | 471 baris (47.8 KB) | Halaman beranda utama: Sticky Header, Hero section YouTube video embed (`BxQztnwi_74`), sambutan Kepala Sekolah, 4 pilar portal, statistik angka kunci, program unggulan, warta sekolah, galeri cuplikan, dan footer 4-kolom. |
| `profil.html` | 275 baris (31.2 KB) | Halaman profil institusi: Sejarah P2LP Universitas Negeri Malang, visi, misi, tujuan, bagan struktur kepemimpinan, profil guru/tenaga pendidik, dan fasilitas sarana prasarana. |
| `akademik.html` | 312 baris (35.6 KB) | Halaman kurikulum & program KBM: Kurikulum Merdeka, Lesson Study, struktur jam belajar, daftar kegiatan ekstrakurikuler, pembinaan prestasi, dan kalender pendidikan. |
| `berita.html` | 230 baris (26.4 KB) | Halaman publikasi berita: Pencarian warta, filter kategori (Akademik, Prestasi, Kesiswaan, Pengumuman), daftar kartu berita bertanggal, dan pagination. |
| `galeri.html` | 198 baris (22.1 KB) | Halaman dokumentasi visual: Galeri kegiatan sekolah, filter album (Semua, Akademik, Kesiswaan, Sarpras), dan modal lightbox foto. |
| `kontak.html` | 161 baris (21.5 KB) | Halaman kontak & pengaduan: Informasi sekretariat & tata usaha, jam pelayanan, formulir kirim pesan online, dan iframe Google Maps kampus UM. |
| `ppdb.html` | 412 baris (45.3 KB) | Halaman informasi PPDB: Alur 5 langkah, 4 jalur pendaftaran, jadwal gelombang aktif, persyaratan berkas, biaya pendidikan transparan, FAQ, dan widget cek kelulusan instan. |
| `masuk.html` | 120 baris (14.2 KB) | Pintu masuk autentikasi portal terpadu (diarahkan ke `/masuk` di Next.js). |
| `css/main.css` | 451 baris (9.9 KB) | Definisi token CSS variabel (`:root`), palet warna, tipografi, bayangan (*shadow*), radius sudut, dan reset visual. |
| `css/demo-tailwind.css` | 1.840 baris (37.5 KB) | Utilitas kelas Tailwind CSS kustom prototipe legacy. |

---

## 2. Ringkasan Visual dan Komponen Antarmuka

### A. Struktur Header & Navigasi
- **Sticky Navbar**: Header menempel di bagian atas saat halaman digulir (`sticky top-0 z-50`), latar belakang `bg-navy` (`#0A3D62`) dengan shadow halus.
- **Logo Institusi**: Logo SMP Laboratorium (44×44 px / 48×48 px) berdampingan dengan teks judul berkas dua baris:
  - Baris 1: `SMP Laboratorium` (font Fraunces / bold, warna putih).
  - Baris 2: `P2LP Universitas Negeri Malang` (font Inter / medium, warna gold-soft `#F4E2BD`).
- **Menu Navigasi Desktop**:
  - `Beranda` (`/`)
  - `Profil` (`/profil`)
  - `Akademik` (`/akademik`)
  - `Berita` (`/berita`)
  - `Galeri` (`/galeri`)
  - `Kontak` (`/kontak`)
  - *Indikator Aktif*: Garis bawah emas (`after:bg-gold after:h-[2px] after:rounded-full`) dan teks `text-gold-soft`.
- **Aksi Cepat (Call-to-Action)**:
  - Tombol Utama: `PPDB Online` (latar emas `#C98A1F`, teks navy deep `#062940`, sudut bulat 10px / `rounded-[10px]`, hover shadow).
  - Tombol Sekunder: `Portal Akademik` / `Buka Portal Saya` (outline putih transparan dengan border `border-white/40`, teks putih).
- **Menu Mobile (Drawer / Sidebar)**:
  - Pemicu: Tombol hamburger (`aria-label="Toggle navigation menu"`).
  - Panel Drawer: Meluncur dari kanan (`translate-x-full` $\rightarrow$ `translate-x-0`), lebar `min(320px, 85vw)`, latar navy `#0A3D62`.
  - Aksesibilitas: Mengharuskan `aria-expanded`, tombol close `X`, backdrop gelap, event `Escape` untuk menutup dan mengembalikan fokus keyboard.

### B. Struktur Footer Institusi (4-Kolom)
1. **Kolom 1 — Identitas Sekolah**: Logo sekolah, nama resmi institusi di bawah P2LP UM, dan ringkasan visi membina generasi unggul berkarakter cerdas, religius, dan berwawasan masa depan.
2. **Kolom 2 — Tautan Cepat**: Link internal ke Profil, Akademik, Berita, Galeri, dan Pendaftaran Siswa Baru (PPDB).
3. **Kolom 3 — Akses Portal**: Tautan ke Portal Siswa, Portal Guru & Staf, Portal Orang Tua, dan Cek Status PPDB.
4. **Kolom 4 — Hubungi Kami**:
   - Alamat kampus fisik dengan ikon lokasi.
   - Telepon kantor resmi.
   - WhatsApp layanan admin resmi.
   - Email resmi sekolah.
5. **Baris Bawah (Sub-Footer)**:
   - Hak Cipta: `© 2026 SMP Laboratorium. Seluruh Hak Cipta Dilindungi.`
   - Afiliasi: `Pusat Pengembangan Laboratorium Pendidikan (P2LP) Universitas Negeri Malang`.
   - Garis pemisah atas berwarna emas setebal 4px (`border-t-4 border-gold`).

---

## 3. Referensi Aset dan Lokasi Sumber

| Aset | Lokasi Sumber Legacy | Lokasi Target Next.js | Status Ketersediaan |
|---|---|---|---|
| **Logo Resmi SMP Lab UM** | `legacy/prototype/public/images/logo.png` | `apps/web/public/images/logo.png` | ✅ **Tersedia** (Ukuran 226 KB, terverifikasi tajam dan resmi). |
| **Video Profil Sekolah** | YouTube ID `BxQztnwi_74` | YouTube Embed (`https://www.youtube-nocookie.com/...`) | ✅ **Tersedia** (Video profil resmi SMP Laboratorium UM). |
| **Poster Video Fallback** | `https://img.youtube.com/vi/BxQztnwi_74/maxresdefault.jpg` | Inline poster image URL / Next Image | ✅ **Tersedia**. |
| **Ikon Antarmuka** | Lucide Icons (inline SVG) | Paket npm `lucide-react` (sudah terpasang di `package.json`) | ✅ **Tersedia Lengkap**. |

---

## 4. Palet Warna & Verifikasi Rasio Kontras (WCAG 2.1)

### A. Palet Warna Desain (Tokens)

```css
/* Namespace 'pub' untuk Website Publik */
--pub-navy: #0A3D62;        /* Warna Primer Header & Brand */
--pub-navy-deep: #062940;   /* Warna Footer & Kontras Maksimal */
--pub-navy-light: #155B8A;  /* Hover state / Aksen Biru Terang */
--pub-navy-subtle: #EBF3F8; /* Latar badge / elemen aksen lembut */

--pub-gold: #C98A1F;        /* Aksen Emas Prestasi / Tombol Primer */
--pub-gold-hover: #B27615;  /* Hover Tombol Emas */
--pub-gold-soft: #F4E2BD;   /* Teks Emas di atas Latar Navy */
--pub-gold-subtle: #FEF9F0; /* Latar kartu PPDB / highlight */

--pub-green: #1E7A46;       /* Aksen Karakter / WhatsApp link */
--pub-green-soft: #C3E6D0;  /* Latar badge WhatsApp / status */

--pub-bg-app: #F6F7F9;      /* Latar Halaman Bersih */
--pub-bg-surface: #FFFFFF;  /* Latar Kartu & Konten */
--pub-text-main: #1B2430;   /* Warna Teks Tubuh Utama */
--pub-text-muted: #4A5563;  /* Warna Teks Sekunder / Keterangan */
--pub-border: #DFE3E8;      /* Garis Batas Netral */
```

### B. Pengukuran Rasio Kontras Aksesibilitas (WCAG 2.1)

Pengukuran rasio kontras dihitung secara matematis menggunakan formula luminansi relatif standar WCAG:

| Elemen Antarmuka | Warna Foreground (Teks/Icon) | Warna Background (Latar) | Rasio Kontras | Status Kepatuhan WCAG |
|---|---|---|---|---|
| **Body text utama** | `#1B2430` (Text Main) | `#FFFFFF` (Surface Putih) | **15.65 : 1** | ✅ **LULUS (AAA)** |
| **Body text pada kanvas** | `#1B2430` (Text Main) | `#F6F7F9` (Latar App) | **14.60 : 1** | ✅ **LULUS (AAA)** |
| **Heading utama** | `#0A3D62` (Navy) | `#FFFFFF` (Surface Putih) | **11.31 : 1** | ✅ **LULUS (AAA)** |
| **Heading gelap** | `#062940` (Navy Deep) | `#FFFFFF` (Surface Putih) | **15.01 : 1** | ✅ **LULUS (AAA)** |
| **Tombol CTA PPDB** | `#062940` (Navy Deep) | `#C98A1F` (Gold) | **5.10 : 1** | ✅ **LULUS (AA Teks Normal & AAA Teks Tebal/Besar)** |
| **Teks Navigasi Aktif** | `#F4E2BD` (Gold Soft) | `#0A3D62` (Navy Header) | **8.86 : 1** | ✅ **LULUS (AAA)** |
| **Teks Navigasi Standar** | `#FFFFFF` (Putih) | `#0A3D62` (Navy Header) | **11.31 : 1** | ✅ **LULUS (AAA)** |
| **Teks Footer Resmi** | `#CCCCCC` (Abu Terang 80%) | `#062940` (Navy Deep) | **9.35 : 1** | ✅ **LULUS (AAA)** |
| **Teks Keterangan Muted** | `#4A5563` (Muted Slate) | `#FFFFFF` (Surface Putih) | **7.58 : 1** | ✅ **LULUS (AAA)** |
| **Teks Keterangan Muted** | `#4A5563` (Muted Slate) | `#F6F7F9` (Latar App) | **7.07 : 1** | ✅ **LULUS (AAA)** |
| **Focus Indicator Ring** | `#C98A1F` (Gold) | `#0A3D62` (Navy Header) | **3.84 : 1** | ✅ **LULUS (UI Components Target $\ge$ 3:1)** |
| *Peringatan Khusus:* **Teks Emas di atas Putih** | `#C98A1F` (Gold) | `#FFFFFF` (Surface Putih) | **2.94 : 1** | ❌ **GAGAL (< 4.5:1)** *(Dilarang digunakan sebagai warna teks kecil di atas latar putih; hanya boleh dipakai sebagai aksen garis border atau latar tombol dengan teks navy deep)* |

---

## 5. Konten Resmi yang Berhasil Diverifikasi

Berdasarkan seluruh dokumen prototipe dan data institusi sekolah:
- **Nama Sekolah**: SMP Laboratorium
- **Nama Lembaga Pengelola**: Pusat Pengembangan Laboratorium Pendidikan (P2LP) Universitas Negeri Malang
- **Alamat Kampus**: Jl. Simpang Bogor No. T-7, Sumbersari, Kec. Lowokwaru, Kota Malang, Jawa Timur 65145
- **Telepon Resmi Kantor**: `(0341) 588550` (Tautan semantik: `tel:+62341588550`)
- **WhatsApp Layanan Informasi / PPDB**: `0851-2608-8473` (Tautan semantik: `https://wa.me/6285126088473`)
- **Email Resmi**: `info@smplab-um.sch.id` (Tautan semantik: `mailto:info@smplab-um.sch.id`)
- **Jam Pelayanan Kantor**:
  - Senin – Jumat: 07.00 – 15.30 WIB
  - Sabtu: 07.30 – 12.00 WIB (Khusus Pelayanan PPDB)
- **Slogan / Moto Sekolah**: *"Beradab, Kreatif, Berprestasi"*
- **Visi Pokok**: *"Mewujudkan Generasi Beriman, Berkarakter, Berprestasi, dan Berbudaya"*

---

## 6. Konten yang Belum Tersedia (Placeholder Tercatat)

Untuk mematuhi Larangan Mutlak No. 7 (*"Jangan mengarang identitas sekolah, foto, statistik, maupun tautan sosial"*):

| Elemen Konten | Status di Legacy | Penanganan / Placeholder | Keputusan yang Dibutuhkan |
|---|---|---|---|
| **Akun Media Sosial Resmi** (Instagram, YouTube, Facebook, Twitter) | Tidak ditemukan tautan akun spesifik di HTML (hanya placeholder `#`) | Sesuai aturan: **Tidak menampilkan tautan `#` palsu di footer**. Hanya mencantumkan kanal kontak resmi yang terverifikasi (Telepon, WhatsApp, Email). | Sekolah dapat memberikan URL profil media sosial resmi untuk ditambahkan pada Langkah 2/3. |
| **Kanal YouTube Video Profil** | Tersemat embed `BxQztnwi_74` | Menggunakan ID video tersebut sebagai referensi profil institusi. | Konfirmasi dari pihak sekolah apakah video `BxQztnwi_74` tetap menjadi video profil utama tahun 2026/2027. |
| **Foto Kepala Sekolah & Sambutan** | Teks sambutan ada di `index.html`, namun foto menggunakan placeholder generik | Diberi placeholder terstruktur `[FOTO KEPALA SEKOLAH RESMI]` pada saat konversi beranda di Langkah 2. | Menunggu berkas foto resmi kepala sekolah. |

---

## 7. Rekomendasi Implementasi Langkah 1

1. **Namespace Token Tailwind**:
   - Daftarkan token `pub-*` ke `tailwind.config.ts` di bawah `colors.pub`, sehingga tidak bertabrakan dengan token `brand-*` milik admin dan portal.
2. **Tipografi Terisolasi**:
   - Muat font `Fraunces` (heading) dan `Inter` (sans) melalui `next/font/google` dengan CSS variables `--font-fraunces` dan `--font-inter`.
   - Terapkan CSS variable tersebut secara eksklusif pada `apps/web/src/app/(public)/layout.tsx` tanpa mencemari layout admin atau portal.
3. **Komponen Bersama**:
   - `src/components/shell/public/PublicHeader.tsx`: Desktop sticky navbar + mobile accessible drawer dengan keyboard trap handling & Escape listener.
   - `src/components/shell/public/PublicFooter.tsx`: Footer 4-kolom institusional dengan kontak resmi dan copyright terverifikasi.
   - `src/components/shell/public/SkipLink.tsx`: Tautan aksesibilitas navigasi cepat ke `#main-content`.
4. **Layout Publik Terpadu**:
   - `src/app/(public)/layout.tsx`: Membungkus seluruh rute publik termasuk sub-rute PPDB yang sudah ada (`/ppdb`, `/ppdb/daftar`, `/ppdb/masuk`, `/ppdb/dashboard`), memastikan header dan footer menyatu tanpa merusak form atau guard yang berjalan.
