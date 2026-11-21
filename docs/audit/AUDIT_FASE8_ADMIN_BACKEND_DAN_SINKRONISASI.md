# Audit Admin & Backend + Matriks Sinkronisasi Portal — SISTLABUM (Fase 8)

- **Tanggal:** 27 Oktober 2026
- **Branch:** `fix/fase-8-portal-adoption` (fase-7 utuh, tak tersentuh)
- **Konteks:** Setelah keputusan "portal = `apps/portal` milik Erik" dan penghapusan duplikat `apps/web/(portal)`.
- **Metode:** Analisis statik kode (dibaca langsung di mesin). **Catatan jujur:** test JS/PHPUnit TIDAK dijalankan dari sesi ini (node_modules macOS vs shell Linux); `next build` apps/web sudah diverifikasi hijau. Angka "rute tanpa permission" tidak dikutip sebagai angka kerentanan mentah karena banyak guard diterapkan di level grup middleware, bukan per-rute.

---

## BAGIAN A — AUDIT ADMIN & BACKEND

### A.1 Admin (`apps/web` area admin) — BAIK ✅
| Aspek | Hasil |
|---|---|
| `window.confirm`/`alert`/`prompt` | **0 tersisa** (FE-01 tuntas di seluruh admin) |
| Tombol mati (`href="#"`, `onClick` kosong) | **tidak ada** |
| Placeholder "coming soon/belum" | tidak ada (1 kecocokan hanya string pesan error) |
| State loading | 50 dari 55 page.tsx |
| Penanganan error | 48 dari 55 page.tsx |
| `next build` | **lolos bersih**, tanpa rute `/portal` |

Catatan ringan: `PermissionGate` dipakai di 18 file; sisanya mengandalkan route-guard + middleware backend. Dapat diperketat, tapi bukan celah (backend tetap sumber otoritas).

### A.2 Backend (`apps/api`) — ada sisa temuan

**🔴 BE-SEC (TINGGI, dibawa dari verifikasi Fase 7): bypass rate-limit via environment.**
`docker-compose.yml` menetapkan `TEST_DISABLE_RATE_LIMIT: ${TEST_DISABLE_RATE_LIMIT:-true}` (default **true**) dan ada middleware `BypassThrottle.php`. Risiko: bila var ini `true` di staging/produksi, proteksi brute-force login/reset-password/regenerasi token CBT **mati senyap**. Konsekuensinya 4 test rate-limit (429) juga gagal dengan konfigurasi default. **Rekomendasi:** bypass hanya boleh aktif saat `app()->environment(['local','testing'])` — dijaga di kode, bukan sekadar membaca env var; dan pin `TEST_DISABLE_RATE_LIMIT=false` di CI.

**🟠 BE-01 (SEDANG, belum tuntas): 10 controller masih `catch (Throwable)` saja.**
Error `HttpException` (401/403/422) dari service akan tersamar menjadi HTTP 500 di: `AlbumController`, `AuditLogController`, `SiteSettingController`, `BulletinController`, `MediaItemController`, `NavigationMenuController`, `DownloadController`, `PublicDirectoryController`, `PageController`, `PPDBRequirementController`. Perbaikan BE-01 fase-2 belum menjangkau controller CMS/misc ini. **Rekomendasi:** tangani `HttpException` sebelum `Throwable` (atau exception handler global).

**🟠 SEC-01 residual (SEDANG, by-design tapi defense-in-depth belum merata).**
Guard permission di rute sudah ditambahkan untuk endpoint sensitif (broadcast, template/log notifikasi, BK/konseling, early-warning, point-types/thresholds, report-cards, finance). Namun sekelompok endpoint **akademik/LMS self-service & sesi CBT** masih tanpa `permission:` di rute dan hanya mengandalkan scoping di service, a.l.:
- `PATCH /assignments/{a}/submissions/{s}/grade` (menilai tugas)
- `PATCH /leave-requests/{id}/decision` & `/status` (persetujuan izin)
- `PUT /staff-attendances/settings`, `POST /staff-attendances`
- `POST/PUT/DELETE /teaching-materials`, `/materials`, `/assignments`, `/learning-modules`
- sesi CBT siswa: `start-session`, `save-answer`, `finish-session`, `log-violation`, `exam-sessions/{id}/manage`, `heartbeat`

