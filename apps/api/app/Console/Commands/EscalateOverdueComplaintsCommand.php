<?php

namespace App\Console\Commands;

use App\Services\ComplaintService;
use Illuminate\Console\Command;

/**
 * Eskalasi aduan yang melewati tenggat penanganan.
 *
 * Tanpa command ini, laporan perundungan bisa duduk di status "baru" berhari-hari
 * tanpa ada yang tahu. Setiap aduan yang lewat tenggat diberi tanda, dicatat di
 * timeline pelapor, dan diteruskan ke Kepala Sekolah + Guru BK.
 */
class EscalateOverdueComplaintsCommand extends Command
{
    protected $signature = 'complaints:escalate-overdue';

    protected $description = 'Tandai dan eskalasikan pengaduan yang melewati tenggat penanganan';

    public function handle(ComplaintService $service): int
    {
        $count = $service->escalateOverdue();

        if ($count === 0) {
            $this->info('Tidak ada pengaduan yang melewati tenggat.');

            return self::SUCCESS;
        }

        $this->warn("{$count} pengaduan dieskalasikan ke Kepala Sekolah dan Guru BK.");

        return self::SUCCESS;
    }
}
