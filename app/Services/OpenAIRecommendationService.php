<?php

namespace App\Services;

use App\Models\CalculationResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;
use Throwable;

class OpenAIRecommendationService
{
    /**
     * @param  array<string, mixed>  $weatherAnalysis
     * @param  array<string, mixed>  $energyAnalysis
     * @param  array<string, mixed>  $solarRecommendations
     * @param  array<string, mixed>  $weatherStationStats
     * @return array{
     *     enabled: bool,
     *     source: string,
     *     executive_summary: string|null,
     *     daily_recommendation: string|null,
     *     energy_alerts: array<int, string>,
     *     error: string|null
     * }
     */
    public function generate(
        array $weatherAnalysis,
        array $energyAnalysis,
        array $solarRecommendations,
        ?CalculationResult $calculationResult,
        array $weatherStationStats = [],
    ): array {
        if (! $this->isEnabled()) {
            return $this->emptyResult('disabled');
        }

        if (blank(config('openai.api_key'))) {
            return $this->emptyResult('missing_api_key', 'Configura OPENAI_API_KEY u OPENCODE_API_KEY para habilitar recomendaciones con IA.');
        }

        $payload = $this->structuredPayload(
            $weatherAnalysis,
            $energyAnalysis,
            $solarRecommendations,
            $calculationResult,
            $weatherStationStats,
        );

        $cacheKey = 'openai_recommendations:'.md5(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        /** @var mixed $cached */
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['enabled'] ?? false) === true) {
            return $cached;
        }

        try {
            $decoded = $this->requestStructuredRecommendation($payload);

            if (! is_array($decoded)) {
                return $this->emptyResult('error', 'La IA no devolvio contenido utilizable.');
            }

            $result = [
                'enabled' => true,
                'source' => $this->providerSource(),
                'executive_summary' => $this->stringOrNull($decoded['executive_summary'] ?? null),
                'daily_recommendation' => $this->stringOrNull($decoded['daily_recommendation'] ?? null),
                'energy_alerts' => collect($decoded['energy_alerts'] ?? [])
                    ->filter(fn ($item) => is_string($item) && trim($item) !== '')
                    ->values()
                    ->all(),
                'error' => null,
            ];

            $ttlMinutes = max(1, (int) config('services.openai_recommendations.cache_ttl_minutes', 30));
            Cache::put($cacheKey, $result, now()->addMinutes($ttlMinutes));

            return $result;
        } catch (Throwable $exception) {
            report($exception);

            return $this->emptyResult('error', $this->humanReadableAiError($exception->getMessage()));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function requestStructuredRecommendation(array $payload): ?array
    {
        $model = (string) config('services.openai_recommendations.model', 'gpt-5-mini');
        $useResponsesApi = $this->supportsResponsesApi($model);
        $lastErrorMessage = null;

        if ($useResponsesApi) {
            try {
                $response = OpenAI::responses()->create([
                    'model' => $model,
                    'input' => $this->buildInput($payload),
                    'max_output_tokens' => max(150, (int) config('services.openai_recommendations.max_output_tokens', 400)),
                    'temperature' => 0.2,
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => 'solar_ai_recommendations',
                            'description' => 'Executive summary, daily recommendation, and energy alerts for a solar dashboard.',
                            'strict' => true,
                            'schema' => $this->responseSchema(),
                        ],
                    ],
                ]);

                $decoded = $this->decodeResponsePayload($this->extractResponseText($response));

                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (Throwable $responsesException) {
                $lastErrorMessage = $responsesException->getMessage();
                Log::warning('AI recommendations: responses endpoint failed', [
                    'provider' => $this->providerSource(),
                    'model' => (string) config('services.openai_recommendations.model', 'gpt-5-mini'),
                    'message' => $responsesException->getMessage(),
                ]);
            }
        }

        try {
            $chatResponse = OpenAI::chat()->create([
                'model' => $model,
                'messages' => $this->buildChatMessages($payload),
                'temperature' => 0.2,
                'max_tokens' => max(150, (int) config('services.openai_recommendations.max_output_tokens', 400)),
                'response_format' => [
                    'type' => 'json_object',
                ],
            ]);

            return $this->decodeResponsePayload($this->extractResponseText($chatResponse));
        } catch (Throwable $chatException) {
            $lastErrorMessage = $chatException->getMessage();
            Log::warning('AI recommendations: chat json_object failed', [
                'provider' => $this->providerSource(),
                'model' => $model,
                'message' => $chatException->getMessage(),
            ]);
        }

        try {
            $plainChatResponse = OpenAI::chat()->create([
                'model' => $model,
                'messages' => $this->buildChatMessages($payload),
                'temperature' => 0.2,
                'max_tokens' => max(150, (int) config('services.openai_recommendations.max_output_tokens', 400)),
            ]);

            return $this->decodeResponsePayload($this->extractResponseText($plainChatResponse));
        } catch (Throwable $plainChatException) {
            $lastErrorMessage = $plainChatException->getMessage();
            Log::error('AI recommendations: all provider calls failed', [
                'provider' => $this->providerSource(),
                'model' => (string) config('services.openai_recommendations.model', 'gpt-5-mini'),
                'message' => $plainChatException->getMessage(),
            ]);
        }

        throw new RuntimeException($lastErrorMessage ?: 'No fue posible generar recomendaciones con IA.');
    }

