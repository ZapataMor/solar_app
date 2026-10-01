<?php

namespace App\Actions\SolarProjects;

use App\Domain\Climate\NoClimateData;
use App\Domain\Solar\MissingTechnicalParameters;
use App\Models\SolarProject;
use Throwable;

/**
 * Use case: recalculate, with the best available source, only the projects that need it.
 */
final class RecalculateProjects
{
    public function __construct(
        private readonly CheckCalculationFreshness $checkFreshness,
        private readonly CalculateSolarProject $calculateSolarProject,
    ) {}

    /**
     * @param  iterable<SolarProject>  $solarProjects
     * @return array{recalculated: int, failed: int, up_to_date: int}
     */
    public function __invoke(iterable $solarProjects): array
    {
        $summary = ['recalculated' => 0, 'failed' => 0, 'up_to_date' => 0];

        foreach ($solarProjects as $solarProject) {
            if (! ($this->checkFreshness)($solarProject)->needsRecalculation()) {
                $summary['up_to_date']++;

                continue;
            }

            try {
                ($this->calculateSolarProject)($solarProject);
                $summary['recalculated']++;
            } catch (MissingTechnicalParameters|NoClimateData) {
                $summary['failed']++;
            } catch (Throwable $exception) {
                report($exception);
                $summary['failed']++;
            }
        }

        return $summary;
    }
}
