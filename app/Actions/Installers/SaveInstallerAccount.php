<?php

namespace App\Actions\Installers;

use App\Models\Installer;
use App\Models\User;

/**
 * Use case: an administrator gives an allied installer the account it answers with (ADR-0023). There
 * is no public sign-up: the platform decides who is an ally.
 */
final class SaveInstallerAccount
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Installer $installer, array $data): User
    {
        $account = $installer->account;

        $attributes = [
            'name' => $data['account_name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'role' => 'installer',
            'installer_id' => $installer->id,
        ];

        // On an edit the password field is left empty when it is not being changed.
        if (filled($data['password'] ?? null)) {
            $attributes['password'] = $data['password'];
        }

        if ($account === null) {
            $account = User::query()->create($attributes);
            // The administrator vouches for the address, as the development seeders do: an installer
            // who cannot get past verification cannot answer anything.
            $account->forceFill(['email_verified_at' => now()])->save();

            return $account;
        }

        $account->update($attributes);

        return $account->refresh();
    }
}
