<?php

namespace Tests\Unit;

use App\Models\WeatherStationReading;
use App\Services\WeatherAnalysisService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class WeatherAnalysisServiceTest extends TestCase
{
    public function test_it_returns_structured_temperature_air_and_uv_findings(): void
    {
        $service = new WeatherAnalysisService();

        $results = $service->analyzeReadings(new Collection([
            $this->reading([
                'id' => 1,
                'temperature' => 31.0,
                'humidity' => 55.0,
                'co2' => 700,
                'uv_index' => 2.1,
                'measured_at' => '2026-05-23 09:00:00',
            ]),
            $this->reading([
                'id' => 2,
                'temperature' => 32.0,
                'humidity' => 58.0,
                'co2' => 720,
                'uv_index' => 2.4,
                'measured_at' => '2026-05-23 10:00:00',
            ]),
            $this->reading([
                'id' => 3,
                'temperature' => 33.0,
                'humidity' => 60.0,
                'co2' => 760,
                'uv_index' => 3.0,
                'measured_at' => '2026-05-23 11:00:00',
            ]),
            $this->reading([
                'id' => 4,
                'temperature' => 34.0,
                'humidity' => 61.0,
                'co2' => 790,
                'uv_index' => 3.2,
                'measured_at' => '2026-05-23 12:00:00',
            ]),
            $this->reading([
                'id' => 5,
                'temperature' => 35.1,
                'humidity' => 78.0,
                'thermal_sensation' => 44.0,
                'co2' => 1618,
                'pm25' => 40.0,
                'pm10' => 62.0,
                'uv_index' => 6.4,
                'solar_radiation' => 740.0,
                'measured_at' => '2026-05-23 13:00:00',
            ]),
        ]));

        $this->assertArrayHasKey('current', $results);
        $this->assertArrayHasKey('historical', $results);
        $this->assertContains([
            'type' => 'warning',
            'message' => 'Calor extremo detectado: la temperatura actual supera los 35 C.',
        ], $results['current']);
        $this->assertContains([
            'type' => 'warning',
            'message' => 'Alta humedad detectada: puede aumentar la sensacion de calor y afectar el confort.',
        ], $results['current']);
        $this->assertContains([
            'type' => 'warning',
            'message' => 'Contaminacion elevada por CO2: la ventilacion del entorno deberia revisarse.',
        ], $results['current']);
        $this->assertContains([
            'type' => 'warning',
            'message' => 'Alta radiacion detectada: el potencial solar y la exposicion UV estan elevados.',
        ], $results['current']);
        $this->assertContains([
            'type' => 'info',
            'message' => 'Historico reciente con temperatura promedio elevada: el periodo analizado ha sido caluroso.',
        ], $results['historical']);
    }

    public function test_it_detects_outliers_from_recent_history(): void
    {
        $service = new WeatherAnalysisService();

        $results = $service->analyzeReadings(new Collection([
            $this->reading(['id' => 1, 'temperature' => 31.0, 'humidity' => 56.0, 'co2' => 780, 'uv_index' => 2.1, 'measured_at' => '2026-05-23 08:00:00']),
            $this->reading(['id' => 2, 'temperature' => 31.4, 'humidity' => 57.0, 'co2' => 760, 'uv_index' => 2.2, 'measured_at' => '2026-05-23 09:00:00']),
            $this->reading(['id' => 3, 'temperature' => 31.1, 'humidity' => 56.5, 'co2' => 790, 'uv_index' => 2.0, 'measured_at' => '2026-05-23 10:00:00']),
            $this->reading(['id' => 4, 'temperature' => 31.3, 'humidity' => 57.3, 'co2' => 775, 'uv_index' => 2.3, 'measured_at' => '2026-05-23 11:00:00']),
            $this->reading(['id' => 5, 'temperature' => 45.0, 'humidity' => 85.0, 'co2' => 1800, 'uv_index' => 9.5, 'measured_at' => '2026-05-23 12:00:00']),
        ]));

        $this->assertContains([
            'type' => 'warning',
            'message' => 'Posible outlier detectado en temperatura: el valor actual se desvia del patron reciente.',
        ], $results['current']);
        $this->assertContains([
            'type' => 'warning',
            'message' => 'Posible outlier detectado en humedad: el valor actual se desvia del patron reciente.',
        ], $results['current']);
        $this->assertContains([
            'type' => 'warning',
            'message' => 'Posible outlier detectado en co2: el valor actual se desvia del patron reciente.',
        ], $results['current']);
    }

    public function test_it_builds_historical_conclusions_from_full_period(): void
    {
        $service = new WeatherAnalysisService();

        $results = $service->analyzeReadings(new Collection([
            $this->reading(['id' => 1, 'temperature' => 32.5, 'humidity' => 71.0, 'co2' => 1200, 'uv_index' => 5.2, 'solar_radiation' => 610.0, 'measured_at' => '2026-05-23 08:00:00']),
            $this->reading(['id' => 2, 'temperature' => 32.7, 'humidity' => 72.0, 'co2' => 1180, 'uv_index' => 5.4, 'solar_radiation' => 590.0, 'measured_at' => '2026-05-23 09:00:00']),
            $this->reading(['id' => 3, 'temperature' => 32.9, 'humidity' => 73.0, 'co2' => 1220, 'uv_index' => 5.1, 'solar_radiation' => 605.0, 'measured_at' => '2026-05-23 10:00:00']),
            $this->reading(['id' => 4, 'temperature' => 33.1, 'humidity' => 74.0, 'co2' => 1250, 'uv_index' => 5.5, 'solar_radiation' => 620.0, 'measured_at' => '2026-05-23 11:00:00']),
        ]));

        $this->assertContains([
            'type' => 'info',
            'message' => 'Historico reciente con temperatura promedio elevada: el periodo analizado ha sido caluroso.',
        ], $results['historical']);
        $this->assertContains([
            'type' => 'info',
            'message' => 'Historico reciente con humedad alta: el ambiente se ha mantenido humedo en el periodo analizado.',
        ], $results['historical']);
        $this->assertContains([
            'type' => 'warning',
            'message' => 'Historico de CO2 por encima del nivel recomendado: la ventilacion parece insuficiente de forma recurrente.',
        ], $results['historical']);
        $this->assertContains([
            'type' => 'info',
            'message' => 'Historico UV alto: el periodo analizado muestra exposicion solar importante.',
        ], $results['historical']);
        $this->assertContains([
            'type' => 'info',
            'message' => 'Historico de radiacion favorable: el potencial solar se ha mantenido alto en el periodo analizado.',
        ], $results['historical']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function reading(array $attributes): WeatherStationReading
    {
        $reading = new WeatherStationReading();
        $reading->forceFill($attributes);

        return $reading;
    }
}
