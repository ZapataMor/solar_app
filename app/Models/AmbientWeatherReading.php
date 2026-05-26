<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmbientWeatherReading extends Model
{
    use HasFactory;

    protected $fillable = [
        'mac_address',
        'recorded_at',
        'raw_payload',
        'temperature',
        'humidity',
        'wind_speed',
        'wind_direction',
        'rainfall',
        'uv_index',
        'solar_radiation',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at'    => 'datetime',
            'raw_payload'    => 'array',
            'temperature'    => 'decimal:2',
            'humidity'       => 'decimal:2',
            'wind_speed'     => 'decimal:3',
            'wind_direction' => 'integer',
            'rainfall'       => 'decimal:3',
            'uv_index'       => 'decimal:3',
            'solar_radiation'=> 'decimal:3',
        ];
    }

    /**
     * Return solar radiation if available, or null.
     * Mirrors WeatherStationReading::radiationValue() for interface consistency.
     */
    public function radiationValue(): ?float
    {
        if ($this->solar_radiation !== null) {
            return (float) $this->solar_radiation;
        }

        // Ambient stations without a pyranometer may still expose uv_index
        return $this->uv_index !== null ? (float) $this->uv_index : null;
    }
}
