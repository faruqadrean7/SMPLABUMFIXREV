# Prompt Tugas: BE-GUEST-04 — Lampiran Foto pada Pengaduan (Lapor Kejadian)

> Kerjakan **BE-GUEST-04** sesuai PRD `PRD_BE-GUEST-04_LAMPIRAN_FOTO_PENGADUAN.md`. Ikuti `AGENTS.md`.
> **Tulis rencana singkat dulu, tunggu persetujuan sebelum coding.**
> **DATA SANGAT SENSITIF** (identitas pelapor, bisa memuat wajah/lokasi anak) → tunduk penuh pada
> `09_SECURITY_PRIVACY.md`. Fakta Bagian 1 diverifikasi dari kode (`apps/api`, 2026-11-03).
> JANGAN mengarang (AGENTS.md §6.6). Stack = **Laravel 13 + PostgreSQL**.

## Konteks
- Memperluas **BE-GUEST-02** (modul Complaint sudah ada) + reuse **BE-FILE-01** (files).
- Satu tugas = satu ID. Keputusan privasi/retensi (Bagian 8) **harus** dijawab client dulu.

## 0. Keputusan yang SUDAH DITETAPKAN (default; tandai "KONFIRMASI" ke client)
- **D1. Reuse tabel `files` polymorphic** (`owner_type='complaint'`, `owner_id=complaint.id`) —
  **tanpa tabel lampiran baru** (pola ini sudah ada; AGENTS.md §4 jangan buat pola baru).
- **D2. Wajib hapus metadata via RE-ENCODE** gambar saat confirm (bukan sekadar strip EXIF);
  buang GPS & thumbnail tersembunyi. Waktu "saat kejadian" = timestamp **server**, bukan EXIF.
- **D3. Anonimitas:** untuk `is_anonymous=true`, jangan simpan `reporter_user_id`, IP, user-agent di
  pengaduan maupun di `files` (owner = complaint, bukan pelapor).
- **D4. Akses foto hanya via signed URL berumur pendek; setiap baca DIAUDIT** (`audit_logs`).
- **D5. Notifikasi pengaduan baru TANPA foto/isi sensitif** (AGENTS.md §6.7).

## 1. Fakta terverifikasi dari kode

### 1.1 Rute — `routes/api/guest.php`
| Aksi | Method + Path | Akses |
|---|---|---|
| Submit pengaduan | `POST /public/complaints` → `ComplaintController@submit` | publik, `throttle:60,1` |
| Lacak via kode | `GET /public/complaints/{code}` → `@track` | publik |
| Daftar | `GET /complaints` → `@index` | auth:sanctum |
| Detail | `GET /complaints/{id}` → `@show` | auth:sanctum |
| Ubah status | `PATCH /complaints/{id}/status` → `@updateStatus` | `permission:complaint.manage` |

### 1.2 Tabel `complaints` — `database/migrations/2026_10_07_000010_create_public_relations_and_alumni_tables.php`
`id uuid`, `school_id?`, `reporter_user_id?` (NULL jika anonim), `ticket_code(50) unique`,
`category(100)`, `content text`, `is_anonymous bool` default false,
`status(50)` default `baru` (`baru|ditinjau|ditindaklanjuti|selesai`), `assigned_to?`, timestamps.
→ **Belum ada dukungan lampiran.**

### 1.3 Tabel `files` (polymorphic) — `2026_10_07_000001_create_storage_files_table.php`
`id`, `school_id?`, `owner_id?`, `owner_type(50)?`, `bucket(100)`, `object_key(500) unique`,
`mime_type(100)`, `size_bytes`, `visibility(20)` default `privat`, `checksum?`, timestamps.

### 1.4 Modul yang disentuh
- `app/Http/Controllers/Api/V1/ComplaintController.php` (`submit`, `show`)
- `app/Services/ComplaintService.php`
- `app/Http/Requests/Complaint/CreateComplaintRequest.php`
- `app/Http/Controllers/Api/V1/Storage/FileController.php` + `app/Services/Storage/FileStorageService.php`
  (presign/confirm saat ini **auth:sanctum** di `reports.php` — lihat Bagian 2.3).

## 2. Komponen yang DIBUAT / DIUBAH

### 2.1 Dependency baru (catat di PR)
- Lib pengolah gambar untuk re-encode + buang metadata: **`intervention/image`** (atau ext `imagick`
  `stripImage()`). Konfirmasi mana yang tersedia di server deploy.

