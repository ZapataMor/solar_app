<?php

namespace Tests\Feature;

use App\Models\SolarProject;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_projects_are_located_in_a_municipality(): void
    {
        $this->seed(DatabaseSeeder::class);

        $locations = SolarProject::with('municipality')->get()
            ->mapWithKeys(fn (SolarProject $project) => [$project->name => [
                $project->municipality?->name,
                $project->location_type,
                (float) $project->latitude === (float) $project->municipality?->latitude,
            ]])
            ->all();

        $this->assertSame([
            'Vivienda familiar Riohacha' => ['Riohacha', 'urbana', true],
            'Local comercial centro' => ['Maicao', 'urbana', true],
            'Institución educativa rural' => ['Uribia', 'rural', true],
        ], $locations);
    }

    /**
     * The three demo projects of the pitch: one per kind of place, with Air-e tariffs and real appliances.
     */
    public function test_the_demo_projects_are_a_house_a_business_and_an_institution(): void
    {
        $this->seed(DatabaseSeeder::class);
        // Seeding again updates them instead of duplicating them.
        $this->seed(DatabaseSeeder::class);

        $projects = SolarProject::with(['user', 'technicalParameter', 'appliances'])->orderBy('id')->get();

        $this->assertCount(3, $projects);
        $this->assertSame(['cliente'], $projects->pluck('user.username')->unique()->values()->all());

        // None writes its own tariff: they follow the reference one (ADR-0015).
        $this->assertTrue($projects->every(fn (SolarProject $project) => $project->usesReferenceEnergyRate()));

        $summary = $projects->mapWithKeys(fn (SolarProject $project) => [$project->property_type => [
            $project->location_name,
            (float) $project->energy_rate_cop_kwh,
            round($project->monthlyConsumption()),
            (float) $project->technicalParameter->available_area_m2,
            $project->appliances->count(),
        ]])->all();

        $this->assertSame([
            'house' => ['Riohacha, La Guajira, Colombia', 890.0, 534.0, 38.0, 14],
            'business' => ['Maicao, La Guajira, Colombia', 1068.0, 1115.0, 50.0, 11],
            'institution' => ['Uribia, La Guajira, Colombia', 890.0, 897.0, 130.0, 12],
        ], $summary);
    }
}
