# Dokumen Desain Autentikasi Token & Perlindungan Sesi (GAP-01 & GAP-04)

> **Status:** Menunggu Persetujuan (Langkah 0)  
> **Target:** Monorepo SISTLABUM (`apps/api` Laravel & `apps/web` Next.js 14)  
> **Referensi:** `AGENTS.md`, `02_TECH_STACK.md`, `08_DATABASE_SCHEMA.md`, `09_SECURITY_PRIVACY.md`, `docs/portal-architecture-guide.md`

---

## 1. Analisis Kondisi Saat Ini (Current State Analysis)

Berdasarkan audit celah keamanan (GAP-01 dan GAP-04):

1. **Penyimpanan Token Klien (`apps/web/src/lib/auth/token-storage.ts`)**:
   - `accessToken` disimpan di `sessionStorage` (`sistlabum_access_token`).
   - `refreshToken` disimpan di `localStorage` (`sistlabum_refresh_token`).
   - **Kerentanan (GAP-01)**: Penyimpanan refresh token berumur panjang (14 hari) di `localStorage` melanggar aturan `09_SECURITY_PRIVACY.md` dan `AGENTS.md`. Token tersebut rentan diekstrak oleh skrip pihak ketiga jika terjadi serangan Cross-Site Scripting (XSS).
2. **Kondisi Backend API (`apps/api`)**:
   - Backend sudah memiliki implementasi rotasi refresh token dan deteksi pemakaian ulang (*reuse detection*) di `AuthService.php` (`tokens()->delete()` dan pencabutan seluruh sesi aktif jika reuse terdeteksi).
   - Namun, endpoint `/api/v1/auth/login` dan `/api/v1/auth/refresh` saat ini mengembalikan `refreshToken` dalam payload JSON biasa, belum menerbitkan cookie `HttpOnly; Secure; SameSite=Lax` untuk klien web browser.
3. **Ketiadaan Middleware Route Guard (`apps/web/src/middleware.ts`)**:
   - Belum ada file `middleware.ts` di `apps/web/src/`.
   - **Kerentanan (GAP-04)**: Rute-rute terproteksi (`/portal/*` dan `/admin/*`) saat ini hanya mengandalkan client-side guard (`RoleGuard` atau pengecekan di layout/page via React hooks). Akibatnya, terjadi *flash of unauthenticated/unauthorized content* (FOUC), dan halaman HTML awal sempat terunduh sebelum client script mengalihkan pengguna ke `/masuk`.

---

## 2. Analisis Topologi Deployment & Implikasi Cookie

### 2.1. Topologi yang Teridentifikasi dari Repositori
Berdasarkan berkas `.env.example`, konfigurasi CORS (`apps/api/config/cors.php`), dan panduan arsitektur sistem:
- **Lingkungan Produksi / Staging:**
  - Web Portal Siswa, Ortu, & Staf: `portal.sistlabum.sch.id` (atau `app.sekolah.sch.id`)
  - Web Admin Back-Office: `admin.sistlabum.sch.id`
  - Backend API: `api.sistlabum.sch.id` (atau `api.sekolah.sch.id`)
  - **Kesimpulan Domain:** Berada pada **Domain Induk yang Sama** (*Same Parent Domain*), yaitu `.sistlabum.sch.id` (atau `.sekolah.sch.id`).
- **Lingkungan Pengembangan Lokal (Localhost):**
  - Web Portal: `http://localhost:3000`
  - Web Admin: `http://localhost:3001`
  - Backend API: `http://127.0.0.1:8000` (atau `http://localhost:8000`)
  - **Karakteristik Localhost:** Port berbeda dianggap sebagai *different origin*. Browser modern tidak mengizinkan atribut `Domain=localhost` untuk berbagi cookie lintas port tanpa konfigurasi khusus.

