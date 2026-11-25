# PRD — BE-GUEST-04: Lampiran Foto pada Pengaduan/Aspirasi (Lapor Kejadian)

> **Status: USULAN / DRAFT — belum disetujui.** Catatan sesuai AGENTS.md §2.2. Menyentuh data
> **SANGAT SENSITIF** (identitas pelapor, bisa memuat wajah/lokasi anak) → wajib tunduk penuh pada
> `09_SECURITY_PRIVACY.md`. Keputusan di Bagian 9 (privasi & retensi) harus dijawab sekolah/client
> sebelum dikerjakan (AGENTS.md §8).
>
> Memperluas **BE-GUEST-02 Aspirasi/whistleblowing** (`04_PRD_BACKEND.md:197`) yang sudah menyebut
> "lampiran" + opsi anonim + kode tiket, dan memakai ulang **BE-FILE-01** (presign upload).
> Nama tabel induk `complaints` perlu **diverifikasi dari kode** saat implementasi (jangan mengarang).

---

## 1. Latar & ruang lingkup

**Keinginan client:** pelapor bisa **melampirkan foto saat kejadian** ketika mengisi pengaduan /
aspirasi / laporan perundungan (termasuk dari HP, ambil foto langsung).

**Konteks sensitif:** foto kejadian di sekolah dapat memuat wajah anak (korban/pelaku/saksi) dan
petunjuk lokasi. Pengaduan bisa **anonim**. Maka lampiran foto **tidak boleh** mengurangi
anonimitas atau bocor. Ini bukan unggah foto biasa.

**Lingkup:** menambah lampiran foto pada alur pengaduan yang ada (publik & terautentikasi),
dengan pembersihan metadata, enkripsi penyimpanan, akses terbatas, dan audit baca.
**Di luar lingkup:** moderasi/AI konten, alur tindak lanjut (sudah ada di BE-GUEST-02).

---

## 2. Aktor

| Aktor | Boleh |
|---|---|
| Pelapor (publik, boleh anonim) | melampirkan foto saat membuat pengaduan; cek status via kode tiket |
| Admin GUEST / unit tertuju | melihat pengaduan + foto (ter-audit), menindaklanjuti |
| BK (bila kategori perundungan) | akses sesuai disposisi (ter-audit) |

Deny-by-default. Siapa persisnya boleh melihat foto = **keputusan sekolah** (Bagian 9-D2).

---

## 3. Alur

```
(anonim atau login)
(1) POST /public/complaints/attachments/presign   (rate-limited, batasi jumlah & ukuran)
      → kembalikan URL unggah bertanda tangan + attachment_token
(2) Klien unggah foto ke URL tsb
(3) POST /public/complaints/attachments/{token}/confirm
      → server: validasi MIME+magic bytes → re-encode gambar → HAPUS SEMUA metadata (EXIF/GPS)
        → simpan ke bucket PRIVAT terenkripsi → nama file acak
(4) POST /public/complaints   { kategori, isi, anonim, attachment_tokens[] }
      → buat tiket + kode pelacak acak panjang ; anonim: IP & identitas TIDAK disimpan
(5) GET /public/complaints/{code}  → pelapor lihat status (tanpa login)
(6) Admin: GET /complaints/{id}  → lihat detail + foto via signed URL berumur pendek (DIAUDIT baca)
```

Waktu "saat kejadian" = **timestamp server saat unggah**, bukan EXIF (EXIF dibuang; tidak dipercaya).

---

## 4. Keamanan & privasi (WAJIB — inti fitur ini)

1. **Hapus seluruh metadata foto** saat confirm, khususnya **GPS/EXIF**. Gambar di-**re-encode**
   (bukan sekadar strip) agar tidak ada metadata/thumbnail tersembunyi. Deanonimisasi lewat EXIF
   adalah risiko utama (bertentangan dengan "anonim: IP tidak disimpan", `04:198`).
2. **Klasifikasi SANGAT SENSITIF** (`09:11`): **enkripsi at-rest level aplikasi**, akses hanya peran
   berwenang, **setiap baca diaudit** (siapa melihat foto/pengaduan, kapan).
3. **Bucket privat**; akses hanya via **signed URL berumur pendek** (BE-FILE-01). Tidak pernah via
   `/public/*` statis. Nama objek acak (tidak mengandung nama/kelas/kode tiket).
4. **Anonimitas:** untuk pengaduan anonim, jangan simpan `uploaded_by`, IP, user-agent pada file
   maupun pengaduan.
5. **Validasi unggahan:** MIME + **magic bytes** + ukuran; hanya gambar (jpg/png/webp — HEIC lihat
   D4); batasi jumlah foto & ukuran per foto (D3); pindai virus (P1) — `09:54`.
6. **Notifikasi tidak memuat isi sensitif** (AGENTS.md §6.7): push/WA hanya "ada pengaduan baru,
   buka portal" — **tanpa** foto, thumbnail, kategori rinci, atau isi.
