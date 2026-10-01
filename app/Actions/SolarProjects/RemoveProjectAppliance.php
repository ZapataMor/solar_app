<?php

namespace App\Actions\SolarProjects;

use App\Models\SolarProject;
use App\Models\SolarProjectAppliance;
use Illuminate\Support\Facades\DB;

/**
 * Use case: take an appliance out of the consumption diary (ADR-0013).
 */
final class RemoveProjectAppliance
{
    public function __construct(
        private readonly SyncProjectConsumption $syncProjectConsumption,
    ) {}

    public function __invoke(SolarProject $solarProject, SolarProjectAppliance $appliance): void
    {
        DB::transaction(function () use ($solarProject, $appliance): void {
            $appliance->delete();
            ($this->syncProjectConsumption)($solarProject);
        });
    }
}