### 2.2. Implikasi Terhadap Konfigurasi Cookie
1. **Untuk Domain Induk Sama di Produksi (`.sistlabum.sch.id`)**:
   - Cookie yang disetel dengan atribut `Domain=.sistlabum.sch.id` dapat diakses oleh semua subdomain (`portal.*`, `admin.*`, dan `api.*`).
   - Browser otomatis mengirimkan cookie tersebut saat pengguna membuka halaman di Next.js maupun saat mengirimkan permintaan AJAX/Fetch ke API.
2. **Untuk Lingkungan Localhost**:
   - Backend API menyetel cookie tanpa atribut `Domain` eksplisit (host-only) atau dengan `SESSION_DOMAIN=null`.
   - Permintaan browser dari `localhost:3000` ke API `localhost:8000` dikirim menggunakan `withCredentials: true`, sehingga browser menyimpan dan mengirim cookie untuk host API.
   - Karena middleware Next.js berjalan di host `localhost:3000`, middleware memerlukan sinyal sesi yang berada di cakupan domain web tersebut (dibahas di Bagian 4).

---

## 3. Perbandingan Pilihan Arsitektur Autentikasi

| Kriteria Evaluasi | Opsi A: Sanctum SPA Stateful Cookie Auth | Opsi B: Access Token di Memori + Refresh Token di HttpOnly Cookie (Rekomendasi) |
|---|---|---|
| **Mekanisme Utama** | Stateful session di server (Redis/DB). Menggunakan cookie session Laravel terenkripsi + CSRF cookie (`/sanctum/csrf-cookie`). | Stateless access token (15 menit) di memori JS + Refresh token (14 hari) di HttpOnly cookie berotasi. |
| **Penyimpanan di Klien** | Seluruh autentikasi ditangani cookie bawaan Laravel (`sistlabum_session`). | `accessToken` hanya di variabel memori JS (hilang saat tab ditutup/reload). `refreshToken` di HttpOnly cookie. |
| **Dukungan Klien Mobile (Android Kotlin)** | **Sulit / Tidak Alami**. Sanctum SPA dirancang untuk browser. Klien mobile harus mengelola cookie jar dan token CSRF manual. | **Sangat Baik**. Kontrak endpoint sama: klien web menerima cookie HttpOnly, klien mobile menerima refresh token di JSON body. |
| **Ketahanan terhadap XSS** | Tinggi (token sesi HttpOnly). | **Sangat Tinggi**. Refresh token di HttpOnly cookie (kebal XSS). Access token di memori tidak bisa diakses dari storage. |
| **Ketahanan terhadap CSRF** | Membutuhkan penanganan CSRF token manual (`X-XSRF-TOKEN`) pada setiap request mutasi data. | **Aman**. Request data menggunakan header `Authorization: Bearer <token>` (kebal CSRF). Endpoint refresh dilindungi `SameSite=Lax` + verifikasi Origin. |
| **Kinerja & Skalabilitas Backend** | Membutuhkan pembacaan/penulisan session di DB/Redis pada setiap panggilan API. | Cepat dan stateless untuk request API reguler. Akses database hanya terjadi saat rotasi refresh token (tiap 15 menit). |
| **Kesesuaian dengan Dokumen Spesifikasi** | Mengubah arsitektur token yang sudah disepakati di `02_TECH_STACK.md` dan `03_ARCHITECTURE.md`. | **100% Selaras** dengan `03_ARCHITECTURE.md` (bagian 4), `09_SECURITY_PRIVACY.md` (bagian 2), dan `AGENTS.md`. |

### Rekomendasi & Alasan
Kami **merekomendasikan Opsi B**:
1. **Unifikasi Web dan Mobile**: Backend melayani klien Web Next.js dan Android Native. Opsi B memungkinkan arsitektur API tetap stateless dan elegan untuk kedua platform.
2. **Kesesuaian Regulasi Proyek**: Dokumen `03_ARCHITECTURE.md` Bab 4 dan `09_SECURITY_PRIVACY.md` Bab 2 secara eksplisit menetapkan aturan: *"Token: access 15 menit, refresh 14 hari dengan rotasi dan deteksi pemakaian ulang. Web/Admin: refresh token di cookie HttpOnly; Secure; SameSite=Lax"*.

