<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blind index untuk NIK. Kolom `nik_enc` dienkripsi non-deterministik sehingga
 * tidak bisa dicari / dicek duplikat. `nik_hash` menyimpan HMAC-SHA256(nik) yang
 * deterministik untuk deteksi ganda, dipasangkan dengan partial-unique index
 * (ditambahkan di migrasi constraint, khusus PostgreSQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('nik_hash', 64)->nullable()->after('nik_enc');
        });
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('nik_hash', 64)->nullable()->after('nik_enc');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('nik_hash');
        });
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn('nik_hash');
        });
    }
};
