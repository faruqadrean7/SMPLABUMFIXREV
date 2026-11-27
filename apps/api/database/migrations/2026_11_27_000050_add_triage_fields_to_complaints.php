<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Triase & SLA pengaduan (fokus anti-bullying).
 *
 * Sebelumnya semua aduan diperlakukan sama: tidak ada tingkat keparahan,
 * tidak ada tenggat penanganan, dan tidak ada eskalasi bila terlewat.
 * Kolom di bawah menambahkan triase tanpa mengubah struktur lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            if (! Schema::hasColumn('complaints', 'severity')) {
                $table->string('severity', 20)->default('sedang');
            }
            if (! Schema::hasColumn('complaints', 'due_at')) {
                $table->timestamp('due_at')->nullable();
            }
            if (! Schema::hasColumn('complaints', 'first_responded_at')) {
                $table->timestamp('first_responded_at')->nullable();
            }
            if (! Schema::hasColumn('complaints', 'escalated_at')) {
                $table->timestamp('escalated_at')->nullable();
            }
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->index(['status', 'due_at'], 'idx_complaints_status_due');
            $table->index(['category', 'severity'], 'idx_complaints_category_severity');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex('idx_complaints_status_due');
            $table->dropIndex('idx_complaints_category_severity');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn(['severity', 'due_at', 'first_responded_at', 'escalated_at']);
        });
    }
};
