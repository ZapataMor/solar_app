<?php

namespace Database\Seeders;

use App\Actions\SolarProjects\CalculateSolarProject;
use App\Actions\SolarProjects\SyncProjectConsumption;
use App\Domain\Climate\NoClimateData;
use App\Domain\Property\PropertyType;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The three demo projects of the "cliente" account (pitch of 2026-10-15): a house in Riohacha, a
 * minimarket in Maicao whose roof falls short, and a rural school in Uribia. Each one has its
 * appliances by space (ADR-0013), and its consumption comes from them, as in the app.
 * DatabaseSeederTest pins these numbers.
 */
class UserSolarProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(SyncProjectConsumption $syncProjectConsumption, CalculateSolarProject $calculateSolarProject): void
    {
        $user = User::where('username', 'cliente')->firstOrFail();
        // From January up to today: never after today (AnalysisPeriod), where there is no climate data yet.
        $today = now(config('app.display_timezone', config('app.timezone')))->toDateString();

        $projects = [
            [
                // By name (LaGuajiraMunicipalitySeeder runs first); the coordinates come from it.
                'municipality' => 'Riohacha',

                'project' => [
                    'name' => 'Vivienda familiar Riohacha',
                    'property_type' => PropertyType::HOUSE,
                    'location_type' => 'urbana',
                    'description' => 'Casa estrato 3 de una familia de cuatro. Aire en la habitación principal y en la de los niños; motobomba para el tanque.',
                    'start_date' => '2026-01-01',
                    'end_date' => $today,
                    // No tariff of its own: it follows the reference one (ADR-0015), $890/kWh in a house.
                    'energy_rate_cop_kwh' => null,
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

                // Familia de cuatro: los aires de noche son casi el 70 % del consumo (≈ 535 kWh/mes, ≈ $476.000).
                // [space, appliance, variant, quantity, hours per day]
                'appliances' => [
                    ['kitchen', 'fridge', 'medium.conventional', 1, 24],
                    ['kitchen', 'microwave', 'default', 1, 0.25],
                    ['kitchen', 'blender', 'default', 1, 0.15],
                    ['living', 'tv', '43', 1, 5],
                    ['living', 'fan', 'stand', 2, 8],
                    ['living', 'lighting', 'led', 8, 6],
                    ['bedrooms', 'air_conditioner', '12000.inverter', 1, 8],
                    ['bedrooms', 'air_conditioner', '9000.conventional', 1, 6],
                    ['bedrooms', 'fan', 'ceiling', 2, 8],
                    ['bedrooms', 'computer', 'laptop', 1, 4],
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
                    'description' => 'Minimercado en el centro de Maicao, abierto de 7 a. m. a 9 p. m. Local angosto: el techo no alcanza para todo el consumo.',
                    'start_date' => '2026-01-01',
                    'end_date' => $today,
                    // Reference tariff plus the commercial contribution: $890 × 1,2 = $1.068/kWh.
                    'energy_rate_cop_kwh' => null,
                ],

                'technical_parameters' => [
                    // Local angosto del centro: caben 14 paneles y harían falta unos 17.
                    'available_area_m2' => 50,
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

                // Minimercado: la refrigeración trabaja día y noche y el aire, en horario de atención
                // (≈ 1.115 kWh/mes, ≈ $1,19 M).
                'appliances' => [
                    ['sales', 'beverage_cooler', 'two_doors', 1, 24],
                    ['sales', 'beverage_cooler', 'one_door', 1, 24],
                    ['sales', 'display_case', 'default', 1, 24],
                    ['sales', 'air_conditioner', '18000.inverter', 1, 10],
                    ['sales', 'lighting', 'led', 12, 12],
                    ['sales', 'fan', 'stand', 2, 10],
                    ['sales', 'tv', '43', 1, 10],
                    ['office', 'computer', 'desktop', 1, 12],
                    ['office', 'computer', 'laptop', 1, 8],
                    ['office', 'router', 'default', 1, 24],
                    ['storage', 'freezer', 'chest_large', 2, 24],
                ],
            ],

            [
                'municipality' => 'Uribia',

                'project' => [
                    'name' => 'Institución educativa rural',
                    'property_type' => PropertyType::INSTITUTION,
                    'location_type' => 'rural',
                    'description' => 'Colegio rural de jornada de mañana, con sala de sistemas y comedor escolar (PAE). Clases de lunes a viernes.',
                    'start_date' => '2026-01-01',
                    'end_date' => $today,
                    // Official user: the reference tariff without subsidy or contribution ($890/kWh).
                    'energy_rate_cop_kwh' => null,
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

                // Aulas, sala de sistemas, rectoría y comedor escolar (≈ 900 kWh/mes, ≈ $800.000). Lo que
                // solo se usa en clase cuenta 5 días de 7; la refrigeración y la vigilancia, todos los días.
                'appliances' => [
                    ['classrooms', 'fan', 'ceiling', 16, 6 * 5 / 7],
                    ['classrooms', 'lighting', 'led', 24, 5 * 5 / 7],
                    ['classrooms', 'tv', '55', 2, 3 * 5 / 7],
                    ['classrooms', 'computer', 'laptop', 20, 4 * 5 / 7],
                    ['offices', 'computer', 'desktop', 3, 8 * 5 / 7],
                    ['offices', 'air_conditioner', '12000.inverter', 1, 8 * 5 / 7],
                    ['offices', 'router', 'default', 1, 24],
                    ['kitchen', 'fridge', 'large.conventional', 2, 24],
                    ['kitchen', 'freezer', 'chest_large', 2, 24],
                    ['kitchen', 'blender', 'default', 1, 1 * 5 / 7],
                    ['common', 'water_pump', 'one_hp', 1, 3],
                    ['common', 'lighting', 'led', 12, 12],
                ],
            ],
        ];

        foreach ($projects as $projectData) {
            $municipality = Municipality::where('name', $projectData['municipality'])->firstOrFail();

            $project = $user->solarProjects()->updateOrCreate(
                ['name' => $projectData['project']['name']],
                [
                    ...$projectData['project'],
                    // Same as the app (SaveSolarProject); the column's default says Riohacha.
                    'location_name' => "{$municipality->name}, La Guajira, Colombia",
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

            // Ready for the demo when there is climate data; on a new database they wait for "Recalcular".
            try {
                $calculateSolarProject($project->fresh());
            } catch (NoClimateData) {
                $this->command?->warn("{$project->name}: sin datos climáticos todavía; calcúlalo después.");
            }
        }
    }
}
