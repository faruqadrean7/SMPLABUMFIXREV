<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit dan penambahan CHECK enum bertahap untuk integritas status kolom:
 * - bookings.status
 * - facilities.status
 * - ppdb_waves.status
 * - leave_requests.status
 * - applicants.status
 *
 * Masing-masing pernyataan PostgreSQL dibungkus guard driver agar SQLite aman.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->addCheck('bookings', 'chk_bookings_status', "status IN ('diajukan', 'menunggu', 'pending', 'disetujui', 'approved', 'ditolak', 'rejected', 'selesai', 'completed', 'dibatalkan', 'cancelled')");
        $this->addCheck('facilities', 'chk_facilities_status', "status IN ('tersedia', 'available', 'pemeliharaan', 'maintenance', 'perbaikan', 'rusak', 'damaged', 'nonaktif', 'inactive')");
        $this->addCheck('ppdb_waves', 'chk_ppdb_waves_status', "status IN ('draft', 'aktif', 'active', 'nonaktif', 'inactive', 'selesai', 'completed', 'ditutup', 'closed')");
        $this->addCheck('leave_requests', 'chk_leave_requests_status', "status IN ('menunggu', 'menunggu_persetujuan', 'pending', 'diajukan', 'disetujui', 'approved', 'ditolak', 'rejected', 'dibatalkan', 'cancelled')");
        $this->addCheck('applicants', 'chk_applicants_status', "status IN ('draft', 'submitted', 'verified', 'revision', 'selected', 'waitlist', 'rejected', 'enrolled', 'cancelled', 'withdrawn')");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $this->dropCheck('bookings', 'chk_bookings_status');
        $this->dropCheck('facilities', 'chk_facilities_status');
        $this->dropCheck('ppdb_waves', 'chk_ppdb_waves_status');
        $this->dropCheck('leave_requests', 'chk_leave_requests_status');
        $this->dropCheck('applicants', 'chk_applicants_status');
    }

    private function addCheck(string $table, string $name, string $expr): void
    {
        DB::statement(<<<SQL
        DO \$\$
        BEGIN
          ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name};
          ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expr});
        END \$\$;
        SQL);
    }

    private function dropCheck(string $table, string $name): void
    {
        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
    }
};
