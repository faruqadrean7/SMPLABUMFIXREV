<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasUuids;

    protected $fillable = [
        'school_id',
        'setting_key',
        'setting_value',
    ];

    protected function casts(): array
    {
        return [
            'setting_value' => 'array',
        ];
    }
}
