<?php

namespace Database\Seeders;

use App\Domain\Installers\QuoteRequestStatus;
use App\Models\Installer;
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

        // One of each state, so the inbox shows what it looks like full: Uribia is the one the
        // installer covers, and the other two are there to give the summary something to add up.
        // Asked days ago and answered after that, so the dates of a card read in order.
        $requests = [
            ['Institución educativa rural', QuoteRequestStatus::SENT, null, 'Queremos empezar por las aulas; el comedor puede esperar.', 1, null],
            ['Vivienda familiar Riohacha', QuoteRequestStatus::CONTACTED, null, null, 6, 4],
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
    }
}
