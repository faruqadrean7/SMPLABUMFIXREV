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
        Schema::table('assets', function (Blueprint $table) {
            $table->string('brand', 100)->nullable()->after('category');
            $table->string('model', 100)->nullable()->after('brand');
            $table->string('serial_number', 100)->nullable()->after('model');
            $table->text('notes')->nullable()->after('acquired_at');
        });

        Schema::table('asset_audits', function (Blueprint $table) {
            $table->uuid('room_id')->nullable()->after('school_id');
            $table->text('notes')->nullable()->after('status');

            $table->foreign('room_id')->references('id')->on('rooms')->onDelete('set null');
        });

        Schema::table('asset_audit_items', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('is_present');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_audit_items', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        Schema::table('asset_audits', function (Blueprint $table) {
            $table->dropForeign(['room_id']);
            $table->dropColumn(['room_id', 'notes']);
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['brand', 'model', 'serial_number', 'notes']);
        });
    }
};
