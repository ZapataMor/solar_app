<?php

namespace App\Actions\Catalog;

use App\Actions\SolarProjects\SyncProjectConsumption;
use App\Domain\Consumption\ApplianceCatalog;
use App\Models\CatalogAppliance;
use App\Models\SolarProject;
use App\Models\SolarProjectAppliance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Use case: an administrator adds an appliance to the catalog or changes one they added (ADR-0017).
 *
 * Variants keep their key when renamed. Diary rows whose option no longer exists move to the
 * default one, and the projects that use the appliance get their consumption recomputed (so they
 * show the "!" to recalculate).
 */
final class SaveCatalogAppliance
{
    /**
     * @param  array{label: string, icon: string, segments: list<string>, usage: string, default_hours: float|string, default_quantity: int|string, hint?: string|null, option_label?: string|null, variants: list<array{key?: string|null, label?: string|null, watts: float|string}>, active?: bool}  $data
     */
    public function __invoke(array $data, ?CatalogAppliance $appliance, ?User $savedBy): CatalogAppliance
    {
        $appliance ??= new CatalogAppliance(['key' => $this->newKey($data['label'])]);

        DB::transaction(function () use ($appliance, $data, $savedBy) {
            $appliance->fill([
                'label' => trim($data['label']),
                'icon' => $data['icon'],
                'segments' => array_values(array_unique($data['segments'])),
                'usage' => $data['usage'],
                'default_hours' => $data['usage'] === 'always' ? 24 : (float) $data['default_hours'],
                'default_quantity' => (int) $data['default_quantity'],
                'hint' => filled($data['hint'] ?? null) ? trim($data['hint']) : null,
                'option_label' => filled($data['option_label'] ?? null) ? trim($data['option_label']) : null,
                'variants' => $this->variants($data['variants']),
                'active' => (bool) ($data['active'] ?? true),
                'user_id' => $appliance->user_id ?? $savedBy?->id,
            ])->save();

            $this->moveOrphanRows($appliance);
        });

        $this->resyncProjectsUsing($appliance->key);

        return $appliance;
    }

    /**
     * Keys unique within the appliance; an existing variant keeps its key even if its label changes.
     *
     * @param  list<array{key?: string|null, label?: string|null, watts: float|string}>  $rows
     * @return list<array{key: string, label: string, watts: float}>
     */
    private function variants(array $rows): array
    {
        $variants = [];
        $taken = [];

        foreach (array_values($rows) as $index => $row) {
            $label = trim((string) ($row['label'] ?? '')) ?: 'Opción '.($index + 1);
            $key = filled($row['key'] ?? null) ? (string) $row['key'] : (Str::slug($label, '_') ?: 'option');

            for ($base = $key, $n = 2; in_array($key, $taken, true); $n++) {
                $key = "{$base}_{$n}";
            }

            $taken[] = $key;
            $variants[] = ['key' => $key, 'label' => $label, 'watts' => round((float) $row['watts'], 2)];
        }

        return $variants;
    }

    /**
     * A free key: never one of the built-in appliances, nor one already added.
     */
    private function newKey(string $label): string
    {
        $base = Str::slug($label, '_') ?: 'appliance';
        $key = $base;

        for ($n = 2; (new ApplianceCatalog)->isBuiltIn($key) || CatalogAppliance::query()->where('key', $key)->exists(); $n++) {
            $key = "{$base}_{$n}";
        }

        return $key;
    }

    /**
     * Diary rows whose option no longer exists (renamed away, removed, or one ↔ several options)
     * take the default option.
     */
    private function moveOrphanRows(CatalogAppliance $appliance): void
    {
        $entry = $appliance->toCatalogEntry();

        SolarProjectAppliance::query()
            ->where('appliance_key', $appliance->key)
            ->whereNotIn('variant_key', array_keys($entry['watts']))
            ->update(['variant_key' => $entry['default_variant']]);
    }

    private function resyncProjectsUsing(string $key): void
    {
        // The catalog of this request was read before the change.
        app()->forgetScopedInstances();
        $sync = app(SyncProjectConsumption::class);

        SolarProject::query()
            ->whereHas('appliances', fn ($query) => $query->where('appliance_key', $key))
            ->get()
            ->each(fn (SolarProject $solarProject) => $sync($solarProject));
    }
}
