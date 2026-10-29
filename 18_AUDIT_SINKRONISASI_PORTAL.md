# 18. Audit Sinkronisasi Portal ↔ Backend API — SISTLABUM

> Audit menyeluruh konektivitas: apakah SEMUA endpoint yang dipanggil frontend portal punya rute backend. Tanggal: 2026-10-29.

## Metode
1. Ekstraksi semua pemanggilan `apiClient.<verb>(...)` di seluruh permukaan frontend (`src/app/(portal)`, `src/lib`, `src/features`) → **314 endpoint unik** (parameter dinormalkan ke `{id}`).
2. Parse seluruh `routes/api/*.php` (resolusi prefix group) → **467 rute backend**.
3. Diff `FE − BE` untuk menemukan endpoint menggantung.

## Hasil: ✅ TERSAMBUNG PENUH
Dari 314 endpoint frontend, **0 gap nyata**. Hanya 3 yang tak match literal, semuanya **false positive**:

| Endpoint FE | Penjelasan | Status |
|---|---|---|
| `/me/{id}/self-attendance` | FE membangun `/me/${prefix}/self-attendance` (prefix `teacher`/`staff`). Backend punya `/me/teacher/self-attendance` & `/me/staff/self-attendance`. | ✅ Tersambung |
| `/me/{id}/leave-requests` | Idem (prefix dinamis). Backend punya versi teacher & staff. | ✅ Tersambung |
| `/notifications/read-all` | Backend mendefinisikannya via `Route::match(['post','patch'], ...)`; parser hanya menangkap verb eksplisit. | ✅ Tersambung |

## Perbaikan yang membuat ini tercapai (commit `d6bb05f`)
Sebelumnya 2 endpoint guru menggantung → 404; kini ditambahkan (aditif, tanpa mengubah halaman FE):
- `GET /me/teacher/subjects` → `MeTeacherController::subjects` → `TeacherDashboardController::subjects`.
- `GET /me/teacher/assignments/{id}` → `MeTeacherController::showAssignment` → `TeacherLmsController::showAssignment`.
- Config CORS/Sanctum diselaraskan ke satu origin `:3000`.

## Cakupan per area portal
Semua area portal terverifikasi tersambung: **Guru** (dashboard, jadwal, jurnal, presensi, nilai, materi, tugas, ujian/bank soal, izin-cuti, wali-kelas, slip-gaji, fasilitas, tiket, subjects, detail tugas), **Siswa** (dashboard, jadwal, nilai, tugas, exams, CBT live, konseling, poin, tagihan, notifikasi), **Ortu** (anak, nilai, presensi, rapor, catatan karakter, biaya/invoice), **Staf** (dashboard, presensi, izin-cuti, tiket, slip-gaji), **Kepsek** (approvals), **Notifikasi** (list, read, read-all, preferensi).

## Batas verifikasi (penting)
Audit ini **statis**: memastikan setiap endpoint yang dipanggil memiliki rute + method controller yang ada, dan path FE cocok dengan BE. Belum menguji **respons runtime** (status 200 & bentuk payload nyata). Setelah toolchain siap, konfirmasi dengan:
```
cd apps/api && php artisan test
cd apps/api && php artisan route:list | grep ' me/'   # verifikasi rute terdaftar
```
Serta smoke test login per peran (guru/siswa/ortu/staf/kepsek) menelusuri tiap halaman.

## Catatan arsitektur
Halaman guru/siswa/ortu live masih memakai lapisan data lama `src/lib/use-*-data.ts` (bukan `src/features/portal/*-api.ts`). Keduanya kini sama-sama tersambung ke backend; penyatuan menjadi satu lapisan tetap disarankan sebagai kebersihan jangka menengah (bukan masalah konektivitas).
