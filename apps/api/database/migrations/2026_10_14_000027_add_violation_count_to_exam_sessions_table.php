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
            if (! Schema::hasColumn('exam_sessions', 'violation_count')) {
                $table->integer('violation_count')->default(0)->after('blur_count');
            }
        });

        // Sinkronisasi data awal dari blur_count bila tersedia
        DB::statement('UPDATE exam_sessions SET violation_count = COALESCE(blur_count, 0) WHERE violation_count = 0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('exam_sessions', 'violation_count')) {
                $table->dropColumn('violation_count');
            }
        });
    }
};
