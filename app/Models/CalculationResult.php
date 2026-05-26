<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculationResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'usable_area_m2',
        'number_of_panels',
        'installed_capacity_kwp',
        'estimated_daily_generation_kwh',
        'estimated_monthly_generation_kwh',
        'estimated_annual_generation_kwh',
        'annual_consumption_kwh',
        'coverage_percentage',
        'estimated_annual_savings_cop',
        'installation_cost_cop',
        'payback_period_years',
    ];

    protected function casts(): array
    {
        return [
            'usable_area_m2' => 'decimal:4',
            'number_of_panels' => 'integer',
            'installed_capacity_kwp' => 'decimal:4',
            'estimated_daily_generation_kwh' => 'decimal:4',
            'estimated_monthly_generation_kwh' => 'decimal:4',
            'estimated_annual_generation_kwh' => 'decimal:4',
            'annual_consumption_kwh' => 'decimal:4',
            'coverage_percentage' => 'decimal:4',
            'estimated_annual_savings_cop' => 'decimal:4',
            'installation_cost_cop' => 'decimal:4',
            'payback_period_years' => 'decimal:4',
        ];
    }

    public function solarProject(): BelongsTo
    {
        return $this->belongsTo(SolarProject::class);
    }
}
