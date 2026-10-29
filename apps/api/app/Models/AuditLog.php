<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'school_id',
        'actor_id',
        'actor_role',
        'action',
        'entity',
        'entity_type',
        'entity_id',
        'old_value',
        'old_values',
        'new_value',
        'new_values',
        'reason',
        'ip',
        'user_agent',
        'request_id',
        'is_authoritative',
        'created_at',
    ];

    protected $appends = [
        'entity_type',
        'old_values',
        'new_values',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
            'is_authoritative' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function getEntityTypeAttribute(): ?string
    {
        return $this->attributes['entity'] ?? null;
    }

    public function setEntityTypeAttribute(?string $value): void
    {
        $this->attributes['entity'] = $value;
    }

    public function getOldValuesAttribute(): ?array
    {
        $val = $this->attributes['old_value'] ?? null;
        if (is_string($val)) {
            return json_decode($val, true);
        }

        return $val;
    }

    public function setOldValuesAttribute($value): void
    {
        $this->attributes['old_value'] = is_array($value) ? json_encode($value) : $value;
    }

    public function getNewValuesAttribute(): ?array
    {
        $val = $this->attributes['new_value'] ?? null;
        if (is_string($val)) {
            return json_decode($val, true);
        }

        return $val;
    }

    public function setNewValuesAttribute($value): void
    {
        $this->attributes['new_value'] = is_array($value) ? json_encode($value) : $value;
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
