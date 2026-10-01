<?php

namespace App\Http\Controllers;

use App\Actions\SolarProjects\BuildConsumptionDiary;
use App\Actions\SolarProjects\CheckCalculationFreshness;
use App\Actions\SolarProjects\RemoveProjectAppliance;
use App\Actions\SolarProjects\SaveProjectAppliance;
use App\Domain\Consumption\ApplianceCatalog;
use App\Domain\Property\PropertyType;
use App\Http\Requests\ProjectApplianceRequest;
use App\Models\SolarProject;
use App\Models\SolarProjectAppliance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The consumption diary of a project (ADR-0013): appliances added space by space after creating it.
 */
class SolarProjectConsumptionController extends Controller
{
    public function show(
        Request $request,
        SolarProject $solarProject,
        BuildConsumptionDiary $buildConsumptionDiary,
        CheckCalculationFreshness $checkFreshness,
        ApplianceCatalog $catalog,
    ): View {
        abort_unless($request->user()->can('manage', $solarProject), 403);

        return view('solar-projects.consumption', [
            'solarProject' => $solarProject,
            'diary' => $buildConsumptionDiary($solarProject),
            'calculationFreshness' => $checkFreshness($solarProject),
            'applianceCatalog' => $catalog->all(),
            'primarySegments' => PropertyType::applianceSegments($solarProject->property_type),
            'usesBillConsumption' => ! $solarProject->appliances()->exists() && $solarProject->monthlyConsumption() > 0,
        ]);
    }

    public function store(
        ProjectApplianceRequest $request,
        SolarProject $solarProject,
        SaveProjectAppliance $saveProjectAppliance,
        ApplianceCatalog $catalog,
    ): RedirectResponse {
        abort_unless($request->user()->can('manage', $solarProject), 403);

        $appliance = $saveProjectAppliance($solarProject, $request->validated());

        return $this->backToSpace($solarProject, $appliance->space)
            ->with('status', 'Se agregó '.$catalog->label($appliance->appliance_key).' a '.$this->spaceLabel($solarProject, $appliance->space).'.');
    }

    public function update(
        ProjectApplianceRequest $request,
        SolarProject $solarProject,
        SolarProjectAppliance $appliance,
        SaveProjectAppliance $saveProjectAppliance,
        ApplianceCatalog $catalog,
    ): RedirectResponse {
        abort_unless($request->user()->can('manage', $solarProject), 403);

        $appliance = $saveProjectAppliance($solarProject, $request->validated(), $appliance);

        return $this->backToSpace($solarProject, $appliance->space)
            ->with('status', 'Se actualizó '.$catalog->label($appliance->appliance_key).'.');
    }

    public function destroy(
        Request $request,
        SolarProject $solarProject,
        SolarProjectAppliance $appliance,
        RemoveProjectAppliance $removeProjectAppliance,
        ApplianceCatalog $catalog,
    ): RedirectResponse {
        abort_unless($request->user()->can('manage', $solarProject), 403);

        $space = $appliance->space;
        $label = $catalog->label($appliance->appliance_key);
        $removeProjectAppliance($solarProject, $appliance);

        return $this->backToSpace($solarProject, $space)->with('status', "Se quitó {$label} del proyecto.");
    }

    private function backToSpace(SolarProject $solarProject, ?string $space): RedirectResponse
    {
        $space = PropertyType::spaceOf($solarProject->property_type, $space);

        return redirect()->to(route('solar-projects.consumption', $solarProject).'#espacio-'.$space);
    }

    private function spaceLabel(SolarProject $solarProject, ?string $space): string
    {
        $spaces = PropertyType::spaces($solarProject->property_type);

        return $spaces[PropertyType::spaceOf($solarProject->property_type, $space)];
    }
}
