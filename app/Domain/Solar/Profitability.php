<?php

namespace App\Domain\Solar;

/**
 * Whether a system is a good investment, from how long it takes to pay for itself. The same verdict
 * answers "¿Me conviene?" and labels the portfolio cards and table.
 */
final readonly class Profitability
{
    public const GOOD = 'good';

    public const FAIR = 'fair';

    public const POOR = 'poor';

    /** The savings never pay for the installation. */
    public const NONE = 'none';

    /** Fast payback for this kind of installation. */
    public const GOOD_PAYBACK_YEARS = 6;

    /** Moderate payback: worth it, comparing quotes. */
    public const FAIR_PAYBACK_YEARS = 10;

    private function __construct(
        public string $level,
        public ?float $paybackYears,
    ) {}

    public static function fromPaybackYears(?float $paybackYears): self
    {
        $level = match (true) {
            $paybackYears === null || $paybackYears <= 0 => self::NONE,
            $paybackYears <= self::GOOD_PAYBACK_YEARS => self::GOOD,
            $paybackYears <= self::FAIR_PAYBACK_YEARS => self::FAIR,
            default => self::POOR,
        };

        return new self($level, $level === self::NONE ? null : $paybackYears);
    }

    /**
     * Short label for the card ribbon and the table.
     */
    public function label(): string
    {
        return match ($this->level) {
            self::GOOD => 'Rentable',
            self::FAIR => 'Retorno medio',
            self::POOR => 'Poco rentable',
            default => 'No rentable',
        };
    }

    /**
     * What the label means, for a tooltip.
     */
    public function description(): string
    {
        return match ($this->level) {
            self::GOOD => 'Se paga en '.self::GOOD_PAYBACK_YEARS.' años o menos: un retorno rápido.',
            self::FAIR => 'Se paga en '.self::FAIR_PAYBACK_YEARS.' años o menos: vale la pena, comparando cotizaciones.',
            self::POOR => 'Tarda más de '.self::FAIR_PAYBACK_YEARS.' años en pagarse.',
            default => 'El ahorro no alcanza a pagar la instalación.',
        };
    }

    /**
     * "2 años y 6 meses", "8 meses".
     */
    public static function paybackText(float $years): string
    {
        if ($years < 1) {
            $months = max(1, (int) round($years * 12));

            return $months === 1 ? '1 mes' : "{$months} meses";
        }

        $whole = (int) floor($years);
        $months = (int) round(($years - $whole) * 12);

        if ($months === 12) {
            $whole++;
            $months = 0;
        }

        $text = $whole === 1 ? '1 año' : "{$whole} años";

        return $months === 0 ? $text : $text.' y '.($months === 1 ? '1 mes' : "{$months} meses");
    }
}
