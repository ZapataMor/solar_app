<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * What an installer offers for a quote request (ADR-0026).
 */
class InstallerQuote extends Model
{
    protected $fillable = [
        'quote_request_id',
        'amount_cop',
        'power_kw',
        'includes_battery',
        'scope',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'amount_cop' => 'decimal:2',
            'power_kw' => 'decimal:2',
            'includes_battery' => 'boolean',
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
}
