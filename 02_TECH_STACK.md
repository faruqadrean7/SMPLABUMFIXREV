# 02. Tech Stack & Keputusan Teknis

Keputusan di bawah **mengikat** untuk AI agent dan developer. Mengubahnya harus lewat ADR (bagian 7).

## 1. Ringkasan Stack
| Lapisan | Pilihan | Alasan |
|---|---|---|
| Backend API | **Laravel 11 (PHP 8.2)** *(Implementasi Aktual)* / NestJS *(Desain Awal)* | Ekosistem matang, Eloquent ORM, Sanctum, modular, validasi ketat & OpenAPI |
| Database | **PostgreSQL 16** | Relasional kuat, JSONB, full-text search |
| ORM | **Eloquent ORM** (Laravel) / Prisma (NestJS) | Relasi kaya, migrasi terstruktur, model scoped |
| Cache/Queue | **Redis 7 + Laravel Queue / BullMQ** | Sesi CBT, antrean PDF/notifikasi |
| Realtime | **WebSocket (Socket.IO/Reverb)** untuk monitor CBT, fallback SSE | Live monitor |
| Storage | **S3-compatible (MinIO/Cloudflare R2)** | Berkas, materi, foto |
| Website publik + PPDB | **Next.js 14+ (App Router, SSR/ISR)** | SEO, cepat |
| Sistem Admin | **Next.js (App Router, CSR dominan)** atau Vite + React | Dashboard kaya interaksi |
| UI Web | **Tailwind CSS + shadcn/ui** | Konsisten, cepat |
| State/data web | **TanStack Query + Zod + React Hook Form** | Cache, validasi |
| Mobile | **Android Native (Kotlin 2.x)** | Performa optimal, keamanan CBT native |
| UI Mobile | **Jetpack Compose + Material 3** | Deklaratif, modern |
| State/data mobile | **ViewModel + StateFlow + Hilt** | Testable, lifecycle-aware |
| HTTP mobile | **Retrofit + OkHttp** + Interceptor token | Refresh token otomatis |
| Push | **Firebase Cloud Messaging** | Standar |
| WhatsApp/Email | Provider via **adapter interface** | Mudah ganti vendor |
| PDF | **Playwright/Puppeteer (HTML→PDF)** di worker / Snappy PDF | Layout rapor fleksibel |
| QR | `qrcode` lib / BaconQrCode; verifikasi via URL bertanda tangan | Tanda tangan e-progress report |
| Excel | `exceljs` / Laravel Excel (`Maatwebsite`) | Import/export |
| Testing | PHPUnit / Pest (BE Laravel), Vitest + Playwright (Web), JUnit + Compose Test (Mobile) | |
| CI/CD | **GitHub Actions** | Lint, test, build, deploy |
| Kontainer | **Docker + docker-compose** (dev), Docker/K8s atau VPS (prod) | |
| Reverse proxy | **Nginx / Caddy** + HTTPS (Let's Encrypt) | |
| Observability | **Monolog/Pino** log, **Sentry** error, **Prometheus/Grafana** (P1), Uptime check | |

> **Catatan Implementasi Aktual**: Repositori backend `apps/api` secara resmi diimplementasikan menggunakan **Laravel 11 (PHP 8.2)** dengan database **PostgreSQL 16**, cache **Redis 7**, dan otentikasi token **Laravel Sanctum**. Seluruh konvensi nama tabel, route URL, ID fitur, dan skema respons API tetap mengikat sesuai PRD.

## 2. Struktur Repository
Dua pendekatan; pilih **A** (rekomendasi).

### A. Monorepo Struktur Terpadu
```
school-portal/                  # monorepo (pnpm + turborepo / unified workspace)
├─ apps/
│  ├─ api/                      # Backend REST API (Laravel 11 / PHP 8.2)
│  ├─ web/                      # Next.js Web Portal Terpadu (Publik, PPDB, Admin, Guru, Siswa, Ortu)
│  └─ mobile/                   # Android Native (Kotlin 2.x + Jetpack Compose)
├─ packages/
│  ├─ api-client/               # TS client hasil generate OpenAPI
│  ├─ ui/                       # komponen UI web bersama (shadcn/ui preset)
│  ├─ config/                   # eslint, tsconfig, tailwind preset
│  └─ shared/                   # zod schema, enum, konstanta
├─ docs/                        # paket PRD, audit, dan spesifikasi desain
├─ infra/                       # docker, nginx, CI
└─ AGENTS.md                    # aturan kerja AI agent
```

### B. Repo terpisah penuh (api, web, admin, mobile)
Dipakai bila tim berbeda dan rilis tidak boleh saling terkait. Kontrak tetap lewat OpenAPI.

## 3. Struktur Backend (Laravel 11 — Implementasi Aktual)
```
apps/api/
├─ app/
│  ├─ Http/
│  │  ├─ Controllers/Api/V1/    # Controller API per modul domain (Auth, Master, Academic, Cbt, Teacher, dsb)
│  │  ├─ Middleware/            # RequirePermission, BypassThrottle, ForceJsonResponse
│  │  └─ Requests/              # FormRequest validasi ketat
│  ├─ Models/                   # Model Eloquent (User, Student, Teacher, Exam, Invoice, dsb)
│  ├─ Services/                 # Logika bisnis terisolasi (AuthService, PostService, dsb)
│  └─ Traits/                   # ApiResponse standar ({data, meta} / {error})
├─ routes/
│  ├─ api.php                   # Loader utama modular prefix v1
│  └─ api/                      # 15 berkas rute modular (auth, portal, master, academic, cbt, dsb)
├─ database/
│  ├─ migrations/               # Skema tabel PostgreSQL
│  ├─ seeders/                  # Seeder RBAC & simulasi sistem
│  └─ factories/                # Model factories untuk testing
└─ tests/
   ├─ Feature/                  # Pengujian fungsional, RBAC, dan adversarial security
   └─ Unit/                     # Pengujian unit logika bisnis
```
*Aturan*: Controller tipis, validasi di FormRequest / DTO, akses data melalui Eloquent Model / Repository, tidak ada query raw tanpa parameter binding.

*(Catatan Arsitektur: Pola modular NestJS tetap dapat diadaptasi jika tim bermigrasi ke TypeScript backend).*

## 4. Struktur Frontend (web & admin)
```
src/
├─ app/                   # route (App Router)
├─ components/            # komponen spesifik app
├─ features/<modul>/      # komponen, hooks, api, schema per modul
├─ lib/                   # api client, auth, util
└─ styles/
```

## 5. Struktur Mobile (Android Kotlin)
```
app/src/main/java/id/sch/labschool/sistlabum/
├─ SistLabUmApp.kt           # Application class & Hilt setup
├─ MainActivity.kt           # Single activity entry point
├─ core/
│  ├─ network/               # Retrofit, OkHttp, AuthInterceptor, TokenAuthenticator, ErrorParser
│  ├─ database/              # Room DB, Entity, DAO (offline cache & pending actions queue)
│  ├─ security/              # TokenManager (EncryptedSharedPreferences / Android Keystore)
│  ├─ navigation/            # Navigation Compose, NavGraph, Route definitions, RoleGuard
│  ├─ theme/                 # Color, Type, Shape, Theme (Material 3 Labschool brand)
│  └─ common/                # UIState (Loading/Empty/Error), Result wrapper, Extension
└─ features/
   ├─ auth/                  # Login, ChangePassword, ForgotPassword, Profile
   ├─ notification/          # Inbox, FCM Service, DeepLinkHandler
   ├─ guardian/              # Shell Ortu, ChildSelector, Attendance, Grades, Report PDF
   ├─ teacher/               # Shell Guru, Schedule, QuickJournalAttendance, GradeEntry, LeaveApproval
   ├─ student/               # Shell Siswa, Schedule, DigitalCard, Assignments
   └─ cbt/                   # TokenEntry, ExamWorkspace, AntiCheat (FLAG_SECURE, lifecycle), ReviewSubmit
```

## 6. Environment & Konfigurasi
| Env | Fungsi | Data |
|---|---|---|
| `local` | Developer | Seed dummy |
| `staging` | UAT sekolah | Data uji, bukan data asli siswa |
| `production` | Live | Data asli, backup aktif |

Variabel env wajib (contoh, **jangan commit nilai asli**):
```
NODE_ENV, PORT, DATABASE_URL, REDIS_URL,
JWT_ACCESS_SECRET, JWT_REFRESH_SECRET, JWT_ACCESS_TTL=15m, JWT_REFRESH_TTL=14d,
S3_ENDPOINT, S3_BUCKET, S3_ACCESS_KEY, S3_SECRET_KEY,
FCM_SERVICE_ACCOUNT, WA_PROVIDER, WA_API_KEY, SMTP_URL,
APP_PUBLIC_URL, ADMIN_URL, CORS_ORIGINS, SENTRY_DSN
```
Sediakan `.env.example` yang lengkap, tanpa rahasia.

## 7. Standar Kode
| Aspek | Aturan |
|---|---|
| Bahasa kode | Identifier & komentar teknis Bahasa Inggris; teks UI Bahasa Indonesia |
| Penamaan DB | `snake_case`, tabel jamak (`students`) |
| Penamaan API | `kebab-case` path, `camelCase` JSON |
| Format | Prettier + ESLint (TS), ktlint + Android Lint (Kotlin) |
| Commit | Conventional Commits: `feat(be-ppdb-03): ...` |
| Branch | `main` (rilis), `develop`, `feat/<ID>-<slug>`, `fix/<slug>` |
| PR | Wajib review, CI hijau, mencantumkan ID fitur |
| Versi API | `/api/v1`, perubahan breaking → `/v2` |
| Migrasi DB | Hanya maju lewat Prisma migrate; jangan edit migrasi yang sudah dirilis |
| Waktu | Simpan UTC, tampilkan WIB/zona sekolah |
| ID | UUID v7 (atau ULID) untuk PK entitas utama |
| Uang | Integer (satuan terkecil), bukan float |

## 8. ADR (Architecture Decision Record)
Setiap keputusan teknis penting dicatat di `docs/adr/NNNN-judul.md` dengan format: Konteks → Keputusan → Konsekuensi → Status.

| ADR | Keputusan | Status |
|---|---|---|
| 0001 | Backend Laravel 11 (PHP 8.2) + PostgreSQL 16 (menggantikan draf awal NestJS) | Diterima |
| 0002 | API REST + OpenAPI sebagai kontrak | Diterima |
| 0003 | Monorepo web/admin/api, mobile terpisah | Diterima |
| 0004 | CBT memakai Redis untuk jawaban sementara | Diterima |
| 0005 | PDF dibuat di worker (queue) | Diterima |

## Changelog
- 1.1 (2026-10-27): Sinkronisasi stack backend resmi ke Laravel 11 (PHP 8.2) + PostgreSQL 16 + Redis 7 sesuai implementasi aktual repositori `apps/api` (Fase 5 GOV-01).
- 1.0: Draf awal.
