<?php

namespace Tests\Feature;

use App\Models\SolarProject;
use App\Models\User;
use App\Models\WeatherStationReading;
use App\Services\WeatherStationImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('api-data.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_view_data_from_both_apis(): void
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());

        $solarProject->weatherData()->create([
            'date_time' => '2026-05-21 00:00:00',
            'allsky_sfc_sw_dwn' => 5.245,
            't2m' => 28.2,
            'rh2m' => 70.4,
            'prectotcorr' => 0.0123,
            'ws10m' => 4.8,
        ]);

        $solarProject->weatherStationReadings()->create([
            'device_code' => 'ST-001',
            'temperature' => 29.1,
            'humidity' => 71.2,
            'co2' => 410,
            'pm25' => 8.2,
            'pm10' => 17.5,
            'uva' => 1.1,
            'uvb' => 0.4,
            'uv_index' => 6.2,
            'solar_radiation' => 5.6,
            'measured_at' => '2026-05-21 12:30:00',
        ]);

        $this->actingAs($user)
            ->get(route('api-data.index'))
            ->assertOk()
            ->assertSee('Datos APIs')
            ->assertSee('NASA POWER')
            ->assertSee('Centro meteorologico')
            ->assertSee('Obtener datos NASA POWER')
            ->assertSee('Obtener datos de estacion')
            ->assertSee($solarProject->name)
            ->assertSee('ST-001')
            ->assertSee('2026-05-21 12:30');
    }

    public function test_user_can_fetch_nasa_data_from_api_data_page(): void
    {
        Http::fake([
            'power.larc.nasa.gov/*' => Http::response([
                'properties' => [
                    'parameter' => [
                        'ALLSKY_SFC_SW_DWN' => [
                            '20260521' => 5.2,
                        ],
                        'T2M' => [
                            '20260521' => 28.1,
                        ],
                        'RH2M' => [
                            '20260521' => 70.5,
                        ],
                        'PRECTOTCORR' => [
                            '20260521' => 0.01,
                        ],
                        'WS10M' => [
                            '20260521' => 4.2,
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());

        $this->actingAs($user)
            ->post(route('api-data.fetch-nasa-data'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'NASA POWER sincronizado. Nuevos: 1. Existentes actualizados: 0.')
            ->assertRedirect();

        $this->assertDatabaseHas('api_weather_data', [
            'solar_project_id' => $solarProject->id,
            'date_time' => '2026-05-21 00:00:00',
            'allsky_sfc_sw_dwn' => 5.2,
        ]);
    }

    public function test_user_can_fetch_weather_station_data_from_api_data_page(): void
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());

        $this->app->instance(WeatherStationImportService::class, new class extends WeatherStationImportService
        {
            public function importAll(?SolarProject $solarProject = null): array
            {
                WeatherStationReading::query()->create([
                    'solar_project_id' => $solarProject?->id,
                    'device_code' => 'ST-API',
                    'temperature' => 29.1,
                    'measured_at' => '2026-05-21 12:30:00',
                ]);

                WeatherStationReading::query()->create([
                    'solar_project_id' => $solarProject?->id,
                    'device_code' => 'ST-OUT-OF-RANGE',
                    'temperature' => 27.4,
                    'measured_at' => '2026-01-01 08:00:00',
                ]);

                return [
                    'received' => 2,
                    'created' => 2,
                    'updated' => 0,
                    'skipped' => 0,
                ];
            }
        });

        $this->actingAs($user)
            ->post(route('api-data.fetch-weather-station-data'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Datos del centro meteorologico obtenidos desde el endpoint. Nuevos: 2. Actualizados: 0. Existentes omitidos: 0. Lecturas asociadas: 0. Total visible: 2.')
            ->assertRedirect();

        $this->assertDatabaseHas('weather_station_readings', [
            'solar_project_id' => $solarProject->id,
            'device_code' => 'ST-API',
        ]);

        $this->assertDatabaseHas('weather_station_readings', [
            'solar_project_id' => $solarProject->id,
            'device_code' => 'ST-OUT-OF-RANGE',
        ]);
    }

    public function test_weather_station_import_service_fetches_readings_from_public_api(): void
    {
        config([
            'services.weather_station.endpoint' => 'https://meteoestacion.desarrollougmaicao.com/api_publica.php',
            'services.weather_station.device_code' => 'METEOESTACION',
        ]);

        Http::fake([
            'meteoestacion.desarrollougmaicao.com/api_publica.php' => Http::response([
                'status' => 'success',
                'total' => 1,
                'datos' => [
                    [
                        'id' => 1,
                        'fecha' => '2025-08-20 10:28:47',
                        'temperatura' => '35.9',
                        'humedad' => '58.2',
                        'sensacion_termica' => '0.0',
                        'co2' => 1090,
                        'pm25' => '9.4',
                        'pm10' => '46.7',
                        'uva' => '25.3',
                        'uvb' => '24.0',
                        'indice_uv' => '0.1',
                    ],
                ],
            ]),
        ]);

        $imported = app(WeatherStationImportService::class)->importAll();

        $this->assertSame(['received' => 1, 'created' => 1, 'updated' => 0, 'skipped' => 0], $imported);
        $this->assertDatabaseHas('weather_station_readings', [
            'device_code' => 'METEOESTACION',
            'measured_at' => '2025-08-20 10:28:47',
            'temperature' => 35.9,
            'humidity' => 58.2,
            'co2' => 1090,
        ]);
    }

    public function test_weather_station_import_service_only_persists_new_readings(): void
    {
        config([
            'services.weather_station.endpoint' => 'https://meteoestacion.desarrollougmaicao.com/api_publica.php',
            'services.weather_station.device_code' => 'METEOESTACION',
            'services.weather_station.since_parameter' => 'fecha_desde',
        ]);

        WeatherStationReading::query()->create([
            'device_code' => 'METEOESTACION',
            'temperature' => 35.9,
            'measured_at' => '2025-08-20 10:28:47',
        ]);

        Http::fake([
            'meteoestacion.desarrollougmaicao.com/api_publica.php*' => Http::response([
                'status' => 'success',
                'total' => 2,
                'datos' => [
                    [
                        'id' => 1,
                        'fecha' => '2025-08-20 10:28:47',
                        'temperatura' => '99.9',
                    ],
                    [
                        'id' => 2,
                        'fecha' => '2025-08-20 10:35:00',
                        'temperatura' => '36.4',
                    ],
                ],
            ]),
        ]);

        $imported = app(WeatherStationImportService::class)->importAll();

        $this->assertSame(['received' => 2, 'created' => 1, 'updated' => 0, 'skipped' => 1], $imported);
        $this->assertDatabaseHas('weather_station_readings', [
            'device_code' => 'METEOESTACION',
            'measured_at' => '2025-08-20 10:28:47',
            'temperature' => 35.9,
        ]);
        $this->assertDatabaseHas('weather_station_readings', [
            'device_code' => 'METEOESTACION',
            'measured_at' => '2025-08-20 10:35:00',
            'temperature' => 36.4,
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://meteoestacion.desarrollougmaicao.com/api_publica.php?fecha_desde=2025-08-20%2010%3A28%3A47');
    }

    public function test_weather_station_fetch_command_stores_valid_readings_without_duplicates(): void
    {
        config([
            'services.weather_station.endpoint' => 'https://meteoestacion.desarrollougmaicao.com/api_publica.php',
            'services.weather_station.device_code' => 'METEOESTACION',
        ]);

        Http::fake([
            'meteoestacion.desarrollougmaicao.com/api_publica.php*' => Http::response([
                'status' => 'success',
                'datos' => [
                    [
                        'fecha' => '2025-08-20 10:28:47',
                        'temperatura' => '35.9',
                        'radiacion' => '512.4',
                    ],
                    [
                        'fecha' => '2025-08-20 10:28:47',
                        'temperatura' => '99.9',
                        'radiacion' => '999.9',
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());

        $this->artisan('weather-station:fetch')
            ->expectsOutput('Consulta finalizada. Recibidos: 2. Guardados: 1. Existentes omitidos: 1. Proyectos con error: 0.')
            ->assertSuccessful();

        $this->artisan('weather-station:fetch')
            ->expectsOutput('Consulta finalizada. Recibidos: 2. Guardados: 0. Existentes omitidos: 2. Proyectos con error: 0.')
            ->assertSuccessful();

        $this->assertSame(1, WeatherStationReading::query()->whereBelongsTo($solarProject)->count());
        $this->assertDatabaseHas('weather_station_readings', [
            'solar_project_id' => $solarProject->id,
            'device_code' => 'METEOESTACION',
            'measured_at' => '2025-08-20 10:28:47',
            'temperature' => 35.9,
            'solar_radiation' => 512.4,
        ]);
    }

    public function test_weather_station_fetch_command_handles_api_errors(): void
    {
        config([
            'services.weather_station.endpoint' => 'https://meteoestacion.desarrollougmaicao.com/api_publica.php',
        ]);

        Http::fake([
            'meteoestacion.desarrollougmaicao.com/api_publica.php*' => Http::response([], 500),
        ]);

        $user = User::factory()->create();
        $user->solarProjects()->create($this->projectAttributes());

        $this->artisan('weather-station:fetch')
            ->expectsOutput('Consulta finalizada. Recibidos: 0. Guardados: 0. Existentes omitidos: 0. Proyectos con error: 1.')
            ->assertFailed();
    }

    public function test_user_does_not_view_other_users_associated_api_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherProject = $otherUser->solarProjects()->create([
            ...$this->projectAttributes(),
            'name' => 'Proyecto externo',
        ]);

        $otherProject->weatherData()->create([
            'date_time' => '2026-05-21 00:00:00',
            'allsky_sfc_sw_dwn' => 5.245,
        ]);

        $otherProject->weatherStationReadings()->create([
            'device_code' => 'ST-PRIVATE',
            'temperature' => 29.1,
            'measured_at' => '2026-05-21 12:30:00',
        ]);

        $this->actingAs($user)
            ->get(route('api-data.index'))
            ->assertOk()
            ->assertDontSee('Proyecto externo')
            ->assertDontSee('ST-PRIVATE');
    }

    /**
     * @return array<string, mixed>
     */
    private function projectAttributes(): array
    {
        return [
            'name' => 'Sistema solar institucional',
            'description' => 'Proyecto base para simulacion en Riohacha.',
            'start_date' => '2026-05-21',
            'end_date' => '2026-05-22',
            'annual_consumption_kwh' => 24500,
            'energy_rate_cop_kwh' => 820,
        ];
    }
}
