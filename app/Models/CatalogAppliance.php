<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An appliance added by an administrator to the catalog (ADR-0017).
 *
 * @property list<array{key: string, label: string, watts: float}> $variants
 * @property list<string> $segments
 */
class CatalogAppliance extends Model
{
    protected $fillable = [
        'key',
        'label',
        'icon',
        'segments',
        'usage',
        'default_hours',
        'default_quantity',
        'hint',
        'option_label',
        'variants',
        'active',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'segments' => 'array',
            'variants' => 'array',
            'default_hours' => 'float',
            'default_quantity' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * The same shape as a built-in entry of App\Domain\Consumption\ApplianceCatalog: one option group
     * when it has several variants, "default" when it has one.
     *
     * @return array<string, mixed>
     */
    public function toCatalogEntry(): array
    {
        $variants = array_values($this->variants ?? []);
        $several = count($variants) > 1;

        return [
            'label' => $this->label,
            'icon' => $this->icon,
            'segments' => array_values($this->segments ?? []),
            'usage' => $this->usage,
            'default_hours' => (float) $this->default_hours,
            'default_quantity' => (int) $this->default_quantity,
            'hint' => $this->hint,
            'groups' => $several
                ? [['key' => 'option', 'label' => $this->option_label ?: 'Tipo', 'choices' => array_map(
                    fn (array $variant) => ['key' => $variant['key'], 'label' => $variant['label']],
                    $variants,
                )]]
                : [],
            'default_variant' => $several ? $variants[0]['key'] : 'default',
            'watts' => $several
                ? array_map('floatval', array_column($variants, 'watts', 'key'))
                : ['default' => (float) ($variants[0]['watts'] ?? 0)],
            'active' => (bool) $this->active,
            'custom' => true,
        ];
    }
}