    private function supportsResponsesApi(string $model): bool
    {
        $normalized = strtolower(trim($model));

        return str_starts_with($normalized, 'gpt-');
    }

    private function providerSource(): string
    {
        $provider = strtolower((string) config('services.openai_recommendations.provider', 'openai'));

        return $provider !== '' ? $provider : 'openai';
    }

    private function isEnabled(): bool
    {
        return (bool) config('services.openai_recommendations.enabled', false);
    }

    /**
     * @param  array<string, mixed>  $weatherAnalysis
     * @param  array<string, mixed>  $energyAnalysis
     * @param  array<string, mixed>  $solarRecommendations
     * @param  array<string, mixed>  $weatherStationStats
     * @return array<string, mixed>
     */
    private function structuredPayload(
        array $weatherAnalysis,
        array $energyAnalysis,
        array $solarRecommendations,
        ?CalculationResult $calculationResult,
        array $weatherStationStats,
    ): array {
        return [
            'energy_metrics' => [
                'estimated_daily_generation_kwh' => $calculationResult ? (float) $calculationResult->estimated_daily_generation_kwh : null,
                'estimated_monthly_generation_kwh' => $calculationResult ? (float) $calculationResult->estimated_monthly_generation_kwh : null,
                'estimated_annual_generation_kwh' => $calculationResult ? (float) $calculationResult->estimated_annual_generation_kwh : null,
                'annual_consumption_kwh' => $calculationResult ? (float) $calculationResult->annual_consumption_kwh : null,
                'coverage_percentage' => $calculationResult ? (float) $calculationResult->coverage_percentage : null,
                'estimated_annual_savings_cop' => $calculationResult ? (float) $calculationResult->estimated_annual_savings_cop : null,
            ],
            'weather_analysis' => [
                'current' => collect($weatherAnalysis['current'] ?? [])->pluck('message')->values()->all(),
                'historical' => collect($weatherAnalysis['historical'] ?? [])->pluck('message')->values()->all(),
            ],
            'energy_analysis' => [
                'insights' => collect($energyAnalysis['insights'] ?? [])->map(fn (array $item) => [
                    'title' => $item['title'] ?? null,
                    'message' => $item['message'] ?? null,
                ])->values()->all(),
                'monthly' => collect($energyAnalysis['monthlyInterpretations'] ?? [])->map(fn (array $item) => [
                    'title' => $item['title'] ?? null,
                    'message' => $item['message'] ?? null,
                ])->values()->all(),
            ],
            'rule_based_recommendations' => [
                'recommendations' => collect($solarRecommendations['recommendations'] ?? [])->pluck('message')->values()->all(),
                'alerts' => collect($solarRecommendations['alerts'] ?? [])->pluck('message')->values()->all(),
                'risks' => collect($solarRecommendations['risks'] ?? [])->pluck('message')->values()->all(),
                'opportunities' => collect($solarRecommendations['opportunities'] ?? [])->pluck('message')->values()->all(),
            ],
            'weather_station_summary' => [
                'average_radiation' => isset($weatherStationStats['averageRadiation']) ? (float) $weatherStationStats['averageRadiation'] : null,
                'max_uv_index' => isset($weatherStationStats['maxUvIndex']) ? (float) $weatherStationStats['maxUvIndex'] : null,
                'reading_count' => isset($weatherStationStats['total']) ? (int) $weatherStationStats['total'] : 0,
            ],
            'nasa_radiation_quality' => [
                'total_rows' => isset($weatherStationStats['nasaDataQuality']['totalRows']) ? (int) $weatherStationStats['nasaDataQuality']['totalRows'] : 0,
                'estimated_rows' => isset($weatherStationStats['nasaDataQuality']['estimatedRows']) ? (int) $weatherStationStats['nasaDataQuality']['estimatedRows'] : 0,
                'estimated_ratio' => isset($weatherStationStats['nasaDataQuality']['estimatedRatio']) ? (float) $weatherStationStats['nasaDataQuality']['estimatedRatio'] : 0.0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function buildInput(array $payload): array
    {
        return [
            [
                'role' => 'system',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => implode("\n", [
                            'Eres un analista energetico para un dashboard solar en Riohacha.',
                            'Tu tarea es transformar analisis estructurados en texto claro, prudente y accionable.',
                            'No inventes datos, no agregues cifras que no aparezcan en la entrada.',
                            'Si la informacion es insuficiente, dilo de forma explicita.',
                            'Si la calidad de radiacion NASA indica estimaciones, evita recomendaciones agresivas y aclara la incertidumbre.',
                            'Enfocate en autoconsumo, horarios de carga, cobertura solar y riesgos operativos.',
                            'Devuelve unicamente JSON valido que siga exactamente el esquema solicitado.',
                        ]),
                    ],
                ],
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'input_text',
                        'text' => "Analiza este resumen estructurado y genera una respuesta ejecutiva y prudente:\n".json_encode(
                            $payload,
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        ),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function buildChatMessages(array $payload): array
    {
        return [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'Eres un analista energetico para un dashboard solar en Riohacha.',
                    'Tu tarea es transformar analisis estructurados en texto claro, prudente y accionable.',
                    'No inventes datos, no agregues cifras que no aparezcan en la entrada.',
                    'Si la informacion es insuficiente, dilo de forma explicita.',
                    'Enfocate en autoconsumo, horarios de carga, cobertura solar y riesgos operativos.',
                    'Responde unicamente JSON valido con las claves: executive_summary, daily_recommendation, energy_alerts.',
                ]),
            ],
            [
                'role' => 'user',
                'content' => "Analiza este resumen estructurado y genera una respuesta ejecutiva y prudente:\n".json_encode(
                    $payload,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'executive_summary' => [
                    'type' => 'string',
                    'description' => 'Short executive summary in Spanish.',
                ],
                'daily_recommendation' => [
                    'type' => 'string',
                    'description' => 'Daily recommendation in natural Spanish.',
                ],
                'energy_alerts' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                    'description' => 'Short energy alerts in Spanish.',
                ],
            ],
            'required' => [
                'executive_summary',
                'daily_recommendation',
                'energy_alerts',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeResponsePayload(mixed $output): ?array
    {
        if (! is_string($output) || trim($output) === '') {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($output, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        if (! preg_match('/\{.*\}/s', $output, $matches)) {
            return null;
        }

        /** @var mixed $fallbackDecoded */
        $fallbackDecoded = json_decode($matches[0], true);

        return is_array($fallbackDecoded) ? $fallbackDecoded : null;
    }

    private function extractResponseText(mixed $response): ?string
    {
        if (is_object($response)) {
            if (isset($response->outputText) && is_string($response->outputText) && trim($response->outputText) !== '') {
                return $response->outputText;
            }

            if (isset($response->choices[0]->message->content) && is_string($response->choices[0]->message->content)) {
                return $response->choices[0]->message->content;
            }

            if (isset($response->output) && is_iterable($response->output)) {
                foreach ($response->output as $item) {
                    if (! isset($item->content) || ! is_iterable($item->content)) {
                        continue;
                    }

                    foreach ($item->content as $content) {
                        if (isset($content->text) && is_string($content->text) && trim($content->text) !== '') {
                            return $content->text;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return array{
     *     enabled: bool,
     *     source: string,
     *     executive_summary: string|null,
     *     daily_recommendation: string|null,
     *     energy_alerts: array<int, string>,
     *     error: string|null
     * }
     */
    private function emptyResult(string $source, ?string $error = null): array
    {
        return [
            'enabled' => false,
            'source' => $source,
            'executive_summary' => null,
            'daily_recommendation' => null,
            'energy_alerts' => [],
            'error' => $error,
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function humanReadableAiError(string $message): string
    {
        $normalized = strtolower(trim($message));

        if ($normalized === '') {
            return 'No fue posible generar recomendaciones con IA en este momento.';
        }

        if (str_contains($normalized, 'no payment method')) {
            return 'IA OpenCode no disponible: la cuenta no tiene metodo de pago habilitado.';
        }

        if (str_contains($normalized, 'rate limit')) {
            return 'IA OpenCode temporalmente limitada por cuota (rate limit). Intenta nuevamente en unos minutos.';
        }

        if (str_contains($normalized, 'not supported') && str_contains($normalized, 'model')) {
            return 'IA OpenCode: el modelo configurado no es compatible con este endpoint.';
        }

        if (str_contains($normalized, 'syntax error')) {
            return 'IA OpenCode devolvio un error de formato en la solicitud.';
        }

        return "IA OpenCode error: {$message}";
    }
}
