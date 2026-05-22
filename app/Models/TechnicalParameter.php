<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalParameter extends Model
{
    use HasFactory;

    protected $fillable = [
        'available_area_m2',
        'usable_area_percentage',
        'panel_power_w',
        'panel_area_m2',
        'performance_ratio',
        'system_losses_percentage',
    ];

    protected function casts(): array
    {
        return [
            'available_area_m2' => 'decimal:2',
            'usable_area_percentage' => 'decimal:2',
            'panel_power_w' => 'decimal:2',
            'panel_area_m2' => 'decimal:2',
            'performance_ratio' => 'decimal:3',
            'system_losses_percentage' => 'decimal:2',
        ];
    }

    public function solarProject(): BelongsTo
    {
        return $this->belongsTo(SolarProject::class);
    }
}
