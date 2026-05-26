<?php

namespace App\Console\Commands;

use App\Services\AmbientWeatherImportService;
use App\Services\AmbientWeatherService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

/**
 * Backfill historical readings from Ambient Weather.
 *
 * The API paginates backwards: each call returns up to 288 readings
 * (one per 5-min interval ≈ 24 h) ending at a given epoch-ms timestamp.
 * We walk backwards from --to until we reach --from, sleeping 1 s between
 * requests to respect the 1-req/s rate limit.
 *
 * Usage:
 *   php artisan ambient:sync-history
 *   php artisan ambient:sync-history --from=2025-01-01
 *   php artisan ambient:sync-history --from=2025-01-01 --to=2025-06-30
 *   php artisan ambient:sync-history --mac=AA:BB:CC:DD:EE:FF --from=2025-01-01
 */
class SyncAmbientWeatherHistory extends Command
{
    protected $signature = 'ambient:sync-history
        {--from=2025-01-01 : Fecha de inicio (Y-m-d), inclusive}
        {--to=            : Fecha de fin (Y-m-d), por defecto ayer}
        {--mac=           : MAC address de la estacion (por defecto todas)}
        {--sleep=1        : Segundos de pausa entre requests de API}
        {--limit=288      : Lecturas por request (max 288)}';

    protected $description = 'Importa el historial completo de lecturas Ambient Weather entre dos fechas.';

    public function handle(
        AmbientWeatherService $service,
        AmbientWeatherImportService $importService,
    ): int {
        if (! $service->isEnabled()) {
            $this->error('Ambient Weather esta deshabilitado o faltan credenciales.');
            return self::FAILURE;
        }

        $from  = Carbon::parse($this->option('from'), 'UTC')->startOfDay();
        $toRaw = $this->option('to');
        $to    = $toRaw ? Carbon::parse($toRaw, 'UTC')->endOfDay() : Carbon::yesterday('UTC')->endOfDay();
        $sleep = max(0, (int) $this->option('sleep'));
        $mac   = $this->option('mac') ? trim($this->option('mac')) : null;

        if ($from->greaterThan($to)) {
            $this->error("--from ({$from->toDateString()}) es posterior a --to ({$to->toDateString()}).");
            return self::FAILURE;
        }

        $this->info("Importando historial desde {$from->toDateString()} hasta {$to->toDateString()}. Pausa: {$sleep}s/request.");

        try {
            if ($mac !== null) {
                [$received, $created, $skipped] = $importService->importHistoricalForDevice($mac, $from, $to, $sleep);
            } else {
                $summary  = $importService->importHistoricalForAllDevices($from, $to, $sleep);
                $received = $summary['received'];
                $created  = $summary['created'];
                $skipped  = $summary['skipped'];
            }
        } catch (Throwable $e) {
            $this->error("Error: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("✓ Historial completado. Recibidos: {$received}. Guardados: {$created}. Omitidos: {$skipped}.");

        return self::SUCCESS;
    }
}
