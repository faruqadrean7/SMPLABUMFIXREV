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
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->string('name', 200)->nullable()->after('code');
            $table->string('subject', 255)->nullable()->after('channel');
            $table->jsonb('variables')->nullable()->after('template_text');
            $table->boolean('is_active')->default(true)->after('variables');
        });

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->string('recipient', 255)->nullable()->after('channel');
            $table->string('subject', 255)->nullable()->after('recipient');
            $table->text('body')->nullable()->after('subject');
            $table->jsonb('payload')->nullable()->after('body');
            $table->integer('retry_count')->default(0)->after('status');
            $table->timestamp('sent_at')->nullable()->after('error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropColumn([
                'recipient',
                'subject',
                'body',
                'payload',
                'retry_count',
                'sent_at',
            ]);
        });

        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'subject',
                'variables',
                'is_active',
            ]);
        });
    }
};
