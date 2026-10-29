<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE exams DROP CONSTRAINT IF EXISTS exams_status_check;');
            DB::statement("ALTER TABLE exams ADD CONSTRAINT exams_status_check CHECK (status IN ('draft', 'dijadwalkan', 'scheduled', 'berlangsung', 'aktif', 'active', 'published', 'selesai', 'completed', 'dibatalkan', 'cancelled', 'batal', 'arsip', 'archived'));");

            DB::statement('ALTER TABLE exam_sessions DROP CONSTRAINT IF EXISTS exam_sessions_status_check;');
            DB::statement("ALTER TABLE exam_sessions ADD CONSTRAINT exam_sessions_status_check CHECK (status IN ('belum', 'mengerjakan', 'in_progress', 'selesai', 'submitted', 'terkunci'));");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE exams DROP CONSTRAINT IF EXISTS exams_status_check;');
            DB::statement("ALTER TABLE exams ADD CONSTRAINT exams_status_check CHECK (status IN ('draft', 'dijadwalkan', 'berlangsung', 'selesai', 'dibatalkan', 'arsip'));");

            DB::statement('ALTER TABLE exam_sessions DROP CONSTRAINT IF EXISTS exam_sessions_status_check;');
            DB::statement("ALTER TABLE exam_sessions ADD CONSTRAINT exam_sessions_status_check CHECK (status IN ('belum', 'mengerjakan', 'selesai', 'terkunci'));");
        }
    }
};
