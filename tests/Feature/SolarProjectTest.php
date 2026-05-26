<?php

namespace Tests\Feature;

use App\Models\ApiWeatherData;
use App\Models\Municipality;
use App\Models\SolarProject;
use App\Models\User;
use App\Models\WeatherStationReading;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SolarProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_solar_project_with_municipality_quote_and_technical_parameters(): void
    {
        $user = User::factory()->create();
        $municipality = $this->seedMunicipalityPrice('Maicao', 'Media Guajira', 'Base urbana', 'urbana', 4000000, 1.00);

        $response = $this->actingAs($user)->post(route('solar-projects.store'), [
            ...$this->validPayload(),
            'location_name' => 'Otra ciudad',
            'latitude' => 11.3778,
            'longitude' => -72.2389,
        ]);

        $solarProject = SolarProject::query()->first();

        $response->assertRedirect(route('solar-projects.show', $solarProject));

        $this->assertDatabaseHas('solar_projects', [
            'user_id' => $user->id,
            'name' => 'Sistema solar institucional',
            'location_name' => 'Maicao, La Guajira, Colombia',
            'municipality_id' => $municipality->id,
            'latitude' => 11.3778,
            'longitude' => -72.2389,
            'location_type' => 'urbana',
            'required_power_kw' => 5,
            'base_price_per_kw' => 4000000,
            'logistic_factor_used' => 1,
            'final_price_per_kw_used' => 4000000,
            'estimated_installation_cost' => 20000000,
            'monthly_consumption_kwh' => 2000,
            'annual_consumption_kwh' => 24000,
        ]);

        $this->assertSame(66.67, round((float) $solarProject->daily_consumption_kwh, 2));

        $this->assertDatabaseHas('technical_parameters', [
            'solar_project_id' => $solarProject->id,
            'available_area_m2' => 120,
            'performance_ratio' => 0.86,
            'system_losses_percentage' => 14,
        ]);
    }

    public function test_user_can_create_a_solar_project_without_end_date(): void
    {
        $user = User::factory()->create();
        $payload = $this->validPayload();
        unset($payload['end_date']);

        $response = $this->actingAs($user)->post(route('solar-projects.store'), $payload);

        $solarProject = SolarProject::query()->first();

        $response->assertRedirect(route('solar-projects.show', $solarProject));
        $this->assertSame('2017-01-01', $solarProject->end_date->format('Y-m-d'));
    }

    public function test_user_can_create_a_solar_project_without_required_power_kw(): void
    {
        $user = User::factory()->create();
        $payload = $this->validPayload();
        unset($payload['required_power_kw']);

        $response = $this->actingAs($user)->post(route('solar-projects.store'), $payload);

        $solarProject = SolarProject::query()->first();

        $response->assertRedirect(route('solar-projects.show', $solarProject));
        $this->assertSame(13.37, round((float) $solarProject->required_power_kw, 2));
    }

    public function test_user_cannot_view_another_users_project(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $solarProject = $owner->solarProjects()->create($this->projectAttributes());

        $response = $this->actingAs($otherUser)->get(route('solar-projects.show', $solarProject));

        $response->assertForbidden();
    }

    public function test_fetch_weather_data_stores_daily_values_without_duplicates(): void
    {
        Http::fake([
            'power.larc.nasa.gov/*' => Http::response([
                'properties' => [
                    'parameter' => [
                        'ALLSKY_SFC_SW_DWN' => [
                            '20170101' => 5.2,
                            '20170102' => 5.4,
                        ],
                        'T2M' => [
                            '20170101' => 26.3,
                            '20170102' => 26.1,
                        ],
                        'RH2M' => [
                            '20170101' => 78.5,
                            '20170102' => 80.1,
                        ],
                        'PRECTOTCORR' => [
                            '20170101' => 0,
                            '20170102' => 0.03,
                        ],
                        'WS10M' => [
                            '20170101' => 5.2,
                            '20170102' => 5.4,
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());
        $solarProject->technicalParameter()->create($this->technicalParameterAttributes());
        $solarProject->weatherData()->create([
            'date_time' => '2017-01-01 01:00:00',
            'allsky_sfc_sw_dwn' => 500,
        ]);

        $this->actingAs($user)
            ->post(route('solar-projects.fetch-weather-data', $solarProject))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Datos climáticos sincronizados. Nuevos: 2. Existentes actualizados: 0. Total del proyecto: 3.')
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('solar-projects.fetch-weather-data', $solarProject))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Datos climáticos sincronizados. Nuevos: 0. Existentes actualizados: 2. Total del proyecto: 3.')
            ->assertRedirect();

        $this->assertSame(3, ApiWeatherData::query()
            ->whereBetween('date_time', [
                $solarProject->start_date->copy()->startOfDay(),
                $solarProject->end_date->copy()->endOfDay(),
            ])
            ->count());
        $this->assertDatabaseMissing('api_weather_data', [
            'solar_project_id' => $solarProject->id,
            'date_time' => '2017-01-01 01:00:00',
        ]);
        $this->assertDatabaseHas('api_weather_data', [
            'date_time' => '2017-01-02 00:00:00',
            'allsky_sfc_sw_dwn' => 5.4,
            'radiation_source' => 'nasa_real',
            'radiation_fallback_method' => 'nasa_real',
            't2m' => 26.1,
            'rh2m' => 80.1,
            'prectotcorr' => 0.03,
            'ws10m' => 5.4,
        ]);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://power.larc.nasa.gov/api/temporal/daily/point')
            && $request['parameters'] === 'ALLSKY_SFC_SW_DWN,T2M,RH2M,PRECTOTCORR,WS10M'
            && (float) $request['latitude'] === SolarProject::LATITUDE
            && (float) $request['longitude'] === SolarProject::LONGITUDE
            && $request['start'] === '20170101'
            && $request['end'] === '20170102'
            && $request['community'] === 'SB'
            && $request['time-standard'] === 'LST'
            && $request['format'] === 'JSON');
    }

    public function test_fetch_weather_data_estimates_radiation_when_allsky_is_missing(): void
    {
        Http::fake([
            'power.larc.nasa.gov/*' => Http::response([
                'properties' => [
                    'parameter' => [
                        'ALLSKY_SFC_SW_DWN' => [
                            '20170101' => 5.0,
                            '20170103' => 5.4,
                        ],
                        'T2M' => [
                            '20170101' => 26.3,
                            '20170102' => 26.1,
                            '20170103' => 26.2,
                        ],
                        'RH2M' => [
                            '20170101' => 78.5,
                            '20170102' => 80.1,
                            '20170103' => 79.8,
                        ],
                        'PRECTOTCORR' => [
                            '20170101' => 0.0,
                            '20170102' => 0.0,
                            '20170103' => 0.0,
                        ],
                        'WS10M' => [
                            '20170101' => 5.0,
                            '20170102' => 5.1,
                            '20170103' => 5.2,
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create([
            ...$this->projectAttributes(),
            'start_date' => '2017-01-01',
            'end_date' => '2017-01-03',
        ]);

        $this->actingAs($user)
            ->post(route('solar-projects.fetch-weather-data', $solarProject))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('api_weather_data', [
            'date_time' => '2017-01-02 00:00:00',
            'radiation_source' => 'estimated',
            'radiation_fallback_method' => 'interpolated_recent',
        ]);
    }

    public function test_fetch_weather_data_caps_future_end_date_to_latest_available_date(): void
    {
        Carbon::setTestNow('2026-05-22 12:00:00');

        Http::fake([
            'power.larc.nasa.gov/*' => Http::response([
                'properties' => [
                    'parameter' => [
                        'ALLSKY_SFC_SW_DWN' => [
                            '20260521' => 5.1,
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create([
            ...$this->projectAttributes(),
            'start_date' => '2026-05-21',
            'end_date' => '2026-12-31',
        ]);

        $this->actingAs($user)
            ->post(route('solar-projects.fetch-weather-data', $solarProject))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        Http::assertSent(fn ($request) => $request['start'] === '20260521'
            && $request['end'] === '20260521');

        Carbon::setTestNow();
    }

    public function test_fetch_weather_station_data_uses_endpoint_and_only_imports_new_readings(): void
    {
        config([
            'services.weather_station.endpoint' => 'https://meteoestacion.desarrollougmaicao.com/api_publica.php',
            'services.weather_station.device_code' => 'METEOESTACION',
        ]);

        Http::fake([
            'meteoestacion.desarrollougmaicao.com/api_publica.php*' => Http::sequence()
                ->push([
                    'status' => 'success',
                    'datos' => [
                        [
                            'fecha' => '2025-08-20 10:28:47',
                            'temperatura' => '35.9',
                            'humedad' => '58.2',
                            'uva' => '25.3',
                            'uvb' => '24.0',
                            'indice_uv' => '0.1',
                        ],
                    ],
                ])
                ->push([
                    'status' => 'success',
                    'datos' => [
                        [
                            'fecha' => '2025-08-20 10:28:47',
                            'temperatura' => '99.9',
                            'humedad' => '99.9',
                            'uva' => '99.9',
                        ],
                        [
                            'fecha' => '2025-08-20 10:35:00',
                            'temperatura' => '36.4',
                            'humedad' => '57.5',
                            'uva' => '26.0',
                            'uvb' => '24.5',
                            'indice_uv' => '0.2',
                        ],
                    ],
                ]),
        ]);

        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create([
            ...$this->projectAttributes(),
            'start_date' => '2025-08-20',
            'end_date' => '2025-08-20',
        ]);

        $this->actingAs($user)
            ->post(route('solar-projects.fetch-weather-station-data', $solarProject))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Datos del centro meteorologico obtenidos desde el endpoint. Lecturas nuevas: 1. Lecturas existentes omitidas: 0. Dias nuevos: 1. Dias actualizados: 0.')
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('solar-projects.fetch-weather-station-data', $solarProject))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Datos del centro meteorologico obtenidos desde el endpoint. Lecturas nuevas: 1. Lecturas existentes omitidas: 1. Dias nuevos: 0. Dias actualizados: 1.')
            ->assertRedirect();

        $this->assertSame(2, WeatherStationReading::query()->count());
        $this->assertDatabaseHas('weather_station_readings', [
            'solar_project_id' => null,
            'measured_at' => '2025-08-20 10:28:47',
            'temperature' => 35.9,
        ]);
        $this->assertDatabaseHas('weather_station_readings', [
            'solar_project_id' => null,
            'measured_at' => '2025-08-20 10:35:00',
            'temperature' => 36.4,
        ]);
        $this->assertDatabaseCount('api_weather_data', 1);
    }

    public function test_user_can_calculate_with_stored_weather_station_data(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create([
            ...$this->projectAttributes(),
            'start_date' => '2025-08-20',
            'end_date' => '2025-08-20',
        ]);
        $solarProject->technicalParameter()->create($this->technicalParameterAttributes());

        $solarProject->weatherStationReadings()->create([
            'measured_at' => '2025-08-20 10:28:47',
            'temperature' => 35.9,
            'humidity' => 58.2,
            'solar_radiation' => 5.5,
        ]);

        $this->actingAs($user)
            ->post(route('solar-projects.calculate-weather-station', $solarProject))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Calculos solares ejecutados correctamente con datos de la estacion meteorologica.')
            ->assertRedirect();

        $this->assertDatabaseHas('calculation_results', [
            'solar_project_id' => $solarProject->id,
        ]);
        $this->assertDatabaseHas('monthly_results', [
            'solar_project_id' => $solarProject->id,
            'month_number' => 8,
        ]);
        $this->assertDatabaseCount('api_weather_data', 0);
        Http::assertNothingSent();
    }

    public function test_solar_project_keeps_historical_price_when_municipal_price_changes(): void
    {
        $user = User::factory()->create();
        $municipality = $this->seedMunicipalityPrice('Maicao', 'Media Guajira', 'Base urbana', 'urbana', 4000000, 1.00);

        $this->actingAs($user)
            ->post(route('solar-projects.store'), $this->validPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $solarProject = SolarProject::query()->firstOrFail();

        $municipality->solarPrices()->first()->update([
            'base_price_per_kw' => 5000000,
            'logistic_factor' => 1.20,
        ]);

        $solarProject->refresh();

        $this->assertSame('4000000.00', $solarProject->base_price_per_kw);
        $this->assertSame('1.000', $solarProject->logistic_factor_used);
        $this->assertSame('4000000.00', $solarProject->final_price_per_kw_used);
        $this->assertSame('20000000.00', $solarProject->estimated_installation_cost);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        $technicalParameters = $this->technicalParameterAttributes();
        unset($technicalParameters['performance_ratio']);

        return [
            ...$this->projectAttributes(),
            ...$technicalParameters,
            'municipality_id' => $this->seedMunicipalityPrice('Maicao', 'Media Guajira', 'Base urbana', 'urbana', 4000000, 1.00)->id,
            'location_type' => 'urbana',
            'required_power_kw' => 5,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectAttributes(): array
    {
        return [
            'name' => 'Sistema solar institucional',
            'description' => 'Proyecto base para simulación en Riohacha.',
            'start_date' => '2017-01-01',
            'end_date' => '2017-01-02',
            'monthly_consumption_kwh' => 2000,
            'energy_rate_cop_kwh' => 820,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function technicalParameterAttributes(): array
    {
        return [
            'available_area_m2' => 120,
            'usable_area_percentage' => 85,
            'panel_power_w' => 550,
            'panel_area_m2' => 2.5,
            'performance_ratio' => 0.86,
            'system_losses_percentage' => 14,
        ];
    }

    private function seedMunicipalityPrice(
        string $name,
        string $zone,
        string $zoneName,
        string $locationType,
        int $basePrice,
        float $factor,
    ): Municipality {
        $municipality = Municipality::query()->firstOrCreate(
            ['name' => $name, 'department' => 'La Guajira'],
            ['zone' => $zone, 'latitude' => 11.3778, 'longitude' => -72.2389, 'active' => true],
        );

        $municipality->solarPrices()->updateOrCreate(
            ['zone_name' => $zoneName, 'location_type' => $locationType],
            [
                'base_price_per_kw' => $basePrice,
                'logistic_factor' => $factor,
                'min_price_per_kw' => $basePrice,
                'max_price_per_kw' => $basePrice,
                'active' => true,
            ],
        );

        return $municipality;
    }
}
