# Alur Git untuk Kolaborator (Erik & lainnya)

Aturan ini **sudah disesuaikan** (lihat `AGENTS.md` §7). Ringkasnya: **push ke `develop` boleh**,
push ke `main` tidak.

## Ringkasan aturan

| Hal | Aturan |
|---|---|
| `develop` | Jalur integrasi utama. **Boleh di-push langsung** oleh kolaborator |
| Branch fitur | **Opsional** — dipakai untuk perubahan besar, berisiko, atau yang butuh review dulu |
| Commit | Conventional Commits: `feat(be-cbt-13): tambah impor soal` |
| `main` | **Hanya lewat Pull Request dari `develop`**. Tidak boleh push langsung |
| Dilarang | commit `.env` / kredensial, hasil build (`.next/`, `vendor/`, `node_modules/`), `--force` ke branch bersama |
| Selesai | CI hijau. Kalau CI merah, perbaiki dulu — jangan tinggalkan `develop` merah |

## Alur harian (paling sederhana)

```bash
git fetch origin
git checkout develop
git pull --ff-only origin develop

# kerjakan perubahan...
git add -A
git commit -m "fix(cbt): perbaiki validasi token ujian"
git push origin develop
```

Setelah push, buka tab **Actions** di GitHub. Kalau hijau, sistem otomatis tayang.
Kalau merah, baca log langkah yang gagal, perbaiki, commit lagi.

## Kalau perubahannya besar atau berisiko

Pakai branch fitur supaya `develop` tetap sehat:

```bash
git fetch origin
git checkout -b feat/nama-fitur origin/develop
# kerjakan...
git commit -m "feat(cbt): audit dan 4 tipe siswa"
git push -u origin feat/nama-fitur
```

Lalu buka PR ke `develop`. Template PR otomatis muncul
(`.github/pull_request_template.md`). Merge setelah CI hijau.

## Kalau sudah terlanjur commit di `develop` lokal dan tertinggal dari GitHub

Gejalanya: `git log origin/develop..develop` menampilkan commit, tapi GitHub tidak.

```bash
# 1. Amankan
git branch backup/lokal-develop-$(date +%Y%m%d)

# 2. Tarik perubahan terbaru, lalu pilih:
git fetch origin

#   a) simpan pekerjaan di branch fitur
git checkout -b feat/nama-fitur
git push -u origin feat/nama-fitur

#   b) atau buang dan samakan dengan GitHub
git checkout develop
git reset --hard origin/develop
```

**Jangan** `git push --force` ke `develop` — itu menghapus pekerjaan orang lain.

## Kalau ada commit berisi kredensial

`AGENTS.md` §7 melarang commit `.env`. Periksa sebelum push:

```bash
git show --stat <sha>
git show <sha> --name-only
```

Kalau ada `.env`, `*.key`, atau file kredensial: **jangan push commit itu**. Buat branch baru
dari `origin/develop`, lalu ambil hanya commit yang aman:

```bash
git checkout -b feat/nama-fitur origin/develop
git cherry-pick <sha-aman-1> <sha-aman-2>
```

Kalau kredensial sudah terlanjur ter-push, password/token itu harus **diganti**, bukan sekadar
dihapus dari commit.

## Versi PowerShell (Windows)

```powershell
git fetch origin
git checkout develop
git pull --ff-only origin develop
git add -A
git commit -m "fix(cbt): perbaiki validasi token ujian"
git push origin develop
```

## Menyinkronkan `main` (hanya pemilik repo)

`main` diisi lewat PR dari `develop`. Setelah PR di-merge, `main` dan `develop` kembali sejajar:

```bash
git fetch origin
git checkout main
git pull --ff-only origin main
git checkout develop
git merge --ff-only origin/main
git push origin develop
```
