<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SolarProject extends Model
{
    use HasFactory;

    public const LOCATION_NAME = 'Riohacha, La Guajira, Colombia';
    public const LATITUDE = 11.5444;
    public const LONGITUDE = -72.9072;

    protected $fillable = [
        'name',
        'description',
        'start_date',
        'end_date',
        'annual_consumption_kwh',
        'energy_rate_cop_kwh',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'latitude' => 'decimal:4',
            'longitude' => 'decimal:4',
            'annual_consumption_kwh' => 'decimal:2',
            'energy_rate_cop_kwh' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function technicalParameter(): HasOne
    {
        return $this->hasOne(TechnicalParameter::class);
    }

    public function weatherData(): HasMany
    {
        return $this->hasMany(ApiWeatherData::class);
    }
}
