<?php

namespace App\Console\Commands;

use App\Services\WeatherStationImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchWeatherStationData extends Command
{
    protected $signature = 'weather-station:fetch';

    protected $description = 'Fetch new readings from the local weather station API.';

    public function handle(WeatherStationImportService $weatherStationImportService): int
    {
        Log::info('Automatic weather station fetch started.', [
            'scope' => 'global',
        ]);

        try {
            $summary = $weatherStationImportService->importAll();
        } catch (Throwable $exception) {
            report($exception);

            Log::error('Automatic weather station fetch failed.', [
                'error' => $exception->getMessage(),
            ]);

            $this->info('Consulta finalizada. Recibidos: 0. Guardados: 0. Existentes omitidos: 0. Proyectos con error: 1.');

            return self::FAILURE;
        }

        Log::info('Automatic weather station fetch finished.', [
            'scope' => 'global',
            'failed' => 0,
            'received' => $summary['received'],
            'created' => $summary['created'],
            'updated' => $summary['updated'],
            'skipped' => $summary['skipped'],
        ]);

        $this->info("Consulta finalizada. Recibidos: {$summary['received']}. Guardados: {$summary['created']}. Existentes omitidos: {$summary['skipped']}. Proyectos con error: 0.");

        return self::SUCCESS;
    }
}
