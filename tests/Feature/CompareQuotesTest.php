<?php

namespace Tests\Feature;

use App\Actions\Installers\CompareProjectQuotes;
use App\Domain\Installers\QuoteRequestStatus;
use App\Models\Installer;
use App\Models\Municipality;
use App\Models\QuoteRequest;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR-0028: the client compares the quotes of one project side by side, and the app marks the best
 * of each row, never the best quote.
 */
class CompareQuotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_client_compares_the_quotes_of_the_project(): void
    {
        [$client, $solarProject] = $this->project();
        $solarProject->calculationResult()->create([
            'estimated_annual_savings_cop' => 3_680_000,
            'coverage_percentage' => 84,
            'installed_capacity_kwp' => 5.5,
        ]);
        $this->quoted($solarProject, 'Energía Wayúu', 'wayuu', ['amount_cop' => 22_300_000]);
        $this->quoted($solarProject, 'Sol de Riohacha', 'sol', ['amount_cop' => 18_400_000]);

        $this->actingAs($client)
            ->get(route('installers.quotes.compare', $solarProject))
            ->assertOk()
            ->assertSee('Compara tus cotizaciones')
            ->assertSee('Energía Wayúu')
            ->assertSee('Sol de Riohacha')
            ->assertSee('$18.400.000')
            ->assertSee('$22.300.000')
            // The rows that decide, in the order of the ADR.
            ->assertSee('Precio total')
            ->assertSee('Precio por kW')
            ->assertSee('Se paga en')
            ->assertSee('Certificación RETIE')
            ->assertSee('Garantía de los paneles')
            // The best of each row, and the promise that nothing else is marked.
            ->assertSee('Lo mejor')
            ->assertSee('no la mejor cotización');
    }

    public function test_the_cheapest_column_goes_first_and_carries_the_marks_of_its_rows(): void
    {
        [, $solarProject] = $this->project();
        // The savings of the project are what turn a price into a payback (ADR-0026).
        $solarProject->calculationResult()->create([
            'estimated_annual_savings_cop' => 3_680_000,
            'coverage_percentage' => 84,
            'installed_capacity_kwp' => 5.5,
        ]);
        $this->quoted($solarProject, 'Energía Wayúu', 'wayuu', [
            'amount_cop' => 22_300_000,
            'panel_warranty_years' => 25,
        ]);
        $this->quoted($solarProject, 'Sol de Riohacha', 'sol', [
            'amount_cop' => 18_400_000,
            'panel_warranty_years' => 12,
        ]);

        $comparison = app(CompareProjectQuotes::class)($solarProject->fresh());

        $this->assertNotNull($comparison);
        $this->assertSame(['Sol de Riohacha', 'Energía Wayúu'], array_column($comparison['columns'], 'installerName'));
        $this->assertTrue($comparison['calculated']);

        $rows = collect($comparison['groups'])->flatMap(fn (array $group): array => $group['rows'])->keyBy('key');

        // 18.400.000 / 5,5 kW: cheaper in total and per kW, so it pays for itself sooner too.
        $this->assertTrue($rows['amountCop']['cells'][0]['best']);
        $this->assertTrue($rows['pricePerKwCop']['cells'][0]['best']);
        $this->assertTrue($rows['paybackYears']['cells'][0]['best']);
        // The longest warranty is the other one: that is why there is no single winner.
        $this->assertTrue($rows['panelWarrantyYears']['cells'][1]['best']);
    }

    public function test_with_a_single_quote_there_is_no_screen_and_no_link(): void
    {
        [$client, $solarProject] = $this->project();
        $this->quoted($solarProject, 'Energía Wayúu', 'wayuu', ['amount_cop' => 18_400_000]);

        $this->actingAs($client)
            ->get(route('installers.quotes.compare', $solarProject))
            ->assertNotFound();

        $this->actingAs($client)
            ->get(route('installers.index', ['proyecto' => $solarProject->id]))
            ->assertOk()
            ->assertDontSee('Comparar tus');

        $this->assertNull(app(CompareProjectQuotes::class)($solarProject));
    }

    public function test_with_two_quotes_the_link_appears_in_the_directory_and_in_the_quote(): void
    {
        [$client, $solarProject] = $this->project();
        $request = $this->quoted($solarProject, 'Energía Wayúu', 'wayuu', ['amount_cop' => 22_300_000]);
        $this->quoted($solarProject, 'Sol de Riohacha', 'sol', ['amount_cop' => 18_400_000]);

        $this->actingAs($client)
            ->get(route('installers.index', ['proyecto' => $solarProject->id]))
            ->assertOk()
            ->assertSee('Comparar tus 2 cotizaciones')
            ->assertSee(route('installers.quotes.compare', $solarProject), false);

        $this->actingAs($client)
            ->get(route('installers.quotes.show', $request))
            ->assertOk()
            ->assertSee('Comparar las 2 cotizaciones lado a lado');
    }

    public function test_only_whoever_may_manage_the_project_compares_it(): void
    {
        [, $solarProject] = $this->project();
        $this->quoted($solarProject, 'Energía Wayúu', 'wayuu', ['amount_cop' => 22_300_000]);
        $this->quoted($solarProject, 'Sol de Riohacha', 'sol', ['amount_cop' => 18_400_000]);

        // Another client, with their own projects, has nothing to do with these prices.
        $this->actingAs(User::factory()->create())
            ->get(route('installers.quotes.compare', $solarProject))
            ->assertForbidden();
    }

    public function test_what_a_quote_does_not_say_is_shown_as_no_lo_dice(): void
    {
        [$client, $solarProject] = $this->project();
        $this->quoted($solarProject, 'Energía Wayúu', 'wayuu', [
            'amount_cop' => 22_300_000,
            'inverter_warranty_years' => 10,
        ]);
        // This one leaves the warranty of the inverter blank: that absence is information.
        $this->quoted($solarProject, 'Sol de Riohacha', 'sol', [
            'amount_cop' => 18_400_000,
            'inverter_warranty_years' => null,
        ]);

        $this->actingAs($client)
            ->get(route('installers.quotes.compare', $solarProject))
            ->assertOk()
            ->assertSee('Garantía del inversor')
            ->assertSee('No lo dice');
    }

    public function test_a_row_nobody_declares_is_named_once_instead_of_filling_the_table(): void
    {
        [$client, $solarProject] = $this->project();
        $this->quoted($solarProject, 'Energía Wayúu', 'wayuu', ['amount_cop' => 22_300_000, 'delivery_days' => null]);
        $this->quoted($solarProject, 'Sol de Riohacha', 'sol', ['amount_cop' => 18_400_000, 'delivery_days' => null]);

        $this->actingAs($client)
            ->get(route('installers.quotes.compare', $solarProject))
            ->assertOk()
            ->assertSee('Lo que ninguna dice')
            ->assertSee('Plazo hasta energizar');
    }

    public function test_an_expired_price_stays_in_the_table_marked_and_out_of_the_best(): void
    {
        [$client, $solarProject] = $this->project();
        // The cheapest one, with the shortest validity. Nobody can send a price already expired, so
        // the dollar moves instead: three weeks later only that one stopped being an offer.
        $this->quoted($solarProject, 'Energía Wayúu', 'wayuu', [
            'amount_cop' => 14_000_000,
            'valid_until' => now()->addDays(10)->format('Y-m-d'),
        ]);
        $this->quoted($solarProject, 'Sol de Riohacha', 'sol', [
            'amount_cop' => 18_400_000,
            'valid_until' => now()->addDays(60)->format('Y-m-d'),
        ]);
        $this->quoted($solarProject, 'Guajira Solar', 'guajira', [
            'amount_cop' => 21_000_000,
            'valid_until' => now()->addDays(60)->format('Y-m-d'),
        ]);

        $this->travel(21)->days();

        $this->actingAs($client)
            ->get(route('installers.quotes.compare', $solarProject))
            ->assertOk()
            ->assertSee('$14.000.000')
            ->assertSee('Precio vencido')
            ->assertSee('no entra');

        $comparison = app(CompareProjectQuotes::class)($solarProject);
        $rows = collect($comparison['groups'])->flatMap(fn (array $group): array => $group['rows'])->keyBy('key');

        // The expired one goes last whatever figure it carries, and the mark goes to the cheapest
        // price that is still an offer.
        $this->assertSame('Energía Wayúu', $comparison['columns'][2]['installerName']);
        $this->assertTrue($comparison['columns'][2]['expired']);
        $this->assertFalse($rows['amountCop']['cells'][2]['best']);
        $this->assertTrue($rows['amountCop']['cells'][0]['best']);
    }

    public function test_it_warns_above_the_numbers_when_one_leaves_the_legalization_out(): void
    {
        [$client, $solarProject] = $this->project();
        $this->quoted($solarProject, 'Energía Wayúu', 'wayuu', [
            'amount_cop' => 22_300_000,
            'includes_retie' => '1',
            'includes_grid_paperwork' => '1',
        ]);
        $this->quoted($solarProject, 'Sol de Riohacha', 'sol', [
            'amount_cop' => 16_000_000,
            'includes_retie' => '1',
            'includes_grid_paperwork' => '0',
        ]);

        $this->actingAs($client)
            ->get(route('installers.quotes.compare', $solarProject))
            ->assertOk()
            ->assertSee('No todas legalizan la instalación')
            ->assertSee('la más barata puede terminar costando más');
    }

    public function test_without_a_calculation_the_payback_row_is_replaced_by_the_reason(): void
    {
        [$client, $solarProject] = $this->project();
        $this->quoted($solarProject, 'Energía Wayúu', 'wayuu', ['amount_cop' => 22_300_000]);
        $this->quoted($solarProject, 'Sol de Riohacha', 'sol', ['amount_cop' => 18_400_000]);

        $this->actingAs($client)
            ->get(route('installers.quotes.compare', $solarProject))
            ->assertOk()
            ->assertSee('Todavía no sabemos en cuánto se paga')
            ->assertSee('Calcular mi proyecto')
            ->assertDontSee('Se paga en');
    }

    public function test_savings_of_zero_do_not_turn_the_payback_into_a_silence_of_the_installers(): void
    {
        [$client, $solarProject] = $this->project();
        // Calculated, but with nothing saved: the payback cannot be worked out either.
        $solarProject->calculationResult()->create([
            'estimated_annual_savings_cop' => 0,
            'coverage_percentage' => 0,
            'installed_capacity_kwp' => 0,
        ]);
        $this->quoted($solarProject, 'Energía Wayúu', 'wayuu', ['amount_cop' => 22_300_000]);
        $this->quoted($solarProject, 'Sol de Riohacha', 'sol', ['amount_cop' => 18_400_000]);

        $response = $this->actingAs($client)
            ->get(route('installers.quotes.compare', $solarProject))
            ->assertOk()
            ->assertSee('Todavía no sabemos en cuánto se paga');

        // The row leaves, but it must not be listed as something the installers failed to declare.
        $response->assertDontSee('Se paga en');
        $this->assertNotContains('Se paga en', app(CompareProjectQuotes::class)($solarProject)['silent']);
    }

    public function test_a_total_without_vat_is_flagged_before_comparing_it_with_one_that_has_it(): void
    {
        [$client, $solarProject] = $this->project();
        $this->quoted($solarProject, 'Energía Wayúu', 'wayuu', [
            'amount_cop' => 18_000_000,
            'vat_included' => '1',
        ]);
        // Cheaper on paper, 19 millones once IVA lands on top.
        $this->quoted($solarProject, 'Sol de Riohacha', 'sol', [
            'amount_cop' => 16_000_000,
            'vat_included' => '0',
        ]);

        $this->actingAs($client)
            ->get(route('installers.quotes.compare', $solarProject))
            ->assertOk()
            ->assertSee('No todos los totales llevan IVA')
            // And next to the name of the column whose total is still missing it.
            ->assertSee('IVA aparte');
    }

    /**
     * An installer with coverage, their account, a request for this project and their quote: the
     * only way to get a price in is the one the installer uses (ADR-0026).
     *
     * @param  array<string, mixed>  $quote
     */
    private function quoted(SolarProject $solarProject, string $name, string $username, array $quote): QuoteRequest
    {
        $installer = Installer::query()->create(['name' => $name, 'phone' => '300 000 0002', 'active' => true]);
        $installer->municipalities()->sync([$this->riohacha()->id]);
        $account = User::factory()->create([
            'role' => 'installer',
            'installer_id' => $installer->id,
            'username' => $username,
        ]);

        $quoteRequest = QuoteRequest::query()->create([
            'solar_project_id' => $solarProject->id,
            'installer_id' => $installer->id,
            'status' => QuoteRequestStatus::SENT,
        ]);

        $this->actingAs($account)
            ->put(route('installer-inbox.quote', $quoteRequest), [...$this->quote(), ...$quote])
            ->assertSessionHasNoErrors();

        return $quoteRequest;
    }

    /**
     * @return array<string, mixed>
     */
    private function quote(): array
    {
        return [
            'amount_cop' => 18_400_000,
            'power_kw' => 5.5,
            'panel_count' => 10,
            'panel_watts' => 550,
            'panel_model' => 'Jinko Tiger Neo',
            'inverter_model' => 'Growatt MIN 5000TL-X',
            'includes_battery' => '0',
            'includes_retie' => '1',
            'includes_grid_paperwork' => '1',
            'includes_bidirectional_meter' => '0',
            'includes_maintenance' => '1',
            'panel_warranty_years' => 25,
            'inverter_warranty_years' => 10,
            'workmanship_warranty_years' => 2,
            'vat_included' => '1',
            'down_payment_percentage' => 40,
            'delivery_days' => 45,
            'scope' => 'Paneles, inversor y mano de obra.',
            'valid_until' => now()->addDays(30)->format('Y-m-d'),
        ];
    }

    private function riohacha(): Municipality
    {
        return Municipality::query()->firstOrCreate(
            ['name' => 'Riohacha', 'department' => 'La Guajira'],
            ['latitude' => 11.5444, 'longitude' => -72.9072, 'active' => true],
        );
    }

    /**
     * @return array{0: User, 1: SolarProject}
     */
    private function project(): array
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create([
            'name' => 'Mi casa',
            'property_type' => 'house',
            'location_name' => SolarProject::LOCATION_NAME,
            'municipality_id' => $this->riohacha()->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'monthly_consumption_kwh' => 300,
            'energy_rate_cop_kwh' => 900,
        ]);
        $solarProject->technicalParameter()->create([
            'available_area_m2' => 40, 'usable_area_percentage' => 80, 'panel_power_w' => 550,
            'panel_area_m2' => 2.6, 'performance_ratio' => 0.86, 'system_losses_percentage' => 14,
        ]);

        return [$user, $solarProject];
    }
}
