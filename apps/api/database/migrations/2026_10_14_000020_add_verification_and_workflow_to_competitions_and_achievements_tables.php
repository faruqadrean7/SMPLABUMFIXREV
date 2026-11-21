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
            $table->date('registration_deadline')->nullable()->after('held_at');
            $table->string('status', 30)->default('direncanakan')->after('registration_deadline'); // direncanakan, pendaftaran, berlangsung, selesai, dibatalkan
        });

        Schema::table('competition_entries', function (Blueprint $table) {
            $table->string('status', 30)->default('terdaftar')->after('result'); // terdaftar, dikonfirmasi, hadir, gugur, selesai, dibatalkan
        });

        Schema::table('achievements', function (Blueprint $table) {
            $table->uuid('school_id')->nullable()->index()->after('id');
            $table->string('verification_status', 30)->default('verified')->after('points_awarded'); // pending, verified, rejected
            $table->uuid('verified_by')->nullable()->after('verification_status');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
            $table->text('rejection_reason')->nullable()->after('verified_at');
            $table->uuid('student_point_id')->nullable()->after('rejection_reason');

            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('student_point_id')->references('id')->on('student_points')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('achievements', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropForeign(['student_point_id']);
            $table->dropColumn([
                'school_id',
                'verification_status',
                'verified_by',
                'verified_at',
                'rejection_reason',
                'student_point_id',
            ]);
        });

        Schema::table('competition_entries', function (Blueprint $table) {
            $table->dropColumn(['status']);
        });

        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn(['registration_deadline', 'status']);
        });
    }
};
