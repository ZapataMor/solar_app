<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSolarProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('email', 'user@solar-app.test')->firstOrFail();

        $projects = [
            [
                'project' => [
                    'name' => 'Vivienda familiar Riohacha',
                    'description' => 'Sistema fotovoltaico residencial para cubrir consumo basico del hogar.',
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',
                    'annual_consumption_kwh' => 4200,
                    'energy_rate_cop_kwh' => 890,
                ],
                'technical_parameters' => [
                    'available_area_m2' => 42,
                    'usable_area_percentage' => 80,
                    'panel_power_w' => 550,
                    'panel_area_m2' => 2.58,
                    'performance_ratio' => 0.800,
                    'system_losses_percentage' => 14,
                ],
            ],
            [
                'project' => [
                    'name' => 'Local comercial centro',
                    'description' => 'Proyecto solar para reducir costos de energia en horario diurno.',
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',
                    'annual_consumption_kwh' => 9600,
                    'energy_rate_cop_kwh' => 920,
                ],
                'technical_parameters' => [
                    'available_area_m2' => 85,
                    'usable_area_percentage' => 75,
                    'panel_power_w' => 550,
                    'panel_area_m2' => 2.58,
                    'performance_ratio' => 0.820,
                    'system_losses_percentage' => 12,
                ],
            ],
            [
                'project' => [
                    'name' => 'Institucion educativa rural',
                    'description' => 'Dimensionamiento inicial para aulas, oficina administrativa y equipos basicos.',
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',
                    'annual_consumption_kwh' => 14800,
                    'energy_rate_cop_kwh' => 870,
                ],
                'technical_parameters' => [
                    'available_area_m2' => 130,
                    'usable_area_percentage' => 70,
                    'panel_power_w' => 580,
                    'panel_area_m2' => 2.65,
                    'performance_ratio' => 0.800,
                    'system_losses_percentage' => 15,
                ],
            ],
        ];

        foreach ($projects as $projectData) {
            $project = $user->solarProjects()->updateOrCreate(
                ['name' => $projectData['project']['name']],
                $projectData['project'],
            );

            $project->technicalParameter()->updateOrCreate(
                ['solar_project_id' => $project->id],
                $projectData['technical_parameters'],
            );
        }
    }
}
