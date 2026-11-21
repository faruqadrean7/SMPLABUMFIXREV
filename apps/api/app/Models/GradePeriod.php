<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradePeriod extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $table = 'grade_periods';

    protected $fillable = [
        'semester_id',
        'month',
        'status',
        'locked_by',
        'locked_at',
    ];

    protected $appends = ['month_name'];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'locked_at' => 'datetime',
        ];
    }

    public function getMonthNameAttribute(): string
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return $months[$this->month] ?? "Bulan {$this->month}";
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'period_id');
    }

    public function characterNotes(): HasMany
    {
        return $this->hasMany(CharacterNote::class, 'period_id');
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(ProgressReport::class, 'period_id');
    }
}
