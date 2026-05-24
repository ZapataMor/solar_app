<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiWeatherData extends Model
{
    use HasFactory;

    protected $table = 'api_weather_data';

    protected $fillable = [
        'date_time',
        'allsky_sfc_sw_dwn',
        't2m',
        'rh2m',
        'prectotcorr',
        'ws10m',
    ];

    protected function casts(): array
    {
        return [
            'date_time' => 'datetime',
            'allsky_sfc_sw_dwn' => 'decimal:4',
            't2m' => 'decimal:3',
            'rh2m' => 'decimal:3',
            'prectotcorr' => 'decimal:4',
            'ws10m' => 'decimal:3',
        ];
    }
}
