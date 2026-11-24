<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_sessions', 'session_lock_token')) {
                $table->string('session_lock_token', 64)->nullable()->after('device_fingerprint');
            }
        });

        Schema::table('exam_violations', function (Blueprint $table) {
            $table->index(['session_id', 'violation_type'], 'exam_violations_session_type_idx');
        });

        Schema::table('exam_participants', function (Blueprint $table) {
            $table->index(['exam_id', 'exam_room_id'], 'exam_participants_exam_room_idx');
        });

        Schema::table('exam_questions', function (Blueprint $table) {
            $table->index(['exam_id', 'package_code'], 'exam_questions_exam_package_idx');
        });
    }

    public function down(): void
    {
        Schema::table('exam_questions', function (Blueprint $table) {
            $table->dropIndex('exam_questions_exam_package_idx');
        });

        Schema::table('exam_participants', function (Blueprint $table) {
            $table->dropIndex('exam_participants_exam_room_idx');
        });

        Schema::table('exam_violations', function (Blueprint $table) {
            $table->dropIndex('exam_violations_session_type_idx');
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('exam_sessions', 'session_lock_token')) {
                $table->dropColumn('session_lock_token');
            }
        });
    }
};
