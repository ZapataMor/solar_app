<?php

namespace Tests\Feature;

use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClimateSyncAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_users_cannot_trigger_any_climate_sync(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create($this->projectAttributes());

        $this->actingAs($user);

        $this->post(route('api-data.fetch-nasa-data'))->assertForbidden();
        $this->post(route('api-data.fetch-weather-station-data'))->assertForbidden();
        $this->postJson(route('api-data.fetch-ambient-data'), ['auto_sync' => true])->assertForbidden();
        $this->post(route('solar-projects.fetch-weather-data', $solarProject))->assertForbidden();
        $this->post(route('solar-projects.fetch-weather-station-data', $solarProject))->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_regular_users_see_the_data_page_without_sync_buttons(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('api-data.index'))
            ->assertOk()
            ->assertDontSee('data-api-fetch-form', false)
            ->assertSee('Actualizada por el administrador de la plataforma.');
    }

    public function test_admins_see_the_sync_buttons(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('api-data.index'))
            ->assertOk()
            ->assertSee('data-api-fetch-form="nasa"', false)
            ->assertSee('data-api-fetch-form="ambient"', false)
            ->assertSee('data-api-fetch-form="weather-station"', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function projectAttributes(): array
    {
        return [
            'name' => 'Proyecto de prueba',
            'location_name' => SolarProject::LOCATION_NAME,
            'latitude' => SolarProject::LATITUDE,
            'longitude' => SolarProject::LONGITUDE,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'monthly_consumption_kwh' => 300,
            'energy_rate_cop_kwh' => 800,
        ];
    }
}
