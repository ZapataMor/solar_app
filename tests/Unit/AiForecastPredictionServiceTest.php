<?php

namespace Tests\Unit;

use App\Models\SolarProject;
use App\Services\AiForecastPredictionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiForecastPredictionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_it_generates_next_week_prediction_with_claude_payload(): void
    {
        config([
            'services.openai_recommendations.enabled' => true,
            'services.openai_recommendations.provider' => 'anthropic',
            'services.openai_recommendations.model' => 'claude-haiku-4-5',
            'openai.api_key' => 'test-key',
            'openai.base_uri' => 'https://api.anthropic.com/v1',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            'title' => 'Prediccion IA proxima semana',
                            'prediction' => 'La semana proxima se espera operacion estable usando la tendencia reciente.',
                            'temperature_outlook' => 'La temperatura proyectada se mantiene cerca de 31 C con variacion moderada.',
                            'solar_window' => 'La mejor ventana solar proyectada es 10:00-12:59 con radiacion favorable.',
                            'actions' => [
                                'Mover cargas flexibles a la ventana solar.',
                                'Monitorear temperatura antes de activar cargas criticas.',
                            ],
                            'confidence' => 'media',
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                ],
            ]),
        ]);

        $project = new SolarProject(['name' => 'Proyecto prueba']);
        $project->id = 3;

        $result = app(AiForecastPredictionService::class)->generate($project, [
            'temperature' => [
                'projected_next_week_c' => 31.2,
                'delta_c' => 0.4,
            ],
            'radiation_window' => [
                'start_hour' => 10,
                'end_hour' => 12,
                'avg_radiation' => 780,
            ],
            'data_window' => [
                'sample_count' => 28,
                'days' => 7,
                'source' => 'Centro meteorologico',
            ],
            'ai_context' => [
                'daily_series' => [],
                'hourly_radiation_profile' => [],
            ],
        ]);

        $this->assertSame('anthropic', $result['source']);
        $this->assertSame('media', $result['confidence']);
        $this->assertStringContainsString('estable', $result['prediction']);
        $this->assertCount(2, $result['actions']);
    }

    public function test_it_uses_partial_claude_prediction_when_json_is_truncated(): void
    {
        config([
            'services.openai_recommendations.enabled' => true,
            'services.openai_recommendations.provider' => 'anthropic',
            'services.openai_recommendations.model' => 'claude-haiku-4-5',
            'openai.api_key' => 'test-key',
            'openai.base_uri' => 'https://api.anthropic.com/v1',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => <<<'JSON'
```json
{
  "title": "Prediccion IA",
  "prediction": "Claude alcanzo a explicar la tendencia semanal con evidencia del historico reciente pero la respuesta se corto antes de cerrar JSON.",
  "temperature_outlook": "Temperatura alta proyectada.",
  "solar_window": "Ventana sugerida 13:00-15:59.",
  "actions": ["Mover cargas flexibles"
JSON,
                    ],
                ],
            ]),
        ]);

        $project = new SolarProject(['name' => 'Proyecto prueba']);
        $project->id = 3;

        $result = app(AiForecastPredictionService::class)->generate($project, [
            'temperature' => [
                'projected_next_week_c' => 39.31,
                'delta_c' => 3.46,
            ],
            'radiation_window' => [
                'start_hour' => 13,
                'end_hour' => 15,
                'avg_radiation' => 475,
            ],
            'data_window' => [
                'sample_count' => 345,
                'days' => 7,
                'source' => 'Centro meteorologico',
            ],
        ]);

        $this->assertSame('anthropic', $result['source']);
        $this->assertNull($result['error']);
        $this->assertStringContainsString('Claude alcanzo', $result['prediction']);
        $this->assertStringContainsString('13:00-15:59', $result['solar_window']);
    }
}
