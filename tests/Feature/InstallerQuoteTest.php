<?php

namespace Tests\Feature;

use App\Actions\Installers\DescribeQuoteRequest;
use App\Domain\Installers\QuoteRequestStatus;
use App\Models\Installer;
use App\Models\InstallerQuote;
use App\Models\Municipality;
use App\Models\QuoteRequest;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/**
 * ADR-0026: the installer answers a request with their price, and the client reads it where they
 * asked for it.
 */
class InstallerQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_installer_sends_a_price_and_the_request_moves_to_quoted(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);

        $this->actingAs($account)
            ->put(route('installer-inbox.quote', $quoteRequest), $this->quote())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('installer-inbox.show', $quoteRequest));

        $quote = InstallerQuote::query()->sole();
        $this->assertEqualsWithDelta(18_400_000, (float) $quote->amount_cop, 0.01);
        $this->assertTrue($quote->includes_battery);
        $this->assertSame(QuoteRequestStatus::QUOTED, $quoteRequest->fresh()->status);
        $this->assertNotNull($quoteRequest->fresh()->answered_at);
    }

    public function test_sending_it_again_corrects_the_price_instead_of_adding_another(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);

        $this->actingAs($account)->put(route('installer-inbox.quote', $quoteRequest), $this->quote());
        $this->actingAs($account)
            ->put(route('installer-inbox.quote', $quoteRequest), [...$this->quote(), 'amount_cop' => 21_000_000])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, InstallerQuote::query()->count());
        $this->assertEqualsWithDelta(21_000_000, (float) InstallerQuote::query()->sole()->amount_cop, 0.01);
    }

    public function test_a_deal_already_closed_does_not_go_back_to_waiting(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);
        $quoteRequest->forceFill(['status' => QuoteRequestStatus::WON, 'contract_value_cop' => 18_400_000])->save();

        $this->actingAs($account)
            ->put(route('installer-inbox.quote', $quoteRequest), $this->quote())
            ->assertSessionHasNoErrors();

        // Correcting the figure of a won deal must not drag it back to "waiting for the client".
        $this->assertSame(QuoteRequestStatus::WON, $quoteRequest->fresh()->status);
    }

    public function test_a_price_needs_a_figure_and_a_date_that_has_not_passed(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);

        $this->actingAs($account)
            ->put(route('installer-inbox.quote', $quoteRequest), [...$this->quote(), 'amount_cop' => ''])
            ->assertSessionHasErrors(['amount_cop' => 'Escribe cuánto cuesta la instalación.']);

        $this->actingAs($account)
            ->put(route('installer-inbox.quote', $quoteRequest), [...$this->quote(), 'valid_until' => now()->subDay()->format('Y-m-d')])
            ->assertSessionHasErrors(['valid_until' => 'La fecha de validez no puede ser anterior a hoy.']);

        // A system costs millions: anything under a million is a missing zero.
        $this->actingAs($account)
            ->put(route('installer-inbox.quote', $quoteRequest), [...$this->quote(), 'amount_cop' => 184_000])
            ->assertSessionHasErrors('amount_cop');

        $this->assertSame(0, InstallerQuote::query()->count());
    }

    public function test_one_installer_never_prices_what_belongs_to_another(): void
    {
        [, $account] = $this->installerWithAccount();
        [$other] = $this->installerWithAccount('Otra empresa', 'otra');
        [, $solarProject] = $this->project();
        $otherRequest = $this->request($solarProject, $other);

        $this->actingAs($account)
            ->put(route('installer-inbox.quote', $otherRequest), $this->quote())
            ->assertForbidden();

        $this->assertSame(0, InstallerQuote::query()->count());
    }

    public function test_the_client_reads_the_price_in_the_directory(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [$client, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);
        $this->actingAs($account)->put(route('installer-inbox.quote', $quoteRequest), $this->quote());

        $this->actingAs($client)
            ->get(route('installers.index', ['proyecto' => $solarProject->id]))
            ->assertOk()
            ->assertSee('$18.400.000')
            ->assertSee('con baterías')
            ->assertSee('Paneles, inversor y mano de obra.')
            ->assertSee('Te cotizaron')
            // From the card, the detail page where the app judges that price (ADR-0026).
            ->assertSee('Ver la cotización en detalle');
    }

    public function test_an_expired_price_says_so_on_both_sides(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [$client, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);
        $this->actingAs($account)->put(route('installer-inbox.quote', $quoteRequest), $this->quote());

        // The dollar moved and nobody updated the offer.
        $this->travel(40)->days();

        $this->actingAs($client)
            ->get(route('installers.index', ['proyecto' => $solarProject->id]))
            ->assertOk()
            ->assertSee('pídele que lo actualice');

        $this->actingAs($account)
            ->get(route('installer-inbox.show', $quoteRequest))
            ->assertOk()
            ->assertSee('Vencida');
    }

    public function test_a_price_lasts_until_the_end_of_its_day_in_bogota(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);
        $this->actingAs($account)->put(route('installer-inbox.quote', $quoteRequest), [
            ...$this->quote(),
            'valid_until' => '2026-11-20',
        ]);

        // 19:00 in Bogotá of the last valid day: in UTC the day already rolled over. Reading the
        // clock there would retire the offer —and grey out its column in the comparison— a whole
        // day before the date the client was given.
        $this->travelTo(Date::parse('2026-11-21 00:30', 'UTC'));

        $quote = $quoteRequest->fresh()->installerQuote;
        $this->assertFalse($quote->hasExpired());
        $this->assertSame(0, $quote->daysLeft());

        // The next day in Bogotá it really is over.
        $this->travelTo(Date::parse('2026-11-21 14:00', 'UTC'));
        $this->assertTrue($quoteRequest->fresh()->installerQuote->hasExpired());
    }

    public function test_the_client_opens_the_quote_and_the_app_recalculates_the_payback(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [$client, $solarProject] = $this->project();
        // The savings of the project are what turn a price into a payback (ADR-0026).
        $solarProject->calculationResult()->create([
            'estimated_annual_savings_cop' => 3_680_000,
            'coverage_percentage' => 84,
            'installed_capacity_kwp' => 5.5,
        ]);
        $quoteRequest = $this->request($solarProject, $installer);
        $quoteRequest->forceFill(['quoted_cost_cop' => 21_000_000])->save();
        $this->actingAs($account)->put(route('installer-inbox.quote', $quoteRequest), $this->quote());

        $this->actingAs($client)
            ->get(route('installers.quotes.show', $quoteRequest))
            ->assertOk()
            ->assertSee('$18.400.000')
            // 18.400.000 / 3.680.000 = 5 años, dentro del retorno rápido.
            ->assertSee('5 años')
            ->assertSee('Rentable')
            // Queda por debajo del presupuesto de referencia congelado al pedirla (ADR-0024).
            ->assertSee('$2.600.000 por debajo', false)
            ->assertSee('Qué preguntar antes de firmar');
    }

    public function test_without_a_calculation_the_page_says_so_instead_of_inventing_a_payback(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [$client, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);
        $this->actingAs($account)->put(route('installer-inbox.quote', $quoteRequest), $this->quote());

        $this->actingAs($client)
            ->get(route('installers.quotes.show', $quoteRequest))
            ->assertOk()
            ->assertSee('Calcular mi proyecto')
            ->assertDontSee('Rentable');
    }

    public function test_the_page_sends_to_the_comparison_instead_of_listing_the_others(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [$other, $otherAccount] = $this->installerWithAccount('Sol de Riohacha', 'sol');
        [$client, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);
        $otherRequest = $this->request($solarProject, $other);
        $this->actingAs($account)->put(route('installer-inbox.quote', $quoteRequest), $this->quote());
        $this->actingAs($otherAccount)->put(route('installer-inbox.quote', $otherRequest), [...$this->quote(), 'amount_cop' => 22_300_000]);

        // Esta página mide la cotización contra la estimación de la app; compararla con las otras
        // es otra pregunta y tiene su propia pantalla (ADR-0028, ADR-0029).
        $this->actingAs($client)
            ->get(route('installers.quotes.show', $quoteRequest))
            ->assertOk()
            ->assertSee('Tienes 2 cotizaciones para este proyecto')
            ->assertSee(route('installers.quotes.compare', $solarProject), false)
            ->assertDontSee('$22.300.000');
    }

    public function test_only_the_owner_of_the_project_reads_the_quote(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);
        $this->actingAs($account)->put(route('installer-inbox.quote', $quoteRequest), $this->quote());

        // Another client, with their own projects, has nothing to do with this price.
        $this->actingAs(User::factory()->create())
            ->get(route('installers.quotes.show', $quoteRequest))
            ->assertForbidden();
    }

    public function test_a_request_still_without_a_price_has_no_page_to_open(): void
    {
        [$installer] = $this->installerWithAccount();
        [$client, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);

        $this->actingAs($client)
            ->get(route('installers.quotes.show', $quoteRequest))
            ->assertNotFound();
    }

    public function test_the_installer_sends_the_detail_of_a_real_quote(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);

        $this->actingAs($account)
            ->put(route('installer-inbox.quote', $quoteRequest), $this->fullQuote())
            ->assertSessionHasNoErrors();

        $quote = InstallerQuote::query()->sole();
        $this->assertSame(16, $quote->panel_count);
        $this->assertSame(550, $quote->panel_watts);
        $this->assertSame('Jinko Tiger Neo', $quote->panel_model);
        $this->assertSame('Growatt MIN 5000TL-X', $quote->inverter_model);
        // The power was not written: it comes from the panels (ADR-0027).
        $this->assertEqualsWithDelta(8.8, (float) $quote->power_kw, 0.01);
        $this->assertTrue($quote->includes_retie);
        $this->assertTrue($quote->includes_grid_paperwork);
        $this->assertFalse($quote->includes_bidirectional_meter);
        $this->assertSame(25, $quote->panel_warranty_years);
        $this->assertSame(40, $quote->down_payment_percentage);
        $this->assertSame(45, $quote->delivery_days);
        $this->assertTrue($quote->vat_included);
        $this->assertSame('Obra civil y refuerzo del techo.', $quote->exclusions);
    }

    public function test_an_empty_field_clears_what_the_installer_had_written(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);

        $this->actingAs($account)->put(route('installer-inbox.quote', $quoteRequest), $this->fullQuote());
        // Correcting a quote also means taking something out.
        $this->actingAs($account)
            ->put(route('installer-inbox.quote', $quoteRequest), [
                ...$this->fullQuote(),
                'panel_warranty_years' => '',
                'delivery_days' => '',
                'vat_included' => '',
            ])
            ->assertSessionHasNoErrors();

        $quote = InstallerQuote::query()->sole();
        $this->assertNull($quote->panel_warranty_years);
        $this->assertNull($quote->delivery_days);
        $this->assertNull($quote->vat_included);
    }

    public function test_the_client_reads_what_the_price_covers_and_what_it_leaves_out(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [$client, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);
        $this->actingAs($account)->put(route('installer-inbox.quote', $quoteRequest), $this->fullQuote());

        $this->actingAs($client)
            ->get(route('installers.quotes.show', $quoteRequest))
            ->assertOk()
            ->assertSee('Certificación RETIE')
            // The meter was not included, so the page says what that costs the client.
            ->assertSee('no te pagan los excedentes')
            ->assertSee('16 paneles de 550 W · Jinko Tiger Neo', false)
            ->assertSee('Growatt MIN 5000TL-X')
            ->assertSee('25 años')
            ->assertSee('45 días');
    }

    public function test_a_quote_without_legalization_is_flagged_before_comparing_it(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [$client, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);
        // The cheapest total is often the one that leaves the paperwork out (ADR-0027).
        $this->actingAs($account)->put(route('installer-inbox.quote', $quoteRequest), $this->quote());

        $this->actingAs($client)
            ->get(route('installers.quotes.show', $quoteRequest))
            ->assertOk()
            ->assertSee('no cubre todo lo que legaliza la instalación');
    }

    public function test_the_installer_fills_the_quote_in_steps_and_a_rejected_one_marks_where_it_failed(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);

        $this->actingAs($account)
            ->get(route('installer-inbox.show', $quoteRequest))
            ->assertOk()
            ->assertSee('data-quote-panel="precio"', false)
            ->assertSee('data-quote-panel="sistema"', false)
            ->assertSee('data-quote-panel="cubre"', false)
            ->assertSee('data-quote-panel="garantias"', false)
            ->assertSee('data-quote-panel="condiciones"', false);

        $this->actingAs($account)
            ->put(route('installer-inbox.quote', $quoteRequest), [...$this->quote(), 'panel_warranty_years' => 300])
            ->assertSessionHasErrors('panel_warranty_years');

        // A warranty of 300 years belongs to the fourth step, and that is the one the page marks:
        // with the steps folded, an error nobody can see is an error nobody fixes. The view is
        // rendered on its own because the suite runs with the array session driver, where what a
        // redirect flashes never reaches the next request.
        $html = $this->actingAs($account)
            ->withViewErrors(['panel_warranty_years' => 'La garantía de los paneles va entre 1 y 40 años.'])
            ->view('installers.quote-request', app(DescribeQuoteRequest::class)($quoteRequest))
            ->__toString();

        $this->assertSame(1, preg_match_all('/class="[^"]*has-error[^"]*"\s+id="paso-garantias"/', $html));
        $this->assertSame(1, preg_match_all('/class="[^"]*has-error[^"]*"\s+id="paso-/', $html));
        $this->assertStringContainsString('(con errores)', $html);
    }

    public function test_the_client_reads_the_quote_in_steps(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [$client, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);
        $this->actingAs($account)->put(route('installer-inbox.quote', $quoteRequest), $this->fullQuote());

        $response = $this->actingAs($client)->get(route('installers.quotes.show', $quoteRequest))->assertOk();

        // Every step is in the page: without JavaScript they are links to sections that are all shown.
        foreach (['conviene', 'compara', 'cubre', 'garantias', 'firmar'] as $step) {
            $response->assertSee('id="paso-'.$step.'"', false);
            $response->assertSee('data-quote-step="'.$step.'"', false);
        }

        // The price stays out of the steps: it is the number the client never wants to lose.
        $response->assertSee('Lo que te cuesta la instalación');
    }

    /**
     * A quote with the detail of a real one (ADR-0027).
     *
     * @return array<string, mixed>
     */
    private function fullQuote(): array
    {
        return [
            'amount_cop' => 18_400_000,
            'panel_count' => 16,
            'panel_watts' => 550,
            'panel_model' => 'Jinko Tiger Neo',
            'inverter_model' => 'Growatt MIN 5000TL-X',
            'monthly_generation_kwh' => 1100,
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
            'scope' => 'Paneles, inversor, estructura y mano de obra.',
            'exclusions' => 'Obra civil y refuerzo del techo.',
            'valid_until' => now()->addDays(30)->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function quote(): array
    {
        return [
            'amount_cop' => 18_400_000,
            'power_kw' => 5.5,
            'includes_battery' => '1',
            'scope' => 'Paneles, inversor y mano de obra.',
            'valid_until' => now()->addDays(30)->format('Y-m-d'),
        ];
    }

    /**
     * @return array{0: Installer, 1: User}
     */
    private function installerWithAccount(string $name = 'Energía Wayúu', string $username = 'wayuu'): array
    {
        $installer = Installer::query()->create(['name' => $name, 'phone' => '300 000 0002', 'active' => true]);
        // Without coverage the directory filters it out, and the client would never see its price.
        $installer->municipalities()->sync([$this->riohacha()->id]);
        $account = User::factory()->create(['role' => 'installer', 'installer_id' => $installer->id, 'username' => $username]);

        return [$installer, $account];
    }

    private function request(SolarProject $solarProject, Installer $installer): QuoteRequest
    {
        return QuoteRequest::query()->create([
            'solar_project_id' => $solarProject->id,
            'installer_id' => $installer->id,
            'status' => QuoteRequestStatus::SENT,
        ]);
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
        $municipality = $this->riohacha();
        $solarProject = $user->solarProjects()->create([
            'name' => 'Mi casa',
            'property_type' => 'house',
            'location_name' => SolarProject::LOCATION_NAME,
            'municipality_id' => $municipality->id,
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
