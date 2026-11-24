# Desain: Redesign "Fun" Portal Siswa + Maskot Labi

- **Tanggal:** 2026-11-22
- **Scope fitur:** `FE-SISWA-UI-01` — Re-theme Portal Siswa (web) agar ramah & menyenangkan untuk anak SMP.
- **Status:** Menunggu persetujuan dokumen.

## 1. Maksud & alasan (why)
Portal siswa sekarang bergaya formal/korporat (biru tua + abu-abu). Untuk anak SMP terasa kaku
dan membosankan. Tujuan: portal terasa **menyenangkan, ramah, "out of the box"** lewat maskot
karakter pelajar lucu ("Labi") dan animasi halus — supaya siswa betah dan nyaman memakai portal
(cek tugas, ujian, dsb). Pengguna sudah menyetujui arah ini + maskot lewat mockup bergerak.

## 2. Goals
- Re-theme **hanya Portal Siswa** (`/portal/siswa/**`): palet lebih ceria, sudut membulat, tipografi ramah.
- Maskot **Labi** (digambar sendiri sebagai SVG, bukan foto/aset berlisensi tak jelas) muncul kontekstual:
  sapaan dashboard, state loading, halaman kosong, sukses submit.
- Animasi halus: micro-interaction hover kartu, transisi antar menu, konfetti saat tugas/ujian submit.
- Semua **data, API, dan fitur tetap sama** — ini murni lapisan presentasi.

## 3. Non-goals
- Tidak mengubah portal guru/ortu/kepsek/staf/admin.
- Tidak mengubah kontrak API, DTO, query, atau skema DB.
- Tidak mengubah komponen shared lintas-portal (`loading-spinner`, `empty-state`, `error-state`,
  `data-table`, dll) — dibuat **varian khusus siswa** agar portal lain tak terpengaruh.
- Tidak membangun fitur/logika gamifikasi baru di backend.

## 4. Anti-goals (bikin gagal walau "jalan")
- Animasi berlebihan sampai mengganggu baca/fokus, atau berat di komputer lab & HP kelas bawah.
- Maskot menutupi/menunda akses ke informasi penting (tugas, ujian, tagihan, konseling).
- Konten BK/konseling atau data sensitif ditampilkan dengan nada main-main.

## 5. Batasan (constraints) — dari AGENTS.md
- Tanpa data pribadi nyata; semua contoh fiktif.
- UI & pesan Bahasa Indonesia; identifier/komentar Bahasa Inggris.
- Penambahan dependency harus dijustifikasi (lihat Approaches).
- Perubahan kecil & mudah ditinjau: dipecah per lapisan/commit logis.
- Harus ada state loading/kosong/error di tiap halaman (sudah ada → dipercantik, bukan dihapus).

## 6. Approaches (yang dipertimbangkan)
- **A — Maskot + sentuhan ringan.** Cepat & aman, "wow" sedang. Ditolak: kurang menjawab "out of the box".
- **B — Re-theme penuh portal siswa + maskot hidup. ← DIPILIH.** Paling terasa milik anak SMP,
  tapi data/fitur tetap. Risiko terkelola karena murni presentasi + varian komponen khusus siswa.
- **C — Dashboard ala game (peta/XP/avatar).** Paling berani tapi berat, mengubah alur, lama, berisiko
  menyentuh logika baru di luar PRD. Ditolak untuk sekarang (bisa jadi fase lanjutan).

**Teknik animasi — DIPUTUSKAN:** CSS/Tailwind keyframes murni, **tanpa dependency baru**.

**Intensitas — DIPUTUSKAN:** "lebih berani, ala game" (visual Arah C), TAPI **murni presentasi**:
metafora peta/dunia & gaya playful yang kuat, animasi CSS. **Tanpa logika gamifikasi baru**
(tanpa XP engine, tanpa perubahan backend). Poin yang ditampilkan = data poin yang sudah ada apa adanya.

## 7. Keputusan "how" yang sudah diambil (sebagai usulan saya, bisa dikoreksi)
- Maskot = komponen `<Labi expression=... />` berbasis SVG inline + varian ekspresi (sapa, senang, belajar, tidur).
- Theming lewat token warna baru khusus scope siswa (kelas util/CSS variable di bawah pembungkus `.portal-siswa`),
  tidak mengubah token `pub/brand` yang dipakai portal lain.
- Varian siswa: `StudentLoading`, `StudentEmpty`, `StudentError` (membungkus maskot), dipakai di 15 halaman siswa.
- **Hormati `prefers-reduced-motion`**: animasi mati otomatis bila OS pengguna minta — wajib aksesibilitas (keputusan proses saya).

## 8. Keputusan pengguna — SUDAH DIJAWAB (2026-11-22)
1. **Dependency animasi:** CSS murni, tanpa dependency baru.
2. **Poin:** pakai data poin yang sudah ada apa adanya (TANPA framing XP/gamifikasi baru).
3. **Intensitas:** lebih berani, ala game — tapi murni presentasi (lihat bagian 6).

## 9. Yang diserahkan ke builder
- Pemilihan util Tailwind spesifik, struktur file komponen maskot, urutan migrasi 15 halaman.
- Penulisan uji (render maskot, reduced-motion, state loading/kosong/error per halaman).
