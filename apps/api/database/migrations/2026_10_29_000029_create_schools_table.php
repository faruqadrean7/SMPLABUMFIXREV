<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel master `schools` sebagai fondasi multi-sekolah (tenant).
 * v1 berjalan single-school; seluruh tabel lain sudah memiliki kolom `school_id`.
 * Penegakan FK & global tenant-scope penuh direncanakan bertahap (lihat 14_REMEDIATION.md)
 * agar tidak memutus 100+ test yang sudah hijau.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->string('npsn', 20)->nullable()->unique();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->uuid('logo_file_id')->nullable();
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
