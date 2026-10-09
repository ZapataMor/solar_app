<?php

namespace App\Models;

use App\Domain\Consumption\ConsumptionMode;
use App\Domain\Reference\EnergyTariff;
use App\Domain\Reference\ReferenceValueCatalog;
use App\Domain\Reference\ReferenceValues;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'property_type',
        'consumption_mode',
        'location_name',
        'start_date',
        'end_date',
        'municipality_id',
        'latitude',
        'longitude',
        'location_type',
        'required_power_kw',
        'base_price_per_kw',
        'logistic_factor_used',
        'final_price_per_kw_used',
        'estimated_installation_cost',
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
            'required_power_kw' => 'decimal:2',
            'base_price_per_kw' => 'decimal:2',
            'logistic_factor_used' => 'decimal:3',
            'final_price_per_kw_used' => 'decimal:2',
            'estimated_installation_cost' => 'decimal:2',
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

    public function weatherData(): Builder
    {
        if ($this->start_date === null || $this->end_date === null) {
            return ApiWeatherData::query()->whereRaw('1 = 0');
        }

        return ApiWeatherData::query()
            ->whereBetween('date_time', [
                $this->start_date->copy()->startOfDay(),
                $this->end_date->copy()->endOfDay(),
            ])
            ->orderBy('date_time');
    }

    public function weatherStationReadings(): Builder
    {
        if ($this->start_date === null || $this->end_date === null) {
            return WeatherStationReading::query()->whereRaw('1 = 0');
        }

        return WeatherStationReading::query()
            ->whereBetween('measured_at', [
                $this->start_date->copy()->startOfDay(),
                $this->end_date->copy()->endOfDay(),
            ])
            ->orderBy('measured_at');
    }

    public function calculationResult(): HasOne
    {
        return $this->hasOne(CalculationResult::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function monthlyResults(): HasMany
    {
        return $this->hasMany(MonthlyResult::class);
    }

    public function appliances(): HasMany
    {
        return $this->hasMany(SolarProjectAppliance::class);
    }

    public function aiMessages(): HasMany
    {
        return $this->hasMany(SolarProjectAiMessage::class);
    }

    /** Installers this project asked for a quote (ADR-0022). */
    public function quoteRequests(): HasMany
    {
        return $this->hasMany(QuoteRequest::class);
    }

    /**
     * Cuántos instaladores ya respondieron con un precio. Con dos o más hay algo que comparar
     * (ADR-0028), y por eso lo pregunta la navegación del proyecto.
     */
    public function receivedQuotes(): int
    {
        return $this->quoteRequests()->whereHas('installerQuote')->count();
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

        // No consumption yet (a new project before its appliances): 0, never null (NOT NULL column).
        $this->monthly_consumption_kwh = $monthlyConsumption ?? 0;
        $this->daily_consumption_kwh = $dailyConsumption ?? 0;
        $this->annual_consumption_kwh = $annualConsumption ?? 0;
    }

    /**
     * The kWh price the project works with: the client's own, or the reference tariff of its kind of
     * place (ADR-0015). Every reader of energy_rate_cop_kwh gets this value; the stored one may be null.
     */
    protected function energyRateCopKwh(): Attribute
    {
        return Attribute::get(fn ($value): string => number_format(
            $value !== null ? (float) $value : $this->referenceEnergyRate(),
            2,
            '.',
            '',
        ));
    }

    /**
     * True when the client did not write a tariff and the project follows the reference one.
     */
    public function usesReferenceEnergyRate(): bool
    {
        return ($this->attributes['energy_rate_cop_kwh'] ?? null) === null;
    }

    /**
     * The tariff the client wrote, if any (what the edit form shows).
     */
    public function ownEnergyRate(): ?float
    {
        return $this->usesReferenceEnergyRate() ? null : (float) $this->attributes['energy_rate_cop_kwh'];
    }

    public function referenceEnergyRate(): float
    {
        $referenceValues = app(ReferenceValues::class);

        return EnergyTariff::forPropertyType(
            $this->property_type,
            $referenceValues->current(ReferenceValueCatalog::ENERGY_RATE)->value,
            $referenceValues->current(ReferenceValueCatalog::COMMERCIAL_CONTRIBUTION)->value,
        );
    }

    /** The consumption is the kWh of the bill, not the sum of the appliances (ADR-0020). */
    public function usesBillConsumption(): bool
    {
        return ConsumptionMode::normalize($this->consumption_mode) === ConsumptionMode::BILL;
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
