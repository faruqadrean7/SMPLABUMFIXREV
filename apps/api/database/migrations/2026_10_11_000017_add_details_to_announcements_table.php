<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->uuid('attachment_file_id')->nullable()->after('target_ids');
            $table->string('priority', 20)->default('normal')->after('attachment_file_id'); // low | normal | high | urgent
            $table->boolean('is_pinned')->default(false)->after('priority')->index();
            $table->timestamp('expires_at')->nullable()->after('publish_at')->index();

            $table->foreign('attachment_file_id')->references('id')->on('files')->onDelete('set null');
            $table->index(['target_type', 'publish_at']);
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropForeign(['attachment_file_id']);
            $table->dropIndex(['target_type', 'publish_at']);
            $table->dropIndex(['is_pinned']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['attachment_file_id', 'priority', 'is_pinned', 'expires_at']);
        });
    }
};
