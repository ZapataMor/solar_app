<?php

namespace App\Http\Controllers;

use App\Actions\SolarProjects\BuildConsumptionDiary;
use App\Actions\SolarProjects\CheckCalculationFreshness;
use App\Actions\SolarProjects\RemoveProjectAppliance;
use App\Actions\SolarProjects\SaveProjectAppliance;
use App\Actions\SolarProjects\SizeProjectSystem;
use App\Domain\Consumption\ApplianceCatalog;
use App\Domain\Property\PropertyType;
use App\Http\Requests\ProjectApplianceRequest;
use App\Models\SolarProject;
use App\Models\SolarProjectAppliance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The consumption diary of a project (ADR-0013): appliances added space by space after creating it.
 *
 * Saving answers JSON with the re-rendered diary when the page asks for it (no reload), and
 * redirects back to the space otherwise (requests without JavaScript).
 */
class SolarProjectConsumptionController extends Controller
{
    /**
     * Resolved on each request, not in the constructor: the router keeps the controller between requests
     * of one process (tests, Octane), and the catalog changes when an administrator edits it (ADR-0017).
     */
    private function catalog(): ApplianceCatalog
    {
        return app(ApplianceCatalog::class);
    }

    public function show(Request $request, SolarProject $solarProject): View
    {
        abort_unless($request->user()->can('manage', $solarProject), 403);

        return view('solar-projects.consumption', [
            ...$this->diaryData($solarProject),
            // What can be added, plus any hidden appliance this diary already uses (its rows stay editable).
            'applianceCatalog' => $this->catalog()->offered() + array_intersect_key(
                $this->catalog()->all(),
                array_flip($solarProject->appliances()->pluck('appliance_key')->all()),
            ),
            'primarySegments' => PropertyType::applianceSegments($solarProject->property_type),
        ]);
    }

    public function store(
        ProjectApplianceRequest $request,
        SolarProject $solarProject,
        SaveProjectAppliance $saveProjectAppliance,
    ): JsonResponse|RedirectResponse {
        abort_unless($request->user()->can('manage', $solarProject), 403);

        $appliance = $saveProjectAppliance($solarProject, $request->validated());

        return $this->respond(
            $request,
            $solarProject,
            'Se agregó '.$this->catalog()->label($appliance->appliance_key).' a '.$this->spaceLabel($solarProject, $appliance->space).'.',
            $appliance->space,
            $appliance->id,
        );
    }

    public function update(
        ProjectApplianceRequest $request,
        SolarProject $solarProject,
        SolarProjectAppliance $appliance,
        SaveProjectAppliance $saveProjectAppliance,
    ): JsonResponse|RedirectResponse {
        abort_unless($request->user()->can('manage', $solarProject), 403);

        $appliance = $saveProjectAppliance($solarProject, $request->validated(), $appliance);

        return $this->respond(
            $request,
            $solarProject,
            'Se actualizó '.$this->catalog()->label($appliance->appliance_key).'.',
            $appliance->space,
            $appliance->id,
        );
    }

    public function destroy(
        Request $request,
        SolarProject $solarProject,
        SolarProjectAppliance $appliance,
        RemoveProjectAppliance $removeProjectAppliance,
    ): JsonResponse|RedirectResponse {
        abort_unless($request->user()->can('manage', $solarProject), 403);

        $space = $appliance->space;
        $label = $this->catalog()->label($appliance->appliance_key);
        $removeProjectAppliance($solarProject, $appliance);

        return $this->respond($request, $solarProject, "Se quitó {$label} del proyecto.", $space);
    }

    /**
     * What the diary content (summary and spaces) is rendered with.
     *
     * @return array<string, mixed>
     */
    private function diaryData(SolarProject $solarProject): array
    {
        return [
            'solarProject' => $solarProject,
            'diary' => app(BuildConsumptionDiary::class)($solarProject),
            'calculationFreshness' => app(CheckCalculationFreshness::class)($solarProject),
            'usesBillConsumption' => ! $solarProject->appliances()->exists() && $solarProject->monthlyConsumption() > 0,
            // ADR-0014: how much of these appliances the roof covers; it changes with every appliance.
            'sizing' => app(SizeProjectSystem::class)($solarProject),
        ];
    }

    private function respond(
        Request $request,
        SolarProject $solarProject,
        string $message,
        ?string $space,
        ?int $applianceId = null,
    ): JsonResponse|RedirectResponse {
        $space = PropertyType::spaceOf($solarProject->property_type, $space);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'space' => $space,
                'appliance' => $applianceId,
                'html' => view('solar-projects.partials.consumption-diary-content', $this->diaryData($solarProject))->render(),
            ]);
        }

        return redirect()
            ->to(route('solar-projects.consumption', $solarProject).'#espacio-'.$space)
            ->with('status', $message);
    }

    private function spaceLabel(SolarProject $solarProject, ?string $space): string
    {
        return PropertyType::spaces($solarProject->property_type)[PropertyType::spaceOf($solarProject->property_type, $space)];
    }
}