### 2.2 Alur unggah (anonim-capable)
```
(1) POST /public/complaints/attachments/presign   (throttle ketat; batasi jumlah & ukuran)
(2) klien unggah ke signed URL
(3) POST /public/complaints/attachments/{token}/confirm
     → validasi MIME+magic bytes → RE-ENCODE (buang EXIF/GPS) → simpan bucket privat (terenkripsi, D-sec)
     → file owner_type=null sementara, visibility=privat, object_key acak
(4) POST /public/complaints { ..., attachment_file_ids[] }
     → ComplaintService asosiasi file: set files.owner_type='complaint', owner_id=complaint.id
(6) GET /complaints/{id} → sertakan signed URL foto (berumur pendek) + TULIS audit_logs 'complaint.attachment.read'
```

### 2.3 Hal yang perlu diputuskan di implementasi (bukan ditebak diam-diam)
- **Presign publik untuk anonim:** presign saat ini `auth:sanctum`. Buat varian **publik**
  khusus lampiran pengaduan (rate-limit ketat), ATAU terima multipart langsung di `submit`.
  → usul: endpoint presign publik khusus, scope ke lampiran pengaduan saja.
- **Enkripsi at-rest level aplikasi** (wajib "sangat sensitif", `09:11`): apakah
  `FileStorageService` sudah mendukung? Jika belum, tambah envelope-encryption untuk lampiran
  pengaduan (Laravel `Crypt` / SSE bucket) — **flag sebagai sub-keputusan**.

## 3. Migrasi DB
Tidak ada tabel baru (reuse `files` polymorphic, D1). Bila client menolak polymorphic, alternatif
tabel pivot `complaint_attachments(complaint_id, file_id, created_at)` — **tanpa** kolom identitas pelapor.

## 4. Endpoint (baru / perluasan — perbarui OpenAPI + klien)
| Method + Path | Akses |
|---|---|
| `POST /public/complaints/attachments/presign` | publik, rate-limited |
| `POST /public/complaints/attachments/{token}/confirm` | publik, rate-limited |
| `POST /public/complaints` (+field opsional `attachment_file_ids[]`) | publik (kompatibel mundur) |
| `GET /complaints/{id}` (sertakan signed URL foto) | auth (diaudit) |

## 5. Keamanan (WAJIB — inti fitur)
- RE-ENCODE gambar; pastikan **tidak ada** EXIF/GPS tersisa (uji membuktikan).
- Bucket **privat**; akses hanya signed URL pendek; `object_key` acak (tanpa nama/kelas/kode tiket).
- Enkripsi at-rest level aplikasi (Bagian 2.3).
- Anonim: tanpa IP/identitas (D3). Peringatkan pelapor bahwa isi foto bisa membuka identitas.
- Hanya gambar (jpg/png/webp; HEIC = keputusan D4 client). MIME+magic bytes+ukuran+jumlah dibatasi.
- Pindai virus (P1). Notifikasi tanpa isi sensitif (D5). Jangan log foto/isi.
- Setiap baca foto/pengaduan → `audit_logs` (BE-AUTH-05 sudah ada).

## 6. Uji (nama berprefiks `BE-GUEST-04`; pakai gambar FIKTIF, AGENTS.md §6.2)
- happy path: presign→confirm→submit→admin lihat foto (signed URL + audit tercatat).
- **strip metadata**: gambar fixture ber-GPS → setelah confirm tidak ada metadata (AC utama).
- validasi: non-gambar/magic salah/oversize/over-count → `400`.
- anonimitas: submit anonim + foto → tak ada IP/`reporter_user_id`.
- 403: `GET /complaints/{id}` tanpa permission; akses objek publik langsung ditolak.
- notifikasi tidak memuat foto/isi.

## 7. Definition of Done (AGENTS.md §10)
AC PRD §7 satu per satu · lint/typecheck/test/build lulus · uji berprefiks ID · OpenAPI/klien/dokumen ·
permission+cakupan+**audit baca**+enkripsi · tanpa rahasia/data pribadi nyata · ringkasan PR (sebut dependency & isu enkripsi).

## 8. Tunggu keputusan sekolah/client (AGENTS.md §8 — privasi & retensi)
Lihat PRD §9: D1 retensi foto · D2 siapa boleh melihat · D3 batas jumlah/ukuran · D4 format (HEIC?) ·
D5 anonim boleh melampirkan foto?. **Jangan mulai coding sebelum D1,D2,D5 dijawab** (menentukan desain).

## 9. Laporan akhir — pakai format AGENTS.md §11.
