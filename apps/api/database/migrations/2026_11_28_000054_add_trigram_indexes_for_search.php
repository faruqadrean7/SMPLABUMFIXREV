<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P1-43: Tambah indeks GIN trigram selektif pada kolom pencarian teks tinggi
 * untuk melengkapi indeks trigram eksisting dan mengoptimalkan query ILIKE.
 */
return new class extends Migration
{
    private const TRIGRAM_INDEXES = [
        'idx_trgm_students_nis' => ['table' => 'students', 'column' => 'nis'],
        'idx_trgm_applicants_name' => ['table' => 'applicants', 'column' => 'full_name'],
        'idx_trgm_exams_title' => ['table' => 'exams', 'column' => 'title'],
        'idx_trgm_tickets_title' => ['table' => 'tickets', 'column' => 'title'],
        'idx_trgm_assets_name' => ['table' => 'assets', 'column' => 'name'],
        'idx_trgm_announcements_title' => ['table' => 'announcements', 'column' => 'title'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS "pg_trgm"');

        foreach (self::TRIGRAM_INDEXES as $indexName => $target) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$indexName} ON {$target['table']} USING gin ({$target['column']} gin_trgm_ops)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_keys(self::TRIGRAM_INDEXES) as $indexName) {
            DB::statement("DROP INDEX IF EXISTS {$indexName}");
        }
    }
};