Ini konsisten dengan temuan awal: aman **jika** setiap service menegakkan cek, tapi tanpa jaring pengaman rute. **Rekomendasi:** tambahkan guard rute untuk operasi berwenang (minimal grading, keputusan izin, settings presensi, `exam-sessions/{id}/manage`).

**✅ Yang sudah baik:** uang = integer (unsignedBigInteger), setiap FK ber-`onDelete`, envelope response `{success,data,meta}` konsisten, guard untuk modul admin CRUD (auth/users/roles, CMS gallery/post/event/download/page, PPDB, finance) diterapkan di level grup middleware.

---

## BAGIAN B — MATRIKS SINKRONISASI PORTAL (Erik) ↔ ADMIN ↔ BACKEND

**Cara portal Erik bicara ke backend:** `apps/portal/src/lib/api-client.ts` (axios, `NEXT_PUBLIC_API_BASE_URL`/`…:8000/api/v1`), Bearer token dari **sessionStorage**, envelope-aware (`response.data.data`). Hook `use-student-data`, `use-guardian-data`, `use-teacher-data` memanggil endpoint `/me/*` nyata.

### B.1 Status per modul

| Modul | Endpoint backend | Dipakai Admin? | Dipakai Portal Erik? | Status |
|---|---|:--:|:--:|---|
| Guru (dashboard/kelas/jadwal/jurnal/nilai/materi/tugas/ujian) | `/me/teacher/*` | n/a (portal) | ✅ live | **SINKRON** |
| Siswa (dashboard/jadwal/nilai/tugas/ujian/konseling) | `/me/student-dashboard`, `/me/schedule`, `/me/grades`, `/me/tasks`, `/me/exams`, `/me/counseling-requests` | n/a | ✅ live | **SINKRON** |
| Ortu (anak/nilai/presensi/rapor/karakter/progress) | `/me/children/{id}/*` | n/a | ✅ live | **SINKRON** |
| Pengumuman | `/announcements` | ✅ | ✅ (siswa) | **SINKRON** sebagian |
| **Ujian CBT siswa** | `/exams/{id}/session-questions`, `save-answer`, `finish-session`, `log-violation` | n/a | ❌ **pakai MOCK** (`CBT_ACTIVE_EXAM_QUESTIONS` di `siswa/cbt/[id]`) | **GAP** |
| **Keuangan (Ortu)** | `/me/children/{id}/invoices` | ✅ (admin finance penuh) | ❌ `ortu/biaya` tidak memanggil API | **GAP** |
| **Keuangan (Siswa)** | `/me/invoices`, `/me/invoices/{id}/payments`, `/me/payments/{id}/receipt`, `/me/payment-providers` | ✅ | ❌ tidak terpanggil | **GAP** |
| **Notifikasi inbox & preferensi** | `/notifications`, `/me/notifications`, `/notification-preferences` | ✅ | ❌ (portal hanya `/announcements`) | **GAP** |
| Poin siswa | `/students/{id}/points/summary` | ✅ | ⚠️ `siswa/poin` impor `mock-student-data` | **PERLU VERIFIKASI** (tipe vs data) |

### B.2 Gap lintas-area (bukan per-modul)

