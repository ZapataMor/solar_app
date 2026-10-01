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
            'Institucion educativa rural' => ['Uribia', 'rural', true],
        ], $locations);
    }
}
