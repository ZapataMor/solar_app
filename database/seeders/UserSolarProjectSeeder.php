<?php

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSolarProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('username', 'cliente')->firstOrFail();

        $projects = [
            [
                // By name (LaGuajiraMunicipalitySeeder runs first); the coordinates come from it.
                'municipality' => 'Riohacha',

                'project' => [
                    'name' => 'Vivienda familiar Riohacha',
                    'location_type' => 'urbana',
                    'description' => 'Sistema fotovoltaico residencial para cubrir consumo basico del hogar.',
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',

                    // Hogar con nevera, TV, ventiladores, lavadora y uso moderado de AA
                    'monthly_consumption_kwh' => 850,

                    'energy_rate_cop_kwh' => 930,
                ],

                'technical_parameters' => [

                    // Espacio residencial promedio
                    'available_area_m2' => 38,

                    // Espacio realmente utilizable
                    'usable_area_percentage' => 75,

                    // Panel residencial moderno
                    'panel_power_w' => 550,

                    // Área promedio panel 550W
                    'panel_area_m2' => 2.58,

                    // Rendimiento realista residencial
                    'performance_ratio' => 0.85,

                    // Pérdidas normales
                    'system_losses_percentage' => 15,
                ],
            ],

            [
                'municipality' => 'Maicao',

                'project' => [
                    'name' => 'Local comercial centro',
                    'location_type' => 'urbana',
                    'description' => 'Proyecto solar para reducir costos de energia en horario diurno.',
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',

                    // Negocio con iluminación, computadores, vitrinas y aire acondicionado
                    'monthly_consumption_kwh' => 1650,

                    'energy_rate_cop_kwh' => 930,
                ],

                'technical_parameters' => [

                    // Techo comercial mediano
                    'available_area_m2' => 85,

                    // Área útil comercial
                    'usable_area_percentage' => 75,

                    // Panel estándar comercial
                    'panel_power_w' => 550,

                    'panel_area_m2' => 2.58,

                    // Sistema más optimizado
                    'performance_ratio' => 0.88,

                    // Menores pérdidas por mejor instalación
                    'system_losses_percentage' => 12,
                ],
            ],

            [
                'municipality' => 'Uribia',

                'project' => [
                    'name' => 'Institucion educativa rural',
                    'location_type' => 'rural',
                    'description' => 'Dimensionamiento inicial para aulas, oficina administrativa y equipos basicos.',
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',

                    // Aulas, ventiladores, computadores, impresoras e iluminación
                    'monthly_consumption_kwh' => 2400,

                    'energy_rate_cop_kwh' => 930,
                ],

                'technical_parameters' => [

                    // Cubierta amplia institucional
                    'available_area_m2' => 130,

                    // Menor porcentaje útil por divisiones y sombras
                    'usable_area_percentage' => 70,

                    // Paneles de alta eficiencia
                    'panel_power_w' => 580,

                    'panel_area_m2' => 2.65,

                    // Rendimiento estándar institucional
                    'performance_ratio' => 0.85,

                    // Pérdidas típicas
                    'system_losses_percentage' => 15,
                ],
            ],
        ];

        foreach ($projects as $projectData) {
            $municipality = Municipality::where('name', $projectData['municipality'])->firstOrFail();

            $project = $user->solarProjects()->updateOrCreate(
                ['name' => $projectData['project']['name']],
                [
                    ...$projectData['project'],
                    'municipality_id' => $municipality->id,
                    'latitude' => $municipality->latitude,
                    'longitude' => $municipality->longitude,
                ],
            );

            $project->technicalParameter()->updateOrCreate(
                ['solar_project_id' => $project->id],
                $projectData['technical_parameters'],
            );
        }
    }
}
