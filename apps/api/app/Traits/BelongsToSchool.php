<?php

namespace App\Traits;

use App\Models\School;
use App\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToSchool
{
    /**
     * Boot the BelongsToSchool trait for a model.
     */
    public static function bootBelongsToSchool(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function (Model $model) {
            if (empty($model->school_id)) {
                if (app()->has('tenant.school_id')) {
                    $model->school_id = app('tenant.school_id');
                } elseif (auth()->check() && ! empty(auth()->user()->school_id)) {
                    $model->school_id = auth()->user()->school_id;
                }
            }
        });
    }

    /**
     * Relasi ke data sekolah (tenant).
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id');
    }

    /**
     * Scope builder untuk mengabaikan batasan sekolah (mis. untuk pelaporan gabungan).
     */
    public function scopeWithoutSchoolScope(Builder $builder): Builder
    {
        return $builder->withoutGlobalScope(SchoolScope::class);
    }
}
