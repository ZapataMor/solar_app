<?php

namespace App\Domain\Installers;

/**
 * The system an installer proposes, read from what they wrote (ADR-0027).
 *
 * The GIZ quote sheet asks for the nominal power and for the panels with their make and wattage.
 * They are the same number said twice, so the installer writes whichever they have at hand and the
 * app completes the other instead of asking again.
 */
final class QuoteSystem
{
    /**
     * The power of the quote in kW. What the installer wrote wins: they are the ones signing it,
     * and a system is more than the sum of its panels.
     */
    public static function powerKw(?float $written, ?int $panelCount, ?int $panelWatts): ?float
    {
        if ($written !== null && $written > 0) {
            return round($written, 2);
        }

        if ($panelCount === null || $panelWatts === null || $panelCount <= 0 || $panelWatts <= 0) {
            return null;
        }

        return round($panelCount * $panelWatts / 1000, 2);
    }

    /**
     * The batteries of a quote, as a comparison reads them (ADR-0028): "9,6 kWh", "No lleva", or
     * null when they said there are batteries without saying how big.
     *
     * A quote without batteries did say something, so its cell must not read *No lo dice*: that
     * would turn an answer into a silence.
     */
    public static function batteryText(bool $includesBattery, ?float $kwh): ?string
    {
        if (! $includesBattery) {
            return 'No lleva';
        }

        return $kwh !== null && $kwh > 0 ? number_format($kwh, $kwh < 10 ? 1 : 0, ',', '.').' kWh' : null;
    }

    /**
     * "16 paneles de 550 W · Jinko Tiger Neo", with whatever part of it is known.
     */
    public static function panelText(?int $panelCount, ?int $panelWatts, ?string $panelModel): ?string
    {
        $parts = [];

        if ($panelCount !== null && $panelCount > 0) {
            $parts[] = $panelCount === 1 ? '1 panel' : $panelCount.' paneles';
        }

        if ($panelWatts !== null && $panelWatts > 0) {
            $parts[] = ($parts === [] ? 'Paneles de ' : 'de ').$panelWatts.' W';
        }

        $text = $parts === [] ? null : implode(' ', $parts);
        $model = $panelModel !== null && trim($panelModel) !== '' ? trim($panelModel) : null;

        return match (true) {
            $text !== null && $model !== null => $text.' · '.$model,
            $text !== null => $text,
            default => $model,
        };
    }
}
