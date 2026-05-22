<?php

namespace Tests\Feature;

use App\Models\ApiWeatherData;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SolarProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_solar_project_with_fixed_location_and_technical_parameters(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('solar-projects.store'), [
            ...$this->validPayload(),
            'location_name' => 'Otra ciudad',
            'latitude' => 0,
            'longitude' => 0,
        ]);

        $solarProject = SolarProject::query()->first();

        $response->assertRedirect(route('solar-projects.show', $solarProject));

        $this->assertDatabaseHas('solar_projects', [
            'user_id' => $user->id,
            'name' => 'Sistema solar institucional',
            'location_name' => SolarProject::LOCATION_NAME,
            'latitude' => 11.5444,
            'longitude' => -72.9072,
        ]);

        $this->assertDatabaseHas('technical_parameters', [
            'solar_project_id' => $solarProject->id,
            'available_area_m2' => 120,
            'performance_ratio' => 0.82,
        ]);
    }

    public function test_user_cannot_view_another_users_project(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $solarProject = $owner->solarProjects()->create($this->projectAttributes());

        $response = $this->actingAs($otherUser)->get(route('solar-projects.show', $solarProject));

        $response->assertForbidden();
    }

    public function test_fetch_weather_data_stores_hourly_values_without_duplicates(): void
    {
        Http::fake([
            'power.larc.nasa.gov/*' => Http::response([
                'properties' => [
                    'parameter' => [
                        'ALLSKY_SFC_SW_DWN' => [
                            '2017010100' => 0,
                            '2017010101' => 0.12,
                        ],
                        'T2M' => [
                            '2017010100' => 26.3,
                            '2017010101' => 26.1,
                        ],
                        'RH2M' => [
                            '2017010100' => 78.5,
                            '2017010101' => 80.1,
                        ],
                        'PRECTOTCORR' => [
                            '2017010100' => 0,
                            '2017010101' => 0.03,
                        ],
                        'WS10M' => [
                            '2017010100' => 5.2,
                            '2017010101' => 5.4,
                        ],
                    ],
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());
        $solarProject->technicalParameter()->create($this->technicalParameterAttributes());

        $this->actingAs($user)
            ->post(route('solar-projects.fetch-weather-data', $solarProject))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Datos climáticos sincronizados. Nuevos: 2. Existentes actualizados: 0. Total del proyecto: 2.')
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('solar-projects.fetch-weather-data', $solarProject))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Datos climáticos sincronizados. Nuevos: 0. Existentes actualizados: 2. Total del proyecto: 2.')
            ->assertRedirect();

        $this->assertSame(2, ApiWeatherData::query()->whereBelongsTo($solarProject)->count());
        $this->assertDatabaseHas('api_weather_data', [
            'solar_project_id' => $solarProject->id,
            'date_time' => '2017-01-01 01:00:00',
            'allsky_sfc_sw_dwn' => 0.12,
            't2m' => 26.1,
            'rh2m' => 80.1,
            'prectotcorr' => 0.03,
            'ws10m' => 5.4,
        ]);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://power.larc.nasa.gov/api/temporal/hourly/point')
            && $request['parameters'] === 'ALLSKY_SFC_SW_DWN,T2M,RH2M,PRECTOTCORR,WS10M'
            && (float) $request['latitude'] === SolarProject::LATITUDE
            && (float) $request['longitude'] === SolarProject::LONGITUDE
            && $request['start'] === '20170101'
            && $request['end'] === '20170102'
            && $request['community'] === 'SB'
            && $request['format'] === 'JSON');
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            ...$this->projectAttributes(),
            ...$this->technicalParameterAttributes(),
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
            'annual_consumption_kwh' => 24500,
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
            'performance_ratio' => 0.82,
            'system_losses_percentage' => 14,
        ];
    }
}
