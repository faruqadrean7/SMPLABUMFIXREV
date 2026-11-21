<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeachingMaterial extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'description',
        'grade_level_id',
        'subject_id',
        'uploaded_by',
        'file_id',
        'material_type',
        'version',
        'parent_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('version', 'asc');
    }
}