---

## 4. Mekanisme Sinyal Sesi untuk Next.js Edge Middleware (GAP-04)

### 4.1. Masalah Batasan Edge Middleware
- Middleware Next.js berjalan di host web saat browser meminta rute navigasi (misalnya `GET https://portal.sistlabum.sch.id/portal/siswa`).
- Cookie `refreshToken` memiliki atribut `Path=/api/v1/auth/refresh` demi keamanan (*least-privilege scope*). Browser **tidak akan mengirimkan** cookie `refreshToken` ke URL halaman web `/portal/*` atau `/admin/*`.
- Access token hanya disimpan di memori browser JavaScript, sehingga server-side Edge Middleware tidak dapat membacanya.

### 4.2. Solusi: Sinyal Sesi Non-Sensitif (*UX Session Signal Cookie*)
Untuk memungkinkan Next.js Middleware melakukan proteksi dan redirect UX yang mulus tanpa flash konten:
1. **Penerbitan Sinyal oleh Backend saat Login / Refresh:**
   Selain menerbitkan cookie `refreshToken` (HttpOnly, path terisolasi), backend menyetel dua cookie sinyal non-sensitif:
   - **`sistlabum_session`**: Nilai boolean/flag (misal: `"1"`).
     - Atribut: `Path=/`, `SameSite=Lax`, `Secure` (di production), `HttpOnly=false` (atau true jika terbaca middleware).
   - **`sistlabum_role`**: Nilai kode peran primer (misal: `"student"`, `"teacher"`, `"guardian"`, `"admin_tu"`, `"super_admin"`).
     - Atribut: `Path=/`, `SameSite=Lax`, `Secure` (di production).
   - *Catatan Keamanan:* Cookie ini **tidak memuat token rahasia, kata sandi, maupun PII**. Cookie ini semata-mata menjadi sinyal rute UX bagi middleware.
2. **Pencabutan Sinyal saat Logout / Sesi Habis:**
   - Saat logout dipanggil atau refresh token tidak valid, backend menghapus cookie `sistlabum_session` dan `sistlabum_role` (mengirim `Set-Cookie` dengan nilai kedaluwarsa).
   - Frontend juga menyediakan fallback pembersihan cookie di sisi browser.
3. **Logika Eksekusi di `apps/web/src/middleware.ts`:**
   ```mermaid
   flowchart TD
     Req[Permintaan Rute Navigasi] --> Matcher{Apakah Rute Terproteksi?}
     Matcher -- Tidak (/masuk, /ppdb, aset, dll.) --> MasukCheck{Apakah Rute /masuk?}
     Matcher -- Ya (/portal/* atau /admin/*) --> SessionCheck{Ada cookie sistlabum_session?}
     
     SessionCheck -- Tidak --> RedirectLogin["Redirect ke /masuk?redirect=<encodedPath>"]
     SessionCheck -- Ya --> RoleCheck{Periksa sistlabum_role vs Rute}
     
     RoleCheck -- Rute /admin/* tapi role = student/guardian --> AccessDenied["Redirect ke Portal Siswa/Ortu atau /403"]
     RoleCheck -- Rute /portal/guru tapi role = student --> RedirectRolePortal["Redirect ke /portal/siswa"]
     RoleCheck -- Cocok --> NextPass[Lanjutkan ke Halaman]
     
     MasukCheck -- Ya & Ada Sesi --> RedirectPortal["Redirect ke Dashboard Peran (/portal/siswa, dll.)"]
     MasukCheck -- Lainnya --> NextPass
   ```
