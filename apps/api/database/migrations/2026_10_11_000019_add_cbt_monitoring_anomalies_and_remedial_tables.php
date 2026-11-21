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
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->timestamp('last_heartbeat_at')->nullable()->after('score');
            $table->string('ip_address', 45)->nullable()->after('last_heartbeat_at');
            $table->string('user_agent', 500)->nullable()->after('ip_address');
            $table->string('device_fingerprint', 100)->nullable()->after('user_agent');
            $table->integer('blur_count')->default(0)->after('device_fingerprint');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->decimal('max_remedial_score', 5, 2)->nullable()->after('passing_grade');
            $table->string('sync_node_token', 100)->nullable()->after('status');
        });

        Schema::create('cbt_sync_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable()->index();
            $table->uuid('exam_id')->index();
            $table->string('node_id', 100);
            $table->string('sync_type', 20); // pull | push
            $table->string('package_hash', 64);
            $table->integer('records_count')->default(0);
            $table->string('status', 30)->default('success'); // success | failed | conflict
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_sync_logs');

        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['max_remedial_score', 'sync_node_token']);
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'last_heartbeat_at',
                'ip_address',
                'user_agent',
                'device_fingerprint',
                'blur_count',
            ]);
        });
    }
};
