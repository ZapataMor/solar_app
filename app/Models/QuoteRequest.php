<?php

namespace App\Models;

use App\Domain\Installers\QuoteRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client asked an installer to quote a project (ADR-0022): the lead of ADR-0005.
 */
class QuoteRequest extends Model
{
    protected $fillable = [
        'solar_project_id',
        'installer_id',
        'status',
        'note',
    ];

    public function solarProject(): BelongsTo
    {
        return $this->belongsTo(SolarProject::class);
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(Installer::class);
    }

    public function statusLabel(): string
    {
        return QuoteRequestStatus::label($this->status);
    }
}
