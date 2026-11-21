<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('extracurriculars', function (Blueprint $table) {
            $table->string('category', 50)->nullable()->after('name');
            $table->text('description')->nullable()->after('category');
            $table->string('schedule_day', 20)->nullable()->after('description');
            $table->string('schedule_time', 50)->nullable()->after('schedule_day');
            $table->boolean('is_active')->default(true)->after('quota');
        });

        Schema::table('extracurricular_members', function (Blueprint $table) {
            $table->string('role', 50)->default('anggota')->after('student_id');
            $table->date('joined_at')->nullable()->after('role');
        });

        Schema::table('extracurricular_journals', function (Blueprint $table) {
            $table->string('start_time', 10)->nullable()->after('session_date');
            $table->string('end_time', 10)->nullable()->after('start_time');
            $table->text('notes')->nullable()->after('attendance');
        });

        Schema::table('activity_documents', function (Blueprint $table) {
            $table->string('title', 200)->nullable()->after('extracurricular_id');
            $table->text('description')->nullable()->after('doc_type');
            $table->uuid('uploaded_by')->nullable()->after('description');

            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_documents', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by']);
            $table->dropColumn(['title', 'description', 'uploaded_by']);
        });

        Schema::table('extracurricular_journals', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time', 'notes']);
        });

        Schema::table('extracurricular_members', function (Blueprint $table) {
            $table->dropColumn(['role', 'joined_at']);
        });

        Schema::table('extracurriculars', function (Blueprint $table) {
            $table->dropColumn(['category', 'description', 'schedule_day', 'schedule_time', 'is_active']);
        });
    }
};
