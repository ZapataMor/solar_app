<?php

namespace App\Actions\Climate;

use App\Domain\Climate\ClimateSource;
use App\Domain\Sync\ElapsedTime;
use App\Domain\Sync\SchedulerHeartbeat;
use App\Domain\Sync\SourceHealth;
use App\Models\AmbientWeatherReading;
use App\Models\ApiWeatherData;
use App\Models\SyncRun;
use App\Models\WeatherStationReading;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Use case: are the climate sources being brought up to date? (ADR-0016.) Reads the scheduler's
 * heartbeat, the newest data of each source and its latest run, for the health strip of the climate
 * data page and the red dot of the administrator's menu.
 */
final class DescribeSyncHealth
{
    /** Overdue after this many minutes without newer data. The station only counts inside its hours. */
    private const LATE_AFTER_MINUTES = [
        ClimateSource::AMBIENT => 20,
        ClimateSource::LOCAL => 30,
        // NASA publishes with a lag, and its rows are whole days.
        ClimateSource::NASA_POWER => 2 * 24 * 60,
    ];

    private const LABELS = [
        ClimateSource::AMBIENT => 'Ambient Weather',
        ClimateSource::LOCAL => 'Estación local',
        ClimateSource::NASA_POWER => 'NASA POWER',
    ];

    /**
     * @return array{
     *     scheduler: array{status: string, problem: bool, text: string, detail: string},
     *     sources: array<string, array{label: string, status: string, problem: bool, text: string, alert: string, lastData: string, lastRun: string, error: string|null}>,
     *     problems: int,
     * }
     */
    public function __invoke(): array
    {
        $now = CarbonImmutable::now();
        $scheduler = $this->scheduler($now);
        $sources = [];

        foreach (self::LABELS as $key => $label) {
            $sources[$key] = $this->source($key, $label, $now);
        }

        return [
            'scheduler' => $scheduler,
            'sources' => $sources,
            'problems' => count(array_filter($sources, fn (array $source) => $source['problem'])) + ($scheduler['problem'] ? 1 : 0),
        ];
    }

    /**
     * @return array{status: string, problem: bool, text: string, detail: string}
     */
    private function scheduler(CarbonImmutable $now): array
    {
        $beat = Cache::get(RecordSchedulerHeartbeat::CACHE_KEY);
        $health = SchedulerHeartbeat::evaluate($now, $beat === null ? null : CarbonImmutable::createFromTimestamp((int) $beat));

        return [
            'status' => $health->status,
            'problem' => $health->isProblem(),
            'text' => match ($health->status) {
                SchedulerHeartbeat::BEATING => 'Programador al día',
                SchedulerHeartbeat::STOPPED => 'El programador está detenido',
                default => 'Programador sin latido todavía',
            },
            'detail' => match ($health->status) {
                SchedulerHeartbeat::BEATING => 'Último latido '.ElapsedTime::ago($health->minutesSinceBeat).'.',
                SchedulerHeartbeat::STOPPED => 'Lleva '.ElapsedTime::lapse($health->minutesSinceBeat).' sin latir: el cron del servidor no está ejecutando schedule:run.',
                default => 'El cron del servidor debe ejecutar schedule:run cada minuto.',
            },
        ];
    }

    /**
     * @return array{label: string, status: string, problem: bool, text: string, alert: string, lastData: string, lastRun: string, error: string|null}
     */
    private function source(string $key, string $label, CarbonImmutable $now): array
    {
        $run = SyncRun::query()->where('source', $key)->orderByDesc('started_at')->orderByDesc('id')->first();
        $lastDataAt = $this->lastDataAt($key);
        $failed = $run?->result === SyncRun::ERROR;

        $health = SourceHealth::evaluate(
            $now,
            $lastDataAt,
            self::LATE_AFTER_MINUTES[$key],
            $failed,
            ...($key === ClimateSource::LOCAL ? $this->stationHours($now) : []),
        );

        $behind = $health->minutesBehind === null ? null : ElapsedTime::lapse($health->minutesBehind);

        return [
            'label' => $label,
            'status' => $health->status,
            'problem' => $health->isProblem(),
            'text' => match ($health->status) {
                SourceHealth::OK => 'Al día',
                SourceHealth::LATE => "Atrasada: lleva {$behind} sin datos nuevos",
                SourceHealth::FAILING => $behind === null ? 'Fallando: sin datos' : "Fallando: lleva {$behind} sin datos nuevos",
                SourceHealth::IDLE => 'Fuera de horario ('.config('services.weather_station.schedule_from').' a '.config('services.weather_station.schedule_until').')',
                default => 'Sin datos todavía',
            },
            // The line of the warning at the top of the page.
            'alert' => $behind === null ? "{$label} está fallando y todavía no tiene datos." : "{$label} lleva {$behind} sin datos nuevos.",
            'lastData' => $lastDataAt === null ? 'Sin datos' : $this->describeDataMoment($key, $lastDataAt, $now),
            'lastRun' => $run === null ? 'Sin ejecuciones registradas' : $this->describeRun($run, $now),
            'error' => $failed ? $run->message : null,
        ];
    }

    private function lastDataAt(string $key): ?CarbonImmutable
    {
        $latest = match ($key) {
            ClimateSource::AMBIENT => AmbientWeatherReading::query()->max('recorded_at'),
            ClimateSource::LOCAL => WeatherStationReading::query()->max('measured_at'),
            default => ApiWeatherData::query()->max('date_time'),
        };

        if ($latest === null) {
            return null;
        }

        $at = CarbonImmutable::parse($latest, 'UTC');

        // NASA rows are whole days: the data of a day is complete when the day ends.
        return $key === ClimateSource::NASA_POWER ? $at->addDay() : $at;
    }

    /**
     * The station reports in a window of the day: not late before it opens, nor while it is closed.
     *
     * @return array{0: CarbonImmutable, 1: bool} When it opened today, and whether it is closed now.
     */
    private function stationHours(CarbonImmutable $now): array
    {
        $timezone = (string) config('services.weather_station.schedule_timezone', 'America/Bogota');
        $local = $now->setTimezone($timezone);
        $opens = $local->setTimeFromTimeString((string) config('services.weather_station.schedule_from'));
        $closes = $local->setTimeFromTimeString((string) config('services.weather_station.schedule_until'));

        return [$opens, $local < $opens || $local > $closes];
    }

    private function describeDataMoment(string $key, CarbonImmutable $at, CarbonImmutable $now): string
    {
        // A day of NASA data, not a moment: say which day (its date is stored as the day itself).
        if ($key === ClimateSource::NASA_POWER) {
            return 'Día '.$at->subDay()->format('d/m/Y');
        }

        return $at->setTimezone((string) config('app.display_timezone', 'America/Bogota'))->format('d/m/Y H:i').' · '.ElapsedTime::ago(max(0, intdiv($now->getTimestamp() - $at->getTimestamp(), 60)));
    }

    private function describeRun(SyncRun $run, CarbonImmutable $now): string
    {
        $ago = ElapsedTime::ago(max(0, intdiv($now->getTimestamp() - $run->started_at->getTimestamp(), 60)));

        return match ($run->result) {
            SyncRun::OK => "{$ago} · {$run->created} nuevas",
            SyncRun::EMPTY => "{$ago} · sin datos nuevos",
            SyncRun::ERROR => "{$ago} · con error",
            default => "{$ago} · sin terminar",
        };
    }
}
