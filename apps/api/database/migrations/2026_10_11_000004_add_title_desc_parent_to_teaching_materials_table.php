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
        Schema::table('teaching_materials', function (Blueprint $table) {
            $table->string('title', 200)->default('Perangkat Ajar')->after('subject_id');
            $table->text('description')->nullable()->after('title');
            $table->uuid('parent_id')->nullable()->after('version');

            $table->foreign('parent_id')->references('id')->on('teaching_materials')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teaching_materials', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'description', 'title']);
        });
    }
};
