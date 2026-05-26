<?php

namespace Tests\Unit;

use App\Models\CalculationResult;
use App\Services\OpenAIRecommendationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Responses\CreateResponse;
use Tests\TestCase;

class OpenAIRecommendationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_it_returns_disabled_result_when_feature_is_off(): void
    {
        config([
            'services.openai_recommendations.enabled' => false,
            'openai.api_key' => null,
        ]);

        $result = app(OpenAIRecommendationService::class)->generate([], [], [], null, []);

        $this->assertFalse($result['enabled']);
        $this->assertSame('disabled', $result['source']);
        $this->assertNull($result['executive_summary']);
    }

    public function test_it_generates_natural_recommendations_from_structured_analysis(): void
    {
        config([
            'services.openai_recommendations.enabled' => true,
            'services.openai_recommendations.provider' => 'openai',
            'services.openai_recommendations.model' => 'gpt-5-mini',
            'services.openai_recommendations.max_output_tokens' => 300,
            'services.openai_recommendations.cache_ttl_minutes' => 30,
            'openai.api_key' => 'test-key',
        ]);

        $json = json_encode([
            'executive_summary' => 'Hoy se espera una produccion solar alta y una oportunidad clara de ahorro.',
            'daily_recommendation' => 'Hoy se recomienda desplazar cargas de alto consumo al mediodia para maximizar el ahorro energetico.',
            'energy_alerts' => [
                'La cobertura sigue siendo limitada fuera del horario solar.',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        OpenAI::fake([
            CreateResponse::fake([
                'output' => [
                    [
                        'type' => 'message',
                        'id' => 'msg_test',
                        'status' => 'completed',
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => $json,
                                'annotations' => [],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $result = app(OpenAIRecommendationService::class)->generate(
            [
                'current' => [
                    ['type' => 'warning', 'message' => 'Alta radiacion detectada: el potencial solar y la exposicion UV estan elevados.'],
                ],
                'historical' => [],
            ],
            [
                'insights' => [
                    ['level' => 'warning', 'title' => 'Dependencia de red', 'message' => 'La generacion anual es menor al consumo y el proyecto seguira dependiendo de la red.'],
                ],
                'monthlyInterpretations' => [],
            ],
            [
                'recommendations' => [
                    ['type' => 'recommendation', 'priority' => 'high', 'message' => 'Se recomienda operar equipos de alto consumo entre las 11 AM y 2 PM para aprovechar la mayor disponibilidad solar.'],
                ],
                'alerts' => [],
                'risks' => [],
                'opportunities' => [],
            ],
            $this->calculationResult(),
            [
                'averageRadiation' => 620,
                'maxUvIndex' => 6.2,
                'total' => 24,
            ],
        );

        $this->assertTrue($result['enabled']);
        $this->assertSame('openai', $result['source']);
        $this->assertSame('Hoy se espera una produccion solar alta y una oportunidad clara de ahorro.', $result['executive_summary']);
        $this->assertSame('Hoy se recomienda desplazar cargas de alto consumo al mediodia para maximizar el ahorro energetico.', $result['daily_recommendation']);
        $this->assertSame(['La cobertura sigue siendo limitada fuera del horario solar.'], $result['energy_alerts']);
    }

    public function test_it_reports_opencode_as_source_when_provider_is_opencode(): void
    {
        config([
            'services.openai_recommendations.enabled' => true,
            'services.openai_recommendations.provider' => 'opencode',
            'services.openai_recommendations.model' => 'gpt-5-mini',
            'openai.api_key' => 'test-key',
        ]);

        $json = json_encode([
            'executive_summary' => 'Resumen.',
            'daily_recommendation' => 'Recomendacion.',
            'energy_alerts' => ['Alerta.'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        OpenAI::fake([
            CreateResponse::fake([
                'output' => [
                    [
                        'type' => 'message',
                        'id' => 'msg_test',
                        'status' => 'completed',
                        'role' => 'assistant',
                        'content' => [
                            [
                                'type' => 'output_text',
                                'text' => $json,
                                'annotations' => [],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $result = app(OpenAIRecommendationService::class)->generate([], [], [], $this->calculationResult(), []);

        $this->assertSame('opencode', $result['source']);
    }

    public function test_it_rejects_anthropic_placeholder_payloads_and_uses_project_data_fallback(): void
    {
        config([
            'services.openai_recommendations.enabled' => true,
            'services.openai_recommendations.provider' => 'anthropic',
            'services.openai_recommendations.model' => 'claude-haiku-4-5',
            'services.openai_recommendations.cache_ttl_minutes' => 30,
            'openai.api_key' => 'test-key',
            'openai.base_uri' => 'https://api.anthropic.com/v1',
        ]);

        Http::fake([
            'api.anthropic.com/v1/messages' => Http::response([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            'executive_summary' => 'title',
                            'daily_recommendation' => 'date_context',
                            'energy_alerts' => [],
                            'recommendation_pack' => [
                                'risk' => 'message',
                            ],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                ],
            ]),
        ]);

        $result = app(OpenAIRecommendationService::class)->generate(
            [],
            [],
            [],
            $this->calculationResult(),
            [
                'total' => 0,
                'nasaDataQuality' => [
                    'estimatedRatio' => 0.25,
                ],
            ],
            'risk',
            [
                'solar_window' => [
                    'best_window' => '11:00 AM - 2:00 PM',
                ],
            ],
        );

        $this->assertTrue($result['enabled']);
        $this->assertSame('local_ai_fallback', $result['source']);
        $this->assertStringContainsString('cobertura 75,0%', $result['executive_summary']);
        $this->assertStringContainsString('Diagnostico:', $result['recommendation_pack']['risk']);
        $this->assertStringNotContainsString('date_context', $result['daily_recommendation']);
        $this->assertStringNotContainsString('title', $result['executive_summary']);
    }

    private function calculationResult(): CalculationResult
    {
        $result = new CalculationResult();
        $result->forceFill([
            'estimated_daily_generation_kwh' => 28,
            'estimated_monthly_generation_kwh' => 850,
            'estimated_annual_generation_kwh' => 9000,
            'annual_consumption_kwh' => 12000,
            'coverage_percentage' => 75,
            'estimated_annual_savings_cop' => 4200000,
        ]);

        return $result;
    }
}