7. **Jangan log isi/foto** (AGENTS.md §6.5). Audit mencatat *peristiwa akses*, bukan konten.
8. **Peringatan ke pelapor anonim:** tampilkan peringatan bahwa isi foto (wajah/lokasi) dapat
   membuka identitas meski metadata sudah dibersihkan.
9. **Rate limit** endpoint presign & submit publik; kode tiket acak panjang (`09:61`) cegah enumerasi.

---

## 5. Perubahan DB (migrasi baru, kompatibel mundur)

Tabel baru `complaint_attachments` (verifikasi nama tabel induk `complaints` dari kode):

| Kolom | Tipe | Catatan |
|---|---|---|
| `id` | uuid | |
| `complaint_id` | uuid FK | tiket pengaduan |
| `file_id` | uuid FK files | objek di bucket privat terenkripsi |
| `created_at` | timestamptz | = waktu "saat kejadian" (server) |

**Tidak** menyimpan kolom identitas pelapor di tabel ini (jaga anonimitas). Jangan edit migrasi lama.

---

## 6. Endpoint (baru / perluasan)

| Aksi | Method + Path | Akses |
|---|---|---|
| Presign unggah lampiran | `POST /public/complaints/attachments/presign` | publik, rate-limited |
| Konfirmasi (strip EXIF + enkripsi) | `POST /public/complaints/attachments/{token}/confirm` | publik, rate-limited |
| Buat pengaduan + lampiran | `POST /public/complaints` (perluas: `attachment_tokens[]`) | publik |
| Lihat detail + foto | `GET /complaints/{id}` (sertakan signed URL foto) | admin/unit (diaudit) |

Perbarui OpenAPI + klien tergenerate; kode error baku §1.2. Kontrak `POST /public/complaints` hanya
**ditambah** field opsional `attachment_tokens[]` (kompatibel mundur).

## 7. Acceptance Criteria

- **AC1** Pelapor (termasuk anonim) unggah foto → confirm → foto tersimpan di bucket privat terenkripsi, nama acak.
- **AC2** Foto ber-GPS/EXIF → setelah confirm, **tidak ada** metadata/GPS tersisa (dibuktikan uji).
- **AC3** Non-gambar / magic bytes salah / melebihi ukuran / melebihi jumlah → `400 VALIDATION_FAILED`, tidak tersimpan.
- **AC4** Pengaduan anonim + foto → tidak ada IP/identitas pelapor tersimpan di pengaduan maupun lampiran.
- **AC5** Admin membuka foto → signed URL berumur pendek berhasil; **tiap** akses tercatat di `audit_logs`.
- **AC6** Tanpa permission → `403` saat akses `GET /complaints/{id}`; URL publik langsung ke objek → ditolak.
- **AC7** Notifikasi pengaduan baru **tidak** memuat foto/isi sensitif.

## 8. Uji (nama berprefiks ID — AGENTS.md §5)
Happy path (AC1,AC5), strip EXIF (AC2), validasi gagal (AC3), 401/403 (AC6), anonimitas (AC4),
notifikasi bersih (AC7). Jangan turunkan coverage.

## 9. Keputusan yang HARUS diputuskan sekolah/client (AGENTS.md §8 — privasi & retensi)

- **D1 — Retensi foto bukti:** berapa lama disimpan (ikuti kebijakan retensi doc 09; pengaduan vs
  kasus yang naik ke BK bisa beda).
- **D2 — Siapa boleh melihat foto:** admin GUEST saja, atau + unit tertuju / BK (perundungan) /
  kepala sekolah? (matriks akses).
- **D3 — Batas:** jumlah foto per pengaduan & ukuran maksimum per foto.
- **D4 — Format diterima:** jpg/png/webp saja, atau termasuk HEIC (default kamera iPhone)?
- **D5 — Anonim boleh melampirkan foto?** (risiko wajah/lokasi membuka identitas walau EXIF bersih —
  tetap izinkan dengan peringatan, atau larang untuk mode anonim?).

## 10. Risiko
- Deanonimisasi lewat isi/metadata foto → dimitigasi re-encode + hapus metadata + peringatan pelapor.
- Konten foto bisa memilukan/sensitif → akses sangat dibatasi + audit; tidak pernah publik.

---

## Catatan: "lapor kerusakan" (BE-SAR) sudah punya foto
Jika yang dimaksud client ternyata **lapor kerusakan sarpras** (`04:362`: "Tiket kerusakan — lokasi,
**foto**, prioritas"), fitur foto **sudah ada di PRD** dan tidak sensitif. Yang perlu hanya
**verifikasi implementasinya** (presign+magic bytes+ukuran) — bukan PRD baru. Beri tahu bila ini yang
dimaksud, nanti saya cek status implementasinya di kode.
