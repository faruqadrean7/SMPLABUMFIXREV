<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Non-destruktif (P0-18): melebarkan kolom counseling_requests.topic dari varchar(200) ke text
     * agar ciphertext enkripsi at-rest (SafeEncryptedCast) tidak terpotong di PostgreSQL.
     */
    public function up(): void
    {
        Schema::table('counseling_requests', function (Blueprint $table) {
            $table->text('topic')->change();
        });
    }

    public function down(): void
    {
        // Rollback aman: tidak memangkas data jika sudah terenkripsi (> 200 char)
    }
};
