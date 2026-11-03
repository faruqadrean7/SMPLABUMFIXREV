# PRD — Form Pengaduan Bullying (Lapor Perundungan) + Review Admin

> Status: **Draft untuk ditinjau** · Tanggal: 2026-11-03 · Penulis: tim engineering
> Modul ID: `GUEST`/`REPORT` · Format ID fitur: `FE-REPORT-NN` / `ADM-GUEST-NN`
> Backend: Laravel (`apps/api`) · Web/Admin: Next.js (`apps/web`)
> Baca bersama: `04_PRD_BACKEND.md`, `09_SECURITY_PRIVACY.md`, `10_TESTING_QA.md`, `AGENTS.md`

---

## 1. Latar Belakang & Temuan Penting

Permintaan: tambahkan **form pengaduan bullying** di web sekolah yang tersambung ke admin agar admin dapat meninjau laporan.

**Temuan: backend-nya sudah ada** (`BE-GUEST-02`, sistem Aspirasi & Pengaduan / Whistleblowing). Yang sudah berjalan:

- `POST /api/v1/public/complaints` — submit publik, kategori sudah termasuk **`bullying`** (`akademik,fasilitas,bullying,pungli,pelayanan,lainnya`), opsi **anonim**.
- `GET /api/v1/public/complaints/{code}` — lacak status via kode tiket `ASP-YYYYMMDD-XXXX` tanpa login.
- `GET /complaints`, `GET /complaints/{id}`, `PATCH /complaints/{id}/status` — review oleh **Kepala Sekolah & Guru BK** (role `principal`/`counselor`, atau permission `complaint.read`/`complaint.manage`).
- **Proteksi whistleblower:** bila anonim → `reporter_user_id`, IP, dan user-agent **tidak** disimpan. Identitas pelapor non-anonim hanya terlihat oleh yang berwenang (`complaint.view_reporter`/principal/super_admin).
- Alur status `baru → ditinjau → selesai/ditolak` + timeline `complaint_events` + `audit_logs`.
- API client web sudah ada: `public-relations-api.ts` (`submitComplaintPublic`, `trackComplaintPublic`, `getComplaints`, `updateComplaintStatus`).

**Yang BELUM ada:** **halaman form publik di web sekolah** (folder `apps/web/src/app/(public)` belum punya halaman pengaduan/lapor) dan kemungkinan penajaman tab review admin khusus kategori perundungan.

→ Jadi proyek ini **reuse backend sepenuhnya**; pekerjaan utama = **frontend publik + penajaman UI review admin**. Tidak membangun ulang entitas.

## 2. Tujuan & Non-Tujuan

**Tujuan**
- Sediakan halaman publik **"Lapor Perundungan"** di web sekolah yang mengirim ke endpoint complaint existing (kategori `bullying`).
- Sediakan halaman **lacak laporan** via kode tiket.
- Pastikan admin berwenang (Kepsek & Guru BK) bisa meninjau & menanggapi laporan bullying, dengan identitas pelapor terlindungi.

**Non-Tujuan**
- Tidak mengubah kontrak/skema backend complaint (kecuali perbaikan kecil yang disepakati).
- Tidak membuat alur terpisah dari complaint umum (keputusan: **reuse**).
- Bukan kanal darurat real-time; untuk bahaya langsung diarahkan ke kontak darurat sekolah.

## 3. Keputusan yang Sudah Diambil (pemilik produk)
1. **Bangun di atas backend complaint existing** (reuse `BE-GUEST-02`), bukan alur baru.
2. **Akses lapor: publik anonim + login.** Siapa pun bisa lapor tanpa login (anonim didukung backend) **dan** siswa/ortu yang login bisa lapor dengan identitas (tercatat non-anonim, hanya terlihat yang berwenang).

## 4. Aktor & Hak Akses

| Aktor | Kemampuan |
|---|---|
| Publik / tamu | Submit laporan (anonim atau isi kontak), lacak via kode tiket |
| Siswa / Ortu (login) | Submit laporan dengan identitas (opsi tetap bisa anonim), lacak |
| **Guru BK (`counselor`)** | Lihat daftar & detail, ubah status, beri tanggapan |
| **Kepala Sekolah (`principal`)** | idem + boleh melihat identitas pelapor non-anonim |
| Super Admin | Akses penuh (sesuai existing) |

Tidak ada perubahan matriks permission — memakai yang sudah ada (`complaint.read`, `complaint.manage`, `complaint.view_reporter`).

## 5. Alur

**Lapor (publik/login):**
1. Buka `/lapor` (atau `/pengaduan`) → pilih kategori (default **Perundungan/Bullying** untuk entry point ini) → isi uraian (min 10, maks 5000 char) → centang **"Kirim sebagai anonim"** (opsional) → kirim.
2. Jika login & tidak anonim → identitas ikut (dari token). Jika anonim → backend tidak simpan identitas/IP/UA.
3. Sukses → tampilkan **kode tiket** + pesan simpan kode. Rate limit publik 60/menit/IP (sudah ada).

**Lacak:** `/lapor/lacak?kode=ASP-...` → tampilkan status + timeline tanggapan (tanpa identitas).

**Review admin:** di panel Admin → Public Relations / Aspirasi & Pengaduan → filter kategori `bullying`, buka detail, ubah status + tulis tanggapan (tercatat di timeline & audit).

