<?php

namespace App\Services;

use App\Models\AmbientWeatherReading;
use App\Models\WeatherStationReading;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Determines the active climate data source and provides a unified interface
 * to select climate data with automatic fallback.
 *
 * Priority (highest → lowest):
 *   1. Local weather station  (WeatherStationReading — real hardware on-site)
 *   2. Ambient Weather        (AmbientWeatherReading — third-party IoT station)
 *   3. NASA POWER             (ApiWeatherData       — satellite-modelled)
 *
 * A source is considered "online" when it has at least one reading within the
 * configured freshness window (ambient.online_threshold_minutes, default 30 min).
 *
 * Usage:
 *   $source = $this->climateSourceFallbackService->resolveActiveSource();
 *   // $source === 'local' | 'ambient' | 'nasa_power'
 */
class ClimateSourceFallbackService
{
    public function __construct(
        private readonly AmbientWeatherService $ambientWeatherService,
    ) {}

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public const SOURCE_LOCAL    = 'local';
    public const SOURCE_AMBIENT  = 'ambient';
    public const SOURCE_NASA     = 'nasa_power';

    /** Human-readable labels for the dashboard. */
    private const SOURCE_LABELS = [
        self::SOURCE_LOCAL   => 'Estación local',
        self::SOURCE_AMBIENT => 'Ambient Weather',
        self::SOURCE_NASA    => 'NASA POWER',
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Return the identifier of the highest-priority source that currently has
     * fresh data. Falls back gracefully through the priority list.
     *
     * @return 'local'|'ambient'|'nasa_power'
     */
    public function resolveActiveSource(): string
    {
        $threshold = $this->threshold();

        if ($this->localStationIsOnline($threshold)) {
            return self::SOURCE_LOCAL;
        }

        if ($this->ambientIsOnline($threshold)) {
            return self::SOURCE_AMBIENT;
        }

        return self::SOURCE_NASA;
    }

    /**
     * Return a structured descriptor for the dashboard.
     *
     * @return array{source: string, label: string, online: bool, fallbackUsed: bool, fallbackReason: string|null}
     */
    public function resolveActiveSourceDescriptor(): array
    {
        $threshold = $this->threshold();
        $localOnline   = $this->localStationIsOnline($threshold);
        $ambientOnline = $this->ambientIsOnline($threshold);

        $source = match (true) {
            $localOnline   => self::SOURCE_LOCAL,
            $ambientOnline => self::SOURCE_AMBIENT,
            default        => self::SOURCE_NASA,
        };

        $fallbackReason = match ($source) {
            self::SOURCE_NASA => match (true) {
                ! $localOnline && ! $ambientOnline => 'Estación local y Ambient Weather sin datos recientes.',
                ! $localOnline                     => 'Estación local sin datos recientes.',
                default                            => null,
            },
            self::SOURCE_AMBIENT => 'Estación local sin datos recientes.',
            default              => null,
        };

        if ($fallbackReason !== null) {
            Log::warning("Climate source fallback active: using [{$source}].", [
                'reason' => $fallbackReason,
            ]);
        }

        return [
            'source'        => $source,
            'label'         => self::SOURCE_LABELS[$source],
            'online'        => $source !== self::SOURCE_NASA,
            'fallbackUsed'  => $source !== self::SOURCE_LOCAL,
            'fallbackReason'=> $fallbackReason,
        ];
    }

    /**
     * Choose the best daily-row collection for solar calculations, applying
     * the fallback hierarchy:
     *   NASA rows → local station rows → Ambient rows → empty collection
     *
     * The NASA rows are still preferred for calculations (modelled, complete
     * historical coverage), but Ambient and local fill gaps when NASA has no data.
     *
     * @param  Collection<int, mixed>  $nasaRows
     * @param  Collection<int, mixed>  $localRows
     * @param  Collection<int, mixed>  $ambientRows
     * @return array{rows: Collection<int, mixed>, source: string}
     */
    public function selectDailyClimateRows(
        Collection $nasaRows,
        Collection $localRows,
        Collection $ambientRows,
    ): array {
        if ($nasaRows->isNotEmpty()) {
            return ['rows' => $nasaRows, 'source' => self::SOURCE_NASA];
        }

        if ($localRows->isNotEmpty()) {
            return ['rows' => $localRows, 'source' => self::SOURCE_LOCAL];
        }

        if ($ambientRows->isNotEmpty()) {
            return ['rows' => $ambientRows, 'source' => self::SOURCE_AMBIENT];
        }

        return ['rows' => collect(), 'source' => self::SOURCE_NASA];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function threshold(): Carbon
    {
        $minutes = (int) config('ambient.online_threshold_minutes', 30);

        return Carbon::now()->subMinutes($minutes);
    }

    private function localStationIsOnline(Carbon $threshold): bool
    {
        try {
            return WeatherStationReading::query()
                ->where('measured_at', '>=', $threshold)
                ->exists();
        } catch (\Throwable $e) {
            Log::warning('ClimateSourceFallbackService: could not query local station.', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function ambientIsOnline(Carbon $threshold): bool
    {
        if (! $this->ambientWeatherService->isEnabled()) {
            return false;
        }

        try {
            return AmbientWeatherReading::query()
                ->where('recorded_at', '>=', $threshold)
                ->exists();
        } catch (\Throwable $e) {
            Log::warning('ClimateSourceFallbackService: could not query Ambient readings.', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
