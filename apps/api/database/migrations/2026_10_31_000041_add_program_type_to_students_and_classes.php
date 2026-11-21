<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('program_type', 30)->default('reguler')->after('status')->index();
        });

        Schema::table('classes', function (Blueprint $table) {
            $table->string('program_type', 30)->default('reguler')->after('name')->index();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
            DO \$\$
            BEGIN
                ALTER TABLE students DROP CONSTRAINT IF EXISTS chk_students_program_type;
                ALTER TABLE students ADD CONSTRAINT chk_students_program_type
                    CHECK (program_type IN ('reguler', 'tahfidz', 'icp', 'inklusi'));

                ALTER TABLE classes DROP CONSTRAINT IF EXISTS chk_classes_program_type;
                ALTER TABLE classes ADD CONSTRAINT chk_classes_program_type
                    CHECK (program_type IN ('reguler', 'tahfidz', 'icp', 'inklusi'));
            END \$\$;
SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<SQL
            DO \$\$
            BEGIN
                ALTER TABLE students DROP CONSTRAINT IF EXISTS chk_students_program_type;
                ALTER TABLE classes DROP CONSTRAINT IF EXISTS chk_classes_program_type;
            END \$\$;
SQL);
        }

        Schema::table('classes', function (Blueprint $table) {
            $table->dropIndex(['program_type']);
            $table->dropColumn('program_type');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['program_type']);
            $table->dropColumn('program_type');
        });
    }
};
