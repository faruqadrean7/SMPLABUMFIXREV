<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1-41 & P1-24: Pelebaran non-destruktif kolom ciphertext NIK dan token perangkat ke TEXT.
 * Pada PostgreSQL, ALTER COLUMN TYPE TEXT adalah operasi metadata-only tanpa rewrite tabel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE students ALTER COLUMN nik_enc TYPE TEXT');
            DB::statement('ALTER TABLE applicants ALTER COLUMN nik_enc TYPE TEXT');
            DB::statement('ALTER TABLE devices ALTER COLUMN fcm_token TYPE TEXT');

            return;
        }

        Schema::table('students', function (Blueprint $table) {
            $table->text('nik_enc')->change();
        });
        Schema::table('applicants', function (Blueprint $table) {
            $table->text('nik_enc')->change();
        });
        Schema::table('devices', function (Blueprint $table) {
            $table->text('fcm_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $tooLongStudentNik = DB::table('students')->whereRaw('LENGTH(nik_enc) > 255')->exists();
            $tooLongApplicantNik = DB::table('applicants')->whereRaw('LENGTH(nik_enc) > 255')->exists();
            $tooLongFcmToken = DB::table('devices')->whereNotNull('fcm_token')->whereRaw('LENGTH(fcm_token) > 500')->exists();

            if (! $tooLongStudentNik) {
                DB::statement('ALTER TABLE students ALTER COLUMN nik_enc TYPE VARCHAR(255)');
            }
            if (! $tooLongApplicantNik) {
                DB::statement('ALTER TABLE applicants ALTER COLUMN nik_enc TYPE VARCHAR(255)');
            }
            if (! $tooLongFcmToken) {
                DB::statement('ALTER TABLE devices ALTER COLUMN fcm_token TYPE VARCHAR(500)');
            }

            return;
        }

        Schema::table('students', function (Blueprint $table) {
            $table->string('nik_enc', 255)->change();
        });
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('nik_enc', 255)->change();
        });
        Schema::table('devices', function (Blueprint $table) {
            $table->string('fcm_token', 500)->nullable()->change();
        });
    }
};
