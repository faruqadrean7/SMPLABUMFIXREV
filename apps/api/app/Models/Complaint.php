<?php

namespace App\Models;

use App\Casts\SafeEncryptedCast;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Complaint extends Model
{
    use HasUuids;

    protected $table = 'complaints';

    protected $fillable = [
        'school_id',
        'reporter_user_id',
        'ticket_code',
        'category',
        'severity',
        'content',
        'is_anonymous',
        'status',
        'assigned_to',
        'due_at',
        'first_responded_at',
        'escalated_at',
    ];

    protected $casts = [
        'content' => SafeEncryptedCast::class,
        'is_anonymous' => 'boolean',
        'due_at' => 'datetime',
        'first_responded_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    protected $appends = ['is_overdue', 'sla_state'];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ComplaintEvent::class, 'complaint_id')->orderBy('created_at', 'asc');
    }

    /**
     * BE-GUEST-04: Lampiran foto pengaduan di bucket privat.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(File::class, 'owner_id')->where('owner_type', 'complaint');
    }

    /**
     * Triase: aduan lewat tenggat dan belum ditutup dianggap terlambat.
     */
    public function getIsOverdueAttribute(): bool
    {
        if (! $this->due_at || in_array($this->status, ['selesai', 'ditolak'], true)) {
            return false;
        }

        return $this->due_at->isPast();
    }

    /**
     * sla_state: 'selesai' | 'tepat_waktu' | 'mendekati' | 'terlambat' | 'terlambat_berat'
     * Dipakai UI untuk memberi warna badge tanpa menghitung ulang di klien.
     */
    public function getSlaStateAttribute(): string
    {
        if (in_array($this->status, ['selesai', 'ditolak'], true)) {
            return 'selesai';
        }

        if (! $this->due_at) {
            return 'tepat_waktu';
        }

        $hoursLeft = now()->diffInHours($this->due_at, false);

        if ($hoursLeft < 0) {
            return $this->escalated_at ? 'terlambat_berat' : 'terlambat';
        }

        return $hoursLeft <= 6 ? 'mendekati' : 'tepat_waktu';
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->whereNotIn('status', ['selesai', 'ditolak']);
    }

    // Compatibility accessors
    public function getTicketNumberAttribute(): string
    {
        return (string) $this->ticket_code;
    }

    public function setTicketNumberAttribute($value): void
    {
        $this->attributes['ticket_code'] = $value;
    }

    public function getDescriptionAttribute(): string
    {
        return (string) $this->content;
    }

    public function setDescriptionAttribute($value): void
    {
        $this->attributes['content'] = $value;
    }
}
