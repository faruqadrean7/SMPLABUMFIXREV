<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Memindahkan seluruh aturan integritas tingkat-database dari
 * prisma/native_extensions_and_constraints.sql ke jalur migrasi Laravel.
 *
 * Sebelumnya constraint ini HANYA ada di file SQL Prisma, sehingga TIDAK pernah
 * ikut ter-deploy saat `php artisan migrate`. Ini memulihkan jaminan integritas
 * (anti double-booking, status aktif tunggal, anti sesi CBT ganda, dedup NIK, dll).
 *
 * Semua pernyataan khusus PostgreSQL dibungkus guard driver, sehingga environment
 * test SQLite tetap aman (menjadi no-op).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // 1. Ekstensi wajib
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "btree_gist"');
        DB::statement('CREATE EXTENSION IF NOT EXISTS "pg_trgm"');

        // 2. Anti tumpang-tindih reservasi fasilitas yang sudah disetujui
        DB::statement(<<<'SQL'
        DO $$
        BEGIN
          IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'no_overlapping_approved_bookings') THEN
            ALTER TABLE bookings
              ADD CONSTRAINT no_overlapping_approved_bookings
              EXCLUDE USING gist (facility_id WITH =, tstzrange(start_at, end_at) WITH &&)
              WHERE (status = 'disetujui');
          END IF;
        END $$;
        SQL);

        // 3. Status aktif tunggal (partial unique index)
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS uq_single_active_academic_year ON academic_years (school_id) WHERE is_active = true AND deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS uq_single_active_semester ON semesters (academic_year_id) WHERE is_active = true AND deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS uq_single_active_ppdb_wave ON ppdb_waves (school_id) WHERE status = 'aktif'");
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS uq_single_active_cbt_session ON exam_sessions (exam_id, student_id) WHERE status IN ('belum', 'mengerjakan')");
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS uq_single_gate_attendance_per_day ON attendances (student_id, att_date) WHERE source = 'gerbang'");

        // 4. Anti bentrok jadwal: satu guru / satu ruang tidak bisa di dua tempat pada slot yang sama
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS uq_schedule_teacher_clash ON schedules (semester_id, teacher_id, day_of_week, time_slot_id)");
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS uq_schedule_room_clash ON schedules (semester_id, room_id, day_of_week, time_slot_id) WHERE room_id IS NOT NULL");

        // 5. Dedup NIK via blind index (hash deterministik HMAC dihitung di aplikasi)
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS uq_students_nik_hash ON students (nik_hash) WHERE nik_hash IS NOT NULL AND deleted_at IS NULL");
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS uq_applicants_nik_hash ON applicants (nik_hash) WHERE nik_hash IS NOT NULL");

        // 6. Indeks trigram untuk pencarian cepat (ILIKE / fuzzy)
        DB::statement("CREATE INDEX IF NOT EXISTS idx_trgm_students_name ON students USING gin (full_name gin_trgm_ops)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_trgm_teachers_name ON teachers USING gin (full_name gin_trgm_ops)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_trgm_posts_title ON posts USING gin (title gin_trgm_ops)");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_trgm_books_title ON library_books USING gin (title gin_trgm_ops)");

        // 7. CHECK constraint untuk rentang nilai numerik (idempotent via DO block)
        $this->addCheck('grades', 'chk_grades_value_range', 'value >= 0 AND value <= 100');
        $this->addCheck('questions', 'chk_questions_difficulty_range', 'difficulty >= 1 AND difficulty <= 5');
        $this->addCheck('schedules', 'chk_schedules_day_range', 'day_of_week >= 1 AND day_of_week <= 7');
        $this->addCheck('students', 'chk_students_gender', "gender IN ('L','P')");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS no_overlapping_approved_bookings');
        foreach ([
            'uq_single_active_academic_year', 'uq_single_active_semester', 'uq_single_active_ppdb_wave',
            'uq_single_active_cbt_session', 'uq_single_gate_attendance_per_day',
            'uq_schedule_teacher_clash', 'uq_schedule_room_clash',
            'uq_students_nik_hash', 'uq_applicants_nik_hash',
            'idx_trgm_students_name', 'idx_trgm_teachers_name', 'idx_trgm_posts_title', 'idx_trgm_books_title',
        ] as $idx) {
            DB::statement("DROP INDEX IF EXISTS {$idx}");
        }

        DB::statement('ALTER TABLE grades DROP CONSTRAINT IF EXISTS chk_grades_value_range');
        DB::statement('ALTER TABLE questions DROP CONSTRAINT IF EXISTS chk_questions_difficulty_range');
        DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS chk_schedules_day_range');
        DB::statement('ALTER TABLE students DROP CONSTRAINT IF EXISTS chk_students_gender');
    }

    private function addCheck(string $table, string $name, string $expr): void
    {
        DB::statement(<<<SQL
        DO \$\$
        BEGIN
          IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = '{$name}') THEN
            ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expr});
          END IF;
        END \$\$;
        SQL);
    }
};
