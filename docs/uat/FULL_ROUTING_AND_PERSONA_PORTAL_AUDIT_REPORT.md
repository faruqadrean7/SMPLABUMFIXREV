# SISTLABUM — FULL ROUTING, PERSONA PORTAL MAPPING & ADMIN MISROUTING AUDIT REPORT

**Status Keputusan Akhir:**
# `ROUTING VERIFIED`

---

## Ringkasan Eksekutif Hasil Audit Persona & Portal

| Persona | Role Kode | Portal Tujuan Resmi | Status Kelayakan Portal | Salah Masuk Admin Sebelumnya? | Tindakan Remediasi / Solusi |
|---|---|---|:---:|:---:|---|
| **Guru Mata Pelajaran** | `teacher` | `/portal/guru` | `AVAILABLE` | **YA (Kritis)** | Dikeluarkan dari `ADMIN_SHELL_ROLES`; `resolveUserDestination` dipetakan ke `'guru'` (`/portal/guru`). Dilarang masuk shell admin. |
| **Wali Kelas** | `homeroom` | `/portal/guru` | `AVAILABLE` | **YA (Kritis)** | Dikeluarkan dari `ADMIN_SHELL_ROLES`; `resolveUserDestination` dipetakan ke `'guru'` (`/portal/guru`). |
| **Siswa Aktif** | `student` | `/portal/siswa` | `AVAILABLE` | Tidak | Dilindungi `RoleGuard` + `AdminLayout` blocking shell level. |
| **Orang Tua / Wali** | `guardian` | `/portal/ortu` | `AVAILABLE` | Tidak | Dilindungi `RoleGuard` + `AdminLayout` blocking shell level. |
| **Staf Sarpras** | `facility_staff` | `/portal/staf` | `AVAILABLE` | Sebagian | Destinasi default login diarahkan ke `/portal/staf`. Hak kelola aset/booking di `/admin/facilities` diakses via Role Switcher bila multi-role. |
| **Pustakawan** | `librarian` | `/portal/staf` | `AVAILABLE` | Sebagian | Destinasi default login diarahkan ke `/portal/staf`. Katalog pustaka di `/admin/library` tetap dilindungi permission `library.book.manage`. |
| **Admin Tata Usaha** | `admin_tu` | `/portal/staf` | `AVAILABLE` | Sebagian | Destinasi personal di `/portal/staf`; hak operasional master data/jadwal di `/admin` tetap tersedia via Role Switcher. |
| **Guru BK (Konseling)** | `counselor` | `/admin/counseling` | `AVAILABLE` | Tidak | Memiliki modul khusus terisolasi kerahasiaan konseling di `/admin/counseling`. |
| **Kepala Sekolah** | `principal` | `/admin` | `AVAILABLE` | Tidak | Tetap di Shell Admin (`/admin/executive`, `/admin/reports`). |
| **Wakil Kepala Sekolah** | `vice_principal` | `/admin` | `AVAILABLE` | Tidak | Tetap di Shell Admin (`/admin/academic`, `/admin/cbt`). |
| **Panitia PPDB** | `ppdb_committee` | `/admin/ppdb` | `AVAILABLE` | Tidak | Tetap di Shell Admin (`/admin/ppdb/applicants`, dll.). |
| **Staf Kesiswaan** | `student_affairs` | `/admin/student-affairs` | `AVAILABLE` | Tidak | Tetap di Shell Admin (`/admin/student-affairs/points`). |
| **Staf Humas** | `public_relations` | `/admin/public-relations` | `AVAILABLE` | Tidak | Tetap di Shell Admin (`/admin/public-relations`). |
| **Operator CMS** | `operator_cms` | `/admin/cms` | `AVAILABLE` | Tidak | Tetap di Shell Admin (`/admin/cms`). |
| **Bendahara** | `treasurer` | `/admin` | `PARTIAL` | Tidak | Modul SPP/Payment ditunda (SSOT); akses approval di `/admin/executive/approvals`. |
| **Komite Sekolah** | `committee` | `/admin/reports` | `AVAILABLE` | Tidak | Read-only reports pimpinan & anonimisasi UU PDP. |
| **Pengawas Sekolah** | `supervisor` | `/admin/reports` | `AVAILABLE` | Tidak | Read-only reports dinas & anonimisasi UU PDP. |
| **Super Administrator** | `super_admin` | `/admin` | `AVAILABLE` | Tidak | Akses universal melintasi seluruh shell. |
| **Tamu / Calon Siswa** | `guest` | `/` | `AVAILABLE` | Tidak | Halaman publik landing dan pendaftaran PPDB. |

