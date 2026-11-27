<?php

namespace App\Services\Academic;

use Illuminate\Support\Collection;

/**
 * P0-26: Single Source of Truth agregasi presensi harian.
 * Menggabungkan entri presensi gerbang (`source = 'gerbang'`) dan sesi kelas (`source = 'kelas'`)
 * menjadi tepat 1 status kanonis per tanggal kalender (`att_date`) agar tidak terjadi inflasi hari.
 */
class AttendanceAggregator
{
    /**
     * Prioritas status harian ketika hanya ada catatan sesi kelas (tanpa presensi gerbang).
     * Semakin kecil angka, semakin tinggi prioritas (hadir > terlambat > sakit > izin > alpa).
     *
     * @var array<string, int>
     */
    protected const SESSION_PRIORITY = [
        'hadir' => 1,
        'terlambat' => 2,
        'sakit' => 3,
        'izin' => 4,
        'alpa' => 5,
    ];

    /**
     * Agregasi koleksi Attendance milik 1 siswa menjadi ringkasan harian kanonis.
     *
     * @return array{
     *   hadir: int,
     *   terlambat: int,
     *   sakit: int,
     *   izin: int,
     *   alpa: int,
     *   total_recorded: int,
     *   percentage: float
     * }
     */
    public function summarizeDaily(Collection $attendances, int $percentagePrecision = 2): array
    {
        $byDate = $attendances->groupBy(function ($att) {
            if ($att->att_date instanceof \DateTimeInterface) {
                return $att->att_date->format('Y-m-d');
            }

            return substr((string) $att->att_date, 0, 10);
        });

        $counts = [
            'hadir' => 0,
            'terlambat' => 0,
            'sakit' => 0,
            'izin' => 0,
            'alpa' => 0,
        ];

        foreach ($byDate as $dailyRows) {
            $resolvedStatus = $this->resolveDailyStatus($dailyRows);
            if ($resolvedStatus !== null && array_key_exists($resolvedStatus, $counts)) {
                $counts[$resolvedStatus]++;
            }
        }

        $totalDays = $counts['hadir'] + $counts['terlambat'] + $counts['sakit'] + $counts['izin'] + $counts['alpa'];
        $presentDays = $counts['hadir'] + $counts['terlambat'];

        $percentage = $totalDays > 0
            ? round(($presentDays / $totalDays) * 100, $percentagePrecision)
            : 100.0;

        return [
            'hadir' => $counts['hadir'],
            'terlambat' => $counts['terlambat'],
            'sakit' => $counts['sakit'],
            'izin' => $counts['izin'],
            'alpa' => $counts['alpa'],
            'total_recorded' => $totalDays,
            'percentage' => $percentage,
        ];
    }

    /**
     * Tentukan 1 status kanonis harian untuk 1 tanggal kalender.
     */
    protected function resolveDailyStatus(Collection $dailyRows): ?string
    {
        // 1. Jika ada presensi gerbang pada tanggal ini, gerbang menjadi sumber otoritatif harian
        $gateRows = $dailyRows->filter(fn ($row) => strtolower((string) ($row->source ?? '')) === 'gerbang');
        if ($gateRows->isNotEmpty()) {
            $authoritativeGate = $gateRows->sortByDesc('updated_at')->first();
            $status = strtolower((string) ($authoritativeGate->status ?? ''));

            return array_key_exists($status, self::SESSION_PRIORITY) ? $status : null;
        }

        // 2. Jika hanya ada catatan sesi kelas/manual, ambil status dengan prioritas tertinggi (maks 1 per hari)
        $bestStatus = null;
        $bestRank = PHP_INT_MAX;

        foreach ($dailyRows as $row) {
            $status = strtolower((string) ($row->status ?? ''));
            $rank = self::SESSION_PRIORITY[$status] ?? null;
            if ($rank !== null && $rank < $bestRank) {
                $bestRank = $rank;
                $bestStatus = $status;
            }
        }

        return $bestStatus;
    }
}
