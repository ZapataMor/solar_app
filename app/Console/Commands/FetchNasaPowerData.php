<?php

namespace App\Console\Commands;

use App\Models\SolarProject;
use App\Services\NasaPowerService;
use App\Services\NasaWeatherDataService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchNasaPowerData extends Command
{
    protected $signature = 'nasa-power:fetch {--days=45 : Ventana de dias hacia atras para refresco incremental}';

    protected $description = 'Fetch and upsert NASA POWER weather data for solar projects.';

    public function handle(
        NasaPowerService $nasaPowerService,
        NasaWeatherDataService $nasaWeatherDataService,
    ): int {
        $projects = SolarProject::query()->get(['start_date', 'end_date']);

        if ($projects->isEmpty()) {
            Log::info('Automatic NASA POWER fetch skipped: no projects available.');
            $this->info('Sin proyectos solares registrados. Consulta NASA omitida.');

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $timezone = (string) config('app.timezone', 'America/Bogota');
        $yesterday = CarbonImmutable::yesterday($timezone)->startOfDay();
        $defaultStart = $yesterday->subDays($days - 1);
        $projectMinStart = CarbonImmutable::parse((string) $projects->min('start_date'), $timezone)->startOfDay();
        $startDate = $projectMinStart->greaterThan($defaultStart) ? $projectMinStart : $defaultStart;
        $endDate = $yesterday;

        if ($startDate->greaterThan($endDate)) {
            Log::info('Automatic NASA POWER fetch skipped: future-only project ranges.', [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ]);
            $this->info('Rango fuera de disponibilidad de NASA. Consulta omitida.');

            return self::SUCCESS;
        }

        Log::info('Automatic NASA POWER fetch started.', [
            'scope' => 'global',
            'start' => $startDate->toDateString(),
            'end' => $endDate->toDateString(),
            'window_days' => $days,
        ]);

        try {
            $payload = $nasaPowerService->fetchHourlyData($startDate, $endDate);
            ['created' => $created, 'updated' => $updated] = $nasaWeatherDataService->storeDailyData($payload);
        } catch (Throwable $exception) {
            report($exception);

            Log::error('Automatic NASA POWER fetch failed.', [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'error' => $exception->getMessage(),
            ]);

            $this->error('Consulta NASA fallida.');

            return self::FAILURE;
        }

        Log::info('Automatic NASA POWER fetch finished.', [
            'scope' => 'global',
            'start' => $startDate->toDateString(),
            'end' => $endDate->toDateString(),
            'created' => $created,
            'updated' => $updated,
        ]);

        $this->info("NASA POWER sincronizado. Nuevos: {$created}. Actualizados: {$updated}.");

        return self::SUCCESS;
    }
}

