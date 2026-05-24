<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeatherStationReading extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_code',
        'latitude',
        'longitude',
        'temperature',
        'humidity',
        'thermal_sensation',
        'co2',
        'pm25',
        'pm10',
        'uva',
        'uvb',
        'uv_index',
        'solar_radiation',
        'measured_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'temperature' => 'decimal:2',
            'humidity' => 'decimal:2',
            'thermal_sensation' => 'decimal:2',
            'pm25' => 'decimal:2',
            'pm10' => 'decimal:2',
            'uva' => 'decimal:3',
            'uvb' => 'decimal:3',
            'uv_index' => 'decimal:3',
            'solar_radiation' => 'decimal:3',
            'measured_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }
    public function radiationValue(): ?float
    {
        if ($this->solar_radiation !== null) {
            return (float) $this->solar_radiation;
        }

        if ($this->uva === null && $this->uvb === null && $this->uv_index === null) {
            return null;
        }

        return (
            (float) ($this->uva ?? 0)
            + (float) ($this->uvb ?? 0)
            + (float) ($this->uv_index ?? 0)
        ) / 3;
    }
}
