# Alur Git untuk Kolaborator (Erik & lainnya)

Dokumen ini menjelaskan cara kerja yang **lolos aturan `AGENTS.md` §7**.
Aturannya tidak melarang *commit* — yang dilarang adalah **push langsung ke `main`/`develop`**.

## Ringkasan aturan

| Hal | Aturan |
|---|---|
| Branch | `feat/<ID>-<slug>`, `fix/<slug>`, `chore/<slug>` — dibuat **dari `develop`** |
| Commit | Conventional Commits: `feat(be-cbt-13): tambah impor soal` |
| Push | **Hanya ke branch sendiri**, tidak ke `develop`/`main` |
| Masuk `develop` | Lewat Pull Request, setelah CI hijau + review |
| Dilarang | commit `.env`, hasil build (`.next/`, `vendor/`), `--force` ke branch bersama |

Commit boleh dilakukan kapan saja secara lokal. Yang perlu branch adalah saat **mengirim** ke GitHub.

## Alur 6 langkah

### 1. Selalu mulai dari `develop` terbaru
```bash
git fetch origin
git checkout develop
git reset --hard origin/develop
```

### 2. Buat branch fitur
```bash
git checkout -b feat/cbt-audit-dan-4-tipe-siswa
```

### 3. Kerjakan, lalu commit
```bash
git add -A
git commit -m "feat(cbt): audit dan 4 tipe siswa"
```

### 4. Push ke branch sendiri (bukan develop)
```bash
git push -u origin feat/cbt-audit-dan-4-tipe-siswa
```

### 5. Buka Pull Request
Setelah push, GitHub menampilkan tautan "Create pull request".
Atau buka: `https://github.com/faruqadrean7/SMPLABUMFIXREV/compare/develop...feat/cbt-audit-dan-4-tipe-siswa`

Isi template PR otomatis muncul (`.github/pull_request_template.md`).

### 6. Tunggu CI hijau, lalu minta review
Jangan merge sendiri sebelum ada review.

## Versi PowerShell (Windows)

```powershell
git fetch origin
git checkout develop
git reset --hard origin/develop
git checkout -b feat/cbt-audit-dan-4-tipe-siswa
git add -A
git commit -m "feat(cbt): audit dan 4 tipe siswa"
git push -u origin feat/cbt-audit-dan-4-tipe-siswa
```

## Kalau sudah terlanjur commit di `develop` lokal

Gejalanya: `git log origin/develop..develop` menampilkan commit, tapi GitHub tidak.

```bash
# 1. Amankan
git branch backup/lokal-develop-$(date +%Y%m%d)

# 2. Pindahkan commit ke branch baru
git checkout -b feat/nama-fitur
git push -u origin feat/nama-fitur

# 3. Kembalikan develop lokal agar bersih
git checkout develop
git reset --hard origin/develop
```

Setelah itu buka PR dari `feat/nama-fitur` ke `develop`.

## Kalau ada commit berisi kredensial

**Jangan push.** `AGENTS.md` §7 melarang commit `.env`. Periksa dulu:

```bash
git show --stat <sha>
git show <sha> --name-only
```

Kalau ada `.env`, `*.key`, atau file kredensial: buat branch baru dari `origin/develop`,
lalu ambil hanya commit yang aman:

```bash
git checkout -b feat/nama-fitur origin/develop
git cherry-pick <sha-aman-1> <sha-aman-2>
```

Commit yang mengandung kredensial ditinggalkan di branch backup.

## Kalau push ditolak `protected branch`

Berarti kamu mencoba push ke `develop` atau `main`. Pindahkan ke branch sendiri:

```bash
git push -u origin HEAD:feat/nama-fitur
```
