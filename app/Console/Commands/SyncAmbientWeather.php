<?php

namespace App\Console\Commands;

use App\Services\AmbientWeatherImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncAmbientWeather extends Command
{
    protected $signature = 'ambient:sync';

    protected $description = 'Bring every Ambient Weather station up to now (from its last stored reading, filling gaps).';

    public function handle(AmbientWeatherImportService $importService): int
    {
        Log::info('Ambient Weather sync started.');

        try {
            $summary = $importService->importRecentForAllDevices();
        } catch (Throwable $exception) {
            report($exception);

            Log::error('Ambient Weather sync failed with an unexpected error.', [
                'error' => $exception->getMessage(),
            ]);

            $this->error("Ambient Weather sync falló: {$exception->getMessage()}");

            return self::FAILURE;
        }

        Log::info('Ambient Weather sync finished.', [...$summary, 'latest' => $summary['latest']?->toDateTimeString()]);

        $this->info(
            "Ambient Weather sincronizado. "
            . "Recibidos: {$summary['received']}. "
            . "Guardados: {$summary['created']}. "
            . "Omitidos (duplicados): {$summary['skipped']}. "
            . 'Última lectura: '.($summary['latest']?->timezone(config('app.display_timezone'))->format('Y-m-d H:i') ?? 'ninguna').'.'
        );

        return self::SUCCESS;
    }
}
