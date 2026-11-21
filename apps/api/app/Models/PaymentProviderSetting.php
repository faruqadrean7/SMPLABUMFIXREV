<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentProviderSetting extends Model
{
    use Auditable, HasFactory, HasUuids;

    protected $table = 'payment_provider_settings';

    protected $fillable = [
        'school_id',
        'name',
        'instructions',
        'info_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
