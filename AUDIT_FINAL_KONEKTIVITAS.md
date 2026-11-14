# Audit Final Konektivitas & Fabrikasi Data — Portal Sekolah Satu Pintu

> Disusun 2026-11-02 dari pembacaan kode langsung (branch `develop` setelah merge terbaru).
> Lanjutan & pemutakhiran dari `AUDIT_KONEKTIVITAS_PORTAL.md`. Cakupan: seluruh grup role
> (admin, ortu, guru, siswa, kepsek, staf), PPDB publik, dan keuangan admin.
> Fokus: di mana FE **memalsukan / mengabaikan** data backend, link mati (404), dan status state.

## 0. Kesimpulan eksekutif
Aplikasi **jauh lebih sehat** daripada kesan awal. Mayoritas modul sudah memakai API nyata
dengan pola anti-fabrikasi yang benar:
- `executeWithMockFallback` + `mock-mode.ts`: mock **tidak pernah** aktif di production, error
  HTTP (401/403/404/422/5xx) **tidak pernah** disamarkan jadi data palsu, ada build-guard.
  Dipakai di portal **guru** & **staf**.
- Modul **PPDB**, **keuangan admin**, dan sebagian besar **admin** seluruhnya API nyata, tanpa mock.
- Beberapa tiket yang dulu "fabrikasi" **sudah selesai**: CBT siswa (redirect ke `/ujian`),
  biaya ortu (`buildChildFees` + `ParentFinanceTab`), CMS publik (agenda/unduhan/buletin),
  dan link 404 navigasi.

Sisa masalah **terkonsentrasi di portal ORTU** (jadwal & poin anak dikarang) dan beberapa
**fallback `nilai ?? 'palsu'`** kecil yang tersebar. Tidak ada temuan di keuangan admin,
PPDB (selain 1 catatan backend), dan kepsek/staf (selain 1 angka fallback).

## 1. Status per area

| Area | Verdict | Temuan utama |
|---|---|---|
| Admin (navigasi) | ✅ bersih | 0 dead-link (118 target), 0 mock/TODO |
| Admin CMS (tab Profil) | ⚠️ gap | editor `social_media` & `featured_programs` tidak ada, padahal publik membacanya |
| Portal Ortu | ⚠️ fabrikasi | jadwal anak 100% palsu, poin dikarang, NIP/HP wali-kelas fiktif (biaya SUDAH beres) |
| Portal Guru | ✅ sehat | jadwal fallback palsu (`defaultScheduleSlots`); 2 minor (dua layer hook, 3 rute orphan) |
| Portal Siswa | ✅ sehat | 2 fallback palsu (cambridge ID, hafalan evaluator), form konseling default, 4 program tanpa error-state |
| PPDB | ✅ tersehat | ranking di backend (§8 OK); catatan backend `check-nik` bocor nomor pendaftaran |
| Keuangan admin | ✅ bersih | — |
| Portal Kepsek/Staf | ✅ bersih | `ppdbQuota` fallback 240 (angka karangan untuk % KPI) |

## 2. Detail temuan + lokasi (file:baris terverifikasi)

### 2.1 Admin CMS — editor hilang  → prompt: `PROMPT_FIX_CMS_ADMIN_PROFILE.md`
- `social_media` (IG/YT/FB/Twitter/TikTok) dibaca `(public)/kontak/page.tsx:57`, tapi tab Profil
  CMS (`(admin)/admin/cms/page.tsx`) tak punya form-nya → link sosial publik selalu kosong.
- `profile.featured_programs[]` dibaca `(public)/page.tsx:566` & `(public)/akademik/page.tsx:93`,
  tapi tak ada editor → beranda selalu fallback hardcode `APPROACH_PILLARS`.
- FE-only; tipe `SiteSettingsPayload` sudah memuat keduanya.

### 2.2 Portal Ortu — fabrikasi  → prompt: `PROMPT_FIX_ORTU_FABRIKASI.md`
`apps/web/src/lib/guardian-context.tsx`:
- `activeSchedule` (343–398): jadwal + guru fiktif, tidak panggil API. **Endpoint ortu belum ada.**
- `activePoints` (313–342): angka poin `25`/`20` + entri `pt-02` dikarang.
- Wali kelas (202–203, 251–253, `DEFAULT_CHILD` 59–61): NIP `19840214 200902 2 003` & HP
  `0812-3456-7890` fiktif (nama sudah nyata dari `childDetail.homeroom_teacher`).
