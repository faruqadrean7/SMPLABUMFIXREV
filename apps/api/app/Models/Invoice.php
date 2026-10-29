<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $table = 'invoices';

    protected $fillable = [
        'school_id',
        'invoice_number',
        'student_id',
        'academic_year_id',
        'period',
        'va_number',
        'due_date',
        'total_amount',
        'paid_amount',
        'status',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'total_amount' => 'integer',
        'paid_amount' => 'integer',
    ];

    protected $appends = [
        'remaining_amount',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }

    public function getRemainingAmountAttribute(): int
    {
        return max(0, (int) $this->total_amount - (int) $this->paid_amount);
    }

    /**
     * Hitung ulang akumulasi nominal terbayar dari pembayaran yang berstatus terverifikasi.
     * Perbarui status: belum_bayar | sebagian | lunas (kecuali status telah dibatalkan).
     */
    public function recalculatePaidAmountAndStatus(): void
    {
        $verifiedSum = (int) $this->payments()
            ->where('status', 'terverifikasi')
            ->sum('amount');

        $this->paid_amount = $verifiedSum;

        if ($this->status !== 'dibatalkan') {
            if ($verifiedSum >= $this->total_amount && $this->total_amount > 0) {
                $this->status = 'lunas';
            } elseif ($verifiedSum > 0) {
                $this->status = 'sebagian';
            } else {
                $this->status = 'belum_bayar';
            }
        }

        $this->save();
    }
}