## 6. UI (Web)

**Publik (FE-REPORT-01)** — halaman baru di `apps/web/src/app/(public)`:
- `/lapor` — form lapor. Komponen: pilih kategori, textarea uraian, toggle anonim, (opsional saat login) info "dilaporkan sebagai: Nama".
- `/lapor/lacak` — input kode tiket + tampilan status & timeline.
- State **loading/kosong/error** wajib; error server per field (RHF + Zod).
- Teks UI Bahasa Indonesia; nada suportif & aman bagi korban; sertakan disclaimer kerahasiaan + kontak darurat.
- Pakai `publicRelationsApi.submitComplaintPublic` / `trackComplaintPublic` (sudah ada) — jangan `fetch` manual.
- Jangan menaruh isi laporan di URL/query (privasi; POST body saja).

**Admin (ADM-GUEST-01)** — tinjau/rapikan tab pengaduan di `admin/public-relations`:
- Daftar berpaginasi + filter kategori (`bullying`), status, tanggal, pencarian.
- Detail: uraian, timeline, aksi ubah status + tanggapan.
- Badge "Anonim"; identitas hanya tampil bila berwenang (ikuti perilaku backend).
- Indikator laporan **baru** belum ditinjau.

## 7. Acceptance Criteria

**FE-REPORT-01 — Form & lacak publik [P0]**
- AC1: Submit kategori `bullying` dengan uraian valid → dapat kode tiket `ASP-...` & pesan sukses.
- AC2: Uraian < 10 char → error validasi tampil per field (tidak submit).
- AC3: Centang anonim → payload `is_anonymous=true`; UI tidak menampilkan/mengirim identitas.
- AC4: Saat login & tidak anonim → laporan tercatat dengan identitas (verifikasi via tiket di sisi admin).
- AC5: Lacak dengan kode valid → status + timeline tampil; kode salah → pesan "tidak ditemukan" (tanpa membocorkan data).
- AC6: State loading/kosong/error benar; isi laporan tidak pernah muncul di URL.

**ADM-GUEST-01 — Review admin [P0]**
- AC1: Guru BK & Kepsek dapat melihat daftar & detail laporan bullying dalam cakupan sekolahnya.
- AC2: Role tanpa wewenang → 403 (tidak ada data bocor).
- AC3: Laporan anonim → tidak ada identitas/IP/UA yang bisa dilihat admin mana pun.
- AC4: Ubah status + tanggapan → tercatat di timeline & `audit_logs`; status `selesai/ditolak` tak bisa mundur ke `baru/ditinjau`.

## 8. Keamanan & Privasi (wajib, ref doc 09 & §6)
- **Anonimitas kuat:** anonim → tanpa `reporter_user_id`/IP/UA (sudah dijamin `ComplaintService`). Frontend tidak boleh mengirim identitas saat anonim.
- Isi laporan = data sensitif anak: **tidak** masuk log aplikasi, **tidak** masuk isi notifikasi (notif ke admin hanya "ada laporan baru kategori Perundungan", tanpa detail — ref §6.7).
- Deny-by-default; cakupan sekolah (multi-tenant) sudah diterapkan.
- Rate limit publik existing (anti-spam). Pertimbangkan CAPTCHA ringan bila spam (P1, lihat pertanyaan).
- Audit baca untuk detail laporan sensitif (P1 bila belum ada).

## 9. Pengujian (ref doc 10)
- Nama test berawalan ID (`fe-report-01...`, `adm-guest-01...`).
- Backend sudah punya test complaint — tambah/kuatkan: happy path submit `bullying`, anonim tak menyimpan identitas, 401/403 akses review, transisi status ilegal 422.
- FE: test komponen form (validasi, anonim toggle), test halaman lacak.

## 10. Fase & Dependensi
- **P0:** halaman `/lapor` + `/lacak` + penajaman tab review admin (semua pakai API existing).
- **P1:** CAPTCHA/anti-abuse, notifikasi ringan ke BK/Kepsek, audit-baca, entry point menonjol di navigasi web + tautan dari dashboard siswa.
- Dependensi: backend `BE-GUEST-02` (ada), `public-relations-api.ts` (ada). **Tidak ada blocker backend.**

## 11. Pertanyaan untuk Sekolah (AGENTS.md §8)
1. Perlukah **penanda urusan mendesak/eskalasi** khusus perundungan (mis. badge "prioritas") di luar kategori biasa?
2. Siapa **penanggung jawab utama** laporan bullying — Guru BK saja, atau Kepsek juga otomatis dinotifikasi?
3. Perlu **CAPTCHA** untuk form publik anonim? (trade-off: aksesibilitas vs spam)
4. **SLA tanggapan** (mis. ditinjau ≤ 2×24 jam) untuk ditampilkan ke pelapor?
5. Kebijakan **retensi** & siapa boleh membuka identitas pelapor non-anonim.
6. Teks disclaimer kerahasiaan & **kontak darurat** resmi sekolah yang ingin ditampilkan.

## 12. Risiko & Asumsi
- Asumsi: perilaku anonim backend sudah benar (terverifikasi dari `ComplaintService`) — frontend wajib tidak merusaknya.
- Risiko: pelapor mengira ini kanal darurat → mitigasi dengan disclaimer + kontak darurat.
- Risiko: admin tanpa pelatihan membuka identitas → dibatasi permission + audit.
