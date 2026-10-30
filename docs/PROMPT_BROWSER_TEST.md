# Prompt Test Browser (Claude Code CLI + Playwright MCP) — SISTLABUM

> Jalankan `claude` (Claude Code CLI) NATIVE di Mac, di root repo ini. `.mcp.json` sudah menyertakan server **playwright**. Tempel prompt di bawah.

## Prasyarat (sekali)
```
# di root repo
npm run setup                 # composer install, migrate --seed, npm install (web)
npx playwright install chromium   # unduh browser utk Playwright MCP (sekali)
```
Pastikan `apps/api/.env` menunjuk PostgreSQL yang benar & `apps/web/.env.local` punya NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1 dan TANPA NEXT_PUBLIC_USE_MOCK=true.

## Prompt (tempel ke Claude Code)
```
Kamu menguji aplikasi SISTLABUM (monorepo: apps/api Laravel, apps/web Next.js) lewat browser nyata memakai MCP "playwright". Tujuan: temukan error runtime, halaman rusak, dan panggilan API yang gagal di seluruh portal.

LANGKAH:
1. Pastikan server hidup: jalankan `npm run dev` di root (api di :8000, web di :3000). Tunggu keduanya siap. Jika port dipakai, hentikan dulu.
2. Buka browser via playwright MCP ke http://localhost:3000.
3. Untuk SETIAP peran di bawah: login di /masuk, lalu kunjungi tiap rute, dan di tiap halaman:
   - tangkap console error/warning (terutama Uncaught/TypeError),
   - tangkap request jaringan yang gagal (status 4xx/5xx) beserta URL endpoint-nya,
   - ambil screenshot,
   - catat bila halaman blank/crash, data tak muncul, atau tombol tak merespons.
4. Verifikasi khusus (hasil kerja terbaru): dashboard guru, dashboard ortu + ortu/nilai,poin,biaya, dashboard siswa + siswa/nilai,poin harus menampilkan DATA NYATA dari API (bukan nama dummy/avatar unsplash). Laporkan bila masih ada data mock.

KREDENSIAL (dari database/seeders; konfirmasi via grep bila gagal login):
- Admin:  superadmin / Admin123!@#
- Guru/Walas: guru.mtk.staging atau walas.7a.staging / SistLabUM2026!UAT
- Siswa: siswa.lab.001 (atau .002) / Password123!  (sebagian akun pakai SistLabUM2026!UAT)
- Ortu: cari username diawali "ortu" atau "ortu_" di seeder; Kepsek: kepsek_demo
  (Password umum: Admin123!@#, SistLabUM2026!UAT, atau Password123! — cek seeder bila ragu.)

RUTE PER PERAN:
- Publik (tanpa login): /, /profil, /akademik, /berita, /galeri, /kontak, /ppdb, /ppdb/daftar, /ppdb/masuk
- Admin: /admin, /admin/master/(students,teachers,classes,subjects), /admin/academic/(schedules,grades,attendance), /admin/ppdb/(applicants,waves,selection), /admin/cbt, /admin/finance/invoices, /admin/reports, /admin/settings/(users,roles,audit-logs)
- Guru: /guru, /guru/jadwal, /guru/jurnal, /guru/presensi, /guru/nilai, /guru/materi, /guru/tugas, /guru/ujian, /guru/perizinan, /guru/wali-kelas, /guru/sarpras, /guru/slip-gaji, /guru/presensi-saya
- Siswa: /siswa, /siswa/jadwal, /siswa/nilai, /siswa/tugas, /siswa/cbt, /siswa/konseling, /siswa/poin, /siswa/tagihan
- Ortu: /ortu, /ortu/anak, /ortu/nilai, /ortu/presensi, /ortu/poin, /ortu/biaya, /ortu/konseling
- Kepsek: /kepsek, /kepsek/approvals

OUTPUT (markdown): tabel per rute — Status (OK/ERROR), console errors, API gagal (method+URL+status), catatan. Lalu ringkasan temuan diurut parah→ringan + daftar endpoint yang 4xx/5xx (sinyal mismatch FE↔BE). Jangan ubah kode kecuali diminta; cukup laporkan + screenshot.
```

## Catatan
- `.mcp.json` sudah menambahkan MCP `playwright`; Claude Code akan memintanya saat pertama kali (setujui).
- Jika mau tanpa server manual, Claude Code bisa menjalankan `npm run dev` sendiri di satu terminal background.
- Alternatif MCP: Chrome DevTools MCP (skill `browser-testing-with-devtools`) bila ingin profiling/Core Web Vitals.
