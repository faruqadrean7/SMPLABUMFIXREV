# Audit Konektivitas & Relasi Portal SISTLABUM

**Tanggal:** 2026-10-31
**Cakupan:** Relasi antar-peran (siswa ↔ guru ↔ ortu ↔ admin), fokus khusus pada fitur **Ujian CBT**.
**Metode:** Pembacaan kode langsung (backend Laravel `apps/api`, web Next.js `apps/web`) + hasil uji yang sudah ada. Setiap temuan diberi tingkat keyakinan.
**Sifat dokumen:** Audit (analisis), bukan perbaikan. Belum ada kode yang diubah untuk audit ini.

## Legenda tingkat & keyakinan

| Simbol | Arti |
|---|---|
| 🔴 KRITIS | Fitur inti terputus; pengguna akhir tidak mendapat fungsinya |
| 🟠 TINGGI | Salah/menyesatkan atau data penting hilang, tapi tidak crash |
| 🟡 SEDANG | Inkonsistensi/ risiko laten / data direkayasa di UI |
| ⚪ RENDAH | Kerapian, asimetri, potensi masalah multi-tenant |
| ✅ Terkonfirmasi | Dibuktikan dengan membaca kode |
| ❔ Perlu verifikasi | Belum dikonfirmasi tuntas; ditandai jujur |

---

## 0. Ringkasan Eksekutif

Temuan paling penting persis seperti dugaan Anda: **guru membuat soal, tapi di siswa tidak muncul.** Penyebabnya bukan di relasi database (rantai `Exam → ExamQuestion → ExamParticipant` sebenarnya tersambung), melainkan **halaman pengerjaan ujian siswa yang hidup (live) masih merender soal dari data MOCK** dan tidak pernah memanggil endpoint sesi ujian. Ironisnya, implementasi ujian yang benar (memanggil API sesi sungguhan) **sudah ada tetapi yatim/tidak tertaut** (tidak ada link menuju ke sana).

Ringkas angka:
- **CBT:** 2 temuan 🔴 KRITIS, 1 🟠 TINGGI, 3 🟡 SEDANG, 2 ⚪ RENDAH.
- **Relasi lintas-peran non-CBT (nilai, presensi, konseling, biaya, rapor):** sebagian besar **tersambung ke API nyata**. Satu temuan 🟡 SEDANG: angka nilai yang ditampilkan ke **ortu direkayasa** di lapisan UI.
- **Belum diverifikasi tuntas:** fan-out pengumuman, isi notifikasi push/WA (§6.7 AGENTS.md), dan UI admin CBT via browser (terhalang 2FA — diaudit lewat kode & uji backend).

---

## 1. FITUR UJIAN CBT (fokus utama)

### Rantai yang seharusnya tersambung
```
Admin: QuestionBank + Question (status aktif)
  └─> Guru: lihat/ tambah bank & soal (owner ATAU mapel yang diampu)
        └─> Guru: buat Exam  ──> salin soal ke ExamQuestion
              └─> buat ExamRoom + ExamParticipant untuk siswa di kelas
                    └─> Siswa: start-session → session-questions → save-answer → finish-session
                          └─> Grading otomatis (pg/bs/pg_kompleks/menjodohkan/isian) + uraian manual
                                └─> Hasil kembali ke siswa / guru / admin
```
Pada tingkat **database & backend, rantai ini UTUH.** Putusnya ada di **lapisan FE siswa** dan beberapa titik kosmetik/laten.

---

### 🔴 CBT-01 — Halaman pengerjaan ujian siswa merender soal MOCK ✅
**File:** `apps/web/src/app/(portal)/portal/siswa/cbt/[id]/page.tsx`
- Baris 22: `import { CBT_ACTIVE_EXAM_QUESTIONS, CBTQuestionDetail } from '@/lib/mock-student-data';`
- Baris 99: `const currentQ: CBTQuestionDetail = CBT_ACTIVE_EXAM_QUESTIONS[currentIdx] || {...}`
- Semua rendering soal (opsi PG, pernyataan benar/salah, `matching_pairs`) membaca **konstanta MOCK** ini.
- Satu-satunya panggilan API nyata: baris 175 `apiClient.post('/exams/${examId}/log-violation', ...)`.
- **Tidak ada** `start-session`, `session-questions`, `save-answer`, maupun `finish-session`.

**Jalur masuk:** daftar ujian siswa (`portal/siswa/cbt/page.tsx` ~baris 106) melakukan `router.push('/siswa/cbt/${exam.id}')` — yaitu **menuju halaman mock ini**.

