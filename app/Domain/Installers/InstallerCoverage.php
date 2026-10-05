<?php

namespace App\Domain\Installers;

/**
 * The municipalities an installer covers (ADR-0022), as the directory says them.
 *
 * A card has room for a line, not for fifteen names: up to three are listed, and beyond that the
 * first two carry the rest as a count. An installer that covers every active municipality is not a
 * list at all, it is "Toda La Guajira".
 */
final class InstallerCoverage
{
    private const LISTED = 3;

    /**
     * @param  list<string>  $names  Municipality names, in the order they should be read.
     * @param  int  $activeMunicipalities  How many municipalities the app offers today.
     */
    public static function text(array $names, int $activeMunicipalities = 0): string
    {
        $names = array_values(array_filter($names, fn (string $name): bool => trim($name) !== ''));
        $total = count($names);

        if ($total === 0) {
            return 'Sin municipios asignados';
        }

        if ($activeMunicipalities > 0 && $total >= $activeMunicipalities) {
            return 'Toda La Guajira';
        }

        if ($total <= self::LISTED) {
            return self::join($names);
        }

        $rest = $total - 2;

        return $names[0].', '.$names[1].' y '.$rest.' municipios más';
    }

    /**
     * @param  list<string>  $names
     */
    private static function join(array $names): string
    {
        if (count($names) === 1) {
            return $names[0];
        }

        $last = array_pop($names);

        return implode(', ', $names).' y '.$last;
    }

    /**
     * @param  list<int>  $coveredMunicipalityIds
     * @return bool A project without a municipality (older ones) is covered by anyone.
     */
    public static function covers(array $coveredMunicipalityIds, ?int $municipalityId): bool
    {
        return $municipalityId === null || in_array($municipalityId, $coveredMunicipalityIds, true);
    }
}
