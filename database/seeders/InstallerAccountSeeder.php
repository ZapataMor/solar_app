<?php

namespace Database\Seeders;

use App\Domain\Installers\QuoteRequestStatus;
use App\Models\Installer;
use App\Models\InstallerQuote;
use App\Models\QuoteRequest;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The other side of the demo (ADR-0023): one allied installer with an account, and the requests the
 * "cliente" account sent it, in the three states its inbox can show.
 *
 * It runs after InstallerSeeder and UserSolarProjectSeeder: it needs the installers and the projects.
 * Like every development account, the password is the obvious one and the mail is pre-verified.
 */
class InstallerAccountSeeder extends Seeder
{
    public function run(): void
    {
        $installer = Installer::query()->where('name', 'Energía Wayúu')->first();
        $client = User::query()->where('username', 'cliente')->first();

        if ($installer === null || $client === null) {
            return;
        }

        $account = User::query()->updateOrCreate(
            ['username' => 'instalador'],
            [
                'name' => 'Coordinación de proyectos',
                'email' => 'instalador@solar-app.test',
                'password' => '12345',
                'role' => 'installer',
                'installer_id' => $installer->id,
            ],
        );
        $account->forceFill(['email_verified_at' => $account->email_verified_at ?? now()])->save();

        // Only projects this installer covers: Energía Wayúu reaches Maicao, Uribia and Manaure, so a
        // request from Riohacha would be one the app itself refuses to create (ADR-0022), and the
        // client would never see it in their directory.
        // Asked days ago and answered after that, so the dates of a card read in order.
        $requests = [
            ['Institución educativa rural', QuoteRequestStatus::SENT, null, 'Queremos empezar por las aulas; el comedor puede esperar.', 1, null],
            ['Local comercial centro', QuoteRequestStatus::WON, 18_400_000, 'Nos urge por el aire del local.', 20, 12],
        ];

        foreach ($requests as [$projectName, $status, $contractValue, $note, $askedDaysAgo, $answeredDaysAgo]) {
            $project = SolarProject::query()
                ->where('user_id', $client->id)
                ->where('name', $projectName)
                ->first();

            if ($project === null) {
                continue;
            }

            QuoteRequest::query()->updateOrCreate(
                ['solar_project_id' => $project->id, 'installer_id' => $installer->id],
                [
                    'status' => $status,
                    'contract_value_cop' => $contractValue,
                    'note' => $note,
                    'answered_at' => $answeredDaysAgo === null ? null : now()->subDays($answeredDaysAgo),
                ],
            )->forceFill(['created_at' => now()->subDays($askedDaysAgo)])->save();
        }

        $this->requestForRiohacha($client);

        // The closed one carries the price it was closed on (ADR-0026): the client reads the offer in
        // their directory and the installer sees the whole arc, quote and contract, in one card.
        $quoted = QuoteRequest::query()
            ->where('installer_id', $installer->id)
            ->where('status', QuoteRequestStatus::WON)
            ->first();

        if ($quoted !== null) {
            InstallerQuote::query()->updateOrCreate(
                ['quote_request_id' => $quoted->id],
                [
                    'amount_cop' => 17_900_000,
                    // The detail of a real quote (ADR-0027), so the demo shows what it is for.
                    'panel_count' => 16,
                    'panel_watts' => 575,
                    'panel_model' => 'Jinko Tiger Neo 575 W',
                    'inverter_model' => 'Growatt MOD 9000TL3-X',
                    'power_kw' => 9.2,
                    'includes_battery' => false,
                    'monthly_generation_kwh' => 1_180,
                    'includes_retie' => true,
                    'includes_grid_paperwork' => true,
                    'includes_bidirectional_meter' => false,
                    'includes_maintenance' => true,
                    'panel_warranty_years' => 25,
                    'inverter_warranty_years' => 10,
                    'workmanship_warranty_years' => 2,
                    'vat_included' => true,
                    'down_payment_percentage' => 40,
                    'delivery_days' => 45,
                    'scope' => 'Dieciséis paneles de 575 W, inversor, estructura, cableado, protecciones, mano de obra, certificación RETIE y trámite con la electrificadora.',
                    'exclusions' => 'Medidor bidireccional, obra civil y refuerzo del techo si la cubierta lo necesita.',
                    'valid_until' => now()->addDays(21),
                ],
            );
        }
    }

    /**
     * The house in Riohacha asks the installer that does cover Riohacha, so the directory of that
     * project is not empty either.
     */
    private function requestForRiohacha(User $client): void
    {
        $installer = Installer::query()->where('name', 'Sol de Riohacha')->first();
        $project = SolarProject::query()
            ->where('user_id', $client->id)
            ->where('name', 'Vivienda familiar Riohacha')
            ->first();

        if ($installer === null || $project === null) {
            return;
        }

        QuoteRequest::query()->updateOrCreate(
            ['solar_project_id' => $project->id, 'installer_id' => $installer->id],
            ['status' => QuoteRequestStatus::SENT, 'note' => null, 'answered_at' => null],
        )->forceFill(['created_at' => now()->subDays(2)])->save();
    }
}
