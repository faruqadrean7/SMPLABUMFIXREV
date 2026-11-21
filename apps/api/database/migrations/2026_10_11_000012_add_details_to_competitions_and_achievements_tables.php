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
        Schema::table('competitions', function (Blueprint $table) {
            $table->string('organizer', 150)->nullable()->after('name');
            $table->string('category', 50)->nullable()->after('organizer');
            $table->text('description')->nullable()->after('category');
        });

        Schema::table('achievements', function (Blueprint $table) {
            $table->uuid('competition_id')->nullable()->after('student_id');
            $table->uuid('certificate_file_id')->nullable()->after('achieved_at');
            $table->uuid('photo_file_id')->nullable()->after('certificate_file_id');
            $table->integer('points_awarded')->default(0)->after('photo_file_id');
            $table->text('notes')->nullable()->after('points_awarded');

            $table->foreign('competition_id')->references('id')->on('competitions')->onDelete('set null');
            $table->foreign('certificate_file_id')->references('id')->on('files')->onDelete('set null');
            $table->foreign('photo_file_id')->references('id')->on('files')->onDelete('set null');
        });

        Schema::table('competition_entries', function (Blueprint $table) {
            $table->uuid('coach_teacher_id')->nullable()->after('student_id');
            $table->text('notes')->nullable()->after('result');

            $table->foreign('coach_teacher_id')->references('id')->on('teachers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('competition_entries', function (Blueprint $table) {
            $table->dropForeign(['coach_teacher_id']);
            $table->dropColumn(['coach_teacher_id', 'notes']);
        });

        Schema::table('achievements', function (Blueprint $table) {
            $table->dropForeign(['competition_id', 'certificate_file_id', 'photo_file_id']);
            $table->dropColumn(['competition_id', 'certificate_file_id', 'photo_file_id', 'points_awarded', 'notes']);
        });

        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn(['organizer', 'category', 'description']);
        });
    }
};
