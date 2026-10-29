<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $table = 'payments';

    protected $fillable = [
        'school_id',
        'invoice_id',
        'amount',
        'channel',
        'external_reference',
        'payment_date',
        'proof_file_id',
        'status',
        'submitted_by',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'receipt_number',
    ];

    protected $casts = [
        'amount' => 'integer',
        'payment_date' => 'date:Y-m-d',
        'verified_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function proofFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'proof_file_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Integrity guard: Pembayaran yang telah terverifikasi tidak boleh diubah/dihapus secara langsung,
     * hanya boleh dibatalkan melalui status void dengan alasan yang tercatat.
     */
    public function isVerified(): bool
    {
        return $this->status === 'terverifikasi';
    }
}
