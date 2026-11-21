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
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('ticket_number', 50)->nullable()->unique()->after('school_id');
            $table->string('title', 200)->nullable()->after('ticket_number');
            $table->timestamp('resolved_at')->nullable()->after('photo_file_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['ticket_number', 'title', 'resolved_at']);
        });
    }
};
