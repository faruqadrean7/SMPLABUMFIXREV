<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasNikBlindIndex;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Applicant extends Model
{
    use Auditable, HasFactory, HasNikBlindIndex, HasUuids;

    protected $fillable = [
        'wave_id',
        'track_id',
        'user_id',
        'student_id',
        'registration_no',
        'nik_enc',
        'nik_hash',
        'full_name',
        'birth_date',
        'status',
        'score',
        'rank',
        'decision',
        'step_data',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'score' => 'decimal:2',
            'step_data' => 'array',
            'nik_enc' => 'encrypted',
        ];
    }

    public function wave(): BelongsTo
    {
        return $this->belongsTo(PPDBWave::class, 'wave_id');
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(PPDBTrack::class, 'track_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function convertedStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function parents(): HasMany
    {
        return $this->hasMany(ApplicantParent::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicantDocument::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ApplicantStatusHistory::class);
    }

    public function reRegistration(): HasOne
    {
        return $this->hasOne(PPDBReRegistration::class);
    }
}
