# PROMPT — Verifikasi Ujian CBT "PAS Matematika Kelas 9" di VPS

> Berikan prompt ini ke AI engineer yang berjalan di **server VPS** (lingkungan staging/produksi yang
> di-deploy lewat CI/CD). Tujuannya: **memverifikasi** apakah ujian CBT PAS Matematika Kelas 9
> benar-benar sudah ter-deploy dan **siap dipakai ujian** di VPS — bukan hanya di laptop developer.

---

## 0. Peran & Aturan (WAJIB dibaca)

Kamu adalah engineer yang memverifikasi deployment di **VPS**. Ikuti `AGENTS.md`. Khusus tugas ini:

1. **JANGAN** menulis/mencetak kunci jawaban, rahasia (`.env`, token API), atau data pribadi nyata ke
   output, log, atau commit. Verifikasi grading dilakukan lewat **alur E2E** (jawab benar → cek skor),
   **bukan** dengan menampilkan kunci.
2. **JANGAN** mengubah data produksi yang bukan bagian demo ini. Jika harus menyiapkan data, batasi
   hanya pada paket ujian Matematika ini dan akun siswa demo fiktif.
3. **Kunci jawaban tidak boleh bocor ke klien** — ini salah satu yang WAJIB kamu uji (lihat §4.5).
4. Jika ada yang ambigu atau butuh keputusan di luar wewenang (mis. ubah data produksi lain), **berhenti
   dan laporkan**, jangan menebak.
5. Jalankan perintah artisan dari root aplikasi API di VPS (di bawah ini disebut `APP_DIR`, mis.
   `/var/www/sistlabum/apps/api` — sesuaikan dengan path nyata di VPS).

---

## 1. Konteks terverifikasi (sumber kebenaran)

Branch `develop` (yang di-deploy CI/CD ke VPS) **sudah berisi**:

- **Command artisan** (siap dipakai di VPS):
  - `php artisan cbt:import-package {manifest} [--fresh] [--dry-run]` — impor paket soal ke bank soal & bikin ujian.
  - `php artisan cbt:attach-mtk-figures [--exam-title=...] [--base-url=...] [--path=cbt/mtk-pas-9]` — tautkan gambar SVG ke soal (set `body.media_url`), idempoten.
  - `php artisan cbt:demo-schedule-exam` — jadwalkan ujian jadi **berlangsung**, buat ruang + token, enroll kelas.
- **10 berkas figur SVG** di `apps/api/public/cbt/mtk-pas-9/`:
  `q04-line.svg, q05-coord.svg, q09-prism.svg, q11-pool.svg, q13-dome.svg, q17-scatter.svg, q18-scatter-options.svg, q19-stemleaf.svg, q21-plants.svg, q22-shapes.svg`.

Branch `develop` **TIDAK berisi** (gitignore — jadi TIDAK ada di VPS lewat git/CI-CD):

- ⚠️ **Manifest impor** `apps/api/storage/app/import/mtk-pas-9-genap-2526.json` → berkas inilah yang dibaca
  `cbt:import-package`. Tanpa berkas ini, impor **tidak bisa** dijalankan di VPS.
- ⚠️ Berkas sumber `docs/mtk/` (PDF/kisi-kisi).

**Spesifikasi paket (yang harus terpenuhi setelah impor):**

- Judul ujian: **`PAS Matematika Kelas 9 — Genap 2025/2026`** (cocokkan via **judul**, bukan UUID — UUID di VPS berbeda dari lokal).
- Tipe ujian `SAS`, `passing_grade = 75`, durasi 90 menit.
- **26 soal**, total bobot **100**: 20 PG (bobot 3), 1 menjodohkan (bobot 20, 5 pasangan), 5 benar/salah (bobot 4).
- **15 soal bergambar** setelah `attach-mtk-figures` (sort_order: 4, 5, 9, 11, 13, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26).
- Token ruang demo: `MTK9DEMO`.
- Peserta demo: ~21 siswa fiktif (1 kelas 9 + 1 akun kanonik). Password semua: `Password123!`.

---

## 2. Langkah 0 — Cek lingkungan VPS

```bash
cd "$APP_DIR"
php artisan --version
grep -E '^APP_URL=|^APP_ENV=' .env            # APP_URL HARUS domain publik VPS, BUKAN localhost
php artisan migrate:status | tail -20          # pastikan tidak ada migrasi pending
ls -la public/cbt/mtk-pas-9/                   # 10 SVG harus ada (dibawa oleh deploy)
```

- ✅ `APP_URL` wajib = domain publik VPS (mis. `https://portal.sekolah.sch.id`). Ini **menentukan** apakah
  gambar soal bisa dimuat siswa. Catat nilainya; dipakai di §3 & §4.4.
