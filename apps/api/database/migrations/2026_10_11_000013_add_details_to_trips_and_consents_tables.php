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
        Schema::table('trips', function (Blueprint $table) {
            $table->string('destination', 200)->nullable()->after('title');
            $table->date('return_date')->nullable()->after('depart_date');
            $table->text('description')->nullable()->after('route');
            $table->text('cost_notes')->nullable()->after('guide_file_id');
            $table->string('status', 30)->default('direncanakan')->after('cost_notes');
        });

        Schema::table('trip_participants', function (Blueprint $table) {
            $table->string('status', 30)->default('terdaftar')->after('student_id');
            $table->text('notes')->nullable()->after('status');
        });

        Schema::table('trip_consents', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('decision');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_consents', function (Blueprint $table) {
            $table->dropColumn(['notes']);
        });

        Schema::table('trip_participants', function (Blueprint $table) {
            $table->dropColumn(['status', 'notes']);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['destination', 'return_date', 'description', 'cost_notes', 'status']);
        });
    }
};
