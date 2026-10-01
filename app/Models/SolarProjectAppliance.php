<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolarProjectAppliance extends Model
{
    protected $fillable = [
        'appliance_key',
        'variant_key',
        'quantity',
        'hours_per_day',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'hours_per_day' => 'float',
        ];
    }

    public function solarProject(): BelongsTo
    {
        return $this->belongsTo(SolarProject::class);
    }
}
