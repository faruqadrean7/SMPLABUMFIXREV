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
        Schema::table('alumni', function (Blueprint $table) {
            $table->string('current_status', 50)->default('kuliah')->after('graduation_year');
            $table->string('study_program', 150)->nullable()->after('next_institution');
            $table->string('company_name', 150)->nullable()->after('occupation');
            $table->string('phone', 50)->nullable()->after('contact');
            $table->string('email', 100)->nullable()->after('phone');
            $table->string('linkedin_url', 200)->nullable()->after('email');
            $table->text('address')->nullable()->after('linkedin_url');
            $table->boolean('is_public')->default(false)->after('address');
        });

        Schema::table('tracer_surveys', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->integer('target_graduation_year')->nullable()->after('description');
            $table->date('start_date')->nullable()->after('target_graduation_year');
            $table->date('end_date')->nullable()->after('start_date');
            $table->boolean('is_active')->default(true)->after('end_date');
            $table->uuid('created_by')->nullable()->after('is_active');

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::table('tracer_responses', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('answers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tracer_responses', function (Blueprint $table) {
            $table->dropColumn(['submitted_at']);
        });

        Schema::table('tracer_surveys', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn([
                'description',
                'target_graduation_year',
                'start_date',
                'end_date',
                'is_active',
                'created_by',
            ]);
        });

        Schema::table('alumni', function (Blueprint $table) {
            $table->dropColumn([
                'current_status',
                'study_program',
                'company_name',
                'phone',
                'email',
                'linkedin_url',
                'address',
                'is_public',
            ]);
        });
    }
};