1. **🔴 Auth tanpa refresh-token.** `api-client.ts` menangani 401 hanya dengan menghapus token lalu redirect `/login` — **tidak** memanggil `/auth/refresh`. Backend memberi access 15m + refresh 14d berotasi. Akibat: pengguna portal **terlempar login tiap ~15 menit**. Token disimpan di `sessionStorage` (admin/web memakai pola berbeda). **Sinkronkan:** tambah interceptor refresh + samakan kebijakan penyimpanan token.
2. **🟠 2FA tidak ditangani di login portal.** Backend menambah 2FA (gerbang admin). Untuk peran portal (siswa/guru/ortu) kemungkinan tidak wajib — **verifikasi kebijakan**; bila guru/kepsek butuh 2FA, login portal harus menangani tantangan 2FA.
3. **🟠 `fetch()` mentah bercampur dengan `apiClient`.** Sebagian halaman memakai `fetch()` langsung → tidak kebagian Bearer token/penanganan 401/parsing envelope dari interceptor. **Sinkronkan:** semua panggilan lewat `apiClient`.
4. **🟡 Link hardcoded** `http://localhost:3000/aspirasi.html` di `siswa/konseling/page.tsx:332` → ganti ke rute/host yang benar (atau endpoint aspirasi resmi).
5. **🟡 Data MOCK tersisa** (`mock-student-data`, `mock-guardian-data`, `mock-lms-data`): sebagian hanya impor **tipe** (aman), sebagian **data** (CBT siswa). Pastikan tidak ada data mock yang tampil sebagai data nyata di produksi.

### B.3 Ekor di `apps/web` akibat adopsi portal (perlu dibereskan)

- `features/public-site/content/navigation.ts` (footer) & `features/notifications/web-push.ts` (deep link) masih menaut `/portal/siswa`, `/portal/guru`, `/portal/ortu`, dsb — padahal portal kini **app terpisah**. Arahkan ke URL/host app portal (mis. `NEXT_PUBLIC_PORTAL_URL`).
- Folder `apps/web/src/features/portal` dan beberapa util RBAC portal kini **yatim** (tak ada halaman yang memakainya). Kandidat hapus — **tapi butuh `vitest` di Mac** untuk memastikan tak ada test/komponen lain yang masih mengimpornya.
- Masih ada ~16 file test portal di `apps/web/src/test` (mis. `portal-guru.test.ts`, `portal-staf.test.ts`, `portal-foundation.test.ts`, `cross-role-matrix.test.ts`). Triage: pindahkan yang relevan ke `apps/portal`, hapus sisanya — **verifikasi dengan vitest di Mac**.

---

## C — Yang HARUS dijalankan di Mac-mu / Antigravity (tak bisa dari sesi ini)
```
# Backend
docker exec -i sistlabum-api php artisan test
docker exec -i sistlabum-api vendor/bin/pint --test
# Web (admin/publik/ppdb)
cd apps/web && npm run test && ADMIN_LOGIN_PATH=/admin-gate-build npm run build
# Portal Erik
cd apps/portal && npm install && npm run build && npm run test
```
`next build` apps/web sudah saya verifikasi lolos; sisanya menunggu runner test di Mac karena binari native node_modules berbeda OS.

## D — Rekomendasi prioritas
1. **P0 (keamanan backend):** kunci `BypassThrottle` ke environment + pin `TEST_DISABLE_RATE_LIMIT=false` di CI.
2. **P0 (fungsional portal):** wujudkan alur **refresh-token** di `apps/portal/api-client.ts`; ganti sisa `fetch()` mentah ke `apiClient`.
3. **P1 (sinkron fitur):** sambungkan **Keuangan** (ortu/biaya & siswa tagihan) ke `/me/*invoices*`; ganti **soal CBT mock** siswa dengan endpoint sesi ujian; sambungkan inbox **notifikasi**.
4. **P1 (kebersihan):** rapikan nav/web-push & hapus `features/portal` yatim di apps/web (verifikasi vitest di Mac).
5. **P2:** tuntaskan BE-01 di 10 controller CMS/misc; tambah guard rute untuk grading/keputusan-izin/settings-presensi/`exam-sessions manage`.

*Tidak ada kode yang diubah untuk menghasilkan laporan ini; perubahan struktural portal ada di commit `d75382c` dan `7ee6563` pada branch `fix/fase-8-portal-adoption`.*
