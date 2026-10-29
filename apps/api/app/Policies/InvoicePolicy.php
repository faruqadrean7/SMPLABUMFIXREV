<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Hak akses melihat detail tagihan.
     * Admin/Bendahara, Siswa pemilik tagihan, atau Wali Murid yang sah.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->hasRole('super_admin') || $user->hasRole('bendahara') || $user->hasPermission('finance.invoice.manage') || $user->hasPermission('finance.report.view')) {
            return true;
        }

        // Siswa pemilik tagihan
        if ($user->student && $user->student->id === $invoice->student_id) {
            return true;
        }

        // Wali murid dari siswa terkait
        if ($user->guardian) {
            return $user->guardian->students()
                ->where('students.id', $invoice->student_id)
                ->exists();
        }

        return false;
    }

    /**
     * Hak akses membuat tagihan.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('super_admin') || $user->hasRole('bendahara') || $user->hasPermission('finance.invoice.manage');
    }

    /**
     * Hak akses membatalkan tagihan.
     */
    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->hasRole('super_admin') || $user->hasRole('bendahara') || $user->hasPermission('finance.invoice.manage');
    }

    /**
     * Hak akses mengajukan pembayaran untuk tagihan.
     * Wali murid dari siswa, atau admin/bendahara.
     */
    public function pay(User $user, Invoice $invoice): bool
    {
        if ($user->hasRole('super_admin') || $user->hasRole('bendahara') || $user->hasPermission('finance.invoice.manage')) {
            return true;
        }

        if ($user->guardian) {
            return $user->guardian->students()
                ->where('students.id', $invoice->student_id)
                ->exists();
        }

        return false;
    }
}
