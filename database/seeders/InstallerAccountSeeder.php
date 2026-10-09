<?php

namespace Database\Seeders;

use App\Models\Installer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Una cuenta por instalador (ADR-0023), para poder entrar a cualquiera de las bandejas en la demo.
 *
 * Recorre **todos** los instaladores de la base, no una lista fija: el administrador agrega aliados
 * desde la pantalla de instaladores, y al volver a sembrar esos también quedan con su cuenta. El que
 * ya la tiene se deja como está —la contraseña puede haberse cambiado a mano— y solo se repone el
 * vínculo con su ficha.
 *
 * Corre después de InstallerSeeder. Como toda cuenta de desarrollo, la contraseña es la obvia y el
 * correo viene verificado.
 */
class InstallerAccountSeeder extends Seeder
{
    /** La contraseña de todas las cuentas de desarrollo. */
    public const PASSWORD = '12345';

    /**
     * El usuario de cada aliado del InstallerSeeder. `instalador` es el de siempre y sigue siendo el
     * de Energía Wayúu: está documentado en CLAUDE.md y es el que usan las demos.
     */
    private const USERNAMES = [
        'Energía Wayúu' => 'instalador',
        'Sol de Riohacha' => 'solriohacha',
        'Caribe Solar Ingeniería' => 'caribesolar',
        'Soluciones FV del Sur' => 'fvdelsur',
        'Guajira Renovable' => 'guajirarenovable',
    ];

    public function run(): void
    {
        foreach (Installer::query()->orderBy('id')->get() as $installer) {
            $existing = User::query()->where('installer_id', $installer->id)->first();

            if ($existing !== null) {
                // Ya entra: solo se asegura el rol, por si la ficha se creó antes que el rol.
                $existing->forceFill([
                    'role' => 'installer',
                    'email_verified_at' => $existing->email_verified_at ?? now(),
                ])->save();

                continue;
            }

            $username = $this->username($installer);

            $account = User::query()->updateOrCreate(
                ['username' => $username],
                [
                    'name' => $installer->contact_name ?: $installer->name,
                    'email' => $username.'@solar-app.test',
                    'password' => self::PASSWORD,
                    'role' => 'installer',
                    'installer_id' => $installer->id,
                ],
            );

            $account->forceFill(['email_verified_at' => $account->email_verified_at ?? now()])->save();
        }
    }

    /**
     * El usuario de la lista, o uno derivado del nombre para los aliados que agregó un
     * administrador. Si ese nombre ya lo usa otra cuenta, se desempata con el id de la ficha.
     */
    private function username(Installer $installer): string
    {
        $username = self::USERNAMES[$installer->name] ?? Str::slug($installer->name);

        $taken = User::query()
            ->where('username', $username)
            ->where(fn ($query) => $query->whereNull('installer_id')->orWhere('installer_id', '!=', $installer->id))
            ->exists();

        return $taken ? $username.'-'.$installer->id : $username;
    }
}
