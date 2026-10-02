<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded value of a reference value (ADR-0015): what it is worth from `valid_from` on.
 */
class ReferenceValue extends Model
{
    protected $fillable = [
        'key',
        'value',
        'valid_from',
        'source',
        'notes',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'valid_from' => 'date',
        ];
    }

    /**
     * The administrator who recorded it.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
