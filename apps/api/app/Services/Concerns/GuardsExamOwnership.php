<?php

namespace App\Services\Concerns;

use App\Models\Exam;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Aturan tunggal siapa yang boleh mengelola/menilai sebuah ujian CBT.
 *
 * - super_admin, admin, principal: semua ujian (pengawas sekolah).
 * - guru & wali kelas: HANYA ujian yang ia buat sendiri.
 *
 * Catatan: kepemilikan sengaja diperiksa SETELAH peran admin. Izin
 * `cbt.exam.manage` saja tidak boleh melewati batas kepemilikan, karena
 * izin itu diberikan juga kepada guru.
 */
trait GuardsExamOwnership
{
    protected function assertCanManage(Exam $exam, ?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        // Peran pengawas/administratif boleh mengelola semua ujian.
        // Yang dibatasi adalah GURU: hanya ujian miliknya sendiri.
        if ($user->hasRole(['super_admin', 'admin', 'admin_tu', 'admin_kurikulum', 'principal'])) {
            return;
        }

        if ((string) $exam->created_by === (string) $user->id) {
            return;
        }

        throw new HttpException(403, 'Anda hanya dapat mengelola ujian CBT yang Anda buat sendiri.');
    }
}
