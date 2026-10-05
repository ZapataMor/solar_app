<?php

namespace Tests\Feature;

use App\Models\Installer;
use App\Models\Municipality;
use App\Models\MunicipalitySolarPrice;
use App\Models\QuoteRequest;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR-0024: an administrator keeps the price per kW of each municipality, and what was already
 * quoted keeps the price it was quoted with.
 */
class MunicipalityPricesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_administrator_reads_the_prices_of_every_municipality(): void
    {
        $municipality = $this->municipality();
        $this->price($municipality, 'urbana', 4_000_000);

        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('municipality-prices.index'))
            ->assertOk()
            ->assertSee('Maicao')
            ->assertSee('$4.000.000')
            // A municipality with no price is the useful thing to see: a project there cannot quote.
            ->assertSee('Sin precio')
            ->getContent();

        // Closed by default: fifteen municipalities by four kinds of location is a wall of rows.
        $this->assertMatchesRegularExpression('/<details[^>]*data-test="municipality-\d+"(?![^>]*open)/', $html);
        $this->assertStringContainsString('1 de 4 con precio', $html);

        $this->actingAs(User::factory()->create())->get(route('municipality-prices.index'))->assertForbidden();
    }

    public function test_changing_a_price_does_not_touch_what_was_already_quoted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $municipality = $this->municipality();
        $this->price($municipality, 'urbana', 4_000_000);
        [$solarProject, $quoteRequest] = $this->quotedProject($municipality);

        $this->actingAs($admin)
            ->post(route('municipality-prices.store'), [
                'municipality_id' => $municipality->id,
                'location_type' => 'urbana',
                'base_price_per_kw' => 5_200_000,
                'logistic_factor' => 1.1,
                'active' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('municipality-prices.index'));

        $this->assertEqualsWithDelta(5_200_000, (float) MunicipalitySolarPrice::query()->sole()->base_price_per_kw, 0.01);

        // What the client and the installer already agreed on does not move with the dollar.
        $this->assertEqualsWithDelta(29_120_000, (float) $solarProject->fresh()->estimated_installation_cost, 0.01);
        $this->assertEqualsWithDelta(29_120_000, (float) $quoteRequest->fresh()->quoted_cost_cop, 0.01);
        $this->assertEqualsWithDelta(4_000_000, (float) $quoteRequest->fresh()->quoted_price_per_kw_cop, 0.01);
    }

    public function test_saving_twice_keeps_one_price_per_municipality_and_location_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $municipality = $this->municipality();
        $price = $this->price($municipality, 'urbana', 4_000_000);
        $zone = $price->zone_name;

        foreach ([4_500_000, 4_800_000] as $base) {
            $this->actingAs($admin)->post(route('municipality-prices.store'), [
                'municipality_id' => $municipality->id,
                'location_type' => 'urbana',
                'base_price_per_kw' => $base,
                'logistic_factor' => 1,
                'active' => '1',
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(1, MunicipalitySolarPrice::query()->count());
        // The zone names the price; editing the price has no business rewriting it.
        $this->assertSame($zone, MunicipalitySolarPrice::query()->sole()->zone_name);
    }

    public function test_a_price_outside_what_a_system_can_cost_is_a_typo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $municipality = $this->municipality();

        $this->actingAs($admin)
            ->post(route('municipality-prices.store'), [
                'municipality_id' => $municipality->id,
                'location_type' => 'urbana',
                // Four million written without a zero.
                'base_price_per_kw' => 400_000,
                'logistic_factor' => 1,
            ])
            ->assertSessionHasErrors(['base_price_per_kw' => 'El precio por kW debe estar entre $500.000 y $20.000.000.']);

        $this->actingAs($admin)
            ->post(route('municipality-prices.store'), [
                'municipality_id' => $municipality->id,
                'location_type' => 'urbana',
                'base_price_per_kw' => 4_000_000,
                'logistic_factor' => 0.5,
            ])
            ->assertSessionHasErrors('logistic_factor');

        $this->assertSame(0, MunicipalitySolarPrice::query()->count());
    }

    private function municipality(): Municipality
    {
        return Municipality::query()->create([
            'name' => 'Maicao', 'department' => 'La Guajira', 'zone' => 'Media Guajira',
            'latitude' => 11.3778, 'longitude' => -72.2389, 'active' => true,
        ]);
    }

    private function price(Municipality $municipality, string $locationType, float $base): MunicipalitySolarPrice
    {
        return MunicipalitySolarPrice::query()->create([
            'municipality_id' => $municipality->id,
            'zone_name' => 'Base urbana',
            'location_type' => $locationType,
            'base_price_per_kw' => $base,
            'logistic_factor' => 1,
            'active' => true,
        ]);
    }

    /**
     * A project quoted at the old price, and the request its client already sent an installer.
     *
     * @return array{0: SolarProject, 1: QuoteRequest}
     */
    private function quotedProject(Municipality $municipality): array
    {
        $solarProject = User::factory()->create()->solarProjects()->create([
            'name' => 'Local comercial centro',
            'property_type' => 'business',
            'location_name' => SolarProject::LOCATION_NAME,
            'location_type' => 'urbana',
            'municipality_id' => $municipality->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'monthly_consumption_kwh' => 1115,
            'base_price_per_kw' => 4_000_000,
            'logistic_factor_used' => 1,
            'final_price_per_kw_used' => 4_000_000,
            'estimated_installation_cost' => 29_120_000,
        ]);

        $installer = Installer::query()->create(['name' => 'Energía Wayúu', 'phone' => '300 000 0002', 'active' => true]);
        $quoteRequest = QuoteRequest::query()->create([
            'solar_project_id' => $solarProject->id,
            'installer_id' => $installer->id,
            'status' => 'sent',
            'quoted_cost_cop' => 29_120_000,
            'quoted_price_per_kw_cop' => 4_000_000,
        ]);

        return [$solarProject, $quoteRequest];
    }
}
