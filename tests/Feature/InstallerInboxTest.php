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
 * ADR-0023: the installer enters with an account of their own, reads the requests their company
 * received and says how each one went.
 */
class InstallerInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_says_which_request_to_open(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [$client, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);

        $this->actingAs($account)
            ->get(route('installer-inbox.index'))
            ->assertOk()
            ->assertSee('Mi casa')
            ->assertSee('Sin responder')
            ->assertSee('300 kWh/mes')
            ->assertSee($client->name)
            ->assertSee(route('installer-inbox.show', $quoteRequest));
    }

    public function test_the_request_page_shows_the_estimate_the_client_and_the_appliances_they_registered(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [$client, $solarProject] = $this->project();
        // What the client registered in their diary (ADR-0013): a fridge in the kitchen.
        $solarProject->appliances()->create([
            'space' => 'kitchen',
            'appliance_key' => 'fridge',
            'variant_key' => 'medium.conventional',
            'quantity' => 1,
            'hours_per_day' => 24,
        ]);
        $quoteRequest = $this->request($solarProject, $installer);

        $this->actingAs($account)
            ->get(route('installer-inbox.show', $quoteRequest))
            ->assertOk()
            ->assertSee('Mi casa')
            // The estimate the client already calculated: 300 kWh a month on a 40 m² roof.
            ->assertSee('300 kWh/mes')
            ->assertSee('40 m²')
            // The client decided to make contact by asking.
            ->assertSee($client->name)
            ->assertSee($client->email)
            // The appliances, space by space: that is the visit half done.
            ->assertSee('1 equipo registrado')
            ->assertSeeInOrder(['Cocina', 'Nevera']);
    }

    public function test_the_installer_answers_and_a_closed_deal_carries_its_value(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [, $solarProject] = $this->project();
        $quoteRequest = $this->request($solarProject, $installer);

        $this->actingAs($account)
            ->put(route('installer-inbox.update', $quoteRequest), ['status' => 'contacted'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('installer-inbox.show', $quoteRequest));
        $this->assertSame('contacted', $quoteRequest->fresh()->status);
        $this->assertNotNull($quoteRequest->fresh()->answered_at);

        // Won without a value is the one number the commission will need, lost.
        $this->actingAs($account)
            ->put(route('installer-inbox.update', $quoteRequest), ['status' => 'won'])
            ->assertSessionHasErrors(['quote_request' => 'Escribe en cuánto se cerró el negocio.']);
        $this->assertSame('contacted', $quoteRequest->fresh()->status);

        $this->actingAs($account)
            ->put(route('installer-inbox.update', $quoteRequest), ['status' => 'won', 'contract_value_cop' => 18400000])
            ->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(18400000, (float) $quoteRequest->fresh()->contract_value_cop, 0.01);

        // Reopening it drops the value: it would read as money that came in.
        $this->actingAs($account)
            ->put(route('installer-inbox.update', $quoteRequest), ['status' => 'lost'])
            ->assertSessionHasNoErrors();
        $this->assertNull($quoteRequest->fresh()->contract_value_cop);

        // "Sent" is how a request is born, not an answer: going back would erase one that happened.
        $this->actingAs($account)
            ->put(route('installer-inbox.update', $quoteRequest), ['status' => 'sent'])
            ->assertSessionHasErrors('quote_request');
    }

    public function test_signing_in_lands_an_installer_on_their_inbox(): void
    {
        // Its own test, with nobody signed in: the "guest" middleware of the login route sends an
        // already authenticated visitor somewhere else before Fortify answers.
        [, $account] = $this->installerWithAccount();
        $account->forceFill(['password' => 'Un4-clave-larga'])->save();

        $this->post(route('login'), ['email' => $account->username, 'password' => 'Un4-clave-larga'])
            ->assertRedirect(route('installer-inbox.index'));
    }

    public function test_signing_in_still_lands_a_client_on_their_projects(): void
    {
        $client = User::factory()->create(['username' => 'cliente-demo', 'password' => 'Un4-clave-larga']);

        $this->post(route('login'), ['email' => $client->username, 'password' => 'Un4-clave-larga'])
            ->assertRedirect(config('fortify.home'));
    }

    public function test_the_installer_reads_the_prices_the_app_quotes_with(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        $municipality = Municipality::query()->create([
            'name' => 'Maicao', 'department' => 'La Guajira', 'zone' => 'Media Guajira',
            'latitude' => 11.3778, 'longitude' => -72.2389, 'active' => true,
        ]);
        $installer->municipalities()->sync([$municipality->id]);
        MunicipalitySolarPrice::query()->create([
            'municipality_id' => $municipality->id, 'zone_name' => 'Media Guajira', 'location_type' => 'urbana',
            'base_price_per_kw' => 4_000_000, 'logistic_factor' => 1, 'active' => true,
        ]);

        $this->actingAs($account)
            ->get(route('installer-prices.index'))
            ->assertOk()
            ->assertSee('Maicao')
            ->assertSee('$4.000.000')
            // The municipalities this installer covers are worth reading first.
            ->assertSee('Lo cubres')
            // A tariff is hundreds of pesos: it must not be rounded to the nearest thousand.
            ->assertSee('$890');

        // It belongs to the installer: a client has no business reading it here.
        [$client] = $this->project();
        $this->actingAs($client)->get(route('installer-prices.index'))->assertForbidden();
    }

    public function test_an_installer_never_sees_or_answers_what_belongs_to_another(): void
    {
        [$installer, $account] = $this->installerWithAccount();
        [$other] = $this->installerWithAccount('Otra empresa', 'otra');
        [, $solarProject] = $this->project();
        $otherRequest = $this->request($solarProject, $other);

        $this->actingAs($account)
            ->get(route('installer-inbox.index'))
            ->assertOk()
            ->assertSee('Todavía no te han pedido cotización');

        $this->actingAs($account)->get(route('installer-inbox.show', $otherRequest))->assertForbidden();
        $this->actingAs($account)
            ->put(route('installer-inbox.update', $otherRequest), ['status' => 'won', 'contract_value_cop' => 1000])
            ->assertForbidden();
        $this->assertSame('sent', $otherRequest->fresh()->status);

        // Clients and administrators have no inbox: it belongs to one company.
        [$client] = $this->project();
        $this->actingAs($client)->get(route('installer-inbox.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('installer-inbox.index'))->assertForbidden();

        $this->assertSame(1, QuoteRequest::query()->count());
        $this->assertSame($installer->id, $installer->id);
    }

    public function test_only_an_administrator_creates_the_account_of_an_installer(): void
    {
        $installer = Installer::query()->create(['name' => 'Sol de Riohacha', 'phone' => '300 000 0001', 'active' => true]);
        $admin = User::factory()->create(['role' => 'admin']);
        [$client] = $this->project();

        $this->actingAs($client)
            ->post(route('installers.account.store', $installer), $this->account())
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('installers.account.store', $installer), $this->account())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('installers.edit', $installer));

        $account = $installer->fresh()->account;
        $this->assertSame('installer', $account->role);
        $this->assertTrue($account->isInstaller());
        $this->assertNotNull($account->email_verified_at);

        // Editing without touching the password keeps the one in use.
        $this->actingAs($admin)
            ->post(route('installers.account.store', $installer), [...$this->account(), 'password' => '', 'email' => 'otro@ejemplo.test'])
            ->assertSessionHasNoErrors();
        $this->assertSame('otro@ejemplo.test', $account->fresh()->email);
        $this->assertSame(1, User::query()->where('role', 'installer')->count());
    }

    /**
     * @return array<string, string>
     */
    private function account(): array
    {
        return [
            'account_name' => 'Oficina comercial',
            'username' => 'sol-riohacha',
            'email' => 'sol@ejemplo.test',
            'password' => 'Un4-clave-larga',
        ];
    }

    /**
     * @return array{0: Installer, 1: User}
     */
    private function installerWithAccount(string $name = 'Energía Wayúu', string $username = 'wayuu'): array
    {
        $installer = Installer::query()->create(['name' => $name, 'phone' => '300 000 0002', 'active' => true]);
        $account = User::factory()->create(['role' => 'installer', 'installer_id' => $installer->id, 'username' => $username]);

        return [$installer, $account];
    }

    private function request(SolarProject $solarProject, Installer $installer): QuoteRequest
    {
        return QuoteRequest::query()->create([
            'solar_project_id' => $solarProject->id,
            'installer_id' => $installer->id,
            'status' => 'sent',
        ]);
    }

    /**
     * @return array{0: User, 1: SolarProject}
     */
    private function project(): array
    {
        $user = User::factory()->create();
        $municipality = Municipality::query()->firstOrCreate(
            ['name' => 'Riohacha', 'department' => 'La Guajira'],
            ['latitude' => 11.5444, 'longitude' => -72.9072, 'active' => true],
        );
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
