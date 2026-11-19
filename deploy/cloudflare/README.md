# Cloudflare Tunnel + Access

Tunnel berjalan di server ini sebagai `cloudflared.service` (dashboard-managed, token di
`/etc/cloudflared/token`). Routing diatur dari Zero Trust dashboard, bukan dari file lokal.

## Route yang dibutuhkan

Dua route pada hostname yang sama, supaya frontend dan API satu origin
(tidak ada CORS, cookie login tetap jalan):

| Path   | Service URL              |
| ------ | ------------------------ |
| (kosong) | `http://localhost:3100` |
| `^/api`  | `http://localhost:8000` |

Aplikasi memanggil `/api/v1/...` secara relatif, jadi browser selalu memanggil hostname
publik, dan Cloudflare yang meneruskan ke port yang benar.

## Mengunci akses dengan Cloudflare Access

Aplikasi ini tidak punya lapisan login publik yang kuat, jadi jangan biarkan terbuka.
Langkah di dashboard Zero Trust:

1. **Access → Applications → Add an application → Self-hosted**
2. Nama: `SISTLABUM`
3. Public hostname: `smplab.fadrean.web.id` (path kosong — lindungi seluruh host)
4. Policy:
   - Action: `Allow`
   - Include: `Emails` → masukkan email yang boleh masuk
   - (opsional) tambah policy kedua `Bypass` untuk `^/api` kalau API perlu diakses
     tanpa login Access — **jangan** dilakukan kalau API memuat data siswa
5. Session duration: 24 jam
6. Simpan

Setelah aktif, membuka `https://smplab.fadrean.web.id` akan meminta email + OTP dari
Cloudflare sebelum sampai ke server. Tidak ada request yang mencapai VPS sebelum lolos.

## Verifikasi dari server

```bash
# tanpa sesi Access harus 302 ke cloudflareaccess.com, bukan 200
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' https://smplab.fadrean.web.id/

# API
curl -s -o /dev/null -w '%{http_code}\n' https://smplab.fadrean.web.id/api/v1/health
```

Kalau hasilnya 502/1033 → route belum ada atau service di port tujuan mati:

```bash
systemctl --user status sistlabum-web sistlabum-api
curl -s -o /dev/null -w 'web %{http_code}\n' http://127.0.0.1:3100/
curl -s -o /dev/null -w 'api %{http_code}\n' http://127.0.0.1:8000/api/v1/health
```

## Catatan keamanan

- Kedua service bind ke `127.0.0.1` saja — tidak ada yang bisa mencapainya dari LAN
  atau internet selain cloudflared.
- `APP_DEBUG=false` dan `APP_ENV=production` wajib; kalau tidak, stack trace dan isi
  environment bisa terbaca publik.
- `php artisan serve` masih server bawaan PHP (single-process, 8 worker). Untuk beban
  nyata, pakai Apache + PHP-FPM lewat `deploy/setup-vps.sh` dan arahkan route ke port 80.
