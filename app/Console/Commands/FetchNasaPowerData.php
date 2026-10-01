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
    protected $signature = 'nasa-power:fetch
        {--days=45 : Ventana de dias hacia atras que se reconsulta para confirmar estimaciones}
        {--rebuild : Borra las filas horarias antiguas y descarga todo el periodo de los proyectos en datos diarios}';

    protected $description = 'Fetch daily NASA POWER data for solar projects, upgrading estimates once NASA publishes them (ADR-0009).';

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

        if ($this->option('rebuild')) {
            $purged = $nasaWeatherDataService->purgeHourlyRows();
            $startDate = $projectMinStart;
            $this->info("Filas horarias eliminadas: {$purged}. Se descarga todo el periodo de los proyectos.");
        }
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
            $payload = $nasaPowerService->fetchDailyData($startDate, $endDate);
            ['created' => $created, 'updated' => $updated, 'promoted' => $promoted] = $nasaWeatherDataService->storeDailyData($payload);
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
            'confirmed_estimates' => $promoted,
        ]);

        $this->info("NASA POWER sincronizado. Nuevos: {$created}. Actualizados: {$updated}. Estimaciones confirmadas con dato real: {$promoted}.");

        return self::SUCCESS;
    }
}

