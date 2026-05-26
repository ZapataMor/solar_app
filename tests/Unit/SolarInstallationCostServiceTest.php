<?php

namespace Tests\Unit;

use App\Models\Municipality;
use App\Services\SolarInstallationCostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolarInstallationCostServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_maicao_urban_cost(): void
    {
        $municipality = $this->municipalityWithPrice('Maicao', 'Media Guajira', 'Base urbana', 'urbana', 4000000, 1.00);

        $result = app(SolarInstallationCostService::class)->calculate($municipality, 'urbana', 5);

        $this->assertSame(4000000.0, $result['base_price_per_kw']);
        $this->assertSame(1.0, $result['logistic_factor_used']);
        $this->assertSame(4000000.0, $result['final_price_per_kw_used']);
        $this->assertSame(20000000.0, $result['estimated_installation_cost']);
    }

    public function test_calculates_manaure_cost(): void
    {
        $municipality = $this->municipalityWithPrice('Manaure', 'Norte medio', 'Norte medio', 'urbana', 4500000, 1.13);

        $result = app(SolarInstallationCostService::class)->calculate($municipality, 'urbana', 5);

        $this->assertSame(5085000.0, $result['final_price_per_kw_used']);
        $this->assertSame(25425000.0, $result['estimated_installation_cost']);
    }

    public function test_calculates_uribia_urban_cost(): void
    {
        $municipality = $this->municipalityWithPrice('Uribia', 'Alta Guajira', 'Alta Guajira urbana', 'urbana', 4700000, 1.18);

        $result = app(SolarInstallationCostService::class)->calculate($municipality, 'urbana', 5);

        $this->assertSame(5546000.0, $result['final_price_per_kw_used']);
        $this->assertSame(27730000.0, $result['estimated_installation_cost']);
    }

    public function test_calculates_alta_guajira_rural_dispersed_cost(): void
    {
        $municipality = $this->municipalityWithPrice('Uribia', 'Alta Guajira', 'Alta Guajira rural dispersa', 'alta_guajira', 5200000, 1.30);

        $result = app(SolarInstallationCostService::class)->calculate($municipality, 'alta_guajira', 5);

        $this->assertSame(6760000.0, $result['final_price_per_kw_used']);
        $this->assertSame(33800000.0, $result['estimated_installation_cost']);
    }

    public function test_estimated_cost_increases_when_logistic_factor_is_higher(): void
    {
        $municipality = $this->municipalityWithPrice('Riohacha', 'Base urbana', 'Base urbana', 'urbana', 4000000, 1.00);

        $service = app(SolarInstallationCostService::class);
        $urban = $service->calculate($municipality, 'urbana', 5);
        $ruralDispersed = $service->calculate($municipality, 'rural_dispersa', 5);

        $this->assertGreaterThan($urban['estimated_installation_cost'], $ruralDispersed['estimated_installation_cost']);
    }

    private function municipalityWithPrice(
        string $name,
        string $zone,
        string $zoneName,
        string $locationType,
        int $basePrice,
        float $factor,
    ): Municipality {
        $municipality = Municipality::query()->create([
            'name' => $name,
            'department' => 'La Guajira',
            'zone' => $zone,
            'active' => true,
        ]);

        $municipality->solarPrices()->create([
            'zone_name' => $zoneName,
            'location_type' => $locationType,
            'base_price_per_kw' => $basePrice,
            'logistic_factor' => $factor,
            'min_price_per_kw' => $basePrice,
            'max_price_per_kw' => $basePrice,
            'active' => true,
        ]);

        return $municipality;
    }
}
