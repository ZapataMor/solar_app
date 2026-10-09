<?php

namespace Tests\Feature;

use App\Domain\Installers\QuoteRequestStatus;
use App\Models\Installer;
use App\Models\QuoteRequest;
use App\Models\SolarProject;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_projects_are_located_in_a_municipality(): void
    {
        $this->seed(DatabaseSeeder::class);

        $locations = SolarProject::with('municipality')->get()
            ->mapWithKeys(fn (SolarProject $project) => [$project->name => [
                $project->municipality?->name,
                $project->location_type,
                (float) $project->latitude === (float) $project->municipality?->latitude,
            ]])
            ->all();

        $this->assertSame([
            'Vivienda familiar Riohacha' => ['Riohacha', 'urbana', true],
            'Local comercial centro' => ['Maicao', 'urbana', true],
            'Institución educativa rural' => ['Uribia', 'rural', true],
        ], $locations);
    }

    /**
     * The three demo projects of the pitch: one per kind of place, with Air-e tariffs and real appliances.
     */
    public function test_the_demo_projects_are_a_house_a_business_and_an_institution(): void
    {
        $this->seed(DatabaseSeeder::class);
        // Seeding again updates them instead of duplicating them.
        $this->seed(DatabaseSeeder::class);

        $projects = SolarProject::with(['user', 'technicalParameter', 'appliances'])->orderBy('id')->get();

        $this->assertCount(3, $projects);
        $this->assertSame(['cliente'], $projects->pluck('user.username')->unique()->values()->all());

        // None writes its own tariff: they follow the reference one (ADR-0015).
        $this->assertTrue($projects->every(fn (SolarProject $project) => $project->usesReferenceEnergyRate()));

        $summary = $projects->mapWithKeys(fn (SolarProject $project) => [$project->property_type => [
            $project->location_name,
            (float) $project->energy_rate_cop_kwh,
            round($project->monthlyConsumption()),
            (float) $project->technicalParameter->available_area_m2,
            $project->appliances->count(),
        ]])->all();

        $this->assertSame([
            'house' => ['Riohacha, La Guajira, Colombia', 890.0, 534.0, 38.0, 14],
            'business' => ['Maicao, La Guajira, Colombia', 1068.0, 1115.0, 50.0, 11],
            'institution' => ['Uribia, La Guajira, Colombia', 890.0, 897.0, 130.0, 12],
        ], $summary);
    }

    /**
     * Cada aliado entra con su propia cuenta (ADR-0023): en la demo hay que poder abrir cualquier
     * bandeja, no solo la de uno.
     */
    public function test_every_installer_has_an_account_to_log_in_with(): void
    {
        $this->seed(DatabaseSeeder::class);
        // Sembrar otra vez no crea una segunda cuenta para el mismo instalador.
        $this->seed(DatabaseSeeder::class);

        $installers = Installer::query()->orderBy('id')->get();
        $accounts = User::query()->where('role', 'installer')->get();

        $this->assertSame($installers->count(), $accounts->count());
        $this->assertSame(
            $installers->pluck('id')->sort()->values()->all(),
            $accounts->pluck('installer_id')->sort()->values()->all(),
        );
        // La de siempre sigue siendo la de Energía Wayúu: está documentada y la usan las demos.
        $this->assertSame(
            'Energía Wayúu',
            User::query()->where('username', 'instalador')->first()->installer->name,
        );
    }

    /**
     * Varios precios por proyecto, que es lo que el comparador necesita para tener algo que decir
     * (ADR-0028, ADR-0029), y uno con una sola cotización, porque ese caso también existe.
     */
    public function test_some_projects_arrive_with_several_quotes_to_compare(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $quotes = SolarProject::query()
            ->with('quoteRequests.installerQuote')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (SolarProject $project) => [
                $project->name => $project->quoteRequests->filter(
                    fn (QuoteRequest $request) => $request->installerQuote !== null,
                )->count(),
            ])
            ->all();

        $this->assertSame([
            'Vivienda familiar Riohacha' => 3,
            'Local comercial centro' => 2,
            'Institución educativa rural' => 1,
        ], $quotes);
    }

    /**
     * Las tres de la casa son las que estrenan el comparador: una barata que deja la legalización
     * afuera, una completa con baterías y una con el precio ya vencido.
     */
    public function test_the_three_quotes_of_the_house_are_the_ones_worth_comparing(): void
    {
        $this->seed(DatabaseSeeder::class);

        $house = SolarProject::query()->where('name', 'Vivienda familiar Riohacha')->firstOrFail();
        $quotes = $house->quoteRequests()->with(['installer', 'installerQuote'])->get()
            ->filter(fn (QuoteRequest $request) => $request->installerQuote !== null)
            ->sortBy(fn (QuoteRequest $request) => (float) $request->installerQuote->amount_cop)
            ->values();

        $cheapest = $quotes->first()->installerQuote;
        $this->assertTrue($cheapest->missesLegalization());
        $this->assertFalse($cheapest->includes_battery);

        $dearest = $quotes->last()->installerQuote;
        $this->assertFalse($dearest->missesLegalization());
        $this->assertTrue($dearest->includes_battery);

        // Y una vencida, para que la tabla muestre que se lee pero no compite (ADR-0026).
        $this->assertSame(1, $quotes->filter(fn (QuoteRequest $request) => $request->installerQuote->hasExpired())->count());
    }

    /**
     * Un negocio cerrado no puede decir que cotizó una cifra y cerró en otra muy distinta.
     */
    public function test_the_closed_deal_matches_the_price_that_was_quoted(): void
    {
        $this->seed(DatabaseSeeder::class);

        $won = QuoteRequest::query()
            ->where('status', QuoteRequestStatus::WON)
            ->with('installerQuote')
            ->firstOrFail();

        $quoted = (float) $won->installerQuote->amount_cop;
        $contract = (float) $won->contract_value_cop;

        $this->assertGreaterThan(0, $contract);
        $this->assertLessThanOrEqual($quoted, $contract);
        $this->assertGreaterThan($quoted * 0.9, $contract);
    }
}
