<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guest extends Model
{
    use HasUuids;

    protected $table = 'guests';

    protected $fillable = [
        'school_id',
        'target_user_id',
        'full_name',
        'institution',
        'phone',
        'purpose',
        'photo_file_id',
        'signature_file_id',
        'signature_hash',
        'check_in',
        'check_out',
    ];

    protected $casts = [
        'check_in' => 'datetime',
        'check_out' => 'datetime',
    ];

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function personToMeet(): BelongsTo
    {
        return $this->targetUser();
    }

    public function photoFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'photo_file_id');
    }

    public function signatureFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'signature_file_id');
    }

    // Compatibility accessors
    public function getNameAttribute(): string
    {
        return (string) $this->full_name;
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['full_name'] = $value;
    }

    public function getCheckInAtAttribute()
    {
        return $this->check_in;
    }

    public function getCheckOutAtAttribute()
    {
        return $this->check_out;
    }
}
