<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{
    protected $fillable = [
        'name',
        'department',
        'latitude',
        'longitude',
        'zone',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function solarPrices(): HasMany
    {
        return $this->hasMany(MunicipalitySolarPrice::class);
    }

    public function solarProjects(): HasMany
    {
        return $this->hasMany(SolarProject::class);
    }
}