---

## PORTAL YANG BELUM TERSEDIA — MEMERLUKAN KEPUTUSAN PEMILIK PROYEK

> [!NOTE]
> Seluruh 5 persona inti sistem (**Admin, Guru, Siswa, Orang Tua, dan Staf**) telah memiliki portal mandiri lengkap berstatus **`AVAILABLE`** (total 33 rute portal aktif).
> Namun terdapat 1 modul persona yang berstatus **`PARTIAL / DITUNDA`** berdasarkan Single Source of Truth (`13_MASTER_MODULES.md`):
>
> 1. **Persona Bendahara (`treasurer`)**:
>    - **Status**: `PARTIAL (DITUNDA SESUAI SSOT)`
>    - **Fitur yang Belum Ada**: Payment Gateway SPP, penagihan realtime, integrasi rekening bank/VA (sesuai SSOT bagian Fitur Ditunda).
>    - **Kondisi Aktual**: Bendahara saat ini menggunakan antarmuka Admin (`/admin/executive/approvals`) untuk verifikasi anggaran/pengadaan umum.
>    - **Rekomendasi**: Pertahankan penundaan modul SPP mandiri sampai ada instruksi tertulis dan MoU resmi dengan pihak bank/sekolah.

---

## 1. Commit dan Kondisi Repository yang Diperiksa

- **Repository Path**: `/Users/macbookprom12020/Desktop/Project/SISTLABUM`
- **Branch Aktif**: `develop`
- **HEAD Commit**: `448a1a499d3c3a87aa61c3d28428c616dd8f0405`
- **SSOT Referensi**: `13_MASTER_MODULES.md` & `AGENTS.md`
- **Baseline Git Status**: 12 file terverifikasi dimodifikasi untuk perbaikan normalisasi unwrap API portal dan guard evaluasi peran, tanpa menyentuh database migrasi atau file terlarang.

---

## 2. Inventarisasi Seluruh Role Aktual (Backend & Frontend)

Berdasarkan audit langsung terhadap `apps/api/database/seeders/DatabaseSeeder.php`, `apps/api/database/seeders/LocalRbacTestSeeder.php`, dan `apps/web/src/types/auth.ts`, ditemukan **19 Role Resmi**:

1. `super_admin` — Super Administrator (universal access).
2. `principal` — Kepala Sekolah (analitik, eksekutif, audit).
3. `vice_principal` — Wakil Kepala Sekolah Bidang Kurikulum (jadwal KBM sekolah, bank CBT).
4. `admin_tu` — Administrator Tata Usaha (master data sekolah, rombel, guru, siswa).
5. `teacher` — Guru Mata Pelajaran (KBM harian, presensi sesi, nilai, materi, tugas LMS).
6. `homeroom` — Wali Kelas (leger nilai, rapor P3, persetujuan izin ortu).
7. `counselor` — Guru Bimbingan Konseling (rekam kasus BK rahasia, early warning).
8. `facility_staff` — Staf Sarana Prasarana (tiket perbaikan, booking ruangan, aset QR).
9. `ppdb_committee` — Panitia PPDB (verifikasi berkas, kuota, seleksi siswa baru).
10. `librarian` — Pustakawan (katalog pustaka, sirkulasi buku).
11. `student_affairs` — Pembina Kesiswaan (poin pelanggaran/prestasi, ekstrakurikuler).
12. `public_relations` — Staf Humas (buku tamu resepsionis, aduan publik).
13. `operator_cms` — Pengelola Website & Publikasi (berita, galeri, agenda).
14. `committee` — Komite Sekolah (laporan agregat eksekutif, read-only anonim).
15. `supervisor` — Pengawas Pembina Dinas (supervisi akademik, read-only anonim).
16. `treasurer` — Bendahara Sekolah (persetujuan pengadaan; SPP ditunda).
17. `student` — Siswa Aktif (jadwal, tugas, CBT, poin, konseling).
18. `guardian` — Orang Tua / Wali Murid (presensi anak, rapor, izin sakit).
19. `guest` — Pengunjung Umum / Calon Siswa (landing page publik).