- Jika ada migrasi pending → jalankan `php artisan migrate --force` (atau laporkan bila kebijakan deploy melarang).

---

## 3. Langkah 1 — Apakah data ujian sudah ada di DB VPS?

```bash
php artisan tinker --execute='
use App\Models\Exam; use App\Models\ExamQuestion; use App\Models\ExamRoom; use App\Models\ExamParticipant;
$e = Exam::where("title","PAS Matematika Kelas 9 — Genap 2025/2026")->first();
if(!$e){ echo "EXAM_TIDAK_ADA\n"; return; }
echo "EXAM_ADA id=".$e->id." status=".$e->status." type=".$e->exam_type." passing=".$e->passing_grade."\n";
echo "jendela=".$e->start_at." s/d ".$e->end_at."\n";
echo "jml_soal=".ExamQuestion::where("exam_id",$e->id)->count()."\n";
echo "jml_ruang=".ExamRoom::where("exam_id",$e->id)->count()." token=".optional(ExamRoom::where("exam_id",$e->id)->first())->token."\n";
echo "jml_peserta=".ExamParticipant::where("exam_id",$e->id)->count()."\n";
'
```

### Cabang A — kalau `EXAM_TIDAK_ADA`

Data belum ada di VPS. **Jangan** anggap gagal; siapkan lalu verifikasi:

1. **Blokir manifest:** berkas `storage/app/import/mtk-pas-9-genap-2526.json` tidak dibawa git. Pastikan
   berkas ini tersedia di VPS (mis. transfer manual via `scp`, atau artefak CI). **Kalau tidak tersedia,
   BERHENTI dan laporkan** — impor tidak mungkin tanpa manifest.
2. Jalankan berurutan (ganti `$BASE` dengan `APP_URL` VPS dari §2):
   ```bash
   php artisan cbt:import-package storage/app/import/mtk-pas-9-genap-2526.json --dry-run   # pratinjau dulu
   php artisan cbt:import-package storage/app/import/mtk-pas-9-genap-2526.json
   php artisan cbt:attach-mtk-figures --base-url="$BASE"
   php artisan cbt:demo-schedule-exam
   ```
3. Ulangi §3 untuk memastikan angka sesuai §1, lalu lanjut ke §4.

### Cabang B — kalau `EXAM_ADA`

Lanjut verifikasi penuh di §4. Pastikan angka cocok dengan §1 (26 soal, 1 ruang token `MTK9DEMO`, ~21 peserta,
status `berlangsung`, jendela waktu mencakup **saat ini**). Jika status bukan `berlangsung` atau jendela sudah
lewat → jalankan `php artisan cbt:demo-schedule-exam` lagi.

---

## 4. Langkah 2 — Verifikasi kelayakan pakai (WAJIB semua lulus)

### 4.1 Soal & bobot
```bash
php artisan tinker --execute='
use App\Models\Exam; use App\Models\ExamQuestion;
$e = Exam::where("title","PAS Matematika Kelas 9 — Genap 2025/2026")->first();
$q = ExamQuestion::where("exam_id",$e->id)->get();
echo "jml_soal=".$q->count()." (harus 26)\n";
echo "total_bobot=".$q->sum(fn($x)=>$x->weight_override ?? optional($x->question)->weight)." (harus 100)\n";
'
```

### 4.2 Peserta & token
- `jml_peserta` ≈ 21, token ruang = `MTK9DEMO`. Siswa demo bisa login dgn `Password123!`.
  (Jangan cetak daftar email/nama ke log bila kebijakan privasi melarang; cukup hitung jumlah.)

### 4.3 Visibilitas dashboard
- **Admin:** halaman `admin/cbt/exams` memanggil `GET /api/v1/cbt/exams` (`ExamService::listExams`, tidak
  di-scope pemilik) → ujian **harus muncul** untuk admin.
- **Guru:** dashboard guru memanggil `GET /api/v1/me/teacher/exams` yang **di-scope `created_by`** → ujian
  hanya muncul bagi guru yang `user.id`-nya = `exam.created_by`. Verifikasi siapa pembuatnya:
  ```bash
  php artisan tinker --execute='
  use App\Models\Exam; use App\Models\User;
  $e = Exam::where("title","PAS Matematika Kelas 9 — Genap 2025/2026")->first();
  echo "created_by=".optional(User::find($e->created_by))->email."\n";
  '
  ```
  Laporkan email pembuatnya. Kalau diminta agar guru mapel Matematika (`guru.mtk@...`) melihatnya, set
  `exam.created_by` = user.id guru tsb (hanya untuk demo) — **tanyakan dulu** sebelum mengubah.

