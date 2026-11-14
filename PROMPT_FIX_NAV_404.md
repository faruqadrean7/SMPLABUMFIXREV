# Prompt Tugas: FE-NAV-404 — Perbaiki Link Menu yang 404 (Bertahap)

> Salin blok per TAHAP. Dikerjakan bertahap agar aman & mudah ditinjau: Tahap 1 (perbaikan instan link mati), Tahap 2 (keputusan link fasilitas publik), Tahap 3 (pencegahan: uji dead-link otomatis). Semua temuan sudah diverifikasi dari kode pada 2026-11-01 (silang 122 rute `page.tsx` vs seluruh target navigasi, memperhitungkan alias redirect `next.config.mjs`).

## Konteks (berlaku untuk semua tahap)
User mengalami halaman 404 saat berpindah antar-menu. Penyebab: beberapa item menu/link
menunjuk rute yang TIDAK ADA. Rute portal yang BENAR-BENAR ADA:
- ortu: `anak, anak/[id], biaya, dashboard, konseling, nilai, poin, presensi`
- siswa: `cambridge, cbt, cbt/[id], dashboard, hafalan, jadwal, konseling, nilai, p5, poin, ppi, tagihan, tugas, ujian/[id]`
- publik: `akademik, berita, galeri, kontak, profil, ppdb` (TIDAK ada `fasilitas`)

Temuan link mati:
| # | Menu/link | Target 404 | File:baris | Rute benar |
|---|---|---|---|---|
| 1 | "Izin & Sakit" (header ortu) | /portal/ortu/izin | apps/web/src/components/shell/portal/parent-header.tsx:28 | /portal/ortu/presensi |
| 2 | "E-Rapor Anak" (header ortu) | /portal/ortu/rapor | apps/web/src/components/shell/portal/parent-header.tsx:29 | /portal/ortu/nilai |
| 3 | "E-Rapor" (header siswa) | /portal/siswa/rapor | apps/web/src/components/shell/portal/student-header.tsx:31 | /portal/siswa/nilai |
| 4 | "Fasilitas" (profil publik) | /fasilitas | apps/web/src/app/(public)/profil/page.tsx:820 | (tak ada halaman publik fasilitas) |

Ikuti AGENTS.md. Setiap tahap = satu commit/ID sendiri (§1, §7). Jalankan lint+typecheck tiap tahap.

---

## TAHAP 1 — FE-NAV-404-01: Perbaiki 3 link header mati (quick win)

```
Kerjakan FE-NAV-404-01: perbaiki 3 link header portal yang menuju rute tidak ada (404).
Ikuti AGENTS.md. Ini perubahan satu-baris; tak perlu menunggu persetujuan, tapi tetap
tulis ringkasan.

Ubah href berikut ke rute yang benar (verifikasi rutenya ada sebagai page.tsx):
1. apps/web/src/components/shell/portal/parent-header.tsx:28
   '/portal/ortu/izin'  → '/portal/ortu/presensi'   (fitur izin/sakit ada di halaman presensi:
   memakai useLeaveRequests/useCreateLeaveRequest)
2. apps/web/src/components/shell/portal/parent-header.tsx:29
   '/portal/ortu/rapor' → '/portal/ortu/nilai'
3. apps/web/src/components/shell/portal/student-header.tsx:31
   '/portal/siswa/rapor' → '/portal/siswa/nilai'

Batasan:
- JANGAN ubah label/teks menu (tetap "Izin & Sakit", "E-Rapor Anak", "E-Rapor") kecuali
  diminta; hanya href. Jika label "E-Rapor" dianggap menyesatkan karena mengarah ke
  halaman Nilai, catat sebagai usulan — jangan ubah tanpa persetujuan (§2).
- Jangan sentуh menu lain.

AC:
- AC1 Ketiga href menunjuk rute yang ADA; klik menu tidak lagi 404.
- AC2 Tidak ada perubahan lain selain 3 href tersebut.

Verifikasi:
- grep memastikan tak ada lagi '/portal/ortu/izin' | '/portal/ortu/rapor' | '/portal/siswa/rapor'
  di src/.
- cd apps/web && pnpm typecheck && pnpm eslint <2 file header>
- E2E manual (kredensial uji di memory): login ortu → klik "Izin & Sakit" & "E-Rapor Anak"
  → halaman terbuka (bukan 404); login siswa → klik "E-Rapor" → terbuka.

Commit: fix(fe-nav-404-01): arahkan link header izin/rapor ke rute yang ada
```

