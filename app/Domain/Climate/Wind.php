<?php

namespace App\Domain\Climate;

/**
 * Wind of one reading: how fast it blows and where it comes from, in the meteorological convention
 * (0° = from the north, clockwise). The 3D station of the climate data page points its vane there
 * (ADR-0018).
 */
final readonly class Wind
{
    /** Below this a cup anemometer barely turns: the reading counts as calm. */
    public const CALM_KMH = 1.0;

    private const POINTS = ['norte', 'noreste', 'este', 'sureste', 'sur', 'suroeste', 'oeste', 'noroeste'];

    public function __construct(
        public float $speedKmh,
        public ?int $directionDegrees,
    ) {}

    public function isCalm(): bool
    {
        return $this->speedKmh < self::CALM_KMH;
    }

    /**
     * The nearest of the eight points of the compass ("noreste"), or null without a direction.
     */
    public function from(): ?string
    {
        if ($this->directionDegrees === null) {
            return null;
        }

        $degrees = (($this->directionDegrees % 360) + 360) % 360;

        return self::POINTS[(int) round($degrees / 45) % 8];
    }

    /**
     * "13 km/h del noreste", or "en calma".
     */
    public function describe(): string
    {
        if ($this->isCalm()) {
            return 'en calma';
        }

        $speed = number_format($this->speedKmh, $this->speedKmh < 10 ? 1 : 0, ',', '.').' km/h';
        $from = $this->from();

        return $from === null ? $speed : "{$speed} del {$from}";
    }
}
