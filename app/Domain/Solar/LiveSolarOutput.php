<?php

namespace App\Domain\Solar;

/**
 * What the installed panels would be producing right now with the sun measured by the station,
 * told as appliances it could power ("alcanza para el aire acondicionado y la nevera").
 */
final readonly class LiveSolarOutput
{
    /**
     * @param  list<string>  $poweredAppliances  Labels of the appliances that output could power at once.
     */
    public function __construct(
        public float $irradianceWm2,
        public float $outputKw,
        public array $poweredAppliances,
    ) {}

    /**
     * @param  list<array{label: string, watts: float}>  $appliances  Power drawn by each diary row (watts × quantity).
     */
    public static function for(float $installedCapacityKwp, float $irradianceWm2, float $performanceRatio, array $appliances): self
    {
        // Panels are rated at 1.000 W/m² (standard test conditions).
        $outputKw = max(0.0, $installedCapacityKwp * $irradianceWm2 / 1000 * $performanceRatio);

        // Biggest first: the ones the client cares about (air conditioner, fridge…).
        usort($appliances, fn (array $a, array $b) => $b['watts'] <=> $a['watts']);

        $powered = [];
        $available = $outputKw * 1000;

        foreach ($appliances as $appliance) {
            if ($appliance['watts'] > 0 && $appliance['watts'] <= $available && ! in_array($appliance['label'], $powered, true)) {
                $powered[] = $appliance['label'];
                $available -= $appliance['watts'];
            }
        }

        return new self($irradianceWm2, $outputKw, $powered);
    }

    public function isNight(): bool
    {
        return $this->irradianceWm2 < 20;
    }

    /**
     * How strong the sun is now, in plain words.
     */
    public function sunLabel(): string
    {
        return match (true) {
            $this->isNight() => 'Sin sol',
            $this->irradianceWm2 < 250 => 'Sol débil',
            $this->irradianceWm2 < 600 => 'Sol moderado',
            default => 'Sol fuerte',
        };
    }
}
