# SISTLABUM — Web Portal Terpadu (Next.js 14+ App Router)

Frontend resmi Portal Sekolah Satu Pintu **SMP-SMA Laboratorium Universitas Negeri Malang (SISTLABUM)** yang dibangun menggunakan Next.js 14+ (App Router), TypeScript, Tailwind CSS, shadcn/ui, dan TanStack Query, terintegrasi langsung dengan Laravel 11 REST API (`apps/api`).

---

## 1. Arsitektur Tiga Shell Navigasi
Aplikasi web ini mengadopsi tiga shell navigasi terisolasi berdasarkan peran pengguna:
1. **Gateway SSO (`/masuk`)**: Pintu masuk login tunggal terintegrasi ke endpoint backend `POST /api/v1/auth/login`. Mendeteksi peran otomatis dan mengarahkan pengguna ke shell yang sesuai.
2. **Shell Manajemen & Guru (`/admin/*`)**: Sidebar navigasi hierarkis responsif, topbar dengan indikator tahun ajaran aktif, menu terproteksi berbasis permission Spatie, dan *Role Switcher* bagi pengguna multi-peran.
3. **Shell Siswa (`/portal/siswa/*`)**: Antarmuka ringkas, ramah keyboard, dan dioptimalkan untuk monitor komputer lab sekolah.
4. **Shell Orang Tua (`/portal/ortu/*`)**: Antarmuka pemantauan presensi dan perkembangan anak dengan penyiapan bar *Multi-Child Switcher*.

---

## 2. Kebutuhan Lingkungan & Runtime
- **Node.js**: v20+ (Direkomendasikan v22 atau v26)
- **Package Manager**: npm v10+ atau pnpm v9+
- **Backend API**: Laravel 11 REST API berjalan di `http://127.0.0.1:8000/api/v1`

---

## 3. Konfigurasi Environment Variable
Salin berkas contoh environment:
```bash
cp .env.example .env.local
```

Isi variabel:
```env
# URL REST API Laravel SISTLABUM
NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1
NEXT_PUBLIC_APP_NAME="SISTLABUM - Portal Sekolah Satu Pintu"
```

---

## 4. Instalasi & Menjalankan Aplikasi

### Mode Pengembangan (Development)
```bash
cd apps/web
npm install
npm run dev
```
Buka peramban di: **`http://localhost:3000`**

### Menjalankan Backend API (Secara Paralel)
```bash
cd apps/api
php artisan serve
```

---

## 5. Pemeriksaan Kualitas Kode (QA & Testing)

| Perintah | Deskripsi |
|---|---|
| `npm run typecheck` | Validasi TypeScript (`tsc --noEmit`) tanpa error tipe |
| `npm test` | Menjalankan automated test suite (Vitest + Testing Library) |
| `npm run build` | Kompilasi build produksi Next.js |
| `npm run start` | Menjalankan server produksi lokal |

---

## 6. Alur Autentikasi Aktual yang Diterapkan
- **Login**: `POST /api/v1/auth/login` dengan payload `{ identifier, password, platform: 'web' }`.
- **Response**: Menerima `{ data: { user: {...}, tokens: { accessToken, refreshToken, expiresIn } } }`.
- **Penyimpanan Token**:
  - `accessToken` disimpan dalam memori runtime dan diinjeksi via header `Authorization: Bearer <token>` pada instance Axios `apiClient`.
  - `refreshToken` disimpan di client storage terisolasi dan digunakan secara otomatis oleh interceptor untuk memperbarui sesi saat menerima respons HTTP `401`.
- **Logout**: `POST /api/v1/auth/logout`, membersihkan seluruh state sesi di memori, dan mengarahkan kembali ke `/masuk`.

---

## 7. Arsip Prototipe Desain Lama (Non-Destruktif)
Seluruh prototipe awal berbasis HTML/CSS/Vanilla JS dan demo SQLite tetap diarsipkan secara utuh di:
```text
legacy/prototype/
```
Untuk menjalankan prototipe lama:
```bash
cd legacy/prototype
node server.js
```
Prototipe lama dapat ditinjau kapan saja untuk memverifikasi referensi visual tanpa mengganggu aplikasi produksi Next.js.
