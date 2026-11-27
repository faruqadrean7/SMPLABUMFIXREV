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
    protected function isCbtAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin', 'admin_tu', 'admin_kurikulum', 'principal', 'cbt_operator']);
    }

    protected function assertExamSchoolAccess(Exam $exam, ?User $user): void
    {
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        if ($user->hasRole('super_admin')) {
            return;
        }

        if (! empty($user->school_id) && ! empty($exam->school_id) && (string) $exam->school_id !== (string) $user->school_id) {
            throw new HttpException(403, 'Akses ditolak: Ujian CBT berada di luar sekolah penugasan Anda.');
        }
    }

    protected function canManageExam(Exam $exam, ?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if (! $user->hasRole('super_admin') && ! empty($user->school_id) && ! empty($exam->school_id) && (string) $exam->school_id !== (string) $user->school_id) {
            return false;
        }

        if ($this->isCbtAdmin($user)) {
            return true;
        }

        return $exam->created_by !== null && (string) $exam->created_by === (string) $user->id;
    }

    protected function assertCanManage(Exam $exam, ?User $user): void
    {
        $this->assertExamSchoolAccess($exam, $user);

        if ($this->canManageExam($exam, $user)) {
            return;
        }

        throw new HttpException(403, 'Anda hanya dapat mengelola ujian CBT yang Anda buat sendiri.');
    }
}
