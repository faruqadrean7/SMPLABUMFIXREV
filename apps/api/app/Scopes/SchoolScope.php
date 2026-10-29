<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class SchoolScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // 1. Cek konteks tenant yang diset secara eksplisit di aplikasi / middleware
        if (app()->has('tenant.school_id')) {
            $schoolId = app('tenant.school_id');
            if (! empty($schoolId)) {
                $builder->where($model->getTable().'.school_id', $schoolId);
            }

            return;
        }

        // 2. Cek konteks pengguna terautentikasi (Sanctum / actingAs)
        if (auth()->check()) {
            $user = auth()->user();

            // Super admin memiliki visibilitas global lintas sekolah
            if ($user->hasRole('super_admin')) {
                return;
            }

            if (! empty($user->school_id)) {
                $builder->where($model->getTable().'.school_id', $user->school_id);
            }

            return;
        }

        // Abaikan pembatasan saat berjalan di lingkungan CLI/Console tanpa user & tenant
        if (app()->runningInConsole()) {
            return;
        }
    }
}