4. **Prinsip Pertahanan Berlapis (*Defense in Depth*):**
   - Middleware Next.js bertugas menghentikan navigasi di lapisan tepi (*Edge*) untuk mencegah FOUC.
   - Backend API tetap menjadi **satu-satunya sumber otoritas otentikasi dan otorisasi**. Jika seseorang memalsukan cookie sinyal `sistlabum_session=1`, mereka hanya akan melihat antarmuka kosong, dan semua pemanggilan API data akan langsung ditolak dengan `401 Unauthorized` atau `403 Forbidden`.

---

## 5. Rencana Migrasi Sesi Lama (*Legacy Session Migration*)

### 5.1. Kondisi Sesi Eksisting Pengguna
Pengguna yang saat ini sedang login memiliki:
- `sistlabum_refresh_token` di `localStorage`
- `sistlabum_access_token` di `sessionStorage`

### 5.2. Alur Migrasi Sekali Jalan (*One-time Transparent Migration*)
1. **Fase Inisialisasi Aplikasi (`apps/web/src/providers/auth-provider.tsx` & `token-storage.ts`):**
   - Saat aplikasi pertama kali dimuat di browser (`checkAuth`):
     - Periksa apakah terdapat token lama di `localStorage.getItem('sistlabum_refresh_token')`.
     - **Kasus A (Migrasi Token Lama):**
       - Kirim permintaan `POST /api/v1/auth/refresh` dengan menyertakan token lama tersebut dalam body request (`{ refreshToken: legacyToken }`) dengan `withCredentials: true`.
       - Backend menerima token lama, memvalidasi hash di DB, menerbitkan cookie `refreshToken` HttpOnly yang baru, dan menyetel cookie sinyal sesi.
       - Segera hapus token dari penyimpanan lokal:
         ```ts
         window.localStorage.removeItem('sistlabum_refresh_token');
         window.sessionStorage.removeItem('sistlabum_access_token');
         window.sessionStorage.removeItem('sistlabum_user_profile');
         ```
       - Simpan `accessToken` baru ke dalam memori JavaScript.
     - **Kasus B (Sesi Baru / Sudah Dimigrasi):**
       - Kirim permintaan silent refresh `POST /api/v1/auth/refresh` tanpa body (`withCredentials: true`), memanfaatkan cookie HttpOnly yang telah tersimpan di browser.
       - Simpan `accessToken` baru ke dalam memori JavaScript.
     - **Kasus C (Tidak Ada Sesi):**
       - Set `user = null`, `isLoading = false`.
2. **Manajemen UX saat Silent Refresh Awal:**
   - Selama `checkAuth` berjalan, `isLoading` tetap bernilai `true`.
   - Layout aplikasi menampilkan skeleton / loading screen terpadu.
   - **Dilarang** melakukan redirect prematur ke `/masuk` sebelum alur silent refresh selesai dievaluasi.

---

## 6. Rangkuman Pertanyaan & Rekomendasi Desain untuk Pengesahan

1. **Topologi Deploy:** Apakah Anda mengonfirmasi bahwa produksi menggunakan domain induk yang sama (misal `portal.sekolah.sch.id` dan `api.sekolah.sch.id`), sehingga cookie `Domain=.sekolah.sch.id` dapat diterapkan di produksi?
2. **Pilihan Arsitektur:** Apakah Opsi B (Access Token di memori + Refresh Token di HttpOnly cookie berotasi + kompatibilitas body token untuk Android) disetujui?
3. **Sinyal Sesi Middleware:** Apakah penggunaan cookie sinyal non-sensitif `sistlabum_session` dan `sistlabum_role` untuk keperluan UX redirect di Next.js Edge Middleware disetujui?
4. **Rencana Migrasi:** Apakah skema migrasi otomatis sekali jalan untuk menghapus token lama dari `localStorage` disetujui?

---
*Setelah rencana ini disetujui, kami akan melanjutkan ke implementasi kode GAP-01 dan GAP-04 sesuai batasan tugas.*
