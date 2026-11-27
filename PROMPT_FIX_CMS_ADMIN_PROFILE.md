# Prompt Tugas: FE-CMS-ADMIN-01 — Editor Social Media & Program Unggulan di Tab Profil CMS

> Salin blok PROMPT di bawah. Semua path, baris, tipe, dan titik wiring sudah diverifikasi
> langsung dari kode pada 2026-11-02 (audit admin, kondisi branch `develop` setelah merge
> CMS publik `cc62db8`). **FE-only — JANGAN ubah backend, JANGAN ubah tipe (sudah memuat kedua field).**

## Konteks temuan (sudah diaudit — verifikasi secukupnya, jangan audit ulang)
Website publik SUDAH membaca dua field site-settings, tetapi **tab Profil CMS tidak punya
form untuk mengisinya**, sehingga sekolah tak bisa mengaturnya dan seksinya selalu kosong /
jatuh ke fallback hardcode:

| Field | Dibaca publik di | Masalah |
|---|---|---|
| `social_media` (instagram/youtube/facebook/twitter/tiktok) | `apps/web/src/app/(public)/kontak/page.tsx:57` (`socialLinks`) + footer | Admin hanya bisa isi `contact.whatsapp`; tak ada editor social_media → link sosial di /kontak **selalu kosong** |
| `profile.featured_programs[]` | `apps/web/src/app/(public)/page.tsx:566` (seksi "Keunggulan") + `apps/web/src/app/(public)/akademik/page.tsx:93` | Tak ada editor → beranda **selalu pakai fallback `APPROACH_PILLARS` hardcode** |

**Kabar baik (kenapa ini kecil):**
- Tipe `SiteSettingsPayload` di `apps/web/src/features/cms/types.ts` SUDAH memuat `social_media`
  dan `profile.featured_programs` — **tidak perlu ubah tipe**.
- Handler simpan `apps/web/src/app/(admin)/admin/cms/page.tsx:332` memakai `{ ...profileForm,
  profile: { ...profileForm.profile, mission } }` — begitu kedua field masuk ke `profileForm`,
  payload otomatis terkirim. **Backend sudah menerima field ini** (SiteProfile memuatnya).

Tipe yang relevan (`apps/web/src/features/cms/types.ts`):
```
interface SiteSocialMedia { instagram?; youtube?; facebook?; twitter?; tiktok?; }   // :23
interface FeaturedProgram { title: string; description?: string; icon?: string; }   // :44
```

---

## PROMPT (siap pakai)

