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
        Schema::table('counseling_requests', function (Blueprint $table) {
            $table->string('requester_role', 50)->nullable()->after('requested_by');
            $table->date('preferred_date')->nullable()->after('topic');
            $table->string('preferred_time', 50)->nullable()->after('preferred_date');
            $table->uuid('preferred_counselor_id')->nullable()->after('preferred_time');
            $table->timestamp('scheduled_at')->nullable()->after('status');
            $table->uuid('counselor_id')->nullable()->after('scheduled_at');
            $table->text('notes')->nullable()->after('counselor_id');
            $table->text('rejection_reason')->nullable()->after('notes');

            $table->foreign('counselor_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('counseling_requests', function (Blueprint $table) {
            $table->dropForeign(['counselor_id']);
            $table->dropColumn([
                'requester_role',
                'preferred_date',
                'preferred_time',
                'preferred_counselor_id',
                'scheduled_at',
                'counselor_id',
                'notes',
                'rejection_reason',
            ]);
        });
    }
};
