<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'month_number',
        'month_name',
        'days_in_month',
        'average_daily_solar_radiation',
        'estimated_generation_kwh',
        'estimated_consumption_kwh',
        'coverage_percentage',
        'estimated_savings_cop',
    ];

    protected function casts(): array
    {
        return [
            'month_number' => 'integer',
            'days_in_month' => 'integer',
            'average_daily_solar_radiation' => 'decimal:6',
            'estimated_generation_kwh' => 'decimal:4',
            'estimated_consumption_kwh' => 'decimal:4',
            'coverage_percentage' => 'decimal:4',
            'estimated_savings_cop' => 'decimal:4',
        ];
    }

    public function solarProject(): BelongsTo
    {
        return $this->belongsTo(SolarProject::class);
    }
}
