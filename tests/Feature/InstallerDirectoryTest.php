<?php

namespace Tests\Feature;

use App\Models\Installer;
use App\Models\Municipality;
use App\Models\QuoteRequest;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * ADR-0021: the client finds the installers that cover their project's municipality and asks one of
 * them for a quote; an administrator keeps the list.
 */
class InstallerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_directory_only_shows_who_covers_the_project_municipality(): void
    {
        [$riohacha, $uribia] = $this->municipalities();
        $covering = $this->installer('Sol de Riohacha', [$riohacha]);
        $this->installer('Energía Wayúu', [$uribia]);

        [$client] = $this->project($riohacha);

        $this->actingAs($client)
            ->get(route('installers.index'))
            ->assertOk()
            ->assertSee('Sol de Riohacha')
            ->assertDontSee('Energía Wayúu')
            // The contact data waits for the request.
            ->assertDontSee($covering->phone)
            ->assertDontSee($covering->email)
            ->assertSee('Pedir cotización');
    }

    public function test_the_directory_hands_the_3d_scene_the_roof_of_the_chosen_project(): void
    {
        [$riohacha] = $this->municipalities();
        $this->installer('Sol de Riohacha', [$riohacha]);
        [$client] = $this->project($riohacha);

        $html = $this->actingAs($client)->get(route('installers.index'))->assertOk()->getContent();

        // 40 m² × 80 % ÷ 2,6 m² = 12 slots; 300 kWh need fewer panels, and the roof is not filled (ADR-0014).
        $this->assertMatchesRegularExpression(
            '/<figure[^>]*data-solar-scene[^>]*data-scene-mode="preview"[^>]*data-property-type="house"[^>]*data-panels-fit="12"/s',
            $html,
        );
        $this->assertStringContainsString('data-sketch="house"', $html);
        // The scene downloads with the page, from the head, so it arrives sooner.
        $this->assertStringContainsString(Vite::asset('resources/js/solar-scene/scene.js'), $html);
    }

    public function test_asking_for_a_quote_records_the_lead_once_and_reveals_the_contact(): void
    {
        [$riohacha] = $this->municipalities();
        $installer = $this->installer('Sol de Riohacha', [$riohacha]);
        [$client, $solarProject] = $this->project($riohacha);

        $this->actingAs($client)
            ->post(route('installers.quote-requests.store', $installer), ['solar_project_id' => $solarProject->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('installers.index', ['proyecto' => $solarProject->id]));

        $request = QuoteRequest::query()->sole();
        $this->assertSame('sent', $request->status);
        $this->assertSame($solarProject->id, $request->solar_project_id);

        // Asking again keeps the first lead, so the date of the contact does not move.
        $this->actingAs($client)
            ->post(route('installers.quote-requests.store', $installer), ['solar_project_id' => $solarProject->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, QuoteRequest::query()->count());

        $this->actingAs($client)
            ->get(route('installers.index', ['proyecto' => $solarProject->id]))
            ->assertOk()
            ->assertSee('Solicitud enviada')
            ->assertSee($installer->phone)
            ->assertSee($installer->email)
            ->assertDontSee('Pedir cotización');
    }

    public function test_a_project_without_consumption_cannot_ask_for_a_quote(): void
    {
        [$riohacha] = $this->municipalities();
        $installer = $this->installer('Sol de Riohacha', [$riohacha]);
        [$client, $solarProject] = $this->project($riohacha, monthlyConsumption: 0);

        $this->actingAs($client)
            ->get(route('installers.index'))
            ->assertOk()
            ->assertSee('Define el consumo del proyecto para pedirle cotización.')
            ->assertDontSee('Pedir cotización');

        $this->actingAs($client)
            ->post(route('installers.quote-requests.store', $installer), ['solar_project_id' => $solarProject->id])
            ->assertSessionHasErrors('installer');
        $this->assertSame(0, QuoteRequest::query()->count());
    }

    public function test_a_client_cannot_quote_a_project_that_is_not_theirs_or_an_installer_outside_its_coverage(): void
    {
        [$riohacha, $uribia] = $this->municipalities();
        $elsewhere = $this->installer('Energía Wayúu', [$uribia]);
        [, $solarProject] = $this->project($riohacha);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('installers.quote-requests.store', $elsewhere), ['solar_project_id' => $solarProject->id])
            ->assertForbidden();

        $owner = $solarProject->user;
        $this->actingAs($owner)
            ->post(route('installers.quote-requests.store', $elsewhere), ['solar_project_id' => $solarProject->id])
            ->assertSessionHasErrors('installer');

        $this->assertSame(0, QuoteRequest::query()->count());
    }

    public function test_only_administrators_add_installers_and_hiding_one_takes_it_off_the_directory(): void
    {
        [$riohacha] = $this->municipalities();
        [$client] = $this->project($riohacha);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($client)->get(route('installers.create'))->assertForbidden();
        $this->actingAs($client)->get(route('installers.index'))->assertDontSee(route('installers.create'));

        $this->actingAs($admin)
            ->post(route('installers.store'), [
                'name' => 'Caribe Solar',
                'tagline' => 'Proyectos comerciales',
                'phone' => '300 000 0003',
                'municipalities' => [$riohacha->id],
                'active' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('installers.index'));

        $installer = Installer::query()->sole();
        $this->assertSame([$riohacha->id], $installer->municipalities()->pluck('municipalities.id')->all());
        // By the card, not by the name: the flash of the previous request also says it.
        $card = 'data-test="installer-'.$installer->id.'"';
        $this->actingAs($client)
            ->get(route('installers.index'))
            ->assertSee($card, false)
            // Editing is the administrator's, even on a card the client can see.
            ->assertDontSee(route('installers.edit', $installer));
        $this->actingAs($admin)->get(route('installers.index'))->assertSee(route('installers.edit', $installer));

        // Hidden: gone for the client, still there for the administrator.
        $this->actingAs($admin)
            ->put(route('installers.update', $installer), [
                'name' => 'Caribe Solar',
                'phone' => '300 000 0003',
                'municipalities' => [$riohacha->id],
                'active' => '0',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($client)->get(route('installers.index'))->assertDontSee($card, false);
        $this->actingAs($admin)->get(route('installers.index'))->assertSee('Oculto');
    }

    public function test_an_installer_needs_a_way_to_be_contacted_and_a_municipality(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('installers.store'), ['name' => 'Sin datos'])
            ->assertSessionHasErrors([
                'phone' => 'Deja al menos un teléfono o un correo de contacto.',
                'municipalities' => 'Elige al menos un municipio que cubra.',
            ]);

        $this->assertSame(0, Installer::query()->count());
    }

    /**
     * @return array{0: Municipality, 1: Municipality}
     */
    private function municipalities(): array
    {
        return [
            Municipality::query()->create(['name' => 'Riohacha', 'department' => 'La Guajira', 'latitude' => 11.5444, 'longitude' => -72.9072, 'active' => true]),
            Municipality::query()->create(['name' => 'Uribia', 'department' => 'La Guajira', 'latitude' => 11.7149, 'longitude' => -72.2660, 'active' => true]),
        ];
    }

    /**
     * @param  list<Municipality>  $municipalities
     */
    private function installer(string $name, array $municipalities): Installer
    {
        $installer = Installer::query()->create([
            'name' => $name,
            'tagline' => 'Instalación residencial',
            'phone' => '300 000 000'.Installer::query()->count(),
            'email' => str($name)->slug().'@ejemplo.test',
            'years_experience' => 7,
            'active' => true,
        ]);
        $installer->municipalities()->sync(array_map(fn (Municipality $one) => $one->id, $municipalities));

        return $installer;
    }

    /**
     * @return array{0: User, 1: SolarProject}
     */
    private function project(Municipality $municipality, float $monthlyConsumption = 300): array
    {
        $user = User::factory()->create();
        $solarProject = $user->solarProjects()->create([
            'name' => 'Mi casa',
            'property_type' => 'house',
            'location_name' => SolarProject::LOCATION_NAME,
            'municipality_id' => $municipality->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'monthly_consumption_kwh' => $monthlyConsumption,
            'energy_rate_cop_kwh' => 900,
        ]);
        $solarProject->technicalParameter()->create([
            'available_area_m2' => 40, 'usable_area_percentage' => 80, 'panel_power_w' => 550,
            'panel_area_m2' => 2.6, 'performance_ratio' => 0.86, 'system_losses_percentage' => 14,
        ]);

        return [$user, $solarProject];
    }
}
