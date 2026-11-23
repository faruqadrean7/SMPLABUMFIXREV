<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\GradeComponent;
use Illuminate\Console\Command;

/**
 * Penanganan DATA LAMA: tugas yang dibuat sebelum komponen nilai diwajibkan.
 *
 * Tidak menghapus apa pun. Tanpa --apply hanya melaporkan; dengan --apply
 * menautkan otomatis ke komponen yang cocok (default: kode TGS) pada
 * kelas & mapel yang sama.
 *
 *   php artisan assignments:link-component
 *   php artisan assignments:link-component --apply
 *   php artisan assignments:link-component --assignment=<uuid> --component=<uuid>
 */
class LinkAssignmentGradeComponent extends Command
{
    protected $signature = 'assignments:link-component
        {--apply : Tautkan otomatis (default hanya laporan)}
        {--assignment= : ID tugas tertentu}
        {--component= : ID komponen tertentu (dipakai bersama --assignment)}
        {--code=TGS : Kode komponen yang dipakai saat --apply}';

    protected $description = 'Laporkan / tautkan tugas lama yang belum punya komponen buku nilai';

    public function handle(): int
    {
        // Mode satu tugas eksplisit
        if ($this->option('assignment')) {
            return $this->linkOne((string) $this->option('assignment'), $this->option('component'));
        }

        $rows = Assignment::with(['class:id,name', 'subject:id,name,code'])
            ->whereNull('grade_component_id')
            ->orderBy('due_at')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Tidak ada tugas tanpa komponen nilai. Semua tugas sudah tertaut.');

            return self::SUCCESS;
        }

        $this->warn("Ditemukan {$rows->count()} tugas tanpa komponen nilai (data TIDAK dihapus):");
        $this->table(
            ['ID', 'Judul', 'Kelas', 'Mapel', 'Jatuh tempo'],
            $rows->map(fn ($a) => [
                substr($a->id, 0, 8),
                mb_strimwidth($a->title, 0, 34, '…'),
                $a->class?->name ?? '-',
                $a->subject?->code ?? '-',
                $a->due_at?->format('d/m/Y') ?? '-',
            ])->all()
        );

        if (! $this->option('apply')) {
            $this->line('Jalankan ulang dengan --apply untuk menautkan otomatis ke komponen '.$this->option('code').'.');

            return self::SUCCESS;
        }

        $berhasil = 0;
        $gagal = [];

        foreach ($rows as $assignment) {
            $component = GradeComponent::where('class_id', $assignment->class_id)
                ->where('subject_id', $assignment->subject_id)
                ->where('code', $this->option('code'))
                ->first();

            if (! $component) {
                $gagal[] = $assignment->id;

                continue;
            }

            $assignment->grade_component_id = $component->id;
            $assignment->save();
            $berhasil++;
        }

        $this->info("Ditautkan: {$berhasil} tugas.");

        if ($gagal) {
            $this->warn('Tidak bisa ditautkan (komponen '.$this->option('code').' tidak ada): '.count($gagal).' tugas.');
            $this->line('  '.implode(', ', array_map(fn ($id) => substr($id, 0, 8), $gagal)));
        }

        return self::SUCCESS;
    }

    private function linkOne(string $assignmentId, ?string $componentId): int
    {
        $assignment = Assignment::find($assignmentId);
        if (! $assignment) {
            $this->error('Tugas tidak ditemukan.');

            return self::FAILURE;
        }

        $component = $componentId
            ? GradeComponent::find($componentId)
            : GradeComponent::where('class_id', $assignment->class_id)
                ->where('subject_id', $assignment->subject_id)
                ->where('code', $this->option('code'))
                ->first();

        if (! $component) {
            $this->error('Komponen nilai tidak ditemukan.');

            return self::FAILURE;
        }

        if ($component->class_id !== $assignment->class_id || $component->subject_id !== $assignment->subject_id) {
            $this->error('Komponen bukan milik kelas/mapel tugas ini.');

            return self::FAILURE;
        }

        $assignment->grade_component_id = $component->id;
        $assignment->save();

        $this->info("Tugas '{$assignment->title}' ditautkan ke komponen {$component->name}.");

        return self::SUCCESS;
    }
}