### 4.4 Gambar bisa dimuat siswa (KRITIS)
`media_url` tiap soal harus memakai **domain VPS**, bukan `localhost`. Uji keterjangkauan nyata:
```bash
php artisan tinker --execute='
use App\Models\Exam; use App\Models\ExamQuestion; use App\Models\Question;
$e = Exam::where("title","PAS Matematika Kelas 9 — Genap 2025/2026")->first();
foreach(ExamQuestion::where("exam_id",$e->id)->get() as $eq){
  $b = Question::find($eq->question_id)->body;
  if(is_array($b) && !empty($b["media_url"])) echo $eq->sort_order." ".$b["media_url"]."\n";
}' | sort -n
```
- Pastikan **tidak ada** yang mengandung `localhost`/`127.0.0.1`. Kalau ada → jalankan ulang
  `php artisan cbt:attach-mtk-figures --base-url="$BASE"`.
- Lalu uji HTTP tiap figur (harus `200` + `content-type: image/svg+xml`):
  ```bash
  for f in q04-line q05-coord q09-prism q11-pool q13-dome q17-scatter q18-scatter-options q19-stemleaf q21-plants q22-shapes; do
    code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/cbt/mtk-pas-9/$f.svg"); echo "$code  $f.svg"; done
  ```

### 4.5 Keamanan — kunci jawaban TIDAK bocor ke klien (KRITIS)
Payload soal untuk siswa **tidak boleh** memuat `is_correct` atau `match_key`. Buktikan lewat API siswa
(atau tinjau `ExamSessionService::getSessionQuestions` memang membuang field tsb):
```bash
# login siswa demo -> ambil token -> start sesi -> ambil soal; cek payload.
# field "is_correct" dan "match_key" HARUS tidak ada di respons.
```
Laporkan hasilnya eksplisit: "kunci tidak bocor" atau temuan bila bocor (ini blocker rilis).

### 4.6 Alur E2E nyata (login → kerjakan → nilai)
Dengan 1 akun siswa demo (`Password123!`) + token `MTK9DEMO`, lewat **API publik VPS**:
1. Login → dapat bearer token.
2. `/portal/siswa/cbt` → mulai ujian → **harus** dapat **26 soal**, tanpa `is_correct`/`match_key`.
3. Jawab semua **benar** → submit/selesai.
4. Pastikan auto-grading jalan dan **skor = 100** (bobot total 100, semua benar). Lalu uji 1 jawaban sengaja
   salah untuk memastikan skor turun sesuai bobot (grading bukan nilai statis).

---

## 5. Langkah 3 — CI/CD sehat

- Pipeline terakhir **hijau** (build + migrate + deploy sukses); tidak ada job gagal.
- `php artisan migrate:status` tanpa pending; `storage/` & `public/cbt/` ter-deploy dan writable sesuai kebutuhan.
- `APP_ENV`/`APP_DEBUG` sesuai lingkungan (debug **off** di produksi).

---

## 6. Definition of Done (centang semua)

- [ ] `APP_URL` = domain VPS; migrasi tidak pending; 10 SVG ada di `public/cbt/mtk-pas-9/`.
- [ ] Ujian ada (judul cocok), status `berlangsung`, jendela mencakup saat ini.
- [ ] 26 soal, total bobot 100, 15 soal bergambar.
- [ ] 1 ruang token `MTK9DEMO`, ~21 peserta; siswa demo bisa login.
- [ ] Semua `media_url` pakai domain VPS (tanpa localhost) & semua SVG HTTP 200.
- [ ] Muncul di dashboard admin; status visibilitas guru dilaporkan (siapa `created_by`).
- [ ] Kunci jawaban **tidak bocor** ke payload siswa.
- [ ] E2E lulus: 26 soal tampil, submit, skor 100 saat semua benar; skor turun saat ada yang salah.
- [ ] CI/CD terakhir hijau.
- [ ] Tidak ada rahasia/data pribadi/kunci jawaban yang tercetak atau ter-commit.

---

## 7. Format laporan (balas dengan ini)

```
## Hasil verifikasi VPS: Ujian CBT PAS Matematika Kelas 9
Status: Siap dipakai | Perlu perbaikan | Terblokir

### Lingkungan
- APP_URL / APP_ENV / migrasi: ...
### Data ujian
- ada/tidak, status, jendela, jml soal, bobot, peserta, token: ...
### Gambar
- media_url pakai domain VPS? semua SVG 200? : ...
### Keamanan
- kunci jawaban bocor ke klien? : ...
### E2E
- langkah + skor yang didapat: ...
### Dashboard
- admin muncul? guru (created_by=?) : ...
### CI/CD
- status pipeline terakhir: ...
### Temuan / blokir / langkah berikutnya
- ...
```
