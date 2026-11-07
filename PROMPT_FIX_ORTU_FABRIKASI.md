# Prompt Tugas: FE-ORTU-FABRIKASI-01 — Hentikan Jadwal/Poin/Wali-Kelas Palsu di Portal Ortu

> Diverifikasi langsung dari kode & rute backend pada 2026-11-02 (branch `develop` setelah
> merge terbaru). **Konteks penting:** tiket lama `FE-ORTU-BIAYA-01` SUDAH SELESAI
> (`guardian-context.tsx:134` memakai `buildChildFees(invoicesData)` + `<ParentFinanceTab>`
> nyata). Dokumen ini MENGGANTIKAN `PROMPT_FE_ORTU_BIAYA.md` yang kini usang.

## Masalah (sudah diaudit — verifikasi secukupnya, jangan audit ulang)
Walau biaya sudah nyata, `apps/web/src/lib/guardian-context.tsx` MASIH menyuntik data
karangan yang tampil ke orang tua seolah nyata (langgar AGENTS.md §6.2 "jangan tulis data
olahan seolah nyata" & §9 "dilarang kode/dokumentasi fiktif seolah berjalan"):

| # | Yang dipalsukan | Lokasi | Keterangan |
|---|---|---|---|
| 1 | **Jadwal pelajaran anak — 100% hardcoded** | `guardian-context.tsx:343–398` (`activeSchedule` useMemo) | Hanya cabang `class_name.includes('9')`; isi guru fiktif (Ahmad Yani, Fitri Handayani, Dr. Hendra Wijaya, Budi Santoso, Siti Aminah), ruang, topik, status "Sedang Berlangsung". TIDAK memanggil API apa pun. |
| 2 | **Nilai poin dikarang** | `guardian-context.tsx:313–342` (`activePoints`) | Walau membaca `characterNotesData.homeroomNote` nyata, poin di-hardcode `25`/`20` + entri `pt-02` sepenuhnya fiktif + tanggal karangan. |
| 3 | **NIP & HP wali kelas fallback fiktif** | `guardian-context.tsx:202–203, 252–253` | `NIP. 19840214 200902 2 003` & HP `0812-3456-7890` dipakai saat backend kosong, ditampilkan seolah nyata. (Nama wali kelas SUDAH baca nyata dari `childDetail.homeroom_teacher.name`.) |

Tampil di: dashboard ortu (`features/portal/guardian-dashboard-view.tsx`) & profil anak.

## Realitas endpoint backend (SUDAH diverifikasi — JANGAN mengarang endpoint)
Rute ortu yang ADA (`apps/api/routes/api/portal.php`, prefix `me/children/{child}`, ber-Gate):
  attendance, grades, report-cards, character-notes, progress-reports, invoices.
**TIDAK ADA** `GET /me/children/{child}/schedule` maupun `.../points` untuk ortu.
(`GET /me/schedule` hanya untuk SISWA yang login sendiri — bukan untuk ortu melihat anaknya.)
Wali kelas: `GET /me/children/{child}` (MeChildController::show) sudah memuat relasi wali
kelas → sumber nyata untuk nama/NIP/HP ADA (verifikasi field nyata di payload show).

→ Konsekuensi: perbaikan jadwal & poin TIDAK bisa sekadar "wire API" (endpointnya belum ada).
  Tugas ini FE-only: HENTIKAN fabrikasi, tampilkan keadaan jujur. Menyediakan data nyata
  jadwal/poin untuk ortu = tiket BACKEND terpisah + keputusan sekolah (lihat bagian bawah).

---

## PROMPT (siap pakai) — BAGIAN A: FE-only, kerjakan sekarang

```
Kerjakan FE-ORTU-FABRIKASI-01: hentikan data palsu jadwal, poin, dan wali kelas di portal
ortu. FE-only — JANGAN ubah backend, JANGAN mengarang endpoint yang belum ada.
Ikuti AGENTS.md. Tulis rencana singkat dulu.

File utama: apps/web/src/lib/guardian-context.tsx (+ komponen yang menampilkannya:
features/portal/guardian-dashboard-view.tsx & halaman profil anak bila perlu empty-state).

1) activeSchedule (baris 343–398): HAPUS seluruh array jadwal hardcoded. Karena belum ada
   endpoint jadwal untuk ortu, kembalikan [] dan pastikan UI menampilkan empty-state jujur,
   mis. "Jadwal pelajaran belum tersedia di portal orang tua." JANGAN menampilkan jadwal/guru
   karangan. (Jangan hapus tipe ChildScheduleItem — biarkan untuk dipakai saat endpoint ada.)

2) activePoints (baris 313–342): HAPUS poin & entri hardcoded. Boleh tetap menampilkan catatan
   karakter NYATA dari characterNotesData bila ada, TAPI tanpa mengarang angka poin/tanggal
   yang tidak berasal dari backend. Bila tak ada data poin nyata → []  + empty-state
   "Belum ada catatan poin." (Nilai `points` hanya boleh dari data nyata; bila backend tak
   mengembalikan angka, jangan tampilkan angka poin sama sekali.)

3) Wali kelas (baris 201–203, 251–253 dan DEFAULT_CHILD ~59–61): hapus NIP & HP fiktif.
   - Nama: tetap dari childDetail?.homeroom_teacher?.name; fallback "-" / "Belum ditetapkan".
   - NIP & HP: HANYA dari childDetail?.homeroom_teacher?.nip/phone bila backend menyediakannya
     (verifikasi payload /me/children/{child}); bila tidak ada → "-" / sembunyikan baris.
     JANGAN memakai NIP/HP contoh.
   Periksa juga konstanta DEFAULT_CHILD agar tak membocorkan NIP/HP palsu sebagai default.

Batasan:
- JANGAN ubah backend/route/model. JANGAN tambah endpoint fiktif.
- JANGAN sentuh biaya (sudah selesai), presensi, nilai (sudah nyata).
- Empty-state harus jujur & rapi (loading/kosong), bukan placeholder karangan.
- Teks UI Bahasa Indonesia; identifier Bahasa Inggris.

Acceptance Criteria:
AC1 Tidak ada lagi jadwal/guru/ruang/topik hardcoded di activeSchedule; data kosong →
    empty-state jujur.
AC2 Tidak ada angka poin/tanggal/entri poin yang dikarang; hanya data nyata atau empty-state.
AC3 Tidak ada NIP/HP wali kelas fiktif di mana pun (termasuk DEFAULT_CHILD & cabang fallback);
    hanya data nyata atau "-".
AC4 grep bersih: tidak ada 'Ahmad Yani'/'Fitri Handayani'/'Hendra Wijaya'/'Budi Santoso'/
    'Siti Aminah'/'19840214 200902 2 003'/'0812-3456-7890' di guardian-context.tsx.
AC5 Dashboard & profil anak tetap render tanpa error saat data kosong.

Verifikasi sebelum klaim selesai:
- grep memastikan AC4 bersih.
- cd apps/web && pnpm typecheck (bila error hanya di .next/types, hapus .next lalu ulangi)
- cd apps/web && pnpm eslint src/lib/guardian-context.tsx src/features/portal/guardian-dashboard-view.tsx
- (opsional) tambah/ perbarui uji fungsi murni bila logika diekstrak, nama berawalan
  FE-ORTU-FABRIKASI-01.
- E2E manual (kredensial uji di memory): login ortu → dashboard & profil anak → pastikan
  jadwal/poin kosong tampil sebagai empty-state jujur, wali kelas tanpa NIP/HP palsu.

Format laporan akhir: ikuti AGENTS.md bagian 11.
```

---

## BAGIAN B: Keputusan untuk pemberi tugas (JANGAN dikerjakan di tiket A)
Jika sekolah ingin ORANG TUA melihat **jadwal pelajaran** dan **poin kedisiplinan/prestasi**
anak secara NYATA, itu memerlukan endpoint backend baru (bukan FE):
  - `GET /me/children/{child}/schedule`  (Gate view-child, anti-IDOR) — kelas anak → jadwal.
  - `GET /me/children/{child}/points`     (Gate view-child) — poin dari StudentPointController.
Keduanya tiket BACKEND terpisah (mis. `BE-ORTU-SCHEDULE-01`, `BE-ORTU-POINTS-01`) dan
tunduk pada keputusan sekolah (§8). Setelah endpoint ada, barulah FE menambah hook
useChildSchedule/useChildPoints (pola useChildInvoices) untuk mengisi empty-state Bagian A.

## Catatan untuk Anda (pemberi tugas)
- **Bagian A kecil & FE-only, satu file utama** — hilangkan fabrikasi, tampilkan jujur.
- **Bagian B butuh keputusan + backend** — apakah ortu boleh lihat jadwal & poin anak? Bila ya,
  buat tiket backend; jangan dipaksakan di FE.
- **Base branch:** `develop`.
- **Status terkait:** `PROMPT_FE_ORTU_BIAYA.md` usang (BIAYA-01 sudah mendarat) — arsipkan.
- **Commit (Bagian A):** `fix(fe-ortu-fabrikasi-01): hentikan jadwal/poin/wali-kelas palsu di portal ortu`
