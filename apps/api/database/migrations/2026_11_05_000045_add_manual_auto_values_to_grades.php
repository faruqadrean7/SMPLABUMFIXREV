<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Proteksi nilai manual:
 *
 *  - `manual_value`      : nilai yang diketik guru
 *  - `auto_value`        : nilai otomatis terbaik (tugas / CBT)
 *  - `auto_source`       : asal nilai otomatis ('assignment' | 'cbt')
 *  - `value`             : nilai EFEKTIF = tertinggi antara manual & otomatis
 *  - `grade_histories`   : riwayat setiap perubahan nilai (tidak menimpa apa pun)
 *
 * Data lama TIDAK dihapus. Nilai lama dipindahkan ke kolom yang sesuai
 * berdasarkan `source`-nya, dan `value` tetap sama karena pada data lama
 * hanya ada satu sumber.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->decimal('manual_value', 5, 2)->nullable()->after('value');
            $table->decimal('auto_value', 5, 2)->nullable()->after('manual_value');
            $table->string('auto_source', 20)->nullable()->after('auto_value');
        });

        // Backfill: nilai lama dipindahkan sesuai sumbernya (tanpa mengubah `value`).
        DB::table('grades')->where('source', 'manual')->whereNull('manual_value')
            ->update(['manual_value' => DB::raw('value')]);

        DB::table('grades')->whereIn('source', ['assignment', 'cbt', 'exam'])
            ->whereNull('auto_value')
            ->update([
                'auto_value' => DB::raw('value'),
                'auto_source' => DB::raw('source'),
            ]);

        // Sumber selain manual/otomatis dianggap manual agar tidak ada nilai tanpa asal.
        DB::table('grades')->whereNull('manual_value')->whereNull('auto_value')
            ->update(['manual_value' => DB::raw('value')]);

        Schema::create('grade_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('grade_id')->constrained('grades')->cascadeOnDelete();
            $table->uuid('student_id')->nullable();
            $table->uuid('component_id')->nullable();
            $table->uuid('period_id')->nullable();
            $table->decimal('old_value', 5, 2)->nullable();
            $table->decimal('new_value', 5, 2)->nullable();
            $table->string('old_source', 20)->nullable();
            $table->string('new_source', 20)->nullable();
            // Sumber PERUBAHAN: manual | assignment | cbt
            $table->string('change_source', 20);
            $table->uuid('changed_by')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['grade_id', 'created_at']);
            $table->index(['student_id', 'component_id', 'period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_histories');

        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn(['manual_value', 'auto_value', 'auto_source']);
        });
    }
};