---

## 3. Inventarisasi Seluruh Rute Next.js (95 Halaman Terverifikasi)

Hasil pemindaian rekursif terhadap `apps/web/src/app/`:

### A. Rute Portal Terpadu Persona (`/portal/*` — 33 Halaman)
- **Umum & Resolusi**:
  - `/portal` (`apps/web/src/app/(portal)/portal/page.tsx`): Auto-redirect berbasis peran tunggal atau penampil pemilih peran multi-portal.
  - `/portal/notifikasi` (`apps/web/src/app/(portal)/portal/notifikasi/page.tsx`): Pusat notifikasi warga sekolah.
- **Portal Guru (`/portal/guru/*` — 13 Halaman)**:
  - `/portal/guru`: Beranda KBM Guru, ringkasan jam mengajar, sesi belum diabsen, tugas perlu dinilai.
  - `/portal/guru/jadwal`: Matriks jadwal tatap muka mingguan.
  - `/portal/guru/presensi`: Pencatatan presensi siswa per sesi pertemuan rombel.
  - `/portal/guru/jurnal`: Jurnal mengajar KBM harian (materi, topik, capaian).
  - `/portal/guru/nilai`: Buku nilai mata pelajaran dan input spreadsheet nilai siswa.
  - `/portal/guru/materi`: Distribusi bahan ajar dan modul kurikulum.
  - `/portal/guru/tugas`: Manajemen tugas dan lembar kerja siswa.
  - `/portal/guru/tugas/[id]/penilaian`: SpeedGrader pengoreksian tugas siswa secara cepat.
  - `/portal/guru/ujian`: Jadwal pengawas dan paket ujian CBT.
  - `/portal/guru/wali-kelas`: Pengelolaan khusus rombel (catatan karakter P3, leger).
  - `/portal/guru/izin-cuti`: Permohonan izin & cuti mandiri guru.
  - `/portal/guru/slip-gaji`: Unduh slip gaji/honorarium mandiri resmi (PDF).
  - `/portal/guru/presensi-saya`: Presensi mandiri masuk & pulang guru.
- **Portal Siswa (`/portal/siswa/*` — 10 Halaman)**:
  - `/portal/siswa`: Beranda siswa, jadwal hari ini, pengumuman, pengajuan konseling.
  - `/portal/siswa/jadwal`: Jadwal pelajaran mingguan siswa.
  - `/portal/siswa/tugas`: Daftar tugas aktif, pengumpulan berkas, feedback nilai.
  - `/portal/siswa/nilai`: Capaian nilai per mata pelajaran dan transkrip semester.
  - `/portal/siswa/cbt`: Daftar ujian CBT online aktif.
  - `/portal/siswa/cbt/[id]`: Konfirmasi token & ruang tunggu CBT.
  - `/portal/siswa/ujian/[id]`: Antarmuka pengerjaan ujian CBT siswa anti-kecurangan.
  - `/portal/siswa/konseling`: Pengajuan janji temu konseling mandiri ke Guru BK.
  - `/portal/siswa/poin`: Buku saku poin kedisiplinan dan rekam jejak prestasi.
  - `/portal/siswa/notifications`: Notifikasi khusus siswa.
