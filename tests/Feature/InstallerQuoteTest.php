<?php

namespace Tests\Feature;

use App\Domain\Installers\QuoteRequestStatus;
use App\Models\Installer;
use App\Models\InstallerQuote;
use App\Models\Municipality;
use App\Models\QuoteRequest;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_the_page_puts_the_other_offers_next_to_this_one(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [$other, $otherAccount] = $this->installerWithAccount('Sol de Riohacha', 'sol');
        [$client, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);
        $otherRequest = $this->request($solarProject, $other);
        $this->actingAs($account)->put(route('installer-inbox.quote', $quoteRequest), $this->quote());
        $this->actingAs($otherAccount)->put(route('installer-inbox.quote', $otherRequest), [...$this->quote(), 'amount_cop' => 22_300_000]);

        $this->actingAs($client)
            ->get(route('installers.quotes.show', $quoteRequest))
            ->assertOk()
            ->assertSee('Sol de Riohacha')
            ->assertSee('$22.300.000');
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