```
Kerjakan FE-CMS-ADMIN-01: tambahkan editor "Social Media" dan "Program Unggulan"
(featured_programs) pada tab Profil halaman admin CMS, agar field yang SUDAH dibaca
website publik dapat diisi sekolah. FE-only — JANGAN ubah backend maupun tipe.
Ikuti AGENTS.md. Tulis rencana singkat dulu.

File tunggal yang disentuh: apps/web/src/app/(admin)/admin/cms/page.tsx
(kecuali bila perlu komponen input kecil — tetap di dalam folder cms).

Empat titik wiring (semua sudah terverifikasi):

1) STATE AWAL — profileForm useState (sekitar baris 86–118):
   - Tambah pada level atas objek: social_media: { instagram:'', youtube:'', facebook:'',
     twitter:'', tiktok:'' }
   - Tambah di dalam `profile`: featured_programs: []   (FeaturedProgram[])

2) LOAD/RESET — saat memetakan `d` (data site) ke form (sekitar baris 228–252):
   - Tambah: social_media: { instagram: d.social_media?.instagram || '', youtube:
     d.social_media?.youtube || '', facebook: d.social_media?.facebook || '', twitter:
     d.social_media?.twitter || '', tiktok: d.social_media?.tiktok || '' }
   - Di dalam `profile`: featured_programs: d.profile?.featured_programs || []

3) SIMPAN — handler save (sekitar baris 325–338):
   - `...profileForm` sudah membawa social_media, dan `...profileForm.profile` sudah membawa
     featured_programs — JADI tidak perlu perubahan besar. HANYA: sebelum kirim, buang baris
     program yang title-nya kosong:
       featured_programs: (profileForm.profile?.featured_programs || [])
         .filter(p => p.title && p.title.trim().length > 0)
     dan sertakan ke payload.profile (bersama mission yang sudah ada). Jangan kirim program
     tanpa judul.

4) UI — di dalam render tab Profil (activeTab === 'profile'):
   a. Seksi "Media Sosial": letakkan dekat blok Kontak (setelah input WhatsApp, sekitar
      baris 1017). 5 input teks (Instagram, YouTube, Facebook, Twitter/X, TikTok) dengan
      pola onChange yang sama seperti input kontak:
        setProfileForm({ ...profileForm, social_media: { ...profileForm.social_media,
          instagram: e.target.value } })
      Beri placeholder contoh (mis. "@sekolah atau URL lengkap") dan keterangan bahwa
      boleh username atau URL (publik menormalkan keduanya).
   b. Seksi "Program Unggulan": letakkan dekat Visi/Misi (sekitar baris 1081–1100).
      Editor daftar baris yang bisa tambah/hapus:
        - Setiap baris: input Judul (wajib), input Deskripsi (opsional), input Ikon
          (opsional — nama ikon lucide; VERIFIKASI dulu bagaimana /akademik memakai
          fp.icon di akademik/page.tsx sebelum memberi label, agar konsisten).
        - Tombol "Tambah Program" menambah objek kosong { title:'', description:'', icon:'' }.
        - Tombol hapus per baris.
        - Update immutable: map index untuk mengubah satu field, filter untuk menghapus.
      Gunakan komponen UI yang sudah ada (Button, input bergaya sama); JANGAN buat pola baru.

Batasan:
- JANGAN ubah backend/route/controller/model, JANGAN ubah src/features/cms/types.ts
  (kedua field sudah ada).
- JANGAN sentuh tab lain (posts/events/downloads/albums/bulletins) atau field profil lain.
- Jangan mengarang data contoh; kedua seksi boleh kosong (publik sudah menangani kosong:
  /kontak menyembunyikan social yang kosong, beranda fallback ke pilar bawaan).
- Teks UI Bahasa Indonesia; identifier Bahasa Inggris (AGENTS.md §4).

Acceptance Criteria:
AC1 Tab Profil menampilkan editor Media Sosial (5 platform) dan Program Unggulan
    (tambah/edit/hapus baris).
AC2 Nilai tersimpan: setelah Simpan lalu muat ulang halaman, isian tetap ada
    (round-trip via cmsApi.updateSiteSettings → getSiteSettings).
AC3 Program tanpa judul TIDAK ikut tersimpan (difilter).
AC4 Hasilnya tampil di publik: isi Instagram → muncul di /kontak (socialLinks);
    isi 1+ Program Unggulan → menggantikan fallback APPROACH_PILLARS di beranda
    (seksi "Keunggulan") dan muncul di /akademik.
AC5 Tanpa isi → tidak error; /kontak menyembunyikan sosial kosong, beranda tetap
    fallback rapi.

Verifikasi sebelum klaim selesai:
- cd apps/web && pnpm typecheck   (bila error hanya di .next/types, hapus .next lalu ulangi)
- cd apps/web && pnpm eslint src/app/\(admin\)/admin/cms/page.tsx
- E2E manual (kredensial uji di memory, role dengan permission cms.site.manage):
  login admin → /admin/cms tab Profil → isi Instagram + tambah 1 Program Unggulan →
  Simpan → muat ulang (nilai bertahan) → buka /kontak (link IG muncul) & beranda
  (kartu Keunggulan = program yang diisi, bukan pilar bawaan).

Format laporan akhir: ikuti AGENTS.md bagian 11.
```

---

## Catatan untuk Anda (pemberi tugas)
- **FE-only, satu file** (`apps/web/src/app/(admin)/admin/cms/page.tsx`) — kecil & mudah ditinjau.
- **Tidak perlu backend / tidak perlu ubah tipe:** `SiteSettingsPayload` sudah memuat `social_media`
  & `profile.featured_programs`; handler simpan sudah mengirim `profileForm` utuh.
- **Base branch:** `develop`.
- **Dampak:** menuntaskan tujuan "stop hardcode" cms-public-02 untuk seksi Keunggulan beranda,
  dan mengaktifkan link sosial di /kontak + mendukung /akademik (FE-CMS-PUBLIC-03) yang membaca
  `featured_programs`.
- **Commit:** `feat(fe-cms-admin-01): editor social media & program unggulan di tab profil CMS`
