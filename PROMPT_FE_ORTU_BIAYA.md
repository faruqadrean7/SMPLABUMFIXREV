# Prompt Tugas: FE-ORTU-BIAYA-01 — Hentikan Invoice Palsu di Portal Ortu

> Salin blok di bawah sebagai prompt untuk sesi/agent berikutnya. Semua path, rute, dan payload sudah diverifikasi langsung dari kode pada 2026-11-01 (lanjutan audit `AUDIT_KONEKTIVITAS_PORTAL.md`, temuan X-01 keluarga rekayasa data ortu).

---

## PROMPT (siap pakai)

```
Kerjakan FE-ORTU-BIAYA-01: ganti data biaya/tagihan palsu di portal ortu dengan
data tagihan nyata dari backend.
Ikuti AGENTS.md. Tulis rencana singkat dulu, dan TUNGGU persetujuan sebelum coding.

## Masalah (sudah diaudit — verifikasi secukupnya, jangan audit ulang dari nol)
Portal ortu menampilkan tagihan/kwitansi yang SEPENUHNYA DIKARANG di frontend.
`activeFees` di guardian-context adalah array statis hardcoded (TIDAK memanggil API
sama sekali), berisi nominal, nomor kwitansi, tanggal, dan nama kasir fiktif yang
ditampilkan seolah catatan keuangan nyata. Ini paling berisiko dari seluruh keluarga
rekayasa data ortu (menyesatkan soal uang; AGENTS.md §6.2 "jangan menulis data pribadi
nyata/olahan seolah nyata" & §9 "dilarang membuat kode/dokumentasi fiktif seolah berjalan").

### Bukti (file & baris)
- FE (karangan, dipakai sekarang):
  apps/web/src/lib/guardian-context.tsx → `const activeFees = useMemo(...)`:
  array statis 2 invoice hardcoded ('fee-01'/'fee-02', 'Rp 450.000',
  'KW-TU/2026/10/0189', cashier 'Siti Rohmah (Kasir TU)'). TIDAK ada apiClient/hook.
- Halaman yang menampilkan:
  apps/web/src/app/(portal)/portal/ortu/biaya/page.tsx — render f.item_name,
  f.period_label, f.nominal_text, f.status, f.receipt_number (badge 'lunas' bila
  status === 'Lunas / Bebas Tanggungan').
- Tipe: apps/web/src/lib/mock-guardian-data.ts → interface SchoolFeeItem.
  PENTING: union status hanya { 'Lunas / Bebas Tanggungan' | 'Dalam Proses
  Administrasi TU' } — KEDUANYA bernada "aman". Tidak ada status BELUM BAYAR /
  JATUH TEMPO. Inilah yang membuat semua tagihan tampak lunas.

### Backend (SUDAH ADA — jangan ubah di tugas ini)
Rute (apps/api/routes/api/portal.php):
  GET /me/children/{child}/invoices  -> MeFinanceController::childInvoices
  (Gate 'view-child' → 403 bila bukan wali sah; 404 bila siswa tak ada)
Implementasi (apps/api/app/Http/Controllers/Api/V1/MeFinanceController.php:52):
  mengembalikan koleksi model Invoice (mentah) dengan relasi
  ['items.feeType', 'payments.proofFile'], diurутkan due_date asc.
→ VERIFIKASI bentuk kolom nyata sebelum memetakan:
  - baca apps/api/app/Models/Invoice.php + migrasi invoices (kolom:
    invoice_number, total_amount/amount, status + NILAI enum status nyata,
    due_date, period/billing_period, student_id).
  - baca model/relasi items.feeType (nama item tagihan) & payments
    (tanggal verifikasi, verifiedBy → nama kasir, nomor kwitansi/receipt).

## Yang dikerjakan (scope: FE menampilkan tagihan nyata)
1. Tambah hook useChildInvoices(childId) di apps/web/src/lib/use-guardian-data.ts,
   meniru pola useChildGrades (queryKey ['child-invoices', childId],
   GET /me/children/${childId}/invoices, enabled !!childId, staleTime 5m).
   Tambah interface ChildInvoice sesuai payload backend nyata.
2. Buat fungsi murni buildChildFees(invoices): SchoolFeeItem[] di file baru
   apps/web/src/lib/guardian-fees.ts (konsisten dgn guardian-grades.ts &
   guardian-attendance.ts yang sudah ada):
   - map invoice nyata → SchoolFeeItem; nominal diformat dari angka nyata (Rp);
     receipt_number & cashier_name dari data pembayaran nyata, '' bila belum ada;
     JANGAN mengarang nilai apa pun; array kosong bila tak ada tagihan.
   - map status invoice backend → label jujur (lihat keputusan di bawah).
3. guardian-context: panggil useChildInvoices(activeChildId); ganti isi
   `activeFees` useMemo menjadi buildChildFees(invoicesData). Hapus array statis.
   Sambungkan juga ke isLoading/isError/refetchAll bila pola context memerlukannya.
4. Halaman ortu/biaya: tangani status baru (badge warna utk BELUM BAYAR/JATUH
   TEMPO), state loading/kosong/error (empty-state saat tak ada tagihan).

## Keputusan yang SUDAH DITETAPKAN (jangan tanya lagi; patuhi)
A. Status tagihan DITAMPILKAN JUJUR dari backend. SchoolFeeItem.status WAJIB
   dilebarkan dari 2 status "lunas" menjadi union jujur (mis.
   'Lunas' | 'Belum Dibayar' | 'Menunggu Verifikasi' | 'Jatuh Tempo' | 'Dibayar
   Sebagian') — pemetaan PERSIS tergantung nilai enum status di tabel invoices
   (verifikasi dulu). DILARANG memaksa semua jadi "Lunas".
B. Scope pembayaran: tugas ini HANYA menampilkan tagihan nyata (READ).
   Alur aksi bayar DITETAPKAN = **transfer rekening resmi sekolah + konfirmasi
   via WhatsApp TU** (BUKAN Midtrans/gateway online), dan dikerjakan di TIKET
   TERPISAH FE-ORTU-BIAYA-BAYAR (lihat sketsa di bawah). Jangan bangun alur bayar
   di tiket ini.

## Sketsa tiket lanjutan FE-ORTU-BIAYA-BAYAR (jangan kerjakan sekarang)
- Tombol "Bayar" pada tagihan berstatus belum lunas membuka dialog berisi:
  nomor rekening resmi sekolah (dari site-settings/CMS, BUKAN hardcode) + nominal
  tagihan nyata + instruksi transfer + tombol "Konfirmasi via WhatsApp" yang
  membuka wa.me nomor TU dengan pesan terisi (nama anak, nomor invoice, nominal).
- Tanpa nilai/nomor rekening/nomor WA hardcode; semua dari konfigurasi.
- Bila backend submitPayment (unggah bukti) hendak dipakai, itu keputusan lanjutan.

## Acceptance Criteria
AC1  Portal ortu TIDAK lagi mengimpor/menampilkan array statis activeFees; biaya
     berasal dari GET /me/children/{id}/invoices via useChildInvoices.
AC2  Nominal, nomor kwitansi, tanggal, status, nama kasir yang tampil berasal dari
     data invoice nyata — tidak ada nilai hardcoded (tidak ada 'Rp 450.000',
     'KW-TU/2026/10/0189', 'Siti Rohmah').
AC3  Status tagihan dipetakan jujur: tagihan belum lunas TIDAK ditampilkan sebagai
     "Lunas". (Union SchoolFeeItem.status dilebarkan sesuai keputusan A.)
AC4  Tanpa tagihan → empty-state; saat memuat → loading; gagal → error state.
AC5  Anti-IDOR tetap: hanya memakai endpoint ber-Gate 'view-child' (ortu hanya
     anaknya). Tidak menambah query lintas-anak.
AC6  Logika pemetaan diekstrak ke fungsi murni buildChildFees + uji FE berawalan
     FE-ORTU-BIAYA-01 (map status nyata, nominal nyata, empty = []).

## Batasan
- JANGAN ubah controller/route/model backend (sudah ada & benar).
- JANGAN kerjakan alur submit pembayaran di tugas ini (tiket terpisah).
- JANGAN sentuh rekayasa lain yang masih tersisa (activePoints, activeSchedule,
  nama/NIP wali kelas) — masing-masing tiket sendiri.
- Jangan mengarang nilai; data absen → kosong/empty-state.

## Verifikasi sebelum klaim selesai
- Baca model Invoice + migrasi untuk kolom & nilai enum status PERSIS.
- cd apps/web && npx vitest run src/test/fe-ortu-biaya-01.test.ts
- cd apps/web && pnpm typecheck   (bila error hanya di .next/types, hapus .next lalu ulangi)
- cd apps/web && pnpm eslint <file yang diubah>
- E2E manual (kredensial uji di memory): login ortu → buka /portal/ortu/biaya →
  pastikan tagihan sesuai data invoice anak (buat 1 invoice belum lunas → harus
  tampil "Belum Dibayar", bukan "Lunas").

## Catatan arsitektur (konsisten dgn commit sebelumnya)
Sudah ada pola fungsi murni + uji untuk data ortu:
  - apps/web/src/lib/guardian-grades.ts  (+ test fe-ortu-nilai-01)
  - apps/web/src/lib/guardian-attendance.ts (+ test fe-ortu-presensi-01)
Ikuti pola yang sama untuk guardian-fees.ts.

## Format laporan akhir: ikuti AGENTS.md bagian 11.
```

---

## Catatan untuk Anda (pemberi tugas)
- **Kabar baik:** endpoint `GET /me/children/{child}/invoices` sudah ada dan ber-Gate (anti-IDOR). Jadi ini **FE-only** — tak perlu backend baru.
- **Keputusan sudah dikunci (2026-11-02):** (A) status `SchoolFeeItem` dilebarkan agar jujur menampilkan tagihan belum lunas — WAJIB; (B) aksi bayar = transfer rekening + konfirmasi WhatsApp TU (bukan Midtrans), dikerjakan di tiket terpisah `FE-ORTU-BIAYA-BAYAR`. Tiket BIAYA-01 ini tetap read-only.
- **Base branch:** `develop` (file biaya identik di develop & HEAD, seperti tiket ortu sebelumnya) — agar diff PR bersih.
- Tiket ini melanjutkan pola `guardian-grades.ts` / `guardian-attendance.ts` yang sudah dibuat.
