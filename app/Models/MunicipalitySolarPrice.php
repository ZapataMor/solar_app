<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MunicipalitySolarPrice extends Model
{
    public const LOCATION_TYPES = [
        'urbana',
        'rural',
        'rural_dispersa',
        'alta_guajira',
    ];

    protected $fillable = [
        'municipality_id',
        'zone_name',
        'location_type',
        'base_price_per_kw',
        'logistic_factor',
        'min_price_per_kw',
        'max_price_per_kw',
        'notes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'base_price_per_kw' => 'decimal:2',
            'logistic_factor' => 'decimal:3',
            'min_price_per_kw' => 'decimal:2',
            'max_price_per_kw' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }
}
