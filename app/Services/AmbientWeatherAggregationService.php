<?php

namespace App\Services;

use App\Models\AmbientWeatherReading;
use App\Models\SolarProject;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Queries and aggregates AmbientWeatherReading records.
 *
 * Mirrors WeatherStationAggregationService in purpose and interface so that
 * ProjectDashboardService can use both interchangeably.
 *
 * Memory note: an Ambient station writes a reading every few minutes, so a
 * one-year project easily covers tens of thousands of rows. Hydrating those as
 * Eloquent models exhausts PHP's memory limit, so every project-scoped helper
 * here (`*ForProject`) works on raw rows or SQL aggregates and never
 * materializes the full reading set. The Collection-based methods are kept for
 * callers that already hold a (small) set of models.
 */
class AmbientWeatherAggregationService
{
    /**
     * Columns needed to aggregate readings. `raw_payload` is deliberately
     * excluded: it averages ~700 bytes per row and is never used here.
     *
     * @var array<int, string>
     */
    private const AGGREGATION_COLUMNS = [
        'recorded_at',
        'temperature',
        'humidity',
        'wind_speed',
        'rainfall',
        'uv_index',
        'solar_radiation',
    ];

    /**
     * Return all Ambient readings that fall within the project's date range.
     *
     * WARNING: this hydrates one model per reading. Prefer the `*ForProject`
     * helpers below on wide date ranges.
     *
     * @return Collection<int, AmbientWeatherReading>
     */
    public function readingsForProject(SolarProject $solarProject): Collection
    {
        return AmbientWeatherReading::query()
            ->select(['id', ...self::AGGREGATION_COLUMNS])
            ->whereBetween('recorded_at', $this->projectRange($solarProject))
            ->orderBy('recorded_at')
            ->get();
    }

    /**
     * Return the project's readings recorded within the last N days.
     *
     * Bounded alternative to {@see readingsForProject()} for consumers that
     * only look at recent observations (e.g. the forecast panel).
     *
     * @return Collection<int, AmbientWeatherReading>
     */
    public function recentWindowForProject(SolarProject $solarProject, int $days = 30): Collection
    {
        [$start, $end] = $this->projectRange($solarProject);
        $windowStart = Carbon::now()->subDays($days)->startOfDay();

        return AmbientWeatherReading::query()
            ->select(['id', ...self::AGGREGATION_COLUMNS])
            ->whereBetween('recorded_at', [$start->max($windowStart), $end])
            ->orderBy('recorded_at')
            ->get();
    }

