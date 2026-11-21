# Portal Sekolah Satu Pintu: Paket Dokumen Proyek

Paket ini adalah sumber kebenaran (source of truth) untuk membangun portal sekolah. Dibuat agar mudah dipahami manusia dan dikerjakan AI agent.

## Daftar Dokumen
| File | Isi | Dibaca oleh |
|---|---|---|
| `00_README.md` | Indeks, konvensi ID, cara memakai paket | Semua |
| `01_REQUIREMENTS.md` | Latar belakang, tujuan, role, kebutuhan fungsional & non-fungsional, glosarium | PM, semua dev |
| `02_TECH_STACK.md` | Keputusan teknologi, versi, alasan, struktur repo | Dev, AI agent |
| `03_ARCHITECTURE.md` | Arsitektur sistem, komponen, alur data, deployment | Dev, DevOps |
| `04_PRD_BACKEND.md` | PRD API backend per modul | Backend dev, AI agent |
| `05_PRD_WEB_PPDB.md` | PRD website publik + portal PPDB | Frontend dev |
| `06_PRD_SYSTEM_ADMIN.md` | PRD web portal & dashboard terpadu (admin, guru, siswa, ortu) | Frontend dev |
| `07_PRD_MOBILE.md` | PRD aplikasi mobile ortu/guru/siswa | Mobile dev |
| `08_DATABASE_SCHEMA.md` | Entitas, relasi, aturan data | Backend dev |
| `09_SECURITY_PRIVACY.md` | Keamanan, hak akses, perlindungan data anak | Semua |
| `10_TESTING_QA.md` | Strategi uji, acceptance, definition of done | QA, dev |
| `11_AI_AGENT_RULES.md` | Aturan kerja AI agent (wajib dibaca pertama kali) | AI agent |
| `12_ERD.md` | Visualisasi ERD database per modul | Backend dev, DB engineer |
| `13_MASTER_MODULES.md` | Master daftar 14 modul, dependensi, dan pemetaan ID | Semua |

## Urutan Baca
- Manusia baru: README → Requirements → 13_MASTER_MODULES → Architecture → PRD sesuai peran.
- AI agent: **11_AI_AGENT_RULES → README → 13_MASTER_MODULES → dokumen modul yang ditugaskan → 08 → 09**.

## Konvensi ID
Setiap modul dan fitur punya ID unik agar mudah dilacak, dipecah menjadi fase, dan dirujuk di commit/PR.

```
{PLATFORM}-{MODUL}-{NOMOR}
BE-PPDB-03    = Backend, modul PPDB, fitur ke-3
WEB-PPDB-02   = Website, PPDB, fitur ke-2
ADM-CBT-05    = Sistem admin, CBT, fitur ke-5
MOB-ORT-04    = Mobile, Ortu, fitur ke-4
NFR-PERF-01   = Non-functional requirement
```

Prefiks platform: `BE` backend, `WEB` website, `ADM` admin, `MOB` mobile, `NFR` non-fungsional.

## Label Prioritas
| Label | Arti |
|---|---|
| **P0** | Wajib, tanpa ini sistem tidak bisa dipakai (MVP) |
| **P1** | Penting, dikerjakan setelah MVP |
| **P2** | Nilai tambah, boleh ditunda |

## Template Spesifikasi Fitur
Semua fitur ditulis dengan pola ini:

```
### {ID}: Nama Fitur  [P0/P1/P2]
Tujuan: ...
Aktor: ...
Dependensi: ID lain
Aturan bisnis: ...
Kriteria penerimaan (AC):
- AC1 ...
Endpoint / Layar / Tabel terkait: ...
```

## Indeks Modul (14 Modul Final)
Rincian kuantitatif, daftar fitur lengkap, dan graf dependensi ada di `13_MASTER_MODULES.md`.

| Kode modul | Nama | Prioritas | Backend | Web Portal | Admin | Mobile |
|---|---|:-:|:-:|:-:|:-:|:-:|
| AUTH | Autentikasi & hak akses | **P0** | ✅ | ✅ | ✅ | ✅ |
| MST | Master data | **P0** | ✅ | | ✅ | |
| CMS | Konten & profil sekolah | **P0** | ✅ | ✅ | ✅ | |
| PPDB | Penerimaan murid baru | **P0** | ✅ | ✅ | ✅ | |
| GUEST | Buku tamu digital & aspirasi | **P0** | ✅ | ✅ | ✅ | |
| ACD | Akademik (jadwal, jurnal, presensi, izin, materi) | **P0** | ✅ | ✅ | ✅ | ✅ |
| GRD | Penilaian & Rapor (komponen, karakter, e-progress, e-rapor) | **P0** | ✅ | ✅ | ✅ | ✅ |
| CBT | Ujian digital | **P0** | ✅ | ✅ | ✅ | ✅ |
| STD | Kesiswaan (poin, ekskul, lomba, study trip) | **P1** | ✅ | ✅ | ✅ | ✅ |
| BK | Bimbingan konseling | **P1** | ✅ | ✅ | ✅ | ✅ |
| SAR | Sarpras, aset, helpdesk, perpustakaan | **P1** | ✅ | ✅ | ✅ | ✅ |
| HUM | Humas, kemitraan, alumni & tracer | **P2** | ✅ | ✅ | ✅ | |
| RPT | Laporan & dashboard eksekutif | **P0** | ✅ | ✅ | ✅ | ✅ |
| NTF/SYS | Notifikasi & utilitas sistem | **P0** | ✅ | ✅ | ✅ | ✅ |

> Catatan: Modul SAR memiliki fitur darurat P0 (`BE-SAR-01` booking fasilitas dan `BE-SAR-03` helpdesk perbaikan).

> Memecah fase nanti: pilih modul dari tabel ini, lalu ambil semua ID fitur modul tersebut di 04–07 dan `13_MASTER_MODULES.md`. Setiap fitur sudah menyebut dependensinya.

## Status Dokumen
Versi 1.1 (Penyelarasan 14 modul final, pemisahan GRD & GUEST, penambahan RPT & views_count). Perubahan harus dicatat di bagian *Changelog* tiap dokumen.

## Changelog
- 1.0: Draf awal.
- 1.1: Penyelarasan master 14 modul final, pemisahan modul GRD dari ACD dan GUEST dari HUM, penambahan modul RPT P0, pembaruan istilah KKTP, penambahan referensi `13_MASTER_MODULES.md`.