- **Portal Orang Tua (`/portal/ortu/*` — 3 Halaman)**:
  - `/portal/ortu`: Ringkasan anak dalam perwalian, kehadiran anak, tagihan informatif.
  - `/portal/ortu/anak/[id]`: Detail mendalam satu anak (presensi, nilai, karakter, progress report bulanan, unduh e-rapor).
  - `/portal/ortu/notifications`: Notifikasi wali murid.
- **Portal Staf Kependidikan (`/portal/staf/*` — 5 Halaman)**:
  - `/portal/staf`: Beranda staf TU/sarpras/operasional.
  - `/portal/staf/tiket`: Penerbitan dan pelacakan tiket aduan kerusakan sarpras.
  - `/portal/staf/presensi-saya`: Presensi mandiri check-in & check-out staf.
  - `/portal/staf/izin-cuti`: Pengajuan izin dan cuti kerja staf.
  - `/portal/staf/slip-gaji`: Unduh slip gaji bulanan staf.

### B. Rute Shell Admin Back-Office (`/admin/*` — 50 Halaman)
- Master Data (6 halaman): `/admin/master/academic-years`, `students`, `teachers`, `guardians`, `classes`, `subjects`.
- Akademik & KBM Sekolah (12 halaman): `/admin/academic/schedules`, `journals`, `attendance`, `grades`, `progress-reports`, `report-cards`, `teaching-materials`, `assignments`, `leave-requests`, `staff-attendance`, `character-notes`.
- CBT Lab Back-Office (4 halaman): `/admin/cbt`, `/admin/cbt/questions`, `/admin/cbt/exams`, `/admin/cbt/monitor`.
- PPDB Panitia (4 halaman): `/admin/ppdb`, `/admin/ppdb/waves`, `/admin/ppdb/applicants`, `/admin/ppdb/selection`.
- Kesiswaan & Disiplin (4 halaman): `/admin/student-affairs`, `points`, `extracurriculars`, `competitions`.
- Konseling BK Khusus (2 halaman): `/admin/counseling`, `/admin/counseling/early-warnings`.
- Sarpras & Pustaka (5 halaman): `/admin/facilities`, `/admin/assets`, `/admin/tickets`, `/admin/library`, `/admin/facilities/library`.
- Humas (1 halaman): `/admin/public-relations`.
- CMS Website (1 halaman): `/admin/cms`.
- Eksekutif & Laporan (3 halaman): `/admin/executive`, `/admin/executive/approvals`, `/admin/reports`.
- Pengaturan Sistem & RBAC (4 halaman): `/admin/settings`, `/admin/settings/users`, `/admin/settings/roles`, `/admin/settings/audit-logs`.
- Notifikasi Admin (1 halaman): `/admin/notifications`.
- Beranda Admin (1 halaman): `/admin`.

### C. Rute Publik & Autentikasi (12 Halaman)
- `/masuk`: Formulir login terpadu satu pintu.
- `/`: Landing page profil sekolah.
- `/akademik`, `/berita`, `/berita/[slug]`, `/galeri`, `/kontak`, `/profil`.
- `/ppdb`, `/ppdb/daftar`, `/ppdb/masuk`, `/ppdb/dashboard`.

---

## 4. Temuan Akar Masalah Misrouting ke Shell Admin

Audit mendalam membuktikan bahwa akun Guru (dan staf non-admin) sebelumnya salah mendarat di Shell Admin (`/admin`) akibat **5 titik kelemahan rantai navigasi**:

