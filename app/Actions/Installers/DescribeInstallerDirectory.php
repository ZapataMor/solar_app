<?php

namespace App\Actions\Installers;

use App\Actions\SolarProjects\SizeProjectSystem;
use App\Domain\Installers\InstallerCoverage;
use App\Domain\Installers\QuoteRequestStatus;
use App\Models\Installer;
use App\Models\Municipality;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Use case: the installers directory a client sees (ADR-0021). One project is chosen, and the page
 * shows who covers its municipality and what was already asked of each one.
 *
 * The contact details travel only with a request already made: asking is the moment of contact, and
 * that is what the lead of ADR-0005 records. Administrators also see the hidden installers, so they
 * can find and edit them from the same page.
 */
final class DescribeInstallerDirectory
{
    public function __construct(
        private readonly SizeProjectSystem $sizeProjectSystem,
    ) {}

    /**
     * @return array{
     *     projects: Collection<int, SolarProject>,
     *     project: SolarProject|null,
     *     quotable: bool,
     *     municipalityName: string|null,
     *     scene: array{panels: int, panelsFit: int, roofArea: float, panelArea: float}|null,
     *     installers: list<array<string, mixed>>,
     * }
     */
    public function __invoke(User $user, ?int $projectId = null): array
    {
        $projects = $user->solarProjects()->orderBy('name')->get();
        $project = $this->chosenProject($projects, $projectId);
        $requests = $project === null
            ? collect()
            : $project->quoteRequests()->get()->keyBy('installer_id');

        $installers = Installer::query()
            ->with('municipalities:id,name')
            ->withCount('quoteRequests')
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->active())
            ->covering($project?->municipality_id)
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();

        $activeMunicipalities = Municipality::query()->active()->count();

        return [
            'projects' => $projects,
            'project' => $project,
            // Without consumption there is no estimate to quote (ADR-0013, ADR-0020).
            'quotable' => $project !== null && $project->monthlyConsumption() > 0,
            'municipalityName' => $project?->municipality?->name,
            'scene' => $this->scene($project),
            'installers' => $installers->map(function (Installer $installer) use ($requests, $activeMunicipalities): array {
                $request = $requests->get($installer->id);

                return [
                    'id' => $installer->id,
                    'name' => $installer->name,
                    'tagline' => $installer->tagline,
                    'description' => $installer->description,
                    'yearsExperience' => $installer->years_experience,
                    'active' => $installer->active,
                    'coverage' => InstallerCoverage::text(
                        $installer->municipalities->pluck('name')->all(),
                        $activeMunicipalities,
                    ),
                    'requested' => $request !== null,
                    'requestedAt' => $request?->created_at,
                    'statusLabel' => $request !== null ? QuoteRequestStatus::label($request->status) : null,
                    'contactName' => $request !== null ? $installer->contact_name : null,
                    'phone' => $request !== null ? $installer->phone : null,
                    'whatsapp' => $request !== null ? $installer->whatsappNumber() : null,
                    'email' => $request !== null ? $installer->email : null,
                    'requests' => $installer->quote_requests_count,
                ];
            })->all(),
        ];
    }

    /**
     * The project of the query string, or the first one that can already be quoted.
     *
     * @param  Collection<int, SolarProject>  $projects
     */
    private function chosenProject(Collection $projects, ?int $projectId): ?SolarProject
    {
        $project = $projectId !== null ? $projects->firstWhere('id', $projectId) : null;

        $project ??= $projects->first(fn (SolarProject $candidate): bool => $candidate->monthlyConsumption() > 0)
            ?? $projects->first();

        return $project?->loadMissing('municipality');
    }

    /**
     * What the 3D illustration draws: the roof of the chosen project with the panels its consumption
     * needs (ADR-0012, ADR-0014). Null without technical parameters, and the figure is left out.
     *
     * @return array{panels: int, panelsFit: int, roofArea: float, panelArea: float}|null
     */
    private function scene(?SolarProject $project): ?array
    {
        $sized = $project === null ? null : ($this->sizeProjectSystem)($project);

        if ($sized === null) {
            return null;
        }

        return [
            'panels' => $sized['sizing']->panelsInstalled,
            'panelsFit' => $sized['sizing']->panelsThatFit,
            'roofArea' => (float) $project->technicalParameter->available_area_m2,
            'panelArea' => (float) $project->technicalParameter->panel_area_m2,
        ];
    }
}
