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
        Schema::table('facilities', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('room_id')->index();
            $table->string('location', 150)->nullable()->after('name');
            $table->text('description')->nullable()->after('location');
            $table->boolean('is_active')->default(true)->after('requires_approval');
            $table->string('status', 30)->default('tersedia')->after('is_active'); // tersedia | pemeliharaan | nonaktif
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->uuid('school_id')->nullable()->after('id')->index();
            $table->integer('attendees_count')->nullable()->after('purpose');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['school_id', 'attendees_count']);
        });

        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn(['code', 'location', 'description', 'is_active', 'status']);
        });
    }
};