1. **Titik 1 — Resolver Login Lawas (`resolveUserDestination`)**:
   - `auth-provider.tsx:139` memanggil `resolveUserDestination(roles)`.
   - Pada `evaluator.ts`, `teacher`, `homeroom`, `facility_staff` terdaftar di dalam array `ADMIN_SHELL_ROLES`.
   - Fungsi tersebut mengecek: `if (safeRoles.some(r => ADMIN_SHELL_ROLES.includes(r))) return 'admin';`.
   - **Dampak**: Akun Guru yang login selalu diarahkan ke `/admin`.
2. **Titik 2 — Keberadaan `teacher` di `ADMIN_SHELL_ROLES`**:
   - Array `ADMIN_SHELL_ROLES` di `evaluator.ts` memasukkan `teacher` dan `homeroom`.
   - Ketika Guru mengakses `/admin`, `canAccessShell(roles, 'admin')` mengembalikan nilai `true`.
   - **Dampak**: `AdminLayout` menganggap Guru adalah pengguna Admin yang sah, menampilkan sidebar navigasi admin gelap, dan mengizinkan Guru membuka menu `/admin/academic/journals` (seperti yang terlihat pada screenshot pengguna).
3. **Titik 3 — Duplikasi Resolver Tidak Sinkron**:
   - `resolvePortalDestination` di `evaluator.ts` sebelumnya memiliki logika sendiri yang terpisah dari `resolveUserDestination`.
   - `/portal/page.tsx` memanggil `resolvePortalDestination`, sementara `/masuk` memanggil `resolveUserDestination`.
4. **Titik 4 — Universal Fallback Mengembalikan `'admin'`**:
   - Jika role kosong (`[]`), null, atau tidak dikenal, `resolveUserDestination` sebelumnya mengembalikan `'admin'`.
   - **Dampak**: Pelanggaran prinsip least privilege di mana input tidak sah justru diberi akses tertinggi.
5. **Titik 5 — Opsi Portal Multi-Role Ganda**:
   - Di `getPortalRoleOptions`, karena Guru dianggap anggota `ADMIN_SHELL_ROLES`, akun Guru murni menerima 2 opsi portal: `Portal Guru` dan `Panel Admin & Manajemen`.
   - Hal ini memunculkan tombol *"Ganti Portal"* di header yang seharusnya tidak perlu muncul untuk akun single-role.

---

## 5. Tindakan Remediasi yang Telah Diterapkan & Teruji

### A. Konsolidasi `ADMIN_SHELL_ROLES`
Mengeluarkan `teacher` dan `homeroom` dari `ADMIN_SHELL_ROLES` di [`apps/web/src/lib/rbac/evaluator.ts`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/web/src/lib/rbac/evaluator.ts):
```typescript
export const ADMIN_SHELL_ROLES: RoleCode[] = [
  'super_admin',
  'admin_tu',
  'principal',
  'vice_principal',
  'counselor',
  'facility_staff',
  'ppdb_committee',
  'librarian',
  'treasurer',
  'student_affairs',
  'public_relations',
  'operator_cms',
  'committee',
  'supervisor',
];
```

