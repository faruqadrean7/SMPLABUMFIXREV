<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Hak akses melihat detail pembayaran & kuitansi.
     */
    public function view(User $user, Payment $payment): bool
    {
        if ($user->hasRole('super_admin') || $user->hasRole('bendahara') || $user->hasPermission('finance.payment.verify') || $user->hasPermission('finance.report.view')) {
            return true;
        }

        // Pengaju pembayaran
        if ($user->id === $payment->submitted_by) {
            return true;
        }

        $invoice = $payment->invoice;
        if (! $invoice) {
            return false;
        }

        // Siswa terkait
        if ($user->student && $user->student->id === $invoice->student_id) {
            return true;
        }

        // Wali murid terkait
        if ($user->guardian) {
            return $user->guardian->students()
                ->where('students.id', $invoice->student_id)
                ->exists();
        }

        return false;
    }

    /**
     * Hak akses verifikasi atau tolak pembayaran.
     */
    public function verify(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->hasRole('bendahara') || $user->hasPermission('finance.payment.verify');
    }

    /**
     * Hak akses void pembayaran yang sudah terverifikasi.
     */
    public function void(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->hasRole('bendahara') || $user->hasPermission('finance.payment.verify');
    }
}
