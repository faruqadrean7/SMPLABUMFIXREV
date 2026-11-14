# Prompt Tugas: FE-CMS-PUBLIC — Sambungkan Konten CMS ke Website Publik (Bertahap)

> Salin blok per TAHAP. Diverifikasi dari kode 2026-11-02 (audit admin+CMS).
> **Temuan inti:** backend CMS SUDAH LENGKAP (endpoint publik + field site-settings tersedia). Masalahnya murni di FRONTEND publik yang tidak membaca data itu (mengabaikan endpoint, atau hardcode "SESUAI MOCKUP"). **Semua tahap FE-only — JANGAN ubah backend.**

## Konteks (berlaku semua tahap)
Admin CMS (apps/web/src/app/(admin)/admin/cms/page.tsx) punya 5 tab: profile, posts,
events (Agenda), downloads, albums. Yang SUDAH tampil di publik: posts (/berita), albums
(/galeri), site-settings (header /profil, /kontak, beranda). Yang TIDAK pernah tampil /
masih hardcode: Agenda, Downloads, sebagian beranda, /akademik, /kontak.

Backend yang SUDAH tersedia (apps/api/routes/api/cms.php — JANGAN diubah):
  GET /public/events, /public/events/{id}        (EventController::publicIndex/publicShow)
  GET /public/downloads, /public/downloads/{id}  (DownloadController)
  GET /public/bulletins, /public/bulletins/{id}  (BulletinController)
  GET /public/teachers, /public/staff            (PublicDirectoryController)
  GET /public/site                               (SiteSettingController::publicSite)
Tipe SiteProfile (apps/web/src/features/cms/types.ts:50) SUDAH memuat:
  identity, contact, address, social_media,
  profile.{ vision, mission[], principal_greeting, accreditation, featured_programs[] }
FE publik membaca via publicApi (apps/web/src/features/public-site/public-api.ts) yang saat
ini TIDAK punya method events/downloads/bulletins/teachers.

Ikuti AGENTS.md. Tiap tahap = satu ID/commit. Jalankan typecheck+lint+uji tiap tahap.
Pola yang sudah ada & harus ditiru: publicApi.getPublicPosts / getPublicAlbums +
halaman /berita, /galeri (gunakan useQuery, state loading/kosong/error).

---

## TAHAP 1 — FE-CMS-PUBLIC-01: Tampilkan Agenda & Unduhan di web (P1)
> Konten yang admin isi tapi hilang dari publik. Endpoint sudah ada → FE saja.

```
Kerjakan FE-CMS-PUBLIC-01: tampilkan konten CMS "Agenda" (events) dan "Unduhan"
(downloads) di website publik. Backend sudah menyediakan endpoint; JANGAN ubah backend.
Ikuti AGENTS.md. Tulis rencana singkat; tunggu persetujuan untuk nama rute.

Masalah: admin mengelola Agenda (tab events) & Unduhan (tab downloads) di /admin/cms,
cmsApi menulis ke /events & /downloads, TAPI tidak ada halaman publik yang menampilkannya
dan publicApi tidak punya method-nya → konten tak pernah muncul.

Yang dikerjakan:
1. Tambah di apps/web/src/features/public-site/public-api.ts (tiru getPublicPosts):
   - getPublicEvents(params?)   → GET /public/events
   - getPublicEvent(id)         → GET /public/events/{id}  (opsi, bila perlu detail)
   - getPublicDownloads(params?) → GET /public/downloads
   Tambah tipe PublicEventItem / PublicDownloadItem sesuai payload backend
   (verifikasi bentuknya: baca EventController::publicIndex & DownloadController::publicIndex
   + model Event/Download di apps/api).
2. Buat halaman publik (route di bawah grup (public), tiru struktur /berita):
   - Agenda kegiatan (mis. /agenda) — daftar event + state loading/kosong/error.
   - Unduhan (mis. /unduhan) — daftar berkas unduhan dengan tautan file nyata.
3. Tautkan di navigasi publik (navbar/footer publik) ke rute baru itu.
   PENTING: pastikan href cocok rute (hindari 404 — lihat pola FE-NAV-404).

KEPUTUSAN yang perlu ditegaskan: nama rute publik ('/agenda' vs '/kegiatan',
'/unduhan' vs '/downloads') dan apakah Agenda juga ditampilkan sebagai seksi di beranda.

AC:
- AC1 Event yang dibuat admin muncul di halaman publik Agenda (bukan hardcode).
- AC2 Download yang dibuat admin muncul & dapat diunduh di halaman publik.
- AC3 State loading/kosong/error lengkap; kosong → empty-state (bukan data contoh).
- AC4 Link navigasi publik ke rute baru valid (tidak 404).
Verifikasi: typecheck+eslint; uji FE berawalan FE-CMS-PUBLIC-01 untuk mapper publicApi;
E2E: login admin → buat 1 event + 1 download → buka halaman publik → muncul.
Commit: feat(fe-cms-public-01): tampilkan agenda & unduhan CMS di website publik
```

