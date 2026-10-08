<?php

namespace App\Models;

use App\Domain\Installers\QuoteInclusions;
use App\Domain\Installers\QuoteSystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What an installer offers for a quote request (ADR-0026), with the detail a real quote carries
 * (ADR-0027): the system, what the price covers, the warranties and the commercial terms.
 */
class InstallerQuote extends Model
{
    protected $fillable = [
        'quote_request_id',
        'amount_cop',
        'power_kw',
        'panel_count',
        'panel_watts',
        'panel_model',
        'inverter_model',
        'includes_battery',
        'battery_kwh',
        'monthly_generation_kwh',
        'includes_retie',
        'includes_grid_paperwork',
        'includes_bidirectional_meter',
        'includes_maintenance',
        'panel_warranty_years',
        'inverter_warranty_years',
        'workmanship_warranty_years',
        'vat_included',
        'down_payment_percentage',
        'delivery_days',
        'scope',
        'exclusions',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'amount_cop' => 'decimal:2',
            'power_kw' => 'decimal:2',
            'panel_count' => 'integer',
            'panel_watts' => 'integer',
            'includes_battery' => 'boolean',
            'battery_kwh' => 'decimal:2',
            'monthly_generation_kwh' => 'decimal:2',
            'includes_retie' => 'boolean',
            'includes_grid_paperwork' => 'boolean',
            'includes_bidirectional_meter' => 'boolean',
            'includes_maintenance' => 'boolean',
            'panel_warranty_years' => 'integer',
            'inverter_warranty_years' => 'integer',
            'workmanship_warranty_years' => 'integer',
            'vat_included' => 'boolean',
            'down_payment_percentage' => 'integer',
            'delivery_days' => 'integer',
            'valid_until' => 'date',
        ];
    }

    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    /** The price moves with the dollar, so an offer stops being one (ADR-0026). */
    public function hasExpired(): bool
    {
        return $this->valid_until->endOfDay()->isPast();
    }

    public function daysLeft(): int
    {
        return (int) Carbon::now()->startOfDay()->diffInDays($this->valid_until->startOfDay(), false);
    }

    /**
     * What the price covers and what it does not, in the order both screens use (ADR-0027).
     *
     * @return array{included: list<array{key: string, label: string, hint: string}>, excluded: list<array{key: string, label: string, missing: string}>}
     */
    public function inclusions(): array
    {
        return QuoteInclusions::split($this->inclusionFlags());
    }

    /** Whether it leaves out what legalizes the installation (RETIE or the grid paperwork). */
    public function missesLegalization(): bool
    {
        return QuoteInclusions::missesLegalization($this->inclusionFlags());
    }

    /** "16 paneles de 550 W · Jinko Tiger Neo", with whatever the installer wrote. */
    public function panelText(): ?string
    {
        return QuoteSystem::panelText($this->panel_count, $this->panel_watts, $this->panel_model);
    }

    /** Whether they wrote anything beyond the total: what makes the quote worth opening. */
    public function hasDetail(): bool
    {
        return $this->panelText() !== null
            || $this->inverter_model !== null
            || $this->monthly_generation_kwh !== null
            || $this->panel_warranty_years !== null
            || $this->inverter_warranty_years !== null
            || $this->workmanship_warranty_years !== null
            || $this->delivery_days !== null
            || $this->down_payment_percentage !== null;
    }

    /**
     * @return array<string, bool>
     */
    private function inclusionFlags(): array
    {
        return [
            QuoteInclusions::RETIE => (bool) $this->includes_retie,
            QuoteInclusions::GRID_PAPERWORK => (bool) $this->includes_grid_paperwork,
            QuoteInclusions::BIDIRECTIONAL_METER => (bool) $this->includes_bidirectional_meter,
            QuoteInclusions::BATTERY => (bool) $this->includes_battery,
            QuoteInclusions::MAINTENANCE => (bool) $this->includes_maintenance,
        ];
    }
}
