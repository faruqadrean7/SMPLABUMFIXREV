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
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('actor_role', 100)->nullable()->after('actor_id');
            $table->text('reason')->nullable()->after('new_value');
            $table->string('request_id', 100)->nullable()->after('user_agent');
            $table->boolean('is_authoritative')->default(true)->after('request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['actor_role', 'reason', 'request_id', 'is_authoritative']);
        });
    }
};
