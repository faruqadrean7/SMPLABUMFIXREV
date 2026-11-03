# PROMPT — Pengaduan Bullying · Fase P1 (Pengerasan & Integrasi)

> Sumber PRD: `docs/prd/PRD_PENGADUAN_BULLYING.md`
> Fitur yang dikerjakan: **FE-REPORT-02** / **ADM-GUEST-02** (anti-abuse, notifikasi ringan, audit-baca, entry points)
> Prioritas: P1 · Web/Admin: Next.js (`apps/web`) · Backend: Laravel (`apps/api`)

---

Kerjakan pengerasan & integrasi lanjutan dari `docs/prd/PRD_PENGADUAN_BULLYING.md` (§10 P1).

## Konteks
- **Dependensi: Fase P0 (FE-REPORT-01 + ADM-GUEST-01) HARUS sudah selesai** — form `/lapor`, `/lapor/lacak`, dan tab review admin sudah jalan di atas backend `BE-GUEST-02`.
- Fase ini menambah lapisan keamanan, visibilitas, dan jalur masuk — **tanpa** mengubah alur inti.

## Prasyarat keputusan sekolah (PRD §11)
Konfirmasi dulu jawaban sekolah sebelum implementasi item terkait; bila belum ada, kerjakan yang tidak bergantung keputusan dan tandai sisanya sebagai tertunda:
1. Perlu CAPTCHA untuk form publik anonim? 2. Siapa dinotifikasi (BK saja / + Kepsek)? 3. SLA tanggapan untuk ditampilkan? 4. Badge eskalasi/prioritas khusus perundungan? 5. Kebijakan retensi & buka identitas. 6. Teks disclaimer + kontak darurat resmi.

## Yang bisa dibangun
1. **Anti-abuse**: CAPTCHA ringan / honeypot pada form publik (jika disetujui). Pertahankan rate limit existing. Trade-off aksesibilitas vs spam dicatat di PR.
2. **Notifikasi ringan** ke BK/Kepsek saat laporan baru — **tanpa** memuat isi/detail (hanya "laporan baru kategori Perundungan"), ikut kanal notifikasi existing. (doc 09 §6.7)
3. **Audit-baca** untuk detail laporan sensitif (siapa membuka laporan apa), jika belum ada.
4. **Entry points**: tautan menonjol "Lapor Perundungan" di navigasi web publik + tautan dari dashboard siswa; sertakan teks disclaimer final + kontak darurat.
5. **SLA / badge eskalasi** di UI pelapor & admin (jika disetujui sekolah).

## Batasan
- Jangan ubah semantik anonimitas/akses backend; tambahan harus kompatibel mundur.
- Notifikasi & log tidak boleh membocorkan isi laporan atau identitas pelapor.
- Migrasi (bila ada, mis. tabel audit-baca) baru saja; jangan edit migrasi lama.

## Acceptance Criteria (usulan — finalkan saat implementasi)
- Notifikasi laporan baru terkirim ke peran berwenang **tanpa** detail sensitif; diverifikasi via test.
- (Jika CAPTCHA) submit tanpa lolos verifikasi → ditolak; pelapor sah tetap bisa lapor.
- Audit-baca mencatat akses detail laporan; hanya peran berwenang yang bisa membuka.
- Entry point tampil di navigasi publik & dashboard siswa, mengarah ke `/lapor`.

## Pengujian (doc 10)
- Nama test berawalan ID (`fe-report-02...`, `adm-guest-02...`). Minimal: happy path, 401/403 akses, notifikasi tanpa detail, (jika ada) penolakan anti-abuse. Jangan turunkan coverage.

## Definition of Done
AGENTS.md §11: AC terpenuhi, lint/typecheck/test/build lulus, test ditambah, dokumen diperbarui, permission+audit diterapkan, tak ada data pribadi nyata, ringkasan PR siap.

---
**Ikuti AGENTS.md. Pastikan P0 selesai & keputusan sekolah terkait sudah ada. Tulis rencana singkat, tunggu persetujuan sebelum coding.**
