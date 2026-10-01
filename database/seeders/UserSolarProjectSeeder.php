<?php

namespace Database\Seeders;

use App\Actions\SolarProjects\SyncProjectConsumption;
use App\Domain\Property\PropertyType;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample projects of the "cliente" account: one per kind of property, with their appliances
 * by space (ADR-0013). Their consumption comes from those appliances, as in the app.
 */
class UserSolarProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(SyncProjectConsumption $syncProjectConsumption): void
    {
        $user = User::where('username', 'cliente')->firstOrFail();

        $projects = [
            [
                // By name (LaGuajiraMunicipalitySeeder runs first); the coordinates come from it.
                'municipality' => 'Riohacha',

                'project' => [
                    'name' => 'Vivienda familiar Riohacha',
                    'property_type' => PropertyType::HOUSE,
                    'location_type' => 'urbana',
                    'description' => 'Sistema fotovoltaico residencial para cubrir consumo basico del hogar.',
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',
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

                // Hogar con nevera, TV, abanicos, lavadora y aires en las habitaciones (≈ 750 kWh/mes).
                // [space, appliance, variant, quantity, hours per day]
                'appliances' => [
                    ['kitchen', 'fridge', 'medium.conventional', 1, 24],
                    ['kitchen', 'microwave', 'default', 1, 2 / 7],
                    ['kitchen', 'blender', 'default', 1, 1 / 7],
                    ['living', 'tv', '43', 1, 5],
                    ['living', 'fan', 'stand', 2, 8],
                    ['living', 'lighting', 'led', 6, 6],
                    ['bedrooms', 'air_conditioner', '12000.conventional', 2, 8],
                    ['bedrooms', 'fan', 'ceiling', 2, 8],
                    ['laundry', 'washing_machine', 'up_to_12', 1, 4 / 7],
                    ['laundry', 'water_pump', 'half_hp', 1, 1],
                    ['laundry', 'iron', 'default', 1, 2 / 7],
                    ['other', 'router', 'default', 1, 24],
                ],
            ],

            [
                'municipality' => 'Maicao',

                'project' => [
                    'name' => 'Local comercial centro',
                    'property_type' => PropertyType::BUSINESS,
                    'location_type' => 'urbana',
                    'description' => 'Proyecto solar para reducir costos de energia en horario diurno.',
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',
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

                // Tienda con enfriadores, vitrina, aire y computadores (≈ 1.200 kWh/mes).
                'appliances' => [
                    ['sales', 'beverage_cooler', 'two_doors', 1, 24],
                    ['sales', 'display_case', 'default', 1, 24],
                    ['sales', 'air_conditioner', '18000.conventional', 1, 10],
                    ['sales', 'lighting', 'led', 12, 12],
                    ['sales', 'tv', '43', 1, 10],
                    ['office', 'computer', 'desktop', 2, 10],
                    ['office', 'router', 'default', 1, 24],
                    ['storage', 'freezer', 'chest_large', 1, 24],
                    ['storage', 'fridge', 'large.conventional', 1, 24],
                ],
            ],

            [
                'municipality' => 'Uribia',

                'project' => [
                    'name' => 'Institucion educativa rural',
                    'property_type' => PropertyType::INSTITUTION,
                    'location_type' => 'rural',
                    'description' => 'Dimensionamiento inicial para aulas, oficina administrativa y equipos basicos.',
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',
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

                // Aulas con abanicos y luces, oficinas con aires y computadores, comedor escolar (≈ 1.350 kWh/mes).
                'appliances' => [
                    ['classrooms', 'fan', 'ceiling', 12, 6],
                    ['classrooms', 'lighting', 'led', 30, 6],
                    ['classrooms', 'tv', '55', 2, 4],
                    ['offices', 'computer', 'desktop', 6, 8],
                    ['offices', 'air_conditioner', '12000.conventional', 2, 8],
                    ['offices', 'router', 'default', 1, 24],
                    ['kitchen', 'fridge', 'large.conventional', 2, 24],
                    ['kitchen', 'freezer', 'chest_large', 1, 24],
                    ['common', 'water_pump', 'one_hp', 1, 2],
                    ['common', 'lighting', 'led', 10, 12],
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

            $project->appliances()->delete();
            $project->appliances()->createMany(array_map(fn (array $row) => [
                'space' => $row[0],
                'appliance_key' => $row[1],
                'variant_key' => $row[2],
                'quantity' => $row[3],
                'hours_per_day' => round($row[4], 2),
            ], $projectData['appliances']));

            // Consumption, suggested power and quote follow the appliances, as in the app.
            $syncProjectConsumption($project);
        }
    }
}
