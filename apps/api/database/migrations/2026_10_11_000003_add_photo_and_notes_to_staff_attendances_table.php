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
        Schema::table('staff_attendances', function (Blueprint $table) {
            $table->uuid('photo_file_id')->nullable()->after('lng');
            $table->text('notes')->nullable()->after('status');

            $table->foreign('photo_file_id')->references('id')->on('files')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_attendances', function (Blueprint $table) {
            $table->dropForeign(['photo_file_id']);
            $table->dropColumn(['photo_file_id', 'notes']);
        });
    }
};