---

## TAHAP 2 — FE-CMS-PUBLIC-02: Beranda & Kontak baca site-settings (P2)
> Hentikan blok "SESUAI MOCKUP" hardcode; pakai field SiteProfile yang sudah ada.

```
Kerjakan FE-CMS-PUBLIC-02: ganti konten beranda & kontak yang hardcode dengan data
site-settings (SiteProfile) yang SUDAH tersedia dari /public/site. FE-only.
Ikuti AGENTS.md. Tulis rencana singkat.

Masalah: beranda (apps/web/src/app/(public)/page.tsx) sudah fetch siteData via
publicApi.getPublicSite(), tapi seksi "Tentang Kami & Sambutan Kepala Sekolah" dan
"Keunggulan Sekolah" masih HARDCODE (komentar "SESUAI MOCKUP"). /kontak
(apps/web/src/app/(public)/kontak/page.tsx) punya directChannels hardcode walau siteData
sudah di-fetch.

Yang dikerjakan (hanya baca field SiteProfile yang sudah ada):
- Sambutan Kepala Sekolah → siteData.profile.principal_greeting
- Tentang/visi-misi → siteData.profile.vision / .mission
- Keunggulan sekolah → siteData.profile.featured_programs (dan/atau .accreditation)
- Kontak → siteData.contact / siteData.social_media / siteData.address
  (ganti directChannels hardcode).
Bila field kosong → tampilkan empty-state/sembunyikan seksi; JANGAN mengarang (§6.2/§9).

AC:
- AC1 Mengubah data di tab Profil CMS → berubah di beranda & kontak (tersambung).
- AC2 Tidak ada lagi teks/kontak hardcode untuk bagian yang punya field SiteProfile.
- AC3 Field kosong → seksi kosong/tersembunyi yang rapi, bukan placeholder karangan.
Verifikasi: typecheck+eslint; E2E: ubah principal_greeting/kontak di CMS → cek beranda/kontak.
Commit: fix(fe-cms-public-02): beranda & kontak baca site-settings (stop hardcode)
```

---

## TAHAP 3 — FE-CMS-PUBLIC-03: Halaman Akademik CMS-driven (P2)
> KEPUTUSAN DIKUNCI (2026-11-02): OPSI 5A — FE-only via SiteProfile. Tidak ada entitas
> CMS baru / tidak ada tiket backend. Bagian kurikulum permanen tetap statis.
```
Kerjakan FE-CMS-PUBLIC-03: jadikan bagian tertentu halaman /akademik memakai data CMS
(SiteProfile) yang SUDAH ada, bukan konten statis. FE-only — JANGAN ubah backend,
JANGAN buat entitas CMS baru. Ikuti AGENTS.md. Tulis rencana singkat.

Masalah: apps/web/src/app/(public)/akademik/page.tsx 100% statis (TALENT_CLUSTER_IMAGES
hardcode, komentar "SESUAI MOCKUP"); tak memanggil publicApi sama sekali → sekolah tak
bisa mengeditnya.

Yang dikerjakan (OPSI 5A — hanya baca field SiteProfile yang sudah ada via
publicApi.getPublicSite(), pola sama seperti beranda/kontak di Tahap 2):
  1. Pita Kredensial / Akreditasi: skor & status dari siteData.profile.accreditation.
  2. 4 Program Unggulan: dari siteData.profile.featured_programs BILA diisi di CMS;
     bila KOSONG → fallback ke 4 program baku. PENTING: teks fallback (mis. Reguler,
     ICP, Tahfidz, Inklusi) WAJIB dikonfirmasi benar oleh sekolah sebelum dipakai —
     jangan mengarang nama/arti program (§6.2/§8). Bila ragu, tampilkan empty-state,
     bukan karangan.
  3. CTA Konsultasi / kontak: dari siteData.contact (telp/email/WA/alamat).
BAGIAN BAKU TETAP STATIS (jangan di-wire): pendekatan Lesson Study UM dan tabel
Karakter Berjenjang VII–IX — ini kurikulum permanen sekolah, bukan konten CMS.

AC:
- AC1 Akreditasi, program unggulan, dan CTA kontak membaca SiteProfile nyata
  (berubah saat admin ubah tab Profil CMS).
- AC2 featured_programs kosong → fallback terkonfirmasi / empty-state rapi, TIDAK
  mengarang; bagian kurikulum permanen tetap statis sesuai keputusan.
- AC3 Tidak ada entitas/endpoint backend baru.
Verifikasi: typecheck+eslint; E2E: ubah accreditation/featured_programs/contact di CMS
→ cek /akademik.
Commit: feat(fe-cms-public-03): akademik baca konten CMS (akreditasi, program, kontak)
```

---

## TAHAP 4 — FE-CMS-PUBLIC-04: Buletin & Direktori Pendidik (P3)
> KEPUTUSAN DIKUNCI (2026-11-02): 6A.2 (buletin AKTIF penuh) + 6B.2 (halaman
> /pendidik tersendiri). Pecah jadi dua sub-tiket/commit agar mudah ditinjau.
> Endpoint backend SUDAH ADA — FE-only, JANGAN ubah backend.

