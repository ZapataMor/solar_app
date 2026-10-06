<?php

namespace App\Models;

use App\Domain\Installers\QuoteRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A client asked an installer to quote a project (ADR-0022): the lead of ADR-0005.
 */
class QuoteRequest extends Model
{
    protected $fillable = [
        'solar_project_id',
        'installer_id',
        'quoted_cost_cop',
        'quoted_price_per_kw_cop',
        'status',
        'contract_value_cop',
        'note',
        'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'quoted_cost_cop' => 'decimal:2',
            'quoted_price_per_kw_cop' => 'decimal:2',
            'contract_value_cop' => 'decimal:2',
            'answered_at' => 'datetime',
        ];
    }

    public function solarProject(): BelongsTo
    {
        return $this->belongsTo(SolarProject::class);
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(Installer::class);
    }

    /** What the installer offered for it (ADR-0026); null while they have not answered with a price. */
    public function installerQuote(): HasOne
    {
        return $this->hasOne(InstallerQuote::class);
    }

    public function statusLabel(): string
    {
        return QuoteRequestStatus::label($this->status);
    }

    public function isOpen(): bool
    {
        return QuoteRequestStatus::isOpen($this->status);
    }
}
