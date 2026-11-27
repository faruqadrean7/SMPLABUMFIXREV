<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P0-23: Tambah tenggat daftar ulang per pendaftar (nullable, non-destruktif)
     * agar kandidat cadangan (waitlist) yang dipromosikan memperoleh jendela 3 hari kerja sendiri.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('applicants', 're_registration_deadline_at')) {
            Schema::table('applicants', function (Blueprint $table) {
                $table->timestamp('re_registration_deadline_at')->nullable()->after('decision');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('applicants', 're_registration_deadline_at')) {
            Schema::table('applicants', function (Blueprint $table) {
                $table->dropColumn('re_registration_deadline_at');
            });
        }
    }
};