- **Sudah beres:** `activeFees` kini `buildChildFees(invoicesData)` (131–136) — `PROMPT_FE_ORTU_BIAYA.md` USANG.

### 2.3 Portal Guru — jadwal fallback  → belum ada prompt (lihat §4, blok A)
- `(portal)/portal/guru/jadwal/page.tsx:26` `defaultScheduleSlots` (jadwal palsu lengkap),
  dipakai `:58` saat `liveSchedules` kosong. Halaman hanya ambil `data` → abaikan isLoading/isError.
- Minor: dua layer hook guru paralel (`features/portal/guru/use-guru-portal.ts` vs
  `src/lib/use-teacher-data.ts`) — sebaiknya disatukan.
- Minor: rute orphan (ada file, tak tertaut) `guru/perizinan` (≈izin-cuti), `guru/sarpras`
  (≈fasilitas), `guru/siswa` — perlu keputusan hapus/tautkan.

### 2.4 Portal Siswa — fallback palsu  → belum ada prompt (lihat §4, blok B)
- `(portal)/portal/siswa/cambridge/page.tsx:21` `cambridge_candidate_id ?? 'ID-MAL-2026-042'`.
- `(portal)/portal/siswa/hafalan/page.tsx:25` `tasmi_evaluator ?? 'Ustadz Abdullah, S.Pd.I'`.
- `(portal)/portal/siswa/konseling/page.tsx:32,34` form ter-isi konselor `'Ibu Ratna Dewi, S.Pd., Kons.'`
  & jam `'13:30 WIB'` — bisa submit ke nama konselor karangan.
- 4 halaman program (`cambridge/hafalan/p5/ppi`) tanpa error-state (err:0).

### 2.5 PPDB — catatan backend (bukan FE)
- `POST /ppdb/check-nik` mengembalikan `existing_registration_no` ke pemeriksaan tanpa auth
  (`(ppdb)/ppdb/daftar/page.tsx:92`). Gabung dengan cek hasil publik (reg_no + tgl lahir) →
  menurunkan ambang enumerasi. Perbaikan di backend (jangan kembalikan nomor pendaftaran ke
  pemeriksa anonim). AGENTS.md §6.4. Perlu ditinjau, bukan tuduhan pasti.

### 2.6 Kepsek — angka fallback
- `(portal)/portal/kepsek/page.tsx:68` `ppdbQuota = metrics.ppdbTargetQuota > 0 ? ... : 240`
  → % capaian PPDB dihitung terhadap 240 karangan saat backend 0. Tampilkan "kuota belum diset".

## 3. Status prompt
| Prompt | Tiket | Status |
|---|---|---|
| `PROMPT_FIX_CMS_ADMIN_PROFILE.md` | FE-CMS-ADMIN-01 | siap eksekusi |
| `PROMPT_FIX_ORTU_FABRIKASI.md` | FE-ORTU-FABRIKASI-01 (+Bagian B backend) | siap eksekusi |
| `PROMPT_FE_ORTU_BIAYA.md` | FE-ORTU-BIAYA-01 | **USANG** (sudah mendarat) — arsipkan |
| `PROMPT_FIX_CMS_PUBLIC.md` | FE-CMS-PUBLIC-01..04 | Tahap 1/2/4 sudah mendarat; Tahap 3 akademik tersisa |
| `PROMPT_FIX_NAV_404.md` | FE-NAV-404-01..03 | sudah mendarat |
| `PROMPT_P0_CBT.md` | FE-CBT-UJIAN-01 | sudah mendarat |
| §4 di bawah (anti-fallback-palsu) | FE-ANTIFAKE-01..04 | prompt baru (lihat bawah) |

---

## 4. PROMPT BORONGAN — FE-ANTIFAKE: hapus fallback `nilai ?? 'palsu'`
> Satu kelas masalah, tersebar di beberapa file. Pecah jadi commit kecil per blok (§1/§7 AGENTS.md).
> Semua FE-only, tak ada backend. Base `develop`. Prinsip: **data nyata atau keadaan jujur
> (empty-state / `-`), tidak pernah nilai karangan yang tampak asli** (§6.2/§9).

