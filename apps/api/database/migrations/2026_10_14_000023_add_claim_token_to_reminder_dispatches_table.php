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
        Schema::table('reminder_dispatches', function (Blueprint $table) {
            $table->string('claim_token', 64)->nullable()->index()->after('status');
            $table->timestamp('claim_expires_at')->nullable()->index()->after('claim_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reminder_dispatches', function (Blueprint $table) {
            $table->dropIndex(['claim_token']);
            $table->dropIndex(['claim_expires_at']);
            $table->dropColumn(['claim_token', 'claim_expires_at']);
        });
    }
};
