# Prompt Tugas: Perbaikan P0 CBT — Pengerjaan Ujian Siswa

> Salin blok di bawah sebagai prompt untuk sesi/agent berikutnya. Konteks, file, dan endpoint sudah diverifikasi langsung dari kode pada audit 2026-10-31 (lihat `AUDIT_KONEKTIVITAS_PORTAL.md`, temuan CBT-01 & CBT-02).

---

## PROMPT (siap pakai)

```
Kerjakan FE-CBT-UJIAN-01: sambungkan pengerjaan ujian CBT siswa ke sesi ujian nyata.
Ikuti AGENTS.md. Tulis rencana singkat dulu, dan TUNGGU persetujuan sebelum menulis kode.

## Masalah (sudah diaudit, jangan diaudit ulang dari nol — verifikasi secukupnya saja)
Halaman pengerjaan ujian siswa yang LIVE merender soal dari data MOCK dan tidak pernah
memanggil endpoint sesi ujian. Akibatnya: soal buatan guru/admin tidak pernah tampil ke
siswa, jawaban tidak tersimpan, sesi tidak pernah dimulai/selesai, dan skor tidak pernah
dihitung. Implementasi yang BENAR sudah ada tetapi yatim (tidak ada link menuju ke sana).

### Bukti (file & baris)
- MOCK (dipakai saat ini):
  apps/web/src/app/(portal)/portal/siswa/cbt/[id]/page.tsx
    - baris 22: import { CBT_ACTIVE_EXAM_QUESTIONS, CBTQuestionDetail } from '@/lib/mock-student-data'
    - baris 99: currentQ dibaca dari CBT_ACTIVE_EXAM_QUESTIONS (mock)
    - satu-satunya API nyata: baris ~175 apiClient.post('/exams/${examId}/log-violation', ...)
    - punya anti-cheat (visibilitychange/blur/contextmenu, violationCount/3) — TAPI soal mock.
- REAL (lengkap, tetapi yatim):
  apps/web/src/app/(portal)/portal/siswa/ujian/[id]/page.tsx  (1334 baris)
    - pakai cbtApi dari '@/features/cbt/cbt-api':
      getStudentSessionQuestions, saveStudentAnswer, finishStudentSession,
      logStudentViolation, sendStudentHeartbeat, (startStudentSession, getStudentExamReview tersedia)
    - sudah ada: antrean offline (localStorage), heartbeat, auto-submit saat waktu habis,
      anti-cheat/log pelanggaran, dukungan tipe soal via QuestionType (@/types/cbt).
- Daftar ujian siswa mengarah ke halaman MOCK:
  apps/web/src/app/(portal)/portal/siswa/cbt/page.tsx  baris 106:
    router.push(`/siswa/cbt/${exam.id}`)

### Backend (sudah benar, JANGAN diubah di tugas ini)
routes/api/cbt.php (auth:sanctum):
  POST /exams/{id}/start-session      -> ExamSessionController@start
  GET  /exams/{id}/session-questions  -> @questions   (kunci jawaban SUDAH disaring, §6.6 OK)
  POST /exams/{id}/save-answer        -> @saveAnswer
  POST /exams/{id}/log-violation      -> @logViolation
  POST /exams/{id}/finish-session     -> @finish
  GET  /exams/{id}/review             -> @review
  POST /exam-sessions/{sessionId}/heartbeat -> @heartbeat
cbtApi (apps/web/src/features/cbt/cbt-api.ts) sudah memetakan semua endpoint di atas dengan benar.

## Keputusan yang WAJIB ditegaskan ke pemberi tugas sebelum coding
Halaman ujian KANONIS mana yang dipakai?
  - OPSI A (REKOMENDASI): jadikan `siswa/ujian/[id]` kanonis. Arahkan daftar ujian
    (siswa/cbt/page.tsx:106) ke `/siswa/ujian/${exam.id}`, lalu pensiunkan/hapus halaman
    mock `siswa/cbt/[id]/page.tsx`. Alasan: halaman ujian sudah lengkap (offline queue,
    heartbeat, anti-cheat, semua tipe soal) — tidak ada fitur yang hilang.
  - OPSI B: pertahankan rute `siswa/cbt/[id]`, buang CBT_ACTIVE_EXAM_QUESTIONS, dan tanam
    panggilan sesi cbtApi ke halaman itu. Lebih banyak kerja, duplikasi logika. Hindari
    kecuali ada alasan rute `/siswa/cbt/` harus dipertahankan.
Jangan memutuskan sendiri; tanyakan (§8 AGENTS.md). Default usulan = Opsi A.

## Acceptance Criteria (Opsi A)
AC1  Dari daftar ujian siswa, menekan "Mulai/Masuk ujian" membuka halaman yang memanggil
     endpoint sesi nyata (start-session lalu session-questions), BUKAN data mock.
AC2  Soal yang tampil ke siswa berasal dari ExamQuestion ujian tsb (buatan guru/admin),
     bukan CBT_ACTIVE_EXAM_QUESTIONS. Buktikan dengan 1 skenario: guru buat ujian berisi
     soal X → siswa peserta melihat soal X.
AC3  Semua tipe soal ter-render & terjawab benar: pg, pg_kompleks, bs, menjodohkan, isian,
     uraian. (Grading backend sudah menangani ini.)
AC4  Jawaban tersimpan (save-answer) dan sesi dapat diselesaikan (finish-session); setelah
     selesai, status peserta/sesi berubah (bukan hanya UI lokal).
AC5  Anti-cheat tetap berfungsi (log-violation terkirim) — tidak boleh regресi dari mock.
AC6  Kunci jawaban TIDAK pernah dikirim ke klien (verifikasi payload session-questions, §6.6).
AC7  Halaman mock `siswa/cbt/[id]/page.tsx` dihapus atau di-redirect ke rute kanonis; tidak
     ada lagi impor CBT_ACTIVE_EXAM_QUESTIONS di jalur pengerjaan ujian. Tidak ada link mati.
AC8  State loading / kosong / error lengkap di halaman ujian (§ Web AGENTS.md).

## Batasan
- JANGAN mengubah controller/service/route backend CBT di tugas ini (sudah benar).
- JANGAN menyentuh fitur di luar pengerjaan ujian siswa (bank soal admin, exams() guru,
  token ruang ujian — itu temuan terpisah CBT-03/04/05/06, tugas lain).
- JANGAN mengarang data; bila API kosong tampilkan empty-state, bukan nilai default.
- Satu tugas = satu ID fitur; pecah commit kecil & mudah ditinjau (§7 AGENTS.md).

## Verifikasi yang harus dijalankan sebelum klaim selesai
- Lacak lifecycle sesi di siswa/ujian/[id]: pastikan start-session dipanggil sebelum
  session-questions (cek apakah halaman memanggil cbtApi.startStudentSession; jika sesi
  dibuat implisit di backend saat questions, dokumentasikan).
- E2E manual (gunakan kredensial uji di memory, Password123!):
  login guru → buat ujian berisi soal → login siswa peserta → kerjakan → submit →
  cek jawaban & skor muncul di sisi guru/admin.
- apps/web: lint + typecheck + build.
- Jika ada uji FE/Playwright untuk CBT, jalankan; beri nama uji berawalan FE-CBT-UJIAN-01.

## Format laporan akhir: ikuti AGENTS.md bagian 11.
```

---

## Catatan untuk Anda (pemberi tugas)
- **Yang perlu Anda putuskan lebih dulu:** Opsi A vs B di atas (rekomendasi: **A**).
- Prompt ini sengaja menyuruh agent **menulis rencana dan menunggu persetujuan** sebelum coding, sesuai AGENTS.md §12.
- Tugas turunan P1–P3 (CBT-03 `totalQuestions`/`classNames`, X-01 nilai ortu direkayasa, CBT-06 token ruang, dst.) **tidak** termasuk di prompt ini — dibuat terpisah per-ID agar PR tetap kecil.