### Blok A — FE-ANTIFAKE-01: Jadwal guru
```
Kerjakan FE-ANTIFAKE-01: hentikan jadwal palsu di portal guru. FE-only. Ikuti AGENTS.md.
File: apps/web/src/app/(portal)/portal/guru/jadwal/page.tsx
- HAPUS const defaultScheduleSlots (baris 26) dan pemakaiannya (baris 58).
- Ambil juga isLoading & isError dari useTeacherSchedules() (dari @/lib/use-teacher-data),
  jangan hanya `data`. Tampilkan loading saat memuat, error-state saat gagal, dan empty-state
  jujur ("Belum ada jadwal mengajar") saat kosong — BUKAN jadwal contoh.
AC: tak ada jadwal/guru hardcoded; kosong→empty-state; error→error-state.
Verifikasi: grep bersih 'Ahmad Yani|Fitri Handayani|Hendra Wijaya|Budi Santoso|Siti Aminah'
di file; pnpm typecheck; pnpm eslint file tsb.
Commit: fix(fe-antifake-01): jadwal guru pakai state nyata, hapus jadwal contoh
```

### Blok B — FE-ANTIFAKE-02: Fallback palsu portal siswa
```
Kerjakan FE-ANTIFAKE-02: hentikan nilai fallback fiktif di portal siswa. FE-only. Ikuti AGENTS.md.
1. apps/web/src/app/(portal)/portal/siswa/cambridge/page.tsx:21
   `?? 'ID-MAL-2026-042'` → tampilkan '-' / sembunyikan baris bila cambridge_candidate_id kosong.
2. apps/web/src/app/(portal)/portal/siswa/hafalan/page.tsx:25
   `?? 'Ustadz Abdullah, S.Pd.I'` → '-' / sembunyikan bila tasmi_evaluator kosong.
3. apps/web/src/app/(portal)/portal/siswa/konseling/page.tsx:32,34
   Kosongkan default konselor & jam (string kosong) ATAU isi dari daftar konselor nyata bila
   tersedia; jangan prefilled nama/jam karangan.
4. Tambah error-state pada cambridge/hafalan/p5/ppi (saat ini err:0) — surface isError.
AC: grep bersih 'ID-MAL-2026-042|Ustadz Abdullah|Ibu Ratna Dewi' di halaman siswa; field kosong→'-'/empty.
Verifikasi: pnpm typecheck; pnpm eslint file-file tsb.
Commit: fix(fe-antifake-02): hapus fallback identitas fiktif portal siswa
```

### Blok C — FE-ANTIFAKE-03: Kuota PPDB kepsek
```
Kerjakan FE-ANTIFAKE-03: jangan hitung % capaian PPDB dengan kuota karangan. FE-only.
File: apps/web/src/app/(portal)/portal/kepsek/page.tsx:68
- Bila metrics.ppdbTargetQuota <= 0: JANGAN pakai 240. Tampilkan "Kuota belum ditetapkan"
  dan sembunyikan/menonaktifkan bar persentase (atau tampilkan jumlah diterima tanpa %).
AC: tak ada angka kuota hardcoded; % hanya muncul bila kuota backend > 0.
Verifikasi: pnpm typecheck; pnpm eslint file tsb.
Commit: fix(fe-antifake-03): kuota PPDB kepsek tanpa angka karangan
```

### Blok D — (opsional, keputusan) FE-GURU-ORPHAN-04: rute guru orphan
```
Kerjakan FE-GURU-ORPHAN-04 HANYA setelah keputusan pemberi tugas: rute guru/perizinan,
guru/sarpras, guru/siswa tidak tertaut di nav/halaman mana pun.
Keputusan: (a) hapus bila duplikat (perizinan≈izin-cuti, sarpras≈fasilitas), atau
(b) tautkan di nav bila memang dibutuhkan. Jangan putuskan sendiri (§2/§8).
```

---

## 5. Catatan backend terpisah (perlu keputusan sekolah, §8)
- `GET /me/children/{child}/schedule` & `/points` untuk ortu — agar jadwal & poin anak bisa
  NYATA (saat ini endpoint belum ada; FE hanya bisa empty-state). Tiket BE-ORTU-SCHEDULE / BE-ORTU-POINTS.
- `check-nik` jangan kembalikan `existing_registration_no` ke pemeriksa anonim.