**Akibat (inilah gejala yang Anda sebut):**
1. Soal yang dibuat guru/admin **tidak pernah tampil** ke siswa — yang tampil selalu soal mock yang sama.
2. Jawaban siswa **tidak tersimpan** (tidak ada `save-answer`).
3. Sesi **tidak pernah dimulai/diselesaikan** → `ExamParticipant`/`ExamSession` tidak terisi.
4. **Autograde tidak punya data** → skor tidak pernah dihasilkan dari jawaban nyata → guru/admin/ortu tidak melihat nilai ujian nyata.

> Ini temuan paling krusial. Semua "kenapa soal guru tak muncul di siswa" bermuara di sini.

---

### 🔴 CBT-02 — Implementasi ujian yang BENAR ada tapi yatim (orphan) ✅
**File:** `apps/web/src/app/(portal)/portal/siswa/ujian/[id]/page.tsx`
- Ini implementasi pengerjaan ujian **sungguhan**: memanggil endpoint sesi (`start-session`, `session-questions`, `save-answer`, `finish-session`, `review`, dll. — sekitar 10 panggilan API).
- **Tidak ada satu pun link/route** di portal yang mengarah ke `/siswa/ujian/{id}`.

**Akibat:** kode yang benar menjadi **dead code**, sementara kode mock (CBT-01) yang dipakai. Perbaikan paling murah: arahkan daftar ujian ke `/siswa/ujian/{id}`, atau pindahkan panggilan sesi ke halaman `cbt/[id]`. (Perlu keputusan: halaman mana yang jadi kanonis — lihat bagian Rekomendasi.)

---

### 🟠 CBT-03 — Guru tidak melihat jumlah soal & kelas ujian yang benar ✅
**File:** `apps/api/app/Http/Controllers/Api/V1/Teacher/TeacherExamController.php` (metode `exams()`)
- Baris ~48: `'totalQuestions' => 30` — **di-hardcode**, bukan hitungan nyata `ExamQuestion`.
- Baris ~46: `'classNames' => []` — selalu kosong; guru tak tahu ujian menyasar kelas mana.
- `'isProctor' => false` — di-hardcode.

**Akibat:** di daftar ujian guru, jumlah soal selalu tampil 30 walau bank kosong/berisi lain, dan kolom kelas kosong. Menyesatkan guru saat verifikasi ujian.

---

### 🟡 CBT-04 — Soal buatan guru terkunci status `draft`, tanpa jalur publish ✅
**File:** `TeacherExamController.php` (`storeQuestion`, `storeDirectQuestion`) → membuat `Question` dengan `'status' => 'draft'`.
- Tidak ada endpoint portal guru untuk mengubah status ke `aktif`.
- Admin (`QuestionBankService::updateQuestion` / `importQuestions`) bisa set `aktif`.
- **Saat ini** `ExamSessionService::getSessionQuestions()` dan penyalinan soal **tidak memfilter status**, jadi soal draft tetap ikut tersalin — fitur belum putus karenanya.
- **Risiko laten:** begitu ada filter `status = 'aktif'` ditambahkan di mana pun (hal yang wajar dilakukan), semua soal guru akan **diam-diam hilang** dari siswa. Selain itu state `draft/aktif` praktis tak bermakna di portal guru.

---

### 🟡 CBT-05 — `storeExam` menyalin SELURUH bank ke ujian, tanpa seleksi/jumlah ✅
**File:** `TeacherExamController.php` (`storeExam`)
- Menyalin **semua** soal bank ke `ExamQuestion`; tidak ada pemilihan subset, jumlah soal, atau acak.
- Bila bank kosong → ujian berisi **0 soal** (padahal UI guru menampilkan 30, lihat CBT-03).
- Ada fallback `school_id` di-hardcode (`00000000-…-0001`).

---

### 🟡 CBT-06 — Token ruang ujian bocor ke siswa (perlu verifikasi niat) ✅ kode / ❔ desain
**File:** `apps/api/app/Http/Controllers/Api/V1/MeStudentController.php` (`exams()`)
- Mengembalikan `tokenRequired => $p->examRoom?->token` ke klien siswa.
- Jika token ini adalah gerbang pengawas (proctor) yang mestinya diberikan terpisah saat ujian, mengirimkannya lebih awal ke siswa **meniadakan fungsinya**.
- **Perlu konfirmasi** apakah token memang dimaksudkan tampil ke siswa. Bila tidak → ini kebocoran kontrol akses.

