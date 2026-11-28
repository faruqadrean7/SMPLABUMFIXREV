<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1-44: Tambah foreign key ON DELETE SET NULL non-destruktif (NOT VALID + validasi kondisional)
 * pada tabel Program Khusus, grade_histories, dan schools.logo_file_id.
 */
return new class extends Migration
{
    /**
     * @var list<array{table: string, column: string, ref_table: string, constraint: string}>
     */
    private const FOREIGN_KEYS = [
        ['table' => 'program_materials', 'column' => 'file_id', 'ref_table' => 'files', 'constraint' => 'fk_program_materials_file_id'],
        ['table' => 'program_materials', 'column' => 'class_id', 'ref_table' => 'classes', 'constraint' => 'fk_program_materials_class_id'],
        ['table' => 'program_materials', 'column' => 'assigned_student_id', 'ref_table' => 'students', 'constraint' => 'fk_program_materials_assigned_student_id'],
        ['table' => 'program_materials', 'column' => 'created_by', 'ref_table' => 'users', 'constraint' => 'fk_program_materials_created_by'],
        ['table' => 'program_targets', 'column' => 'set_by', 'ref_table' => 'users', 'constraint' => 'fk_program_targets_set_by'],
        ['table' => 'program_tasks', 'column' => 'file_id', 'ref_table' => 'files', 'constraint' => 'fk_program_tasks_file_id'],
        ['table' => 'program_tasks', 'column' => 'class_id', 'ref_table' => 'classes', 'constraint' => 'fk_program_tasks_class_id'],
        ['table' => 'program_tasks', 'column' => 'assigned_student_id', 'ref_table' => 'students', 'constraint' => 'fk_program_tasks_assigned_student_id'],
        ['table' => 'program_tasks', 'column' => 'created_by', 'ref_table' => 'users', 'constraint' => 'fk_program_tasks_created_by'],
        ['table' => 'program_task_submissions', 'column' => 'file_id', 'ref_table' => 'files', 'constraint' => 'fk_program_task_submissions_file_id'],
        ['table' => 'program_task_submissions', 'column' => 'graded_by', 'ref_table' => 'users', 'constraint' => 'fk_program_task_submissions_graded_by'],
        ['table' => 'grade_histories', 'column' => 'student_id', 'ref_table' => 'students', 'constraint' => 'fk_grade_histories_student_id'],
        ['table' => 'grade_histories', 'column' => 'component_id', 'ref_table' => 'grade_components', 'constraint' => 'fk_grade_histories_component_id'],
        ['table' => 'grade_histories', 'column' => 'period_id', 'ref_table' => 'grade_periods', 'constraint' => 'fk_grade_histories_period_id'],
        ['table' => 'grade_histories', 'column' => 'changed_by', 'ref_table' => 'users', 'constraint' => 'fk_grade_histories_changed_by'],
        ['table' => 'schools', 'column' => 'logo_file_id', 'ref_table' => 'files', 'constraint' => 'fk_schools_logo_file_id'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (self::FOREIGN_KEYS as $fk) {
                DB::statement(<<<SQL
                DO \$\$
                BEGIN
                  IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = '{$fk['constraint']}') THEN
                    ALTER TABLE {$fk['table']}
                      ADD CONSTRAINT {$fk['constraint']}
                      FOREIGN KEY ({$fk['column']}) REFERENCES {$fk['ref_table']}(id)
                      ON DELETE SET NULL NOT VALID;
                  END IF;

                  IF NOT EXISTS (
                    SELECT 1
                    FROM {$fk['table']} t
                    LEFT JOIN {$fk['ref_table']} r ON t.{$fk['column']} = r.id
                    WHERE t.{$fk['column']} IS NOT NULL AND r.id IS NULL
                  ) THEN
                    ALTER TABLE {$fk['table']} VALIDATE CONSTRAINT {$fk['constraint']};
                  END IF;
                END \$\$;
                SQL);
            }

            return;
        }

        foreach (self::FOREIGN_KEYS as $fk) {
            Schema::table($fk['table'], function (Blueprint $table) use ($fk) {
                $table->foreign($fk['column'], $fk['constraint'])
                    ->references('id')
                    ->on($fk['ref_table'])
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (array_reverse(self::FOREIGN_KEYS) as $fk) {
                DB::statement("ALTER TABLE {$fk['table']} DROP CONSTRAINT IF EXISTS {$fk['constraint']}");
            }

            return;
        }

        foreach (array_reverse(self::FOREIGN_KEYS) as $fk) {
            Schema::table($fk['table'], function (Blueprint $table) use ($fk) {
                $table->dropForeign($fk['constraint']);
            });
        }
    }
};
