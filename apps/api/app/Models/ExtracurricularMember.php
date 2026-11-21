<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtracurricularMember extends Model
{
    use HasUuids;

    protected $table = 'extracurricular_members';

    protected $fillable = [
        'extracurricular_id',
        'student_id',
        'role',
        'status',
        'final_grade',
        'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'date',
    ];

    public function extracurricular(): BelongsTo
    {
        return $this->belongsTo(Extracurricular::class, 'extracurricular_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
