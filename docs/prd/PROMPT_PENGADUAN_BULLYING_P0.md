# PROMPT — Pengaduan Bullying · Fase P0 (Form Publik + Review Admin)

> Sumber PRD: `docs/prd/PRD_PENGADUAN_BULLYING.md`
> Fitur yang dikerjakan: **FE-REPORT-01** (form & lacak publik) + **ADM-GUEST-01** (review admin)
> Prioritas: P0 · Web/Admin: Next.js (`apps/web`) · Backend: Laravel (`apps/api`) — **sudah ada, reuse**

---

Kerjakan **FE-REPORT-01** dan **ADM-GUEST-01** dari `docs/prd/PRD_PENGADUAN_BULLYING.md`.

## Konteks (PENTING — reuse, jangan bangun ulang)
- Backend pengaduan **sudah ada** (`BE-GUEST-02`): `POST /api/v1/public/complaints` (kategori sudah termasuk `bullying`, opsi `is_anonymous`), `GET /api/v1/public/complaints/{code}` (lacak via kode tiket `ASP-YYYYMMDD-XXXX`), `GET /complaints`, `GET /complaints/{id}`, `PATCH /complaints/{id}/status` (review Kepsek/Guru BK).
- Proteksi whistleblower sudah dijamin `ComplaintService`: anonim → tidak simpan `reporter_user_id`/IP/UA; identitas non-anonim hanya untuk yang berwenang.
- API client web **sudah ada**: `apps/web/src/features/public-relations/public-relations-api.ts` (`submitComplaintPublic`, `trackComplaintPublic`, `getComplaints`, `getComplaint`, `updateComplaintStatus`) + tipe di `types.ts`.
- Yang BELUM ada: **halaman form publik** di `apps/web/src/app/(public)`. Tab review admin ada di `admin/public-relations` — tinjau & rapikan untuk kategori bullying.

## Batasan
- **Jangan ubah kontrak/skema backend** complaint. Pakai endpoint & API client existing.
- **Jangan** buat alur terpisah dari complaint umum (keputusan produk: reuse).
- **Jangan** kirim identitas saat anonim; **jangan** taruh isi laporan di URL/query — POST body saja.
- Teks UI Bahasa Indonesia, nada suportif & aman bagi korban.

## Yang harus dibangun
1. **Halaman publik `/lapor`** (atau `/pengaduan`): form dengan pilih kategori (default **Perundungan/Bullying** untuk entry point ini), textarea uraian (min 10, maks 5000), toggle **"Kirim sebagai anonim"**, dan — saat login & tidak anonim — info "dilaporkan sebagai: {Nama}". Submit via `submitComplaintPublic`. Sukses → tampilkan **kode tiket** + imbauan simpan kode.
2. **Halaman lacak `/lapor/lacak`**: input kode tiket → tampilkan status + timeline (tanpa identitas) via `trackComplaintPublic`.
3. **Disclaimer** kerahasiaan + **kontak darurat** (teks menyusul dari sekolah; sementara placeholder jelas).
4. **Review admin (ADM-GUEST-01)**: pastikan/rapikan daftar berpaginasi + filter kategori `bullying`/status/tanggal/pencarian, detail + timeline, aksi ubah status + tanggapan, badge "Anonim", indikator laporan baru. Identitas hanya tampil bila berwenang (ikuti perilaku backend).

## Keamanan & Privasi (wajib, PRD §8 + doc 09)
- Anonimitas kuat: frontend tidak boleh mengirim/menampilkan identitas saat anonim.
- Isi laporan = data sensitif anak: jangan masuk log aplikasi; jika ada notifikasi, isinya tanpa detail (hanya "laporan baru kategori Perundungan").
- Form & validasi: RHF + Zod, error server per field; state loading/kosong/error wajib.
- Rate limit publik existing (60/menit/IP) dipertahankan.

## Acceptance Criteria yang harus lulus
- **FE-REPORT-01** AC1–AC6 (dapat kode tiket; validasi < 10 char; anonim tak kirim identitas; login non-anonim tercatat; lacak benar/kode salah aman; state lengkap & isi tak di URL).
- **ADM-GUEST-01** AC1–AC4 (BK/Kepsek lihat dalam cakupan sekolah; role tak berwenang 403; anonim tanpa identitas; ubah status+tanggapan tercatat di timeline & audit, transisi mundur dilarang).

## Pengujian (doc 10)
- Nama test berawalan ID (`fe-report-01...`, `adm-guest-01...`). FE: form (validasi, toggle anonim), halaman lacak. Perkuat test backend complaint existing (jangan hapus/nonaktifkan). Jangan turunkan coverage.

## Definition of Done
AGENTS.md §11: AC terpenuhi satu per satu, lint/typecheck/test/build lulus, test ditambah, dokumen diperbarui, tak ada data pribadi nyata, state UI lengkap, ringkasan PR siap.

---
**Ikuti AGENTS.md. Tulis rencana singkat dulu, tunggu persetujuan sebelum coding. Tidak ada blocker backend — fokus FE + penajaman admin.**
