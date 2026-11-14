# 09. Keamanan & Privasi

Sistem menyimpan data anak dan catatan konseling. Keamanan adalah **persyaratan utama**, bukan fitur tambahan. Dokumen ini mengikat semua platform.

## 1. Klasifikasi Data
| Kelas | Contoh | Perlakuan |
|---|---|---|
| **Publik** | Berita, profil sekolah, agenda publik, nama & foto guru | Boleh lewat endpoint `/public/*` |
| **Internal** | Jadwal, nilai, presensi, jurnal | Auth + permission + pembatasan cakupan (kelas/anak) |
| **Sensitif** | NIK, alamat, kontak wali, berkas PPDB, surat sakit, penghasilan ortu | Enkripsi (kolom/penyimpanan), akses minimum, audit |
| **Sangat sensitif** | Catatan BK, kasus disiplin berat, identitas pelapor | Enkripsi at-rest di level aplikasi, akses hanya peran berwenang, setiap baca diaudit |

## 2. Autentikasi
- Password: min 8 karakter, hash **argon2id**, tolak password umum, akun baru wajib ganti password.
- Login: batas 5 percobaan gagal → kunci 15 menit; respons seragam (tidak membocorkan keberadaan akun).
- Token: access 15 menit, refresh 14 hari dengan rotasi dan deteksi pemakaian ulang.
- Web/Admin: refresh token di cookie `HttpOnly; Secure; SameSite=Lax`; proteksi CSRF untuk endpoint cookie.
- Mobile: token di Keychain/Keystore; biometrik opsional.
- 2FA (TOTP) dinonaktifkan: login langsung menggunakan kredensial (password) tanpa tahapan 2FA/MFA.
- Reset password: token sekali pakai, kedaluwarsa 15 menit, diganti hash di DB.

## 3. Otorisasi (RBAC + cakupan data)
1. **Guard permission** pada setiap endpoint (deny-by-default).
2. **Policy cakupan data** pada service:
   | Role | Batas data |
   |---|---|
   | Guru | Hanya kelas/mapel yang ditugaskan |
   | Walas | Siswa kelas perwalian |
   | Ortu | Hanya anak yang tertaut |
   | Siswa | Hanya data diri |
   | BK | Kasus yang ditangani/seluruh kasus BK |
   | Kepala sekolah | Agregat; detail BK hanya ringkasan non-rahasia |
3. **IDOR:** setiap akses berdasar ID wajib memverifikasi kepemilikan/cakupan, bukan sekadar permission.
4. Operasi sensitif (kunci/buka nilai, ubah hasil seleksi, reset sesi ujian) butuh permission khusus + alasan + audit.

## 4. Perlindungan Data
| Aspek | Aturan |
|---|---|
| Transport | HTTPS wajib (TLS 1.2+), HSTS |
| At-rest DB | Enkripsi disk; kolom sensitif dienkripsi aplikasi (AES-256-GCM, kunci dari KMS/secret manager) |
| Berkas | Bucket privat, signed URL berumur ≤ 5 menit, nama berkas diacak |
| Backup | Terenkripsi, disimpan di lokasi terpisah, uji restore |
| Log | Tidak boleh memuat password, token, NIK utuh, isi catatan BK |
| Data anonim | Aspirasi anonim: IP & identitas tidak disimpan |
| Data demo | Staging tidak memakai data asli siswa |

## 5. Keamanan Aplikasi (OWASP)
| Risiko | Mitigasi wajib |
|---|---|
| Injeksi SQL | ORM/parameterized query; tanpa string concat |
| XSS | Sanitasi HTML rich text (allowlist), CSP ketat, escape output |
| CSRF | SameSite + token CSRF untuk cookie |
| Broken access control | Guard + policy + uji otomatis 401/403/IDOR |
| Upload berbahaya | Validasi MIME & magic bytes, batasi ukuran, jangan eksekusi, pindai virus (P1) |
| SSRF | Tidak mengambil URL arbitrer dari input |
| Brute force | Rate limit + lockout + captcha pada form publik |
| Mass assignment | DTO whitelist (`whitelist: true, forbidNonWhitelisted: true`) |
| Dependency | `npm audit`/`dart pub outdated`, Dependabot, pembaruan rutin |
| Secret | Tidak ada rahasia di repo; scanning (gitleaks) di CI |
| Header | Helmet, CORS allowlist, `X-Content-Type-Options`, `Referrer-Policy` |
| Enumerasi | Pengecekan hasil PPDB dibatasi rate; nomor tiket pengaduan acak panjang |

