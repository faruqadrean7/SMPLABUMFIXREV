<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0-30: Tambah indeks komposit query panas & partial unique index untuk kasus school_id IS NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uq_single_active_academic_year_null_school ON academic_years ((1)) WHERE school_id IS NULL AND is_active = true AND deleted_at IS NULL');
            DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS uq_single_active_ppdb_wave_null_school ON ppdb_waves ((1)) WHERE school_id IS NULL AND status = 'aktif'");

            DB::statement('CREATE INDEX IF NOT EXISTS idx_grades_period_component ON grades (period_id, component_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_grades_student_created_at ON grades (student_id, created_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_grades_exam_id ON grades (exam_id)');

            DB::statement('CREATE INDEX IF NOT EXISTS idx_exam_answers_question_score ON exam_answers (question_id, score)');

            DB::statement('CREATE INDEX IF NOT EXISTS idx_exam_sessions_status_ends_at ON exam_sessions (status, ends_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_exam_sessions_status_deadline_at ON exam_sessions (status, deadline_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_exam_sessions_student_status ON exam_sessions (student_id, status)');

            DB::statement('CREATE INDEX IF NOT EXISTS idx_audit_logs_created_at_desc ON audit_logs (created_at DESC)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_audit_logs_action_created_at ON audit_logs (action, created_at)');

            DB::statement('CREATE INDEX IF NOT EXISTS idx_attendances_att_date_status ON attendances (att_date, status)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_attendances_student_date_status ON attendances (student_id, att_date, status)');

            DB::statement('CREATE INDEX IF NOT EXISTS idx_invoices_status_due_date ON invoices (status, due_date)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_payments_status_payment_date ON payments (status, payment_date)');

            DB::statement('CREATE INDEX IF NOT EXISTS idx_applicants_wave_track_status ON applicants (wave_id, track_id, status)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_applicants_status_rereg_deadline ON applicants (status, re_registration_deadline_at)');

            return;
        }

        Schema::table('grades', function (Blueprint $table) {
            $table->index(['period_id', 'component_id'], 'idx_grades_period_component');
            $table->index(['student_id', 'created_at'], 'idx_grades_student_created_at');
            $table->index('exam_id', 'idx_grades_exam_id');
        });
        Schema::table('exam_answers', function (Blueprint $table) {
            $table->index(['question_id', 'score'], 'idx_exam_answers_question_score');
        });
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->index(['status', 'ends_at'], 'idx_exam_sessions_status_ends_at');
            $table->index(['status', 'deadline_at'], 'idx_exam_sessions_status_deadline_at');
            $table->index(['student_id', 'status'], 'idx_exam_sessions_student_status');
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('created_at', 'idx_audit_logs_created_at_desc');
            $table->index(['action', 'created_at'], 'idx_audit_logs_action_created_at');
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['att_date', 'status'], 'idx_attendances_att_date_status');
            $table->index(['student_id', 'att_date', 'status'], 'idx_attendances_student_date_status');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['status', 'due_date'], 'idx_invoices_status_due_date');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status', 'payment_date'], 'idx_payments_status_payment_date');
        });
        Schema::table('applicants', function (Blueprint $table) {
            $table->index(['wave_id', 'track_id', 'status'], 'idx_applicants_wave_track_status');
            $table->index(['status', 're_registration_deadline_at'], 'idx_applicants_status_rereg_deadline');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach ([
                'uq_single_active_academic_year_null_school',
                'uq_single_active_ppdb_wave_null_school',
                'idx_grades_period_component',
                'idx_grades_student_created_at',
                'idx_grades_exam_id',
                'idx_exam_answers_question_score',
                'idx_exam_sessions_status_ends_at',
                'idx_exam_sessions_status_deadline_at',
                'idx_exam_sessions_student_status',
                'idx_audit_logs_created_at_desc',
                'idx_audit_logs_action_created_at',
                'idx_attendances_att_date_status',
                'idx_attendances_student_date_status',
                'idx_invoices_status_due_date',
                'idx_payments_status_payment_date',
                'idx_applicants_wave_track_status',
                'idx_applicants_status_rereg_deadline',
            ] as $index) {
                DB::statement("DROP INDEX IF EXISTS {$index}");
            }

            return;
        }

        Schema::table('applicants', function (Blueprint $table) {
            $table->dropIndex('idx_applicants_wave_track_status');
            $table->dropIndex('idx_applicants_status_rereg_deadline');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_status_payment_date');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('idx_invoices_status_due_date');
        });
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_attendances_att_date_status');
            $table->dropIndex('idx_attendances_student_date_status');
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_logs_created_at_desc');
            $table->dropIndex('idx_audit_logs_action_created_at');
        });
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_exam_sessions_status_ends_at');
            $table->dropIndex('idx_exam_sessions_status_deadline_at');
            $table->dropIndex('idx_exam_sessions_student_status');
        });
        Schema::table('exam_answers', function (Blueprint $table) {
            $table->dropIndex('idx_exam_answers_question_score');
        });
        Schema::table('grades', function (Blueprint $table) {
            $table->dropIndex('idx_grades_period_component');
            $table->dropIndex('idx_grades_student_created_at');
            $table->dropIndex('idx_grades_exam_id');
        });
    }
};
