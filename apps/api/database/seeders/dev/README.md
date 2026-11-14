# Seeders untuk Development / UAT / Staging

Folder ini berisi seeder yang **HANYA** digunakan untuk keperluan:
- Development lokal
- UAT / Staging environment
- Load testing / simulasi data

## Cara Pakai
```bash
# Jalankan seeder tertentu secara manual
php artisan db:seed --class="Database\Seeders\Dev\CbtDemoSeeder"
```

> ⚠️ **JANGAN** panggil seeder ini dari `DatabaseSeeder.php` atau pipeline produksi.
