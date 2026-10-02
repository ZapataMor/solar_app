<?php

namespace Tests\Feature;

use App\Models\CatalogAppliance;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR-0017: administrators see the catalog as a reference of consumption and add appliances to it.
 */
class ApplianceCatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_see_the_catalog_sorted_by_typical_consumption(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($admin)
            ->get(route('appliance-catalog.index'))
            ->assertOk()
            // 12.000 BTU conventional × 8 h × 30 days = 288 kWh; a medium fridge 43,2 kWh; the modem 7,2 kWh.
            ->assertSeeInOrder(['Aire acondicionado', '288 kWh/mes', 'Nevera', '43 kWh/mes', 'Internet (módem)', '7,2 kWh/mes'])
            // Range and average of its options (600–2.400 W, average 1.312,5 W).
            ->assertSee('600–2.400 W')
            ->assertSee('promedio 1.313 W')
            ->assertSee('8 opciones')
            ->getContent();
        $this->assertStringContainsString('Del sistema', $html);

        $this->actingAs($admin)
            ->get(route('appliance-catalog.index', ['orden' => 'nombre', 'para' => 'business']))
            ->assertOk()
            ->assertSeeInOrder(['Abanico', 'Aire acondicionado', 'Bombillos'])
            ->assertDontSee('Lavadora');

        $client = User::factory()->create();
        $this->actingAs($client)->get(route('appliance-catalog.index'))->assertForbidden();
        $this->actingAs($client)->get(route('solar-projects.index'))->assertDontSee(route('appliance-catalog.index'));
        $this->actingAs($admin)->get(route('solar-projects.index'))->assertSee(route('appliance-catalog.index'));
    }

    public function test_an_added_appliance_reaches_the_diary_and_its_changes_reach_the_projects(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('appliance-catalog.store'), $this->toaster([
                ['label' => '2 ranuras', 'watts' => 850],
                ['label' => '4 ranuras', 'watts' => 1500],
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('appliance-catalog.index', ['orden' => 'nombre']));

        $toaster = CatalogAppliance::query()->sole();
        $this->assertSame('tostadora', $toaster->key);
        $this->assertSame(['2_ranuras', '4_ranuras'], array_column($toaster->variants, 'key'));

        // A client finds it in the diary and adds it: 850 W × 0,29 h a day (2 h a week, stored with 2 decimals) × 30 = 7,4 kWh.
        [$client, $solarProject] = $this->project();
        $this->actingAs($client)->get(route('solar-projects.consumption', $solarProject))->assertSee('Tostadora');
        $this->actingAs($client)
            ->post(route('solar-projects.appliances.store', $solarProject), [
                'space' => 'kitchen', 'key' => 'tostadora', 'variant' => '2_ranuras', 'quantity' => 1, 'hours_per_day' => 2 / 7,
            ])
            ->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(7.395, (float) $solarProject->fresh()->monthly_consumption_kwh, 0.05);

        // Its power is corrected and "2 ranuras" removed: the row moves to the remaining option, and the
        // project's consumption follows (1.000 W × 0,29 h × 30 = 8,7 kWh).
        $this->actingAs($admin)
            ->put(route('appliance-catalog.update', $toaster), $this->toaster([
                ['key' => '4_ranuras', 'label' => '4 ranuras', 'watts' => 1000],
            ], active: '1'))
            ->assertSessionHasNoErrors();

        $this->assertSame('default', $solarProject->appliances()->value('variant_key'));
        $this->assertEqualsWithDelta(8.7, (float) $solarProject->fresh()->monthly_consumption_kwh, 0.05);

        // Hidden: no longer offered, but the project keeps it and its diary still opens.
        $this->actingAs($admin)->put(route('appliance-catalog.update', $toaster), $this->toaster([['key' => '4_ranuras', 'label' => '', 'watts' => 1000]], active: '0'));
        [$otherClient, $otherProject] = $this->project();
        $this->actingAs($otherClient)->get(route('solar-projects.consumption', $otherProject))->assertDontSee('data-diary-pick="tostadora"', false);
        $this->actingAs($client)->get(route('solar-projects.consumption', $solarProject))->assertOk()->assertSee('Tostadora');
        $this->actingAs($admin)->get(route('appliance-catalog.index'))->assertSee('Oculto');
    }

    public function test_an_appliance_needs_a_power_and_never_takes_a_built_in_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('appliance-catalog.store'), $this->toaster([['label' => '', 'watts' => '']]))
            ->assertSessionHasErrors(['variants' => 'Agrega al menos una opción con su potencia.']);

        $this->actingAs($admin)->post(route('appliance-catalog.store'), [...$this->toaster([['label' => '', 'watts' => 60]]), 'label' => 'Fridge']);
        $this->actingAs($admin)->post(route('appliance-catalog.store'), [...$this->toaster([['label' => '', 'watts' => 60]]), 'label' => 'Fridge']);

        // "fridge" is the built-in Nevera: the added ones get their own keys.
        $this->assertSame(['fridge_2', 'fridge_3'], CatalogAppliance::query()->orderBy('id')->pluck('key')->all());
    }

    /**
     * @param  list<array<string, mixed>>  $variants
     * @return array<string, mixed>
     */
    private function toaster(array $variants, ?string $active = null): array
    {
        return array_filter([
            'label' => 'Tostadora',
            'icon' => 'toaster',
            'segments' => ['home'],
            'usage' => 'week',
            'default_hours' => 2,
            'default_quantity' => 1,
            'hint' => 'Unos minutos en el desayuno.',
            'option_label' => 'Tamaño',
            'variants' => $variants,
            'active' => $active,
        ], fn ($value) => $value !== null);
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
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'monthly_consumption_kwh' => 0,
            'energy_rate_cop_kwh' => 900,
        ]);
        $solarProject->technicalParameter()->create([
            'available_area_m2' => 40, 'usable_area_percentage' => 80, 'panel_power_w' => 550,
            'panel_area_m2' => 2.6, 'performance_ratio' => 0.86, 'system_losses_percentage' => 14,
        ]);

        return [$user, $solarProject];
    }
}
