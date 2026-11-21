<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const ROOM_TYPES = [
        'kelas',
        'laboratorium',
        'aula',
        'perpustakaan',
        'kantor',
        'lapangan',
        'lainnya',
    ];

    protected $fillable = [
        'school_id',
        'code',
        'name',
        'room_type',
        'capacity',
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function examRooms(): HasMany
    {
        return $this->hasMany(ExamRoom::class);
    }

    public function facilities(): HasMany
    {
        return $this->hasMany(Facility::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function labEquipment(): HasMany
    {
        return $this->hasMany(LabEquipment::class);
    }
}
