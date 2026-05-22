<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee($solarProject->name)
            ->assertSee('ST-001')
            ->assertSee('2026-05-21 12:30');
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
