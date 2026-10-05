<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An allied installer an administrator added (ADR-0021).
 */
class Installer extends Model
{
    protected $fillable = [
        'name',
        'tagline',
        'description',
        'contact_name',
        'phone',
        'email',
        'years_experience',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'years_experience' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function municipalities(): BelongsToMany
    {
        return $this->belongsToMany(Municipality::class);
    }

    public function quoteRequests(): HasMany
    {
        return $this->hasMany(QuoteRequest::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Installers that cover a municipality. Without one (older projects) nobody is filtered out.
     */
    public function scopeCovering(Builder $query, ?int $municipalityId): Builder
    {
        return $municipalityId === null
            ? $query
            : $query->whereHas('municipalities', fn (Builder $municipality) => $municipality->whereKey($municipalityId));
    }

    /**
     * wa.me needs the number with the country code and no symbols; Colombian numbers are stored as
     * they are written ("300 123 4567"), so 57 is added when it is not already there.
     */
    public function whatsappNumber(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->phone);

        if ($digits === null || Str::length($digits) < 10) {
            return null;
        }

        return Str::startsWith($digits, '57') ? $digits : '57'.$digits;
    }
}