## 6. Keamanan CBT
- Waktu ujian dihitung di server; abaikan jam perangkat.
- Token ujian: acak, berumur pendek, terikat sesi.
- Satu sesi aktif per siswa; login ganda ditolak atau memutus sesi lama dengan log.
- Jawaban idempotent dan bertanda waktu; perubahan oleh pengawas tercatat.
- Soal & kunci tidak dikirim ke klien lebih awal dari yang dibutuhkan; **kunci jawaban tidak pernah dikirim ke klien**.
- Soal dikirim berkelompok per jendela (lazy) bila jumlah besar.
- Anti-cheat bersifat deteksi + pencatatan; keputusan akhir ada pada pengawas.

## 7. Privasi (UU PDP)
- **Dasar pemrosesan:** persetujuan orang tua/wali untuk data anak; pemberitahuan privasi tampil saat pendaftaran PPDB dan login pertama.
- **Minimisasi:** kumpulkan hanya data yang dipakai; halaman publik tidak menampilkan data pribadi anak.
- **Hak subjek data:** akses, koreksi, penghapusan/anonimisasi atas permintaan sah, dengan proses terdokumentasi.
- **Pembagian data:** tidak menjual atau membagikan data ke pihak ketiga selain pemroses layanan yang terikat perjanjian (FCM, WA gateway, email) dan hanya data seperlunya (pesan notifikasi tidak memuat nilai/detail sensitif).
- **Notifikasi:** isi push/WA bersifat umum ("Rapor bulan Oktober sudah terbit"), detail hanya di aplikasi setelah login.
- **Insiden:** prosedur respons kebocoran data (deteksi → isolasi → penilaian → pemberitahuan kepada pihak berwenang & terdampak sesuai ketentuan → perbaikan).
- **Foto anak:** penggunaan di web publik membutuhkan persetujuan tertulis.

## 8. Audit & Pemantauan
| Peristiwa wajib dicatat |
|---|
| Login berhasil/gagal, logout, reset password, perubahan role/permission |
| Perubahan nilai setelah kunci, buka/kunci periode, pengesahan rapor |
| Perubahan status/seleksi PPDB dan publikasi hasil |
| Aksi pengawas CBT (reset, tambah waktu, paksa selesai) |
| Akses baca data BK dan aspirasi |
| Ekspor data massal, perubahan konfigurasi, pembuatan/penghapusan user |

Alert: lonjakan gagal login, banyak 403, error 5xx, job gagal, backup gagal.

## 9. Operasional Keamanan
- Prinsip hak minimum untuk akun server/DB; akses produksi lewat bastion/VPN, kunci SSH, tanpa password.
- Pemisahan lingkungan dan kredensial per environment.
- Patch OS & dependency bulanan; pembaruan keamanan kritis ≤ 7 hari.
- Uji penetrasi ringan sebelum go-live dan setiap tahun.
- Rencana pemulihan bencana: RPO ≤ 24 jam, RTO ≤ 4 jam; uji restore tiap kuartal.

## 10. Checklist Sebelum Rilis
- [ ] Semua endpoint ber-guard; ada uji 401/403/IDOR untuk modul baru
- [ ] Tidak ada rahasia di repo dan log
- [ ] Data sensitif terenkripsi dan tidak bocor di endpoint publik
- [ ] Rate limit aktif di login, OTP, hasil PPDB, form publik
- [ ] Header keamanan & CSP diverifikasi
- [ ] Backup & restore diuji
- [ ] Pemberitahuan privasi & persetujuan tampil
- [ ] Audit log mencatat peristiwa wajib
- [ ] Dependency tanpa kerentanan kritis/tinggi

## Changelog
- 1.0: Draf awal.
