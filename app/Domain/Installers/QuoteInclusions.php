<?php

namespace App\Domain\Installers;

/**
 * What a quote covers, and what the client reads when it does not (ADR-0027).
 *
 * These are the items that decide whether two quotes for the same roof are comparable at all. In
 * Colombia the paperwork with the grid operator and the bidirectional meter are millions of pesos:
 * the cheaper total is often the one that leaves them out. One list for both screens, so what the
 * installer ticks and what the client reads can never drift apart.
 */
final class QuoteInclusions
{
    public const BATTERY = 'includes_battery';

    public const RETIE = 'includes_retie';

    public const GRID_PAPERWORK = 'includes_grid_paperwork';

    public const BIDIRECTIONAL_METER = 'includes_bidirectional_meter';

    public const MAINTENANCE = 'includes_maintenance';

    public const ALL = [
        self::RETIE,
        self::GRID_PAPERWORK,
        self::BIDIRECTIONAL_METER,
        self::BATTERY,
        self::MAINTENANCE,
    ];

    /**
     * Each item as the installer ticks it, why it matters, and what its absence means for the
     * client. The order is the one the two screens use.
     *
     * @return array<string, array{label: string, hint: string, missing: string}>
     */
    public static function all(): array
    {
        return [
            self::RETIE => [
                'label' => 'Certificación RETIE',
                'hint' => 'La inspección y el certificado de la instalación eléctrica.',
                'missing' => 'Sin el RETIE la instalación no se puede legalizar, aunque funcione.',
            ],
            self::GRID_PAPERWORK => [
                'label' => 'Trámite con el operador de red',
                'hint' => 'La solicitud de conexión y el registro como autogenerador.',
                'missing' => 'El trámite corre por tu cuenta: en Colombia cuesta entre $2 y $5 millones.',
            ],
            self::BIDIRECTIONAL_METER => [
                'label' => 'Medidor bidireccional',
                'hint' => 'El contador que mide lo que entregas a la red.',
                'missing' => 'Sin medidor bidireccional no te pagan los excedentes; cuesta entre $1,5 y $3 millones.',
            ],
            self::BATTERY => [
                'label' => 'Baterías',
                'hint' => 'Respaldo cuando no hay sol o se va la luz.',
                'missing' => 'De noche sigues comprando energía de la red.',
            ],
            self::MAINTENANCE => [
                'label' => 'Mantenimiento del primer año',
                'hint' => 'Limpieza y revisión incluidas en el precio.',
                'missing' => 'El mantenimiento se cobra aparte desde el primer año.',
            ],
        ];
    }

    /**
     * The ones a quote covers and the ones it leaves out, each with its text, ready to list.
     *
     * @param  array<string, bool>  $flags
     * @return array{included: list<array{key: string, label: string, hint: string}>, excluded: list<array{key: string, label: string, missing: string}>}
     */
    public static function split(array $flags): array
    {
        $included = [];
        $excluded = [];

        foreach (self::all() as $key => $item) {
            if ($flags[$key] ?? false) {
                $included[] = ['key' => $key, 'label' => $item['label'], 'hint' => $item['hint']];
            } else {
                $excluded[] = ['key' => $key, 'label' => $item['label'], 'missing' => $item['missing']];
            }
        }

        return ['included' => $included, 'excluded' => $excluded];
    }

    /**
     * The ones that legalize the installation. A quote without them is not cheaper, it is shorter:
     * the client still has to pay for them, and that is the warning the page gives.
     *
     * @param  array<string, bool>  $flags
     */
    public static function missesLegalization(array $flags): bool
    {
        return ! ($flags[self::RETIE] ?? false) || ! ($flags[self::GRID_PAPERWORK] ?? false);
    }
}
