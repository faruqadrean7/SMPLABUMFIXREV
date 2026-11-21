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
        Schema::create('reminder_dispatches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable()->index();
            $table->string('event_type', 50); // cbt_exam, lms_assignment, att_recording, att_alpha
            $table->uuid('event_id');
            $table->uuid('recipient_id');
            $table->string('milestone', 50); // 24h, 1h, daily_cutoff, alpha_alert
            $table->string('channel', 30)->default('in_app'); // in_app, push, wa, email
            $table->string('status', 30)->default('dispatched'); // dispatched, queued, skipped, failed
            $table->timestamp('dispatched_at')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('recipient_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(
                ['event_type', 'event_id', 'recipient_id', 'milestone'],
                'reminder_dispatches_event_recip_ms_unique'
            );
            $table->index(['school_id', 'event_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminder_dispatches');
    }
};
