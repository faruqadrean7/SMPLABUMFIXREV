<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_program_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id')->unique();
            $table->string('program_type', 30)->default('reguler')->index();
            $table->jsonb('meta_data')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
            DO \$\$
            BEGIN
                ALTER TABLE student_program_profiles DROP CONSTRAINT IF EXISTS chk_program_profiles_type;
                ALTER TABLE student_program_profiles ADD CONSTRAINT chk_program_profiles_type
                    CHECK (program_type IN ('reguler', 'tahfidz', 'icp', 'inklusi'));
            END \$\$;
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_program_profiles');
    }
};
