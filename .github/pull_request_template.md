<!--
Template ini otomatis muncul saat membuat Pull Request ke develop.
Ikuti AGENTS.md §7: judul memuat ID fitur, isi menjelaskan tujuan, AC,
perubahan DB/API, cara uji, risiko, dan checklist DoD.
-->

## Tujuan
<!-- Apa yang diselesaikan PR ini dan mengapa perlu -->

## ID Fitur / Referensi
<!-- contoh: BE-CBT-13, FE-SISWA-UI-01, atau "tanpa ID (perbaikan kecil)" -->

## Acceptance Criteria yang dipenuhi
- [ ] 
- [ ] 

## Perubahan DB / API
<!-- migration baru? endpoint baru/berubah? tulis "tidak ada" bila kosong -->

## Cara menguji
<!-- langkah reproduksi / perintah test -->
```bash
```

## Risiko & mitigasi
<!-- dampak ke data lama, fitur lain, atau performa -->

## Checklist
- [ ] Branch dibuat dari `develop` (bukan push langsung ke `develop`/`main`)
- [ ] Tidak ada file `.env` / kredensial / hasil build yang ikut ter-commit
- [ ] Test backend lulus (`php artisan test`)
- [ ] Test frontend lulus (`npm test` / `npx tsc --noEmit`)
- [ ] Ukuran PR wajar (< 500 baris perubahan bermakna) atau dijelaskan alasannya
- [ ] Migrasi kompatibel dengan data lama (tanpa reset database)