> Catatan: `ExamSessionService::getSessionQuestions()` **sudah benar** menyaring kunci jawaban (whitelist) sesuai §6.6 AGENTS.md — kunci jawaban tidak dikirim ke klien. Yang jadi soal hanya token ruang.

---

### ⚪ CBT-07 — Scoping bank soal asimetris antara admin & guru ✅
- **Admin** (`QuestionBankService::listQuestionBanks`/`listQuestions`): **tidak** memfilter `status`, `owner`, maupun `school_id` → admin melihat **semua** bank/soal termasuk draft guru, dan **tak ada batas sekolah** (risiko multi-tenant bila nanti multi-sekolah).
- **Guru:** bank terlihat jika `owner_id = user` **ATAU** `subject_id ∈ mapel yang diampu`. Guru bisa **menambah** soal ke bank milik admin untuk mapelnya, tetapi **tidak bisa mengedit/menghapus** (owner-only). Asimetri ini perlu ditegaskan sebagai keputusan desain atau diperbaiki.

---

### ⚪ CBT-08 — Normalisasi tipe soal tidak konsisten (`bs` vs `benar_salah`) ✅
- `storeQuestion` hanya menerima `bs`; `storeDirectQuestion` menerima `bs` **dan** `benar_salah` lalu menormalkan. Jalur pembuatan berbeda menerima himpunan tipe berbeda → risiko soal benar/salah tercipta dengan tipe tak seragam.

**Model tipe soal yang didukung grading** (`ExamGradingService::gradeAnswer`): `pg`, `pg_kompleks`, `bs`, `menjodohkan`, `isian` (otomatis) + `uraian` (manual). Konsisten di sisi grading; inkonsistensi hanya di validasi pembuatan.

---

## 2. Matriks Relasi Antar-Peran

Status di bawah untuk **jalur data** (apakah peran A benar-benar menerima data peran B).

