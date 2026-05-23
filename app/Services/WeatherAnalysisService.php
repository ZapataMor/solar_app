<?php

namespace App\Services;

use App\Models\WeatherStationReading;
use Illuminate\Support\Collection;

class WeatherAnalysisService
{
    /**
     * @param  Collection<int, WeatherStationReading>  $readings
     * @return array{current: array<int, array<string, string>>, historical: array<int, array<string, string>>}
     */
    public function analyzeReadings(Collection $readings): array
    {
        if ($readings->isEmpty()) {
            return [
                'current' => [],
                'historical' => [],
            ];
        }

        /** @var Collection<int, WeatherStationReading> $orderedReadings */
        $orderedReadings = $readings
            ->filter(fn ($reading) => $reading instanceof WeatherStationReading)
            ->sortBy('measured_at')
            ->values();

        /** @var WeatherStationReading|null $latestReading */
        $latestReading = $orderedReadings->last();

        if ($latestReading === null) {
            return [
                'current' => [],
                'historical' => [],
            ];
        }

        return [
            'current' => $this->uniqueResults([
                ...$this->analyzeTemperature($latestReading),
                ...$this->analyzeAirQuality($latestReading),
                ...$this->analyzeUvRadiation($latestReading),
                ...$this->detectAnomalies($latestReading, $orderedReadings),
            ]),
            'historical' => $this->uniqueResults($this->analyzeHistoricalBehavior($orderedReadings)),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function analyzeTemperature(WeatherStationReading $reading): array
    {
        $results = [];
        $temperature = $reading->temperature !== null ? (float) $reading->temperature : null;
        $humidity = $reading->humidity !== null ? (float) $reading->humidity : null;
        $thermalSensation = $reading->thermal_sensation !== null ? (float) $reading->thermal_sensation : null;

        if ($temperature !== null && $temperature >= 35) {
            $results[] = $this->result('warning', 'Calor extremo detectado: la temperatura actual supera los 35 C.');
        }

        if ($thermalSensation !== null && $thermalSensation >= 42) {
            $results[] = $this->result('warning', 'Sensacion termica muy alta: el ambiente puede generar estres por calor.');
        }

        if ($humidity !== null && $humidity >= 75) {
            $results[] = $this->result('warning', 'Alta humedad detectada: puede aumentar la sensacion de calor y afectar el confort.');
        }

        if ($temperature !== null && $humidity !== null && $temperature >= 32 && $humidity >= 70) {
            $results[] = $this->result('warning', 'Combinacion de temperatura y humedad elevada: el riesgo de disconfort termico es alto.');
        }

        return $results;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function analyzeAirQuality(WeatherStationReading $reading): array
    {
        $results = [];
        $co2 = $reading->co2;
        $pm25 = $reading->pm25 !== null ? (float) $reading->pm25 : null;
        $pm10 = $reading->pm10 !== null ? (float) $reading->pm10 : null;

        if ($co2 !== null && $co2 >= 1500) {
            $results[] = $this->result('warning', 'Contaminacion elevada por CO2: la ventilacion del entorno deberia revisarse.');
        } elseif ($co2 !== null && $co2 >= 1000) {
            $results[] = $this->result('info', 'CO2 por encima del nivel recomendado para confort prolongado.');
        }

        if ($pm25 !== null && $pm25 >= 35) {
            $results[] = $this->result('warning', 'PM2.5 elevada: la calidad del aire fino requiere atencion.');
        }

        if ($pm10 !== null && $pm10 >= 50) {
            $results[] = $this->result('warning', 'PM10 elevada: hay presencia significativa de particulas en el ambiente.');
        }

        return $results;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function analyzeUvRadiation(WeatherStationReading $reading): array
    {
        $results = [];
        $uvIndex = $reading->uv_index !== null ? (float) $reading->uv_index : null;
        $radiation = $reading->solar_radiation !== null
            ? (float) $reading->solar_radiation
            : $reading->radiationValue();
        $hour = $reading->measured_at?->hour;

        if (($uvIndex !== null && $uvIndex >= 6) || ($radiation !== null && $radiation >= 700)) {
            $results[] = $this->result('warning', 'Alta radiacion detectada: el potencial solar y la exposicion UV estan elevados.');
        }

        if ($hour !== null && $hour >= 8 && $hour <= 16) {
            if (($uvIndex !== null && $uvIndex <= 2) || ($radiation !== null && $radiation <= 120)) {
                $results[] = $this->result('info', 'Baja radiacion detectada para una franja diurna: conviene revisar nubosidad o variaciones del sensor.');
            }
        }

        return $results;
    }

    /**
     * @param  Collection<int, WeatherStationReading>  $readings
     * @return array<int, array<string, string>>
     */
    public function detectAnomalies(WeatherStationReading $reading, Collection $readings): array
    {
        $results = [];
        $history = $readings
            ->filter(fn (WeatherStationReading $item) => $item->id !== $reading->id)
            ->values();

        if ($history->count() < 4) {
            return $results;
        }

        $checks = [
            'temperatura' => [
                'value' => $reading->temperature !== null ? (float) $reading->temperature : null,
                'series' => $history->pluck('temperature'),
                'margin' => 6.0,
            ],
            'humedad' => [
                'value' => $reading->humidity !== null ? (float) $reading->humidity : null,
                'series' => $history->pluck('humidity'),
                'margin' => 15.0,
            ],
            'co2' => [
                'value' => $reading->co2 !== null ? (float) $reading->co2 : null,
                'series' => $history->pluck('co2'),
                'margin' => 350.0,
            ],
            'indice UV' => [
                'value' => $reading->uv_index !== null ? (float) $reading->uv_index : null,
                'series' => $history->pluck('uv_index'),
                'margin' => 2.5,
            ],
        ];

        foreach ($checks as $label => $check) {
            if ($this->isOutlier($check['value'], $check['series'], $check['margin'])) {
                $results[] = $this->result('warning', "Posible outlier detectado en {$label}: el valor actual se desvia del patron reciente.");
            }
        }

        return $results;
    }

    /**
     * @param  Collection<int, WeatherStationReading>  $readings
     * @return array<int, array<string, string>>
     */
    public function analyzeHistoricalBehavior(Collection $readings): array
    {
        if ($readings->isEmpty()) {
            return [];
        }

        $results = [];
        $temperatureValues = $this->numericSeries($readings->pluck('temperature'));
        $humidityValues = $this->numericSeries($readings->pluck('humidity'));
        $co2Values = $this->numericSeries($readings->pluck('co2'));
        $pm25Values = $this->numericSeries($readings->pluck('pm25'));
        $pm10Values = $this->numericSeries($readings->pluck('pm10'));
        $uvIndexValues = $this->numericSeries($readings->pluck('uv_index'));
        $radiationValues = $readings
            ->map(fn (WeatherStationReading $reading) => $reading->solar_radiation !== null
                ? (float) $reading->solar_radiation
                : $reading->radiationValue())
            ->filter(fn (?float $value) => $value !== null)
            ->values();

        if ($temperatureValues->count() >= 3 && $temperatureValues->avg() >= 32) {
            $results[] = $this->result('info', 'Historico reciente con temperatura promedio elevada: el periodo analizado ha sido caluroso.');
        }

        if ($humidityValues->count() >= 3 && $humidityValues->avg() >= 70) {
            $results[] = $this->result('info', 'Historico reciente con humedad alta: el ambiente se ha mantenido humedo en el periodo analizado.');
        }

        if ($co2Values->count() >= 3 && $co2Values->avg() >= 1000) {
            $results[] = $this->result('warning', 'Historico de CO2 por encima del nivel recomendado: la ventilacion parece insuficiente de forma recurrente.');
        }

        if ($pm25Values->count() >= 3 && $pm25Values->avg() >= 25) {
            $results[] = $this->result('warning', 'Historico de PM2.5 con valores elevados: la exposicion a particulas finas ha sido persistente.');
        }

        if ($pm10Values->count() >= 3 && $pm10Values->avg() >= 40) {
            $results[] = $this->result('warning', 'Historico de PM10 con valores elevados: el material particulado grueso ha sido frecuente.');
        }

        if ($uvIndexValues->count() >= 3 && $uvIndexValues->avg() >= 5) {
            $results[] = $this->result('info', 'Historico UV alto: el periodo analizado muestra exposicion solar importante.');
        }

        if ($radiationValues->count() >= 3 && $radiationValues->avg() >= 550) {
            $results[] = $this->result('info', 'Historico de radiacion favorable: el potencial solar se ha mantenido alto en el periodo analizado.');
        }

        if ($radiationValues->count() >= 3 && $radiationValues->avg() <= 150) {
            $results[] = $this->result('info', 'Historico de baja radiacion: el periodo analizado presenta poco aporte solar util.');
        }

        return $results;
    }

    /**
     * @param  Collection<int, mixed>  $series
     */
    private function isOutlier(?float $value, Collection $series, float $minimumMargin): bool
    {
        if ($value === null) {
            return false;
        }

        $values = $series
            ->filter(fn ($item) => $item !== null)
            ->map(fn ($item) => (float) $item)
            ->values();

        if ($values->count() < 4) {
            return false;
        }

        $mean = $values->avg();
        $variance = $values->avg(fn (float $item) => ($item - $mean) ** 2);
        $standardDeviation = sqrt((float) $variance);
        $distance = abs($value - $mean);
        $adaptiveMargin = max($minimumMargin, $standardDeviation * 2.5);

        return $distance >= $adaptiveMargin;
    }

    /**
     * @param  array<int, array<string, string>>  $results
     * @return array<int, array<string, string>>
     */
    private function uniqueResults(array $results): array
    {
        return collect($results)
            ->unique(fn (array $item) => ($item['type'] ?? '').'|'.($item['message'] ?? ''))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @return Collection<int, float>
     */
    private function numericSeries(Collection $values): Collection
    {
        return $values
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => (float) $value)
            ->values();
    }

    /**
     * @return array<string, string>
     */
    private function result(string $type, string $message): array
    {
        return [
            'type' => $type,
            'message' => $message,
        ];
    }
}