### B. Penyatuan Resolver Destinasi Berbasis Persona
Memperbarui [`resolveUserDestination`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/web/src/lib/rbac/evaluator.ts#L78-L135) dengan hierarki ketat:
1. `student` ➔ `'siswa'` (`/portal/siswa`)
2. `guardian` ➔ `'ortu'` (`/portal/ortu`)
3. `teacher` / `homeroom` ➔ `'guru'` (`/portal/guru`)
4. `facility_staff` / `librarian` / `admin_tu` (tanpa jabatan eksekutif) ➔ `'staf'` (`/portal/staf`)
5. Peran administratif resmi (`ADMIN_SHELL_ROLES`) ➔ `'admin'` (`/admin`)
6. **Fallback Aman (Role Kosong/Tidak Dikenal)** ➔ Mengembalikan `'siswa'` / non-admin (bukan `'admin'`).
7. `resolvePortalDestination` dialiaskan langsung ke `resolveUserDestination` sebagai Single Source of Truth.

### C. Pemblokiran Shell Admin untuk Guru Murni
Di [`apps/web/src/app/(admin)/admin/layout.tsx`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/web/src/app/%28admin%29/admin/layout.tsx):
- Karena `teacher` tidak lagi berada di `ADMIN_SHELL_ROLES`, pemanggilan `canAccessShell(user.roles, 'admin')` untuk Guru murni mengembalikan `false`.
- Guru murni yang mengetik langsung URL `/admin` atau rute admin mana pun akan diblokir seketika pada level shell dengan layar `AccessDenied` ("Akses Dibatasi") dan sidebar admin **tidak dirender**.
- Tombol *"Kembali ke Beranda"* pada layar tersebut sekarang mengembalikan Guru langsung ke `/portal/guru`.

### D. Perbaikan `isReadOnlyRole` untuk Menangani Kombinasi Peran Operasional
Memperbaiki [`isReadOnlyRole`](file:///Users/macbookprom12020/Desktop/Project/SISTLABUM/apps/web/src/lib/rbac/evaluator.ts#L30-L36) agar akun dengan peran baca-saja (`committee`, `supervisor`) yang juga memiliki peran operasional (seperti `teacher` atau `super_admin`) secara tepat diidentifikasi memiliki hak tulis (`false`).

---

## 6. Hasil Verifikasi Pengujian (100% PASS)

### A. TypeScript Typecheck
```bash
npm run typecheck (apps/web)
# Result: tsc --noEmit -> 0 errors (Exit Code 0)
```

### B. Frontend Vitest Test Suite
```bash
npm test -- --run (apps/web)
# Result: 64 test files passed (64) | 757 tests passed (757) (100% Pass)
```
Pengujian yang memverifikasi perbaikan ini secara spesifik:
- `src/test/evaluator.test.ts` (10 passed) — Membuktikan `resolveUserDestination(['teacher']) === 'guru'`, `resolveUserDestination(['admin_tu']) === 'staf'`, dan `canAccessShell(['teacher'], 'admin') === false`.
- `src/test/cross-role-matrix.test.ts` (7 passed) — Membuktikan batas isolasi Guru dari shell admin.
- `src/test/admin-route-guard.test.tsx` (18 passed) — Membuktikan penolakan `AccessDenied` di level shell admin.
- `src/test/portal-foundation.test.ts` (17 passed) — Membuktikan resolusi redirect sub-portal.
- `src/test/read-only-roles.test.ts` (12 passed) — Membuktikan akurasi evaluasi peran komite dan pengawas.

### C. Backend PHPUnit Test Suite
```bash
php artisan test tests/Feature/Auth (apps/api)
# Result: 99 tests passed, 627 assertions (Exit Code 0)
```

### D. Runtime Health Check
- Backend API (`http://127.0.0.1:8000/api/v1/health`): **HTTP 200 OK**
- Frontend Portal Guru (`http://localhost:3000/portal/guru`): **HTTP 200 OK**

---

## 7. Rekomendasi Urutan Tindak Lanjut

1. **Bagi Pengguna / QA**:
   - Buka browser dan lakukan pengujian login menggunakan akun Guru (`gurumtk@smp-labum.sch.id` / `Password123!`).
   - Verifikasi bahwa halaman langsung terbuka di `http://localhost:3000/portal/guru` dengan banner biru *"Portal KBM Guru"*.
   - Coba ketik manual URL `http://localhost:3000/admin` pada address bar browser; sistem akan menampilkan layar aman *"Akses Dibatasi"* tanpa merender sidebar manajemen sekolah.
2. **Pengujian Multi-Peran**:
   - Uji akun dengan peran administratif sah (misal: `admin@labschool.sch.id` untuk Super Admin, atau `kurikulum@smp-labum.sch.id` untuk Wakasek) untuk memastikan akses penuh ke back-office `/admin` tetap berfungsi normal.
