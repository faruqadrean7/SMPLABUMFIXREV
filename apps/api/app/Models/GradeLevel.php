<?php

namespace App\Models;

use App\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeLevel extends Model
{
    use BelongsToSchool, HasFactory, HasUuids;

    protected $fillable = [
        'school_id',
        'name',
        'sort_order',
    ];

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }
}