---

## TAHAP 2 — FE-NAV-404-02: Link "Fasilitas" di profil publik

```
Kerjakan FE-NAV-404-02: perbaiki link "Fasilitas" di halaman profil publik yang 404.
Ikuti AGENTS.md. Tulis rencana singkat; TUNGGU keputusan pemberi tugas sebelum coding.

Lokasi: apps/web/src/app/(public)/profil/page.tsx:820 → href="/fasilitas".
Masalah: tidak ada rute publik '/fasilitas' (hanya '/portal/guru/fasilitas' untuk guru login).

KEPUTUSAN yang harus ditegaskan (§8 — konten publik milik sekolah):
  OPSI A: Buat halaman publik baru app/(public)/fasilitas/page.tsx (perlu konten/aset
          sarpras dari sekolah — jangan mengarang data).
  OPSI B: Alihkan link ke halaman publik yang sudah ada (mis. '/akademik' atau '/profil#fasilitas'
          bila ada seksi fasilitas di profil).
  OPSI C: Hapus tautan "Fasilitas" sampai halamannya siap.
Jangan putuskan sendiri; tanyakan.

AC: link "Fasilitas" tidak lagi 404 sesuai opsi terpilih; bila membuat halaman, ada state
konten nyata (bukan placeholder karangan).
Verifikasi: typecheck + eslint; klik "Fasilitas" dari /profil → sesuai opsi.
Commit: fix(fe-nav-404-02): <sesuai opsi>
```

---

## TAHAP 3 — FE-NAV-404-03: Cegah dead-link terulang (uji otomatis)

```
Kerjakan FE-NAV-404-03: tambah uji otomatis yang menggagalkan build bila ada href menu
menunjuk rute yang tidak ada (mencegah 404 kelas ini muncul lagi).
Ikuti AGENTS.md. Tulis rencana singkat; boleh lanjut setelah Tahap 1 selesai.

Ide: uji (vitest) yang:
1. Mengenumerasi rute nyata dari file app router (semua page.tsx di apps/web/src/app),
   membuang segmen route-group '(xxx)', mengubah '[param]' jadi wildcard.
2. Mengumpulkan target navigasi statis: properti href di konfigurasi menu
   (src/lib/rbac/navigation.ts, src/components/shell/portal/*-header.tsx, portal-shell.tsx)
   dan href literal '<Link href="/...">'.
3. Menerapkan alias redirect dari next.config.mjs (/siswa→/portal/siswa, /guru, /ortu,
   /staf, /kepsek, serta redirect spesifik admin) sebelum mencocokkan.
4. Gagal (assert) bila ada target statis yang tidak cocok dengan rute mana pun.

Catatan implementasi:
- Hanya cek target STATIS (abaikan yang mengandung ${} / template dinamis) agar tidak
  false-positive; dokumentasikan batas ini.
- Letakkan di apps/web/src/test/fe-nav-404-03.test.ts; nama uji berawalan FE-NAV-404-03.
- Uji harus membaca filesystem rute saat test (bukan daftar hardcode) agar tetap akurat
  saat rute berubah.

AC:
- AC1 Uji LULUS pada kondisi setelah Tahap 1 & 2 (semua link statis valid).
- AC2 Bila sengaja ditambah href rusak, uji GAGAL (buktikan sekali lalu kembalikan).
Verifikasi: cd apps/web && npx vitest run src/test/fe-nav-404-03.test.ts
Commit: test(fe-nav-404-03): cegah link menu menuju rute tidak ada
```

---

## Catatan untuk Anda (pemberi tugas)
- **Urutan optimal:** Tahap 1 dulu (hilangkan 404 yang Anda alami — 2 di menu ORTU), lalu Tahap 3 (pencegahan), lalu Tahap 2 (butuh keputusan konten sekolah, bisa menyusul).
- **Base branch:** `develop` (file header & profil identik di develop & HEAD) agar diff PR bersih. Bisa satu branch `fix/nav-404` dengan 3 commit bertahap, atau 3 PR kecil.
- Cakupan analisis: link **statis**. Navigasi dinamis `${id}` menunjuk rute dinamis yang sudah ada; redirect CBT `/portal/siswa/cbt/[id]`→`/portal/siswa/ujian/[id]` aman.
