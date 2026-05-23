<?php

namespace App\Console\Commands;

use App\Models\SolarProject;
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
        $projects = SolarProject::query()
            ->orderBy('id')
            ->get();

        if ($projects->isEmpty()) {
            Log::info('Automatic weather station fetch skipped: no solar projects found.');
            $this->info('No hay proyectos solares registrados para asociar lecturas.');

            return self::SUCCESS;
        }

        Log::info('Automatic weather station fetch started.', [
            'projects' => $projects->count(),
        ]);

        $received = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($projects as $project) {
            try {
                $summary = $weatherStationImportService->importAll($project);

                $received += $summary['received'];
                $created += $summary['created'];
                $updated += $summary['updated'];
                $skipped += $summary['skipped'];

                Log::info('Automatic weather station fetch completed for project.', [
                    'project_id' => $project->id,
                    'received' => $summary['received'],
                    'created' => $summary['created'],
                    'updated' => $summary['updated'],
                    'skipped' => $summary['skipped'],
                ]);
            } catch (Throwable $exception) {
                report($exception);
                $failed++;

                Log::error('Automatic weather station fetch failed for project.', [
                    'project_id' => $project->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        Log::info('Automatic weather station fetch finished.', [
            'projects' => $projects->count(),
            'failed' => $failed,
            'received' => $received,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);

        $this->info("Consulta finalizada. Recibidos: {$received}. Guardados: {$created}. Existentes omitidos: {$skipped}. Proyectos con error: {$failed}.");

        return $failed === $projects->count() ? self::FAILURE : self::SUCCESS;
    }
}
