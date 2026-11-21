<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Materi Program Khusus
        Schema::create('program_materials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable()->index();
            $table->string('program_type', 30)->index(); // tahfidz, icp, inklusi, reguler
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('material_type', 50)->default('panduan'); // murottal, tajwid, panduan, reading, listening, past_paper, modul_adaptif, lainnya
            $table->uuid('file_id')->nullable()->index();
            $table->string('external_url', 500)->nullable();
            $table->jsonb('meta')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->uuid('class_id')->nullable()->index();
            $table->string('target_scope', 30)->default('all'); // all, class, student
            $table->uuid('assigned_student_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Tabel Target Program Khusus Siswa
        Schema::create('program_targets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable()->index();
            $table->uuid('student_id')->index();
            $table->string('program_type', 30)->index();
            $table->string('title', 255);
            $table->string('domain', 50)->nullable(); // akademik, motorik, sosial, english, math, science, tahfidz, umum
            $table->string('target_value', 100)->nullable();
            $table->string('current_value', 100)->nullable();
            $table->string('period', 100)->nullable();
            $table->string('status', 30)->default('aktif'); // aktif, tercapai, batal
            $table->uuid('set_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
        });

        // 3. Tabel Tugas Program Khusus
        Schema::create('program_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable()->index();
            $table->string('program_type', 30)->index();
            $table->string('title', 255);
            $table->text('instructions')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->uuid('file_id')->nullable()->index();
            $table->jsonb('meta')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->uuid('class_id')->nullable()->index();
            $table->string('target_scope', 30)->default('all');
            $table->uuid('assigned_student_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. Tabel Pengumpulan Tugas Program Khusus Siswa
        Schema::create('program_task_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('task_id')->index();
            $table->uuid('student_id')->index();
            $table->uuid('file_id')->nullable()->index();
            $table->string('link', 500)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('dikumpulkan'); // dikumpulkan, dinilai, perlu_revisi
            $table->decimal('score', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('submitted_at')->useCurrent();
            $table->uuid('graded_by')->nullable()->index();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('task_id')->references('id')->on('program_tasks')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->unique(['task_id', 'student_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
            DO $$
            BEGIN
                ALTER TABLE program_materials DROP CONSTRAINT IF EXISTS chk_program_materials_type;
                ALTER TABLE program_materials ADD CONSTRAINT chk_program_materials_type
                    CHECK (program_type IN ('reguler', 'tahfidz', 'icp', 'inklusi'));

                ALTER TABLE program_targets DROP CONSTRAINT IF EXISTS chk_program_targets_type;
                ALTER TABLE program_targets ADD CONSTRAINT chk_program_targets_type
                    CHECK (program_type IN ('reguler', 'tahfidz', 'icp', 'inklusi'));

                ALTER TABLE program_tasks DROP CONSTRAINT IF EXISTS chk_program_tasks_type;
                ALTER TABLE program_tasks ADD CONSTRAINT chk_program_tasks_type
                    CHECK (program_type IN ('reguler', 'tahfidz', 'icp', 'inklusi'));
            END $$;
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('program_task_submissions');
        Schema::dropIfExists('program_tasks');
        Schema::dropIfExists('program_targets');
        Schema::dropIfExists('program_materials');
    }
};