| # | Relasi | Jalur | Status |
|---|---|---|---|
| R1 | siswa → guru | Kumpul tugas → guru menilai (LMS) | ✅ Tersambung (gradebook sync diperbaiki di PR #2) |
| R2 | siswa → guru | Mood/muraqabah per program | ✅ Tersambung (guard cakupan program ditambah) |
| R3 | **siswa ↔ guru** | **Jawaban ujian CBT** | 🔴 **PUTUS** (CBT-01) |
| R4 | guru → siswa | Tugas, materi, roster, label program | ✅ Tersambung |
| R5 | **guru → siswa** | **Soal ujian CBT** | 🔴 **PUTUS** (CBT-01) |
| R6 | guru → admin | Bank soal & Question (tabel bersama) | ✅ Tersambung (lihat scoping CBT-07) |
| R7 | guru → admin | Nilai → gradebook/ledger | ✅ Tersambung |
| R8 | admin → guru | Bank soal admin terlihat guru (mapelnya) | ✅ Tersambung |
| R9 | siswa → ortu | Nilai (`/me/children/*/grades`) | ✅ Tersambung via hook nyata — ⚠️ lihat X-01 |
| R10 | siswa → ortu | Presensi | ✅ Tersambung (`useGuardian` + `useChildAttendance`) |
| R11 | siswa → ortu | Konseling | ✅ Ortu punya jalur pengajuan sendiri (`useGuardianCounselingRequests`) |
| R12 | admin → ortu | Tagihan/biaya | ✅ Tersambung (`activeFees` hook nyata) |
| R13 | admin → ortu | Rapor (report cards) | ✅ Tersambung (`useChildReportCards`) |
| R14 | guru → ortu | Catatan karakter, progress report | ✅ Tersambung (tidak langsung): guru input → ortu baca via `/me/children` |

**Catatan metode:** impor dari file `mock-*-data` pada halaman ortu/siswa ternyata **hanya impor tipe (type)** (`AttendanceCalendarDay`, `GuardianCounselingItem`, dsb.), bukan data. Data aktual diambil dari hook API nyata (`use-student-data.ts`, `use-guardian-data.ts`, `guardian-context.tsx`). Jadi relasi R9–R14 **tidak** mock-driven.

---

## 3. Temuan Lintas-Sektoral

### 🟡 X-01 — Nilai yang ditampilkan ke ORTU direkayasa di lapisan UI ✅
**File:** `apps/web/src/lib/guardian-context.tsx` (`activeGrades`, baris ~309–356)
- `const score = Number(g.finalScore ?? g.score ?? 85)` → **default 85** bila nilai tak ada.
- `formative_avg: Math.min(100, Math.round(score * 1.02 * 10) / 10)` → nilai formatif **dikali 1,02** (dikarang, bukan dari backend).
- `competency_desc: … || 'Menunjukkan capaian pemahaman yang sangat baik …'` → deskripsi kompetensi dikarang.
- Predikat A/B/C/D dihitung dari `score` hasil rekayasa di atas.

**Akibat:** ortu bisa melihat angka/predikat/narasi yang **tidak berasal dari data nyata** anak. Ini menyalahi prinsip kejujuran data AGENTS.md dan berpotensi menyesatkan orang tua. Perlu: tampilkan hanya field yang benar-benar dikirim backend; kalau kosong → state kosong, bukan nilai default.

### 🟡 X-02 — Pola fallback mock saat API kosong ✅
**File:** `guardian-context.tsx` baris ~304 `children: mappedChildren.length > 0 ? mappedChildren : [DEFAULT_CHILD]`
- Saat daftar anak dari API kosong, UI jatuh ke `DEFAULT_CHILD` (data contoh). Bisa menyesatkan (seolah ada anak/data padahal tidak). Sebaiknya empty-state eksplisit.

---

## 4. Belum Diverifikasi Tuntas (ditandai jujur)

- ❔ **Fan-out pengumuman** (`/announcements`): endpoint dipakai siswa (`use-student-data.ts` baris 320) & materi (baris 343), tetapi distribusi lintas-peran (admin→semua, guru→kelas) belum ditelusuri tuntas.
- ❔ **Isi notifikasi push/WA** (§6.7 AGENTS.md: tak boleh memuat nilai/detail sensitif) — belum diaudit.
- ❔ **UI admin CBT** (bank soal/kelola ujian) via browser — tidak diuji langsung karena peran admin butuh 2FA; diaudit lewat kode service & rute + uji backend yang lulus.
- ❔ **Isian ortu→konseling** apakah pengajuan ortu benar-benar sampai & tampil ke BK/admin (hanya sisi FE ortu yang dikonfirmasi).

---

## 5. Rekomendasi Prioritas

| Prioritas | Temuan | Aksi ringkas |
|---|---|---|
| **P0** | CBT-01 + CBT-02 | Tetapkan satu halaman ujian kanonis; arahkan daftar ujian siswa ke implementasi sesi nyata (`siswa/ujian/[id]`) atau tanam panggilan sesi ke `siswa/cbt/[id]`. Hapus/arsipkan yang mock. |
| **P1** | CBT-03 | `exams()` guru: `totalQuestions` dari `withCount('questions')`, `classNames` dari relasi kelas nyata, `isProctor` dari data. |
| **P1** | X-01 | Hentikan rekayasa `formative_avg`/`score=85`/deskripsi; render hanya field backend + empty-state. |
| **P2** | CBT-06 | Konfirmasi niat token ruang; bila proctor-gate, jangan kirim ke siswa lebih awal. |
| **P2** | CBT-04 | Sediakan jalur publish soal guru (draft→aktif) **atau** hilangkan filter status agar konsisten. |
| **P2** | CBT-05 | Tambah seleksi/jumlah soal saat buat ujian; cegah ujian 0 soal. |
| **P3** | CBT-07, CBT-08, X-02 | Tegaskan scoping admin (school_id), seragamkan tipe `bs`, ganti fallback mock dengan empty-state. |

---

## 6. Keputusan yang Perlu Anda Ambil (sesuai §8 AGENTS.md)

1. **Halaman ujian kanonis:** pakai `siswa/ujian/[id]` (sudah lengkap) dan pensiunkan `siswa/cbt/[id]`, atau sebaliknya? (Memengaruhi arah perbaikan P0.)
2. **Token ruang ujian:** apakah memang untuk ditampilkan ke siswa, atau gerbang pengawas?
3. **Status soal:** apakah alur `draft→aktif` diinginkan (butuh UI publish), atau status dihapus saja?
4. **Jumlah soal per ujian:** semua soal bank, atau subset dengan jumlah/acak yang ditentukan guru?

> Dokumen ini hanya analisis. Tidak ada kode yang diubah. Perbaikan menunggu arahan Anda, dan sebaiknya dipecah per-ID fitur (mis. `FE-CBT-FIX-01`, `BE-CBT-EXAMS-02`) sesuai Aturan Emas #1 & #7.
