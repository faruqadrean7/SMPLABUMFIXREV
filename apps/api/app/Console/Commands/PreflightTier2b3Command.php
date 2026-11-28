<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PreflightTier2b3Command extends Command
{
    protected $signature = 'db:preflight-tier2b3 {--json : Output report in JSON format}';

    protected $description = 'Read-only pre-flight check for Batch 2B-3 schema migrations (duplicates & orphan FKs)';

    /**
     * @var list<array{table: string, columns: list<string>, label: string}>
     */
    private const SOFT_DELETE_UNIQUES = [
        ['table' => 'users', 'columns' => ['username'], 'label' => 'users.username'],
        ['table' => 'users', 'columns' => ['email'], 'label' => 'users.email'],
        ['table' => 'academic_years', 'columns' => ['name'], 'label' => 'academic_years.name'],
        ['table' => 'semesters', 'columns' => ['academic_year_id', 'name'], 'label' => 'semesters.(academic_year_id,name)'],
        ['table' => 'rooms', 'columns' => ['code'], 'label' => 'rooms.code'],
        ['table' => 'subjects', 'columns' => ['code'], 'label' => 'subjects.code'],
        ['table' => 'teachers', 'columns' => ['user_id'], 'label' => 'teachers.user_id'],
        ['table' => 'teachers', 'columns' => ['nip'], 'label' => 'teachers.nip'],
        ['table' => 'teachers', 'columns' => ['nuptk'], 'label' => 'teachers.nuptk'],
        ['table' => 'staff', 'columns' => ['user_id'], 'label' => 'staff.user_id'],
        ['table' => 'guardians', 'columns' => ['user_id'], 'label' => 'guardians.user_id'],
        ['table' => 'students', 'columns' => ['user_id'], 'label' => 'students.user_id'],
        ['table' => 'students', 'columns' => ['nis'], 'label' => 'students.nis'],
        ['table' => 'students', 'columns' => ['nisn'], 'label' => 'students.nisn'],
        ['table' => 'classes', 'columns' => ['academic_year_id', 'name'], 'label' => 'classes.(academic_year_id,name)'],
        ['table' => 'teacher_salary_slips', 'columns' => ['teacher_id', 'period_year', 'period_month'], 'label' => 'teacher_salary_slips.(teacher_id,period_year,period_month)'],
        ['table' => 'schools', 'columns' => ['npsn'], 'label' => 'schools.npsn'],
        ['table' => 'program_task_submissions', 'columns' => ['task_id', 'student_id'], 'label' => 'program_task_submissions.(task_id,student_id)'],
    ];

    /**
     * @var list<array{table: string, column: string, ref_table: string}>
     */
    private const ORPHAN_FK_CHECKS = [
        ['table' => 'program_materials', 'column' => 'file_id', 'ref_table' => 'files'],
        ['table' => 'program_materials', 'column' => 'class_id', 'ref_table' => 'classes'],
        ['table' => 'program_materials', 'column' => 'assigned_student_id', 'ref_table' => 'students'],
        ['table' => 'program_materials', 'column' => 'created_by', 'ref_table' => 'users'],
        ['table' => 'program_targets', 'column' => 'set_by', 'ref_table' => 'users'],
        ['table' => 'program_tasks', 'column' => 'file_id', 'ref_table' => 'files'],
        ['table' => 'program_tasks', 'column' => 'class_id', 'ref_table' => 'classes'],
        ['table' => 'program_tasks', 'column' => 'assigned_student_id', 'ref_table' => 'students'],
        ['table' => 'program_tasks', 'column' => 'created_by', 'ref_table' => 'users'],
        ['table' => 'program_task_submissions', 'column' => 'file_id', 'ref_table' => 'files'],
        ['table' => 'program_task_submissions', 'column' => 'graded_by', 'ref_table' => 'users'],
        ['table' => 'grade_histories', 'column' => 'student_id', 'ref_table' => 'students'],
        ['table' => 'grade_histories', 'column' => 'component_id', 'ref_table' => 'grade_components'],
        ['table' => 'grade_histories', 'column' => 'period_id', 'ref_table' => 'grade_periods'],
        ['table' => 'grade_histories', 'column' => 'changed_by', 'ref_table' => 'users'],
        ['table' => 'schools', 'column' => 'logo_file_id', 'ref_table' => 'files'],
    ];

    public function handle(): int
    {
        $issues = [];

        if (Schema::hasTable('academic_years')) {
            $activeYearsNullSchool = DB::table('academic_years')
                ->whereNull('school_id')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->count();

            if ($activeYearsNullSchool > 1) {
                $issues[] = [
                    'check' => 'P0-30.academic_years_null_school',
                    'count' => $activeYearsNullSchool,
                    'detail' => "Ditemukan {$activeYearsNullSchool} academic_years aktif dengan school_id IS NULL.",
                ];
            }
        }

        if (Schema::hasTable('ppdb_waves')) {
            $activeWavesNullSchool = DB::table('ppdb_waves')
                ->whereNull('school_id')
                ->where('status', 'aktif')
                ->count();

            if ($activeWavesNullSchool > 1) {
                $issues[] = [
                    'check' => 'P0-30.ppdb_waves_null_school',
                    'count' => $activeWavesNullSchool,
                    'detail' => "Ditemukan {$activeWavesNullSchool} ppdb_waves aktif dengan school_id IS NULL.",
                ];
            }
        }

        foreach (self::SOFT_DELETE_UNIQUES as $spec) {
            if (! Schema::hasTable($spec['table'])) {
                continue;
            }

            $q = DB::table($spec['table'])->whereNull('deleted_at');
            foreach ($spec['columns'] as $col) {
                $q->whereNotNull($col);
            }

            $dupGroups = $q->select($spec['columns'])
                ->groupBy($spec['columns'])
                ->havingRaw('COUNT(*) > 1')
                ->count();

            if ($dupGroups > 0) {
                $issues[] = [
                    'check' => 'P1-42.'.$spec['label'],
                    'count' => $dupGroups,
                    'detail' => "Ditemukan {$dupGroups} grup duplikat aktif pada {$spec['label']}.",
                ];
            }
        }

        foreach (self::ORPHAN_FK_CHECKS as $fk) {
            if (! Schema::hasTable($fk['table']) || ! Schema::hasTable($fk['ref_table'])) {
                continue;
            }

            $orphanCount = DB::table($fk['table'].' as t')
                ->leftJoin($fk['ref_table'].' as r', 't.'.$fk['column'], '=', 'r.id')
                ->whereNotNull('t.'.$fk['column'])
                ->whereNull('r.id')
                ->count();

            if ($orphanCount > 0) {
                $issues[] = [
                    'check' => 'P1-44.'.$fk['table'].'.'.$fk['column'],
                    'count' => $orphanCount,
                    'detail' => "Ditemukan {$orphanCount} UUID yatim pada {$fk['table']}.{$fk['column']} -> {$fk['ref_table']}(id).",
                ];
            }
        }

        $report = [
            'status' => $issues === [] ? 'CLEAN' : 'ISSUES_FOUND',
            'issue_count' => count($issues),
            'issues' => $issues,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($issues === []) {
            $this->info('PRE-FLIGHT BERSIH: Tidak ditemukan duplikat aktif maupun UUID yatim.');
        } else {
            foreach ($issues as $issue) {
                $this->warn("[{$issue['check']}] {$issue['detail']}");
            }
        }

        return $issues === [] ? self::SUCCESS : self::FAILURE;
    }
}
