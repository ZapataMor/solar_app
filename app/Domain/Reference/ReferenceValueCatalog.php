<?php

namespace App\Domain\Reference;

use InvalidArgumentException;

/**
 * General values of the system that an administrator keeps up to date, instead of each client
 * typing them (ADR-0015). Each change is recorded with the date it applies from.
 */
final class ReferenceValueCatalog
{
    /** Unit cost (CU) of a kWh from Air-e in La Guajira, without subsidy or contribution. */
    public const ENERGY_RATE = 'energy_rate_cop_kwh';

    /** Share that commercial users pay on top of the kWh (Ley 142 de 1994). */
    public const COMMERCIAL_CONTRIBUTION = 'commercial_contribution_percentage';

    /**
     * @return array<string, ReferenceValueDefinition> In the order the administration screen shows them.
     */
    public static function all(): array
    {
        return [
            self::ENERGY_RATE => new ReferenceValueDefinition(
                key: self::ENERGY_RATE,
                label: 'Tarifa del kWh',
                unit: '$/kWh',
                description: 'Costo unitario de Air-e en La Guajira, sin subsidio ni contribución. Lo usan los proyectos cuyo cliente no escribe su propia tarifa.',
                default: 890,
                min: 1,
                max: 5000,
            ),
            self::COMMERCIAL_CONTRIBUTION => new ReferenceValueDefinition(
                key: self::COMMERCIAL_CONTRIBUTION,
                label: 'Contribución de los negocios',
                unit: '%',
                description: 'Lo que un negocio paga de más sobre la tarifa del kWh. Las casas y las instituciones pagan la tarifa sin ella.',
                default: 20,
                min: 0,
                max: 100,
            ),
        ];
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function definition(string $key): ReferenceValueDefinition
    {
        return self::all()[$key] ?? throw new InvalidArgumentException("Unknown reference value [{$key}].");
    }
}
