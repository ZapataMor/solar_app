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
        'monthly_consumption_kwh',
        'daily_consumption_kwh',
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
            'monthly_consumption_kwh' => 'decimal:2',
            'daily_consumption_kwh' => 'decimal:2',
            'annual_consumption_kwh' => 'decimal:2',
            'energy_rate_cop_kwh' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $solarProject): void {
            $solarProject->syncConsumptionScales();
        });
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

    public function apiWeatherData(): HasMany
    {
        return $this->hasMany(ApiWeatherData::class);
    }

    public function weatherStationReadings(): HasMany
    {
        return $this->hasMany(WeatherStationReading::class);
    }

    public function calculationResult(): HasOne
    {
        return $this->hasOne(CalculationResult::class);
    }

    public function monthlyResults(): HasMany
    {
        return $this->hasMany(MonthlyResult::class);
    }

    public function syncConsumptionScales(): void
    {
        $monthlyConsumption = $this->numericConsumption($this->monthly_consumption_kwh);
        $dailyConsumption = $this->numericConsumption($this->daily_consumption_kwh);
        $annualConsumption = $this->numericConsumption($this->annual_consumption_kwh);

        if ($monthlyConsumption !== null) {
            $annualConsumption = $monthlyConsumption * 12;
            $dailyConsumption = $monthlyConsumption / 30;
        } elseif ($annualConsumption !== null) {
            $monthlyConsumption = $annualConsumption / 12;
            $dailyConsumption = $monthlyConsumption / 30;
        } elseif ($dailyConsumption !== null) {
            $monthlyConsumption = $dailyConsumption * 30;
            $annualConsumption = $monthlyConsumption * 12;
        }

        $this->monthly_consumption_kwh = $monthlyConsumption;
        $this->daily_consumption_kwh = $dailyConsumption;
        $this->annual_consumption_kwh = $annualConsumption;
    }

    public function monthlyConsumption(): float
    {
        $monthlyConsumption = $this->numericConsumption($this->monthly_consumption_kwh);

        if ($monthlyConsumption !== null) {
            return $monthlyConsumption;
        }

        $annualConsumption = $this->numericConsumption($this->annual_consumption_kwh);

        if ($annualConsumption !== null) {
            return $annualConsumption / 12;
        }

        $dailyConsumption = $this->numericConsumption($this->daily_consumption_kwh);

        return $dailyConsumption !== null ? $dailyConsumption * 30 : 0.0;
    }

    public function dailyConsumption(): float
    {
        $dailyConsumption = $this->numericConsumption($this->daily_consumption_kwh);

        return $dailyConsumption !== null ? $dailyConsumption : $this->monthlyConsumption() / 30;
    }

    public function annualConsumption(): float
    {
        return $this->monthlyConsumption() * 12;
    }

    private function numericConsumption(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) $value;

        return $number > 0 ? $number : null;
    }
}
