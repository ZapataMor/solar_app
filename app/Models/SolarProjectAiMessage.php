<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SolarProjectAiMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'solar_project_id',
        'type',
        'role',
        'sequence',
        'focus',
        'focus_label',
        'source',
        'title',
        'message',
        'summary',
        'metadata',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'metadata' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function solarProject(): BelongsTo
    {
        return $this->belongsTo(SolarProject::class);
    }
}