### 4a — FE-CMS-PUBLIC-04a: Buletin sekolah (tab CMS ke-6 + halaman /buletin)
```
Kerjakan FE-CMS-PUBLIC-04a: aktifkan fitur Buletin/Majalah sekolah secara penuh.
Backend sudah ada (CRUD admin /bulletins + publik /public/bulletins); JANGAN ubah backend.
Ikuti AGENTS.md. Tulis rencana singkat.

Yang dikerjakan:
1. Admin: tambah tab ke-6 "Buletin" di apps/web/src/app/(admin)/admin/cms/page.tsx
   (tipe tab di baris ~57 jadi 'profile'|'posts'|'events'|'downloads'|'albums'|'bulletins').
   CRUD meniru pola tab Unduhan/Downloads (judul, edisi/tanggal, berkas PDF, status).
   Tambah method cmsApi untuk /bulletins (GET/POST/PUT/DELETE) meniru downloads.
2. Publik: method publicApi.getPublicBulletins() → GET /public/bulletins (tiru
   getPublicDownloads dari Tahap 1) + halaman publik /buletin (daftar edisi + unduh PDF,
   state loading/kosong/error). Verifikasi payload: baca BulletinController::publicIndex
   + model Bulletin di apps/api sebelum memetakan tipe.
3. Tautkan /buletin di navigasi publik; pastikan href valid (hindari 404 — pola FE-NAV-404).

AC: admin buat 1 buletin → muncul & bisa diunduh di /buletin (bukan hardcode);
tab Buletin CRUD berfungsi; state lengkap; link navigasi valid.
Verifikasi: typecheck+eslint; uji mapper publicApi berawalan FE-CMS-PUBLIC-04a;
E2E admin → buat buletin → buka /buletin.
Commit: feat(fe-cms-public-04a): fitur buletin sekolah (CMS + halaman publik)
```

### 4b — FE-CMS-PUBLIC-04b: Halaman publik Direktori Pendidik (/pendidik)
```
Kerjakan FE-CMS-PUBLIC-04b: buat halaman publik direktori guru & staf.
Backend sudah ada (/public/teachers, /public/staff — PublicDirectoryController);
JANGAN ubah backend. Ikuti AGENTS.md. Tulis rencana singkat.

Yang dikerjakan:
1. publicApi: getPublicTeachers() & getPublicStaff() (tiru getPublicPosts). Verifikasi
   payload: baca PublicDirectoryController + model/resource terkait di apps/api.
   PRIVASI (§6): tampilkan HANYA field profesional yang memang disediakan endpoint
   publik (mis. nama, jabatan/mapel, foto). JANGAN tampilkan data pribadi sensitif
   (NIK, no. HP pribadi, alamat) meski ada di payload — pilih field secukupnya.
2. Buat halaman publik tersendiri app/(public)/pendidik/page.tsx: seksi Guru & seksi
   Tenaga Kependidikan, grid kartu, state loading/kosong/error.
3. Tautkan "/pendidik" di navigasi publik (navbar/footer) + boleh tautkan dari /profil
   (seksi Dewan Pendidik → "Lihat semua"). Pastikan href valid (hindari 404).

AC: /pendidik menampilkan guru & staf dari endpoint nyata (bukan hardcode);
hanya field profesional non-sensitif; state lengkap; link navigasi valid.
Verifikasi: typecheck+eslint; uji mapper berawalan FE-CMS-PUBLIC-04b;
E2E: buka /pendidik → daftar tampil.
Commit: feat(fe-cms-public-04b): halaman publik direktori pendidik
```

---

## Catatan untuk Anda (pemberi tugas)
- **Kabar baik:** tidak ada pekerjaan backend. Semua endpoint & field sudah jadi — ini murni menyambungkan FE publik ke data yang sudah tersedia (pola sama seperti berita/galeri yang sudah jalan).
- **Status (2026-11-02):** Tahap 1 (`1f6927d`) & Tahap 2 (`01c8ccf`) SUDAH mendarat.
  Sisa: Tahap 3 (akademik) & Tahap 4a/4b (buletin, pendidik).
- **Urutan sisa:** Tahap 3 (akademik 5A) → Tahap 4a (buletin) → Tahap 4b (pendidik).
- **Base branch:** `develop`. Bisa 1 branch `feat/cms-public` dengan commit bertahap, atau PR per tahap.
- **Semua keputusan sudah DIKUNCI** (tidak ada lagi yang perlu ditanyakan agent):
  Tahap 3 = OPSI 5A (FE-only via SiteProfile); Tahap 4a = buletin aktif penuh (6A.2);
  Tahap 4b = halaman /pendidik tersendiri (6B.2). Satu catatan tersisa: teks fallback
  4 program unggulan di /akademik harus dikonfirmasi benar oleh sekolah sebelum dipakai.
