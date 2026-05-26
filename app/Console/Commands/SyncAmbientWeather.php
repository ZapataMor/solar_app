<?php

namespace App\Console\Commands;

use App\Services\AmbientWeatherImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncAmbientWeather extends Command
{
    protected $signature = 'ambient:sync';

    protected $description = 'Fetch and persist the latest readings from all Ambient Weather stations.';

    public function handle(AmbientWeatherImportService $importService): int
    {
        Log::info('Ambient Weather sync started.');

        try {
            $summary = $importService->importLatestForAllDevices();
        } catch (Throwable $exception) {
            report($exception);

            Log::error('Ambient Weather sync failed with an unexpected error.', [
                'error' => $exception->getMessage(),
            ]);

            $this->error("Ambient Weather sync falló: {$exception->getMessage()}");

            return self::FAILURE;
        }

        Log::info('Ambient Weather sync finished.', $summary);

        $this->info(
            "Ambient Weather sincronizado. "
            . "Recibidos: {$summary['received']}. "
            . "Guardados: {$summary['created']}. "
            . "Omitidos (duplicados): {$summary['skipped']}."
        );

        return self::SUCCESS;
    }
}
