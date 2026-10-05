<?php

namespace App\Actions\Installers;

use App\Models\Installer;

/**
 * Use case: an administrator creates or edits an allied installer (ADR-0022), with the municipalities
 * it covers. Hiding one keeps the quote requests it already received.
 */
final class SaveInstaller
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data, ?Installer $installer = null): Installer
    {
        $municipalities = array_map('intval', $data['municipalities'] ?? []);
        unset($data['municipalities']);

        $data['active'] = (bool) ($data['active'] ?? false);

        $installer = $installer === null
            ? Installer::query()->create($data)
            : tap($installer)->update($data);

        $installer->municipalities()->sync($municipalities);

        return $installer->refresh();
    }
}
