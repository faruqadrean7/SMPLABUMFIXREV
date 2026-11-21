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
        Schema::table('learning_materials', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->uuid('file_id')->nullable()->after('description');
            $table->decimal('max_score', 5, 2)->default(100.00)->after('due_at');
            $table->uuid('grade_component_id')->nullable()->after('max_score');

            $table->foreign('file_id')->references('id')->on('files')->onDelete('set null');
            $table->foreign('grade_component_id')->references('id')->on('grade_components')->onDelete('set null');
        });

        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('file_id');
            $table->text('feedback')->nullable()->after('score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropColumn(['notes', 'feedback']);
        });

        Schema::table('assignments', function (Blueprint $table) {
            $table->dropForeign(['file_id']);
            $table->dropForeign(['grade_component_id']);
            $table->dropColumn(['description', 'file_id', 'max_score', 'grade_component_id']);
        });

        Schema::table('learning_materials', function (Blueprint $table) {
            $table->dropColumn(['description']);
        });
    }
};
