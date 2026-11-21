<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->uuid('signature_file_id')->nullable()->after('photo_file_id');
            $table->string('signature_hash', 64)->nullable()->after('signature_file_id');

            $table->foreign('signature_file_id')->references('id')->on('files')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropForeign(['signature_file_id']);
            $table->dropColumn(['signature_file_id', 'signature_hash']);
        });
    }
};
