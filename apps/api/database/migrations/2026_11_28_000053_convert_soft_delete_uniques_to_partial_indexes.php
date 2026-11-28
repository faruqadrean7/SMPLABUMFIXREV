<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * P1-42: Konversi 18 constraint UNIQUE pada 13 tabel ber-softDeletes()
 * menjadi partial unique index `WHERE deleted_at IS NULL` di PostgreSQL.
 * FK `schools(id)` sengaja ditunda ke fase multi-tenant terpisah.
 */
return new class extends Migration
{
    /**
     * @var list<array{table: string, constraint: string, index: string, columns: string}>
     */
    private const PARTIAL_UNIQUES = [
        ['table' => 'users', 'constraint' => 'users_username_unique', 'index' => 'uq_users_username_active', 'columns' => 'username'],
        ['table' => 'users', 'constraint' => 'users_email_unique', 'index' => 'uq_users_email_active', 'columns' => 'email'],
        ['table' => 'academic_years', 'constraint' => 'academic_years_name_unique', 'index' => 'uq_academic_years_name_active', 'columns' => 'name'],
        ['table' => 'semesters', 'constraint' => 'semesters_academic_year_id_name_unique', 'index' => 'uq_semesters_year_name_active', 'columns' => 'academic_year_id, name'],
        ['table' => 'rooms', 'constraint' => 'rooms_code_unique', 'index' => 'uq_rooms_code_active', 'columns' => 'code'],
        ['table' => 'subjects', 'constraint' => 'subjects_code_unique', 'index' => 'uq_subjects_code_active', 'columns' => 'code'],
        ['table' => 'teachers', 'constraint' => 'teachers_user_id_unique', 'index' => 'uq_teachers_user_id_active', 'columns' => 'user_id'],
        ['table' => 'teachers', 'constraint' => 'teachers_nip_unique', 'index' => 'uq_teachers_nip_active', 'columns' => 'nip'],
        ['table' => 'teachers', 'constraint' => 'teachers_nuptk_unique', 'index' => 'uq_teachers_nuptk_active', 'columns' => 'nuptk'],
        ['table' => 'staff', 'constraint' => 'staff_user_id_unique', 'index' => 'uq_staff_user_id_active', 'columns' => 'user_id'],
        ['table' => 'guardians', 'constraint' => 'guardians_user_id_unique', 'index' => 'uq_guardians_user_id_active', 'columns' => 'user_id'],
        ['table' => 'students', 'constraint' => 'students_user_id_unique', 'index' => 'uq_students_user_id_active', 'columns' => 'user_id'],
        ['table' => 'students', 'constraint' => 'students_nis_unique', 'index' => 'uq_students_nis_active', 'columns' => 'nis'],
        ['table' => 'students', 'constraint' => 'students_nisn_unique', 'index' => 'uq_students_nisn_active', 'columns' => 'nisn'],
        ['table' => 'classes', 'constraint' => 'classes_academic_year_id_name_unique', 'index' => 'uq_classes_year_name_active', 'columns' => 'academic_year_id, name'],
        ['table' => 'teacher_salary_slips', 'constraint' => 'teacher_salary_slips_teacher_id_period_year_period_month_unique', 'index' => 'uq_teacher_salary_slips_period_active', 'columns' => 'teacher_id, period_year, period_month'],
        ['table' => 'schools', 'constraint' => 'schools_npsn_unique', 'index' => 'uq_schools_npsn_active', 'columns' => 'npsn'],
        ['table' => 'program_task_submissions', 'constraint' => 'program_task_submissions_task_id_student_id_unique', 'index' => 'uq_program_task_submissions_active', 'columns' => 'task_id, student_id'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::PARTIAL_UNIQUES as $item) {
            DB::statement("ALTER TABLE {$item['table']} DROP CONSTRAINT IF EXISTS {$item['constraint']}");
            DB::statement("DROP INDEX IF EXISTS {$item['constraint']}");
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS {$item['index']} ON {$item['table']} ({$item['columns']}) WHERE deleted_at IS NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_reverse(self::PARTIAL_UNIQUES) as $item) {
            DB::statement("DROP INDEX IF EXISTS {$item['index']}");
            DB::statement(<<<SQL
            DO \$\$
            BEGIN
              IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = '{$item['constraint']}') THEN
                ALTER TABLE {$item['table']} ADD CONSTRAINT {$item['constraint']} UNIQUE ({$item['columns']});
              END IF;
            END \$\$;
            SQL);
        }
    }
};
