<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProgramProfile extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $table = 'student_program_profiles';

    protected $fillable = [
        'student_id',
        'program_type',
        'meta_data',
    ];

    protected function casts(): array
    {
        return [
            'meta_data' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