    /**
     * Return the N most recent readings across all stations (no project filter).
     *
     * Used by the dashboard's "recent readings" panel.
     *
     * @return Collection<int, AmbientWeatherReading>
     */
    public function latestReadings(int $limit = 60): Collection
    {
        return AmbientWeatherReading::query()
            ->select(['id', ...self::AGGREGATION_COLUMNS])
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Return the N most recent readings inside the project's date range.
     *
     * @return Collection<int, AmbientWeatherReading>
     */
    public function recentReadingsForProject(SolarProject $solarProject, int $limit = 60): Collection
    {
        return AmbientWeatherReading::query()
            ->select(['id', ...self::AGGREGATION_COLUMNS])
            ->whereBetween('recorded_at', $this->projectRange($solarProject))
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Daily rows for the project's date range without hydrating every reading.
     *
     * Produces the same structure as {@see dailyRows()}.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function dailyRowsForProject(SolarProject $solarProject): Collection
    {
        return $this->dailyRowsFromRawRows(
            $this->rowsQuery()
                ->whereBetween('recorded_at', $this->projectRange($solarProject))
                ->orderBy('recorded_at')
                ->cursor()
        );
    }

    /**
     * Daily rows built from the N most recent readings (no project filter).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function dailyRowsForLatest(int $limit): Collection
    {
        $rows = $this->rowsQuery()
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->get()
            ->reverse();

        return $this->dailyRowsFromRawRows($rows);
    }

    /**
     * Daily rows built from the readings recorded inside an arbitrary range.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function dailyRowsForRange(Carbon $start, Carbon $end): Collection
    {
        return $this->dailyRowsFromRawRows(
            $this->rowsQuery()
                ->whereBetween('recorded_at', [$start, $end])
                ->orderBy('recorded_at')
                ->cursor()
        );
    }

    /**
     * Summary statistics for the project's range, computed by the database.
     *
     * Mirrors the shape returned by {@see stats()}.
     *
     * @return array<string, mixed>
     */
    public function statsForProject(SolarProject $solarProject): array
    {
        $range = $this->projectRange($solarProject);

        $aggregates = $this->baseQuery()
            ->whereBetween('recorded_at', $range)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('AVG(COALESCE(solar_radiation, uv_index)) as avg_radiation')
            ->selectRaw('MAX(COALESCE(solar_radiation, uv_index)) as max_radiation')
            ->selectRaw('AVG(temperature) as avg_temperature')
            ->selectRaw('AVG(humidity) as avg_humidity')
            ->selectRaw('MAX(uv_index) as max_uv_index')
            ->first();

        return [
            'total' => (int) ($aggregates->total ?? 0),
            'averageRadiation' => $this->nullableFloat($aggregates->avg_radiation ?? null),
            'maxRadiation' => $this->nullableFloat($aggregates->max_radiation ?? null),
            'averageTemperature' => $this->nullableFloat($aggregates->avg_temperature ?? null),
            'averageHumidity' => $this->nullableFloat($aggregates->avg_humidity ?? null),
            'maxUvIndex' => $this->nullableFloat($aggregates->max_uv_index ?? null),
            'latest' => $this->recentReadingsForProject($solarProject, 1)->first(),
        ];
    }

    /**
     * Chart-ready daily radiation averages for the project's range, grouped by
     * the database instead of in PHP.
     *
     * @return array{labels: array<int, string>, radiation: array<int, float>}
     */
    public function chartDataForProject(SolarProject $solarProject): array
    {
        $rows = $this->baseQuery()
            ->whereBetween('recorded_at', $this->projectRange($solarProject))
            ->selectRaw('DATE(recorded_at) as day')
            ->selectRaw('AVG(COALESCE(solar_radiation, uv_index)) as avg_radiation')
            ->groupByRaw('DATE(recorded_at)')
            ->orderByRaw('DATE(recorded_at)')
            ->get()
            ->filter(fn ($row) => $row->avg_radiation !== null);

        return [
            'labels' => $rows->map(fn ($row) => (string) $row->day)->values()->all(),
            'radiation' => $rows->map(fn ($row) => (float) $row->avg_radiation)->values()->all(),
        ];
    }

    /**
     * Return the single most recent reading, or null when no data exists.
     */
    public function latestReading(): ?AmbientWeatherReading
    {
        return AmbientWeatherReading::query()
            ->orderByDesc('recorded_at')
            ->first();
    }

    /**
     * Collapse a collection of readings into one row per calendar day.
     *
     * Radiation methodology (Ambient Weather vs NASA POWER):
     *   NASA POWER stores allsky_sfc_sw_dwn as the true 24-hour average W/m²
     *   (day + night). The SolarCalculationService converts it to HSP via × 24/1000.
     *
     *   Ambient sensors report instantaneous W/m² every ~5 min during daylight only.
     *   Simply averaging those daytime readings and multiplying by 24 overestimates
     *   HSP by a factor of ~2-3× because nighttime irradiance (= 0) is excluded.
     *
     *   Fix: trapezoidal integration over actual measurement intervals gives total
     *   daily energy (kWh/m²/day = HSP), which is then divided by 24 to produce a
     *   true 24h-average W/m² compatible with the existing calculation engine.
     *
     * Temperature derating:
     *   Since Ambient provides direct ambient temperature, we apply the standard
     *   panel power temperature coefficient (−0.40 %/°C above 25 °C STC) to the
     *   effective irradiance. This improves accuracy over NASA POWER (which only
     *   has satellite-derived air temperature) and makes Ambient the highest-quality
     *   source for solar energy calculations.
     *
     * Unit notes:
     *   allsky_sfc_sw_dwn → true 24h-avg W/m² (temperature-derated)
     *   t2m               → averaged temperature (°C)
     *   rh2m              → averaged humidity (%)
     *   prectotcorr       → summed rainfall (mm/day)
     *   ws10m             → averaged wind_speed converted km/h → m/s
     *   daily_hsp_kwh     → pre-derating HSP (kWh/m²/day) — informational
     *   temp_correction   → derating factor applied (e.g. 0.94 for 40 °C avg)
     *   radiation_source  → always 'ambient_sensor'
     *
     * @param  Collection<int, AmbientWeatherReading>  $readings
     * @return Collection<int, array{date_time: Carbon, allsky_sfc_sw_dwn: float, t2m: float|null, rh2m: float|null, prectotcorr: float|null, ws10m: float|null, daily_hsp_kwh: float, temp_correction: float, radiation_source: string}>
     */
    public function dailyRows(Collection $readings): Collection
    {
        $normalized = $readings
            ->map(fn (AmbientWeatherReading $r) => [
                'date' => $r->recorded_at->toDateString(),
                'timestamp' => $r->recorded_at->getTimestamp(),
                'radiation' => $r->radiationValue(),
                'temperature' => $r->temperature !== null ? (float) $r->temperature : null,
                'humidity' => $r->humidity !== null ? (float) $r->humidity : null,
                'rainfall' => $r->rainfall !== null ? (float) $r->rainfall : null,
                'wind_speed' => $r->wind_speed !== null ? (float) $r->wind_speed : null,
            ])
            ->sortBy('timestamp')
            ->values();

        return $this->dailyRowsFromNormalized($normalized);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Raw query builder on the readings table (no column selection, so it can
     * be used both for row fetching and for SQL aggregates).
     */
    private function baseQuery(): QueryBuilder
    {
        return DB::table((new AmbientWeatherReading)->getTable());
    }

    /**
     * Raw query builder restricted to the columns aggregation needs.
     */
    private function rowsQuery(): QueryBuilder
    {
        return $this->baseQuery()->select(self::AGGREGATION_COLUMNS);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function projectRange(SolarProject $solarProject): array
    {
        return [
            $solarProject->start_date->copy()->startOfDay(),
            $solarProject->end_date->copy()->endOfDay(),
        ];
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    /**
     * Collapse raw database rows (ordered by `recorded_at` ascending) into daily
     * rows, keeping at most one day of readings in memory at a time.
     *
     * @param  iterable<int, object>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function dailyRowsFromRawRows(iterable $rows): Collection
    {
        $dailyRows = collect();
        $currentDate = null;
        $buffer = [];

        foreach ($rows as $row) {
            $recordedAt = (string) $row->recorded_at;
            $date = substr($recordedAt, 0, 10);

            if ($currentDate !== null && $date !== $currentDate) {
                $this->pushDayRow($dailyRows, $currentDate, $buffer);
                $buffer = [];
            }

            $currentDate = $date;
            $buffer[] = [
                'date' => $date,
                'timestamp' => strtotime($recordedAt),
                'radiation' => $this->rawRadiationValue($row),
                'temperature' => $this->nullableFloat($row->temperature ?? null),
                'humidity' => $this->nullableFloat($row->humidity ?? null),
                'rainfall' => $this->nullableFloat($row->rainfall ?? null),
                'wind_speed' => $this->nullableFloat($row->wind_speed ?? null),
            ];
        }

        if ($currentDate !== null) {
            $this->pushDayRow($dailyRows, $currentDate, $buffer);
        }

        return $dailyRows->values();
    }

    /**
     * Same as {@see dailyRowsFromRawRows()} for already-normalized readings.
     *
     * @param  Collection<int, array<string, mixed>>  $normalized
     * @return Collection<int, array<string, mixed>>
     */
    private function dailyRowsFromNormalized(Collection $normalized): Collection
    {
        $dailyRows = collect();

        foreach ($normalized->groupBy('date') as $date => $dayReadings) {
            $this->pushDayRow($dailyRows, (string) $date, $dayReadings->all());
        }

        return $dailyRows->values();
    }

    /**
     * Mirrors AmbientWeatherReading::radiationValue() for raw database rows.
     */
    private function rawRadiationValue(object $row): ?float
    {
        if (($row->solar_radiation ?? null) !== null) {
            return (float) $row->solar_radiation;
        }

        return ($row->uv_index ?? null) !== null ? (float) $row->uv_index : null;
    }

    /**
     * Aggregate one calendar day of readings and append it when it carries
     * usable radiation data.
     *
     * @param  Collection<int, array<string, mixed>>  $dailyRows
     * @param  array<int, array<string, mixed>>  $dayReadings
     */
    private function pushDayRow(Collection $dailyRows, string $date, array $dayReadings): void
    {
        // Only use readings that have valid radiation values
        $sorted = array_values(array_filter(
            $dayReadings,
            fn (array $reading) => $reading['radiation'] !== null
        ));

        if ($sorted === []) {
            return;
        }

        // Trapezoidal integration: Σ [(G_i + G_{i+1})/2 × Δt_hours]
        // Gaps > 30 min (sensor offline) are capped so an outage doesn't
        // add phantom energy to the integral.
        $dailyHsp = $this->trapezoidalHsp($sorted, 30);

        // Convert kWh/m²/day → 24h-average W/m² (NASA-compatible format).
        // SolarCalculationService will apply × 24/1000 to recover HSP.
        $allsky24hAvg = $dailyHsp * 1000.0 / 24.0;

        // Temperature derating: standard monocrystalline Si coefficient
        $avgTemp = $this->averageOf($dayReadings, 'temperature');
        $tempCorrection = $this->temperatureCorrection($avgTemp);

        $avgWindKmh = $this->averageOf($dayReadings, 'wind_speed');
        $rain = array_sum(array_map(
            fn (array $reading) => $reading['rainfall'] ?? 0.0,
            $dayReadings
        ));

        $dailyRows->push([
            'date_time'         => Carbon::parse($date)->startOfDay(),
            'allsky_sfc_sw_dwn' => round($allsky24hAvg * $tempCorrection, 4),
            't2m'               => $avgTemp,
            'rh2m'              => $this->averageOf($dayReadings, 'humidity'),
            'prectotcorr'       => $rain > 0.0 ? $rain : null,
            'ws10m'             => $avgWindKmh !== null ? round($avgWindKmh / 3.6, 3) : null,
            // Metadata — ignored by weatherDataFromRows() but useful for debugging
            'daily_hsp_kwh'     => round($dailyHsp, 4),
            'temp_correction'   => $tempCorrection,
            'radiation_source'  => 'ambient_sensor',
        ]);
    }

    /**
     * Average of a reading field, ignoring nulls (matches Collection::average()).
     *
     * @param  array<int, array<string, mixed>>  $readings
     */
    private function averageOf(array $readings, string $field): ?float
    {
        $values = array_filter(
            array_map(fn (array $reading) => $reading[$field], $readings),
            fn (?float $value) => $value !== null
        );

        return $values === [] ? null : array_sum($values) / count($values);
    }

    /**
     * Trapezoidal integration of solar radiation readings.
     *
     * Returns daily HSP in kWh/m²/day.
     * Nighttime irradiance is 0 W/m² and produces no readings, so it
     * contributes nothing to the sum — which is correct.
     *
     * @param  array<int, array<string, mixed>>  $sorted  Readings sorted by timestamp, non-null radiation only
     * @param  int  $maxGapMinutes  Cap individual intervals to avoid inflating energy during outages
     */
    private function trapezoidalHsp(array $sorted, int $maxGapMinutes = 30): float
    {
        $maxGapH = $maxGapMinutes / 60.0;
        $n = count($sorted);

        if ($n === 1) {
            // Only one reading: assume a single 5-minute measurement window
            return (float) $sorted[0]['radiation'] * (5.0 / 60.0) / 1000.0;
        }

        $totalWh = 0.0;

        for ($i = 1; $i < $n; $i++) {
            $prev = $sorted[$i - 1];
            $curr = $sorted[$i];
            $dtH  = min(abs($curr['timestamp'] - $prev['timestamp']) / 3600.0, $maxGapH);
            $avgG = ((float) $prev['radiation'] + (float) $curr['radiation']) / 2.0;
            $totalWh += $avgG * $dtH;
        }

        // Trailing half-interval for the last reading (same width as the preceding gap)
        $dtLastH = min(
            abs($sorted[$n - 1]['timestamp'] - $sorted[$n - 2]['timestamp']) / 3600.0,
            $maxGapH
        );
        $totalWh += (float) $sorted[$n - 1]['radiation'] * $dtLastH;

        return $totalWh / 1000.0; // Wh/m² → kWh/m²/day
    }

    /**
     * Standard monocrystalline-silicon panel power temperature coefficient.
     *
     * −0.40 %/°C above 25 °C (STC). Result is clamped to [0.50, 1.00].
     */
    private function temperatureCorrection(?float $avgTempC): float
    {
        if ($avgTempC === null || $avgTempC <= 25.0) {
            return 1.0;
        }

        return max(0.5, 1.0 - 0.004 * ($avgTempC - 25.0));
    }

    /**
     * Compute summary statistics for a collection of Ambient readings.
     *
     * Mirrors WeatherStationAggregationService::stats() for dashboard parity.
     *
     * @param  Collection<int, AmbientWeatherReading>  $readings
     * @return array{
     *     total: int,
     *     averageRadiation: float|null,
     *     maxRadiation: float|null,
     *     averageTemperature: float|null,
     *     averageHumidity: float|null,
     *     maxUvIndex: float|null,
     *     latest: AmbientWeatherReading|null
     * }
     */
    public function stats(Collection $readings): array
    {
        $radiationValues = $readings
            ->map(fn (AmbientWeatherReading $r) => $r->radiationValue())
            ->filter(fn (?float $v) => $v !== null)
            ->values();

        return [
            'total'              => $readings->count(),
            'averageRadiation'   => $radiationValues->average(),
            'maxRadiation'       => $radiationValues->max(),
            'averageTemperature' => $readings->avg(fn (AmbientWeatherReading $r) => $r->temperature !== null ? (float) $r->temperature : null),
            'averageHumidity'    => $readings->avg(fn (AmbientWeatherReading $r) => $r->humidity !== null ? (float) $r->humidity : null),
            'maxUvIndex'         => $readings->max('uv_index'),
            'latest'             => $readings->sortByDesc('recorded_at')->first(),
        ];
    }

    /**
     * Build chart-ready arrays from a collection of readings.
     *
     * @param  Collection<int, AmbientWeatherReading>  $readings
     * @return array{labels: array<int, string>, radiation: array<int, float>}
     */
    public function chartData(Collection $readings): array
    {
        $dailyRadiation = $readings
            ->groupBy(fn (AmbientWeatherReading $r) => $r->recorded_at->toDateString())
            ->map(fn (Collection $dayReadings) => $dayReadings
                ->map(fn (AmbientWeatherReading $r) => $r->radiationValue())
                ->filter(fn (?float $v) => $v !== null)
                ->average())
            ->filter(fn (?float $v) => $v !== null);

        return [
            'labels'    => $dailyRadiation->keys()->values()->all(),
            'radiation' => $dailyRadiation->map(fn ($v) => (float) $v)->values()->all(),
        ];
    }
}
