<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_sessions', 'ends_at')) {
                $table->timestamp('ends_at')->nullable()->after('started_at');
            }
        });

        // Sinkronisasi data awal dari deadline_at bila tersedia
        DB::statement('UPDATE exam_sessions SET ends_at = deadline_at WHERE ends_at IS NULL AND deadline_at IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('exam_sessions', 'ends_at')) {
                $table->dropColumn('ends_at');
            }
        });
    }
};
