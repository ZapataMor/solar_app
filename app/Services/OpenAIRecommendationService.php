<?php

namespace App\Services;

use App\Models\CalculationResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
        ?string $userIntent = null,
        array $projectContext = [],
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
            $userIntent,
            $projectContext,
        );

        $cacheKey = 'openai_recommendations:'.md5(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        /** @var mixed $cached */
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['enabled'] ?? false) === true) {
            return $cached;
        }

        try {
            $decoded = $this->requestStructuredRecommendation($payload);

            $decoded = $this->sanitizeDecodedRecommendation($decoded);

            if (! is_array($decoded)) {
                Log::warning('AI recommendations: decoded payload is null/non-array', [
                    'provider' => $this->providerSource(),
                    'model' => (string) config('services.openai_recommendations.model', ''),
                ]);

                return $this->fallbackResult($payload, 'La IA no devolvio contenido utilizable; se genero una recomendacion local con los datos del proyecto.');
            }

            $result = [
                'enabled' => true,
                'source' => $this->providerSource(),
                'executive_summary' => $this->stringOrNull($decoded['executive_summary'] ?? null),
                'daily_recommendation' => $this->stringOrNull($decoded['daily_recommendation'] ?? null),
                'energy_alerts' => $this->normalizeAlerts($decoded['energy_alerts'] ?? []),
                'recommendation_pack' => is_array($decoded['recommendation_pack'] ?? null)
                    ? $decoded['recommendation_pack']
                    : [],
                'error' => null,
            ];

            $result = $this->enforceRecommendationSpecificity($result, $payload);

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
        if ($this->isAnthropicProvider()) {
            return $this->requestAnthropicRecommendation($payload);
        }

        $model = (string) config('services.openai_recommendations.model', 'gpt-5-mini');
        $useResponsesApi = $this->supportsResponsesApi($model);
        $lastErrorMessage = null;

        if ($useResponsesApi) {
            try {
                $response = OpenAI::responses()->create([
                    'model' => $model,
                    'input' => $this->buildInput($payload),
                    'max_output_tokens' => max(600, (int) config('services.openai_recommendations.max_output_tokens', 900)),
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
                'max_tokens' => max(600, (int) config('services.openai_recommendations.max_output_tokens', 900)),
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
                'max_tokens' => max(600, (int) config('services.openai_recommendations.max_output_tokens', 900)),
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

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function requestAnthropicRecommendation(array $payload): ?array
    {
        $baseUrl = rtrim((string) config('openai.base_uri', 'https://api.anthropic.com/v1'), '/');
        $apiKey = (string) config('openai.api_key', '');
        $model = (string) config('services.openai_recommendations.model', 'claude-haiku-4-5');

        if ($apiKey === '') {
            throw new RuntimeException('Missing Anthropic API key.');
        }

        $response = Http::timeout((int) config('openai.request_timeout', 30))
            ->retry(1, 300)
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post("{$baseUrl}/messages", [
                'model' => $model,
                'max_tokens' => max(1500, (int) config('services.openai_recommendations.max_output_tokens', 2048)),
                'temperature' => 0.2,
                'system' => $this->anthropicSystemPrompt(false),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Analiza este resumen estructurado y genera una respuesta ejecutiva y prudente:\n".json_encode(
                            $payload,
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        ),
                    ],
                ],
            ]);

        try {
            $response->throw();
        } catch (Throwable $exception) {
            $message = (string) data_get($response->json(), 'error.message', mb_substr($response->body(), 0, 500));
            throw new RuntimeException($message, 0, $exception);
        }

        $text = $this->extractAnthropicText($response->json());
        if ($text === '') {
            Log::warning('AI recommendations anthropic: empty text payload', [
                'model' => $model,
                'response_excerpt' => mb_substr($response->body(), 0, 800),
            ]);
        }

        $decoded = $this->decodeResponsePayload($text);
        if (! is_array($decoded)) {
            Log::warning('AI recommendations anthropic: unable to decode JSON payload', [
                'model' => $model,
                'text_excerpt' => mb_substr($text, 0, 800),
            ]);

            $retryDecoded = $this->retryAnthropicCompactJson($baseUrl, $apiKey, $model, $payload);
            if (is_array($retryDecoded)) {
                return $retryDecoded;
            }

            $minimalRetryDecoded = $this->retryAnthropicMinimalJson($baseUrl, $apiKey, $model, $payload);
            if (is_array($minimalRetryDecoded)) {
                return $minimalRetryDecoded;
            }
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function retryAnthropicCompactJson(string $baseUrl, string $apiKey, string $model, array $payload): ?array
    {
        $response = Http::timeout((int) config('openai.request_timeout', 30))
            ->retry(0, 0)
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post("{$baseUrl}/messages", [
                'model' => $model,
                'max_tokens' => max(1200, (int) config('services.openai_recommendations.max_output_tokens', 2048)),
                'temperature' => 0.1,
                'system' => $this->anthropicSystemPrompt(true),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Devuelve JSON compacto para este resumen:\n".json_encode(
                            $payload,
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        ),
                    ],
                ],
            ]);

        if (! $response->ok()) {
            return null;
        }

        $text = $this->extractAnthropicText($response->json());
        $decoded = $this->decodeResponsePayload($text);

        if (! is_array($decoded)) {
            Log::warning('AI recommendations anthropic: compact retry decode failed', [
                'model' => $model,
                'text_excerpt' => mb_substr($text, 0, 800),
            ]);
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function retryAnthropicMinimalJson(string $baseUrl, string $apiKey, string $model, array $payload): ?array
    {
        $response = Http::timeout((int) config('openai.request_timeout', 30))
            ->retry(0, 0)
            ->withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->post("{$baseUrl}/messages", [
                'model' => $model,
                'max_tokens' => 1800,
                'temperature' => 0.1,
                'system' => implode("\n", [
                    'Devuelve SOLO JSON plano, sin markdown.',
                    'Claves obligatorias: executive_summary, daily_recommendation, energy_alerts, recommendation_pack.',
                    'executive_summary max 420 caracteres.',
                    'daily_recommendation max 520 caracteres.',
                    'energy_alerts max 4 items, cada item max 180 caracteres.',
                    'recommendation_pack con keys savings, load_shift, risk, maintenance, climate.',
                    'Cada item de recommendation_pack max 520 caracteres con diagnostico + accion + impacto esperado.',
                ]),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "JSON minimo y corto para este resumen:\n".json_encode(
                            $payload,
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                        ),
                    ],
                ],
            ]);

        if (! $response->ok()) {
            return null;
        }

        $text = $this->extractAnthropicText($response->json());
        $decoded = $this->decodeResponsePayload($text);

        if (! is_array($decoded)) {
            Log::warning('AI recommendations anthropic: minimal retry decode failed', [
                'model' => $model,
                'text_excerpt' => mb_substr($text, 0, 800),
            ]);
        }

        return $decoded;
    }

    private function anthropicSystemPrompt(bool $compact): string
    {
        $base = [
            'Eres un analista energetico para un dashboard solar en Riohacha.',
            'Tu tarea es transformar analisis estructurados en texto claro, prudente y accionable.',
            'No inventes datos, no agregues cifras que no aparezcan en la entrada.',
            'Si la informacion es insuficiente, dilo de forma explicita.',
            'Cada recomendacion debe incluir "Diagnostico:", "Accion:" e "Impacto esperado:" con datos disponibles del proyecto.',
            'No des recomendaciones genericas; conecta cada accion con cobertura, consumo, balance, radiacion, calidad de datos o ventana solar.',
            'Responde unicamente JSON valido con las claves: executive_summary, daily_recommendation, energy_alerts, recommendation_pack.',
            'recommendation_pack debe incluir exactamente las claves: savings, load_shift, risk, maintenance, climate.',
        ];

        if ($compact) {
            $base[] = 'Mantener formato compacto pero util: executive_summary max 420 caracteres, daily_recommendation max 520 caracteres, energy_alerts max 4 items y cada item max 180 caracteres, y cada item de recommendation_pack max 520 caracteres.';
            $base[] = 'No uses markdown, no uses bloques ```json, solo JSON plano.';
        }

        return implode("\n", $base);
    }

    private function supportsResponsesApi(string $model): bool
    {
        $normalized = strtolower(trim($model));

        return str_starts_with($normalized, 'gpt-');
    }

    private function isAnthropicProvider(): bool
    {
        $provider = strtolower((string) config('services.openai_recommendations.provider', 'openai'));
        $model = strtolower((string) config('services.openai_recommendations.model', ''));

        return $provider === 'anthropic' || str_starts_with($model, 'claude-');
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
        ?string $userIntent = null,
        array $projectContext = [],
    ): array {
        $intentMap = [
            'savings' => 'Maximizar ahorro economico',
            'load_shift' => 'Desplazar cargas a horas solares',
            'risk' => 'Reducir riesgo operativo',
            'maintenance' => 'Planificar mantenimiento preventivo',
            'climate' => 'Adaptarse a variaciones climaticas',
        ];

        $annualGeneration = $calculationResult ? (float) $calculationResult->estimated_annual_generation_kwh : null;
        $annualConsumption = $calculationResult ? (float) $calculationResult->annual_consumption_kwh : null;
        $coverage = $calculationResult ? (float) $calculationResult->coverage_percentage : null;
        $annualBalance = ($annualGeneration !== null && $annualConsumption !== null)
            ? $annualGeneration - $annualConsumption
            : null;
        $coverageGap = $coverage !== null ? 100 - $coverage : null;

        return [
            'user_intent' => [
                'key' => $userIntent,
                'label' => $intentMap[$userIntent ?? ''] ?? 'General',
            ],
            'energy_metrics' => [
                'estimated_daily_generation_kwh' => $calculationResult ? (float) $calculationResult->estimated_daily_generation_kwh : null,
                'estimated_monthly_generation_kwh' => $calculationResult ? (float) $calculationResult->estimated_monthly_generation_kwh : null,
                'estimated_annual_generation_kwh' => $annualGeneration,
                'annual_consumption_kwh' => $annualConsumption,
                'coverage_percentage' => $coverage,
                'estimated_annual_savings_cop' => $calculationResult ? (float) $calculationResult->estimated_annual_savings_cop : null,
                'annual_energy_balance_kwh' => $annualBalance,
                'coverage_gap_to_100_percent' => $coverageGap,
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
            'analysis_depth' => [
                'required' => 'high',
                'format' => 'Cada recomendacion debe incluir diagnostico, accion concreta y resultado esperado.',
                'focus_priority' => $userIntent && isset($intentMap[$userIntent]) ? $intentMap[$userIntent] : 'General',
            ],
            'content_contract' => [
                'avoid' => [
                    'Consejos genericos no conectados a metricas del proyecto.',
                    'Rangos horarios amplios si no hay ventana solar calculada.',
                    'Cifras nuevas no incluidas en el resumen estructurado.',
                ],
                'required_sections_per_recommendation' => [
                    'Diagnostico basado en cobertura, balance, consumo, radiacion, calidad de datos o ventana horaria.',
                    'Accion concreta ejecutable por el usuario final.',
                    'Impacto esperado expresado de forma prudente y no inventada.',
                    'Incertidumbre declarada cuando falten datos o la calidad sea limitada.',
                ],
            ],
            'project_context' => $projectContext,
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
                            'El enfoque seleccionado en user_intent es prioritario: profundiza en esa categoria sin ignorar riesgos transversales.',
                            'Cada texto debe ser detallado y accionable, incluyendo: diagnostico, accion concreta y efecto esperado.',
                            'Estructura cada recomendacion con frases claramente identificables: "Diagnostico:", "Accion:" e "Impacto esperado:".',
                            'Evita recomendaciones obvias o genericas como "8:00 a 16:00" salvo que project_context.solar_window lo sustente.',
                            'Si project_context.solar_window.best_window existe, usa ese rango como base operativa principal.',
                            'Declara incertidumbre con lenguaje directo cuando falten lecturas, cobertura, consumo o radiacion confiable.',
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
                    'Prioriza el enfoque user_intent y entrega recomendaciones detalladas con diagnostico, accion concreta y efecto esperado.',
                    'Estructura cada recomendacion con frases claramente identificables: "Diagnostico:", "Accion:" e "Impacto esperado:".',
                    'Evita recomendaciones obvias o genericas no sustentadas por project_context.solar_window.',
                    'Declara incertidumbre cuando falten datos o la calidad de radiacion sea limitada.',
                    'Responde unicamente JSON valido con las claves: executive_summary, daily_recommendation, energy_alerts, recommendation_pack.',
                    'recommendation_pack debe incluir exactamente: savings, load_shift, risk, maintenance, climate.',
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
                    'description' => 'Executive summary in Spanish with evidence from project metrics.',
                    'minLength' => 120,
                    'maxLength' => 420,
                ],
                'daily_recommendation' => [
                    'type' => 'string',
                    'description' => 'Detailed daily recommendation in Spanish with explicit Diagnostico, Accion, Impacto esperado, and uncertainty when data is missing.',
                    'minLength' => 180,
                    'maxLength' => 520,
                ],
                'energy_alerts' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'minLength' => 40,
                        'maxLength' => 180,
                    ],
                    'minItems' => 2,
                    'maxItems' => 4,
                    'description' => 'Energy alerts in Spanish, specific and actionable.',
                ],
                'recommendation_pack' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'savings' => ['type' => 'string', 'description' => 'Spanish recommendation with Diagnostico, Accion, Impacto esperado.', 'minLength' => 160, 'maxLength' => 520],
                        'load_shift' => ['type' => 'string', 'description' => 'Spanish recommendation with Diagnostico, Accion, Impacto esperado.', 'minLength' => 160, 'maxLength' => 520],
                        'risk' => ['type' => 'string', 'description' => 'Spanish recommendation with Diagnostico, Accion, Impacto esperado.', 'minLength' => 160, 'maxLength' => 520],
                        'maintenance' => ['type' => 'string', 'description' => 'Spanish recommendation with Diagnostico, Accion, Impacto esperado.', 'minLength' => 160, 'maxLength' => 520],
                        'climate' => ['type' => 'string', 'description' => 'Spanish recommendation with Diagnostico, Accion, Impacto esperado.', 'minLength' => 160, 'maxLength' => 520],
                    ],
                    'required' => ['savings', 'load_shift', 'risk', 'maintenance', 'climate'],
                ],
            ],
            'required' => [
                'executive_summary',
                'daily_recommendation',
                'energy_alerts',
                'recommendation_pack',
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

        $cleanOutput = trim($output);
        $cleanOutput = preg_replace('/^```(?:json)?\s*/i', '', $cleanOutput) ?? $cleanOutput;
        $cleanOutput = preg_replace('/\s*```$/', '', $cleanOutput) ?? $cleanOutput;

        /** @var mixed $decoded */
        $decoded = json_decode($cleanOutput, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $partialDecoded = $this->parsePartialRecommendationPayload($cleanOutput);
        if (is_array($partialDecoded)) {
            return $partialDecoded;
        }

        if (! preg_match('/\{.*\}/s', $cleanOutput, $matches)) {
            return null;
        }

        /** @var mixed $fallbackDecoded */
        $fallbackDecoded = json_decode($matches[0], true);

        if (is_array($fallbackDecoded)) {
            return $fallbackDecoded;
        }
        
        return null;
    }

    /**
     * Best-effort parser for truncated model outputs.
     *
     * @return array<string, mixed>|null
     */
    private function parsePartialRecommendationPayload(string $text): ?array
    {
        $normalizedText = trim($text);
        if (str_contains($normalizedText, '\\"')) {
            $normalizedText = str_replace(['\\"', '\\n', '\\t', '\\r'], ['"', "\n", "\t", "\r"], $normalizedText);
        }

        $extractQuotedValue = function (string $source, string $key): ?string {
            $needle = "\"{$key}\"";
            $keyPos = strpos($source, $needle);
            if ($keyPos === false) {
                return null;
            }

            $colonPos = strpos($source, ':', $keyPos + strlen($needle));
            if ($colonPos === false) {
                return null;
            }

            $firstQuotePos = strpos($source, '"', $colonPos + 1);
            if ($firstQuotePos === false) {
                return null;
            }

            $buffer = '';
            $escaped = false;
            $len = strlen($source);

            for ($i = $firstQuotePos + 1; $i < $len; $i++) {
                $char = $source[$i];

                if ($escaped) {
                    $buffer .= $char;
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($char === '"') {
                    $value = trim(stripcslashes($buffer));
                    return $value !== '' ? $value : null;
                }

                $buffer .= $char;
            }

            return null;
        };

        $extractAlerts = function (string $source): array {
            $needle = '"energy_alerts"';
            $pos = strpos($source, $needle);
            if ($pos === false) {
                return [];
            }

            $open = strpos($source, '[', $pos);
            if ($open === false) {
                return [];
            }

            $close = strpos($source, ']', $open);
            if ($close === false) {
                return [];
            }

            $slice = substr($source, $open + 1, $close - $open - 1);
            preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/s', $slice, $matches);

            return collect($matches[1] ?? [])
                ->map(fn ($raw) => trim(stripcslashes((string) $raw)))
                ->filter(fn ($value) => $value !== '')
                ->values()
                ->all();
        };

        $executiveSummary = $extractQuotedValue($normalizedText, 'executive_summary');
        $dailyRecommendation = $extractQuotedValue($normalizedText, 'daily_recommendation');
        $alerts = $extractAlerts($normalizedText);

        $pack = [];
        foreach (['savings', 'load_shift', 'risk', 'maintenance', 'climate'] as $packKey) {
            $value = $extractQuotedValue($normalizedText, $packKey);
            if ($value !== null) {
                $pack[$packKey] = $value;
            }
        }

        if ($executiveSummary === null && $dailyRecommendation === null) {
            return null;
        }

        if ($pack === []) {
            $pack = [
                'savings' => $dailyRecommendation ?? $executiveSummary,
                'load_shift' => $dailyRecommendation ?? $executiveSummary,
                'risk' => $dailyRecommendation ?? $executiveSummary,
                'maintenance' => $dailyRecommendation ?? $executiveSummary,
                'climate' => $dailyRecommendation ?? $executiveSummary,
            ];
        }

        return [
            'executive_summary' => $executiveSummary,
            'daily_recommendation' => $dailyRecommendation,
            'energy_alerts' => $alerts,
            'recommendation_pack' => $pack,
        ];
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
     * @param array<string, mixed>|null $response
     */
    private function extractAnthropicText(?array $response): string
    {
        $blocks = is_array($response['content'] ?? null) ? $response['content'] : [];

        return collect($blocks)
            ->map(fn ($block) => is_array($block) ? (string) ($block['text'] ?? '') : '')
            ->map(fn (string $text) => trim($text))
            ->filter(fn (string $text) => $text !== '')
            ->implode("\n");
    }

    /**
     * @param array<string, mixed>|null $decoded
     * @return array<string, mixed>|null
     */
    private function sanitizeDecodedRecommendation(?array $decoded): ?array
    {
        if (! is_array($decoded)) {
            return null;
        }

        $executiveSummary = $this->usableAiText($decoded['executive_summary'] ?? null)
            ?? $this->usableAiText($decoded['summary'] ?? null);
        $dailyRecommendation = $this->usableAiText($decoded['daily_recommendation'] ?? null)
            ?? $this->usableAiText($decoded['recommendation'] ?? null)
            ?? $this->recommendationObjectText($decoded);

        $alerts = $this->normalizeAlerts($decoded['energy_alerts'] ?? $decoded['alerts'] ?? []);

        $pack = [];
        if (is_array($decoded['recommendation_pack'] ?? null)) {
            foreach (['savings', 'load_shift', 'risk', 'maintenance', 'climate'] as $key) {
                $value = $this->usableAiText($decoded['recommendation_pack'][$key] ?? null)
                    ?? $this->recommendationObjectText($decoded['recommendation_pack'][$key] ?? null);
                if ($value !== null) {
                    $pack[$key] = $value;
                }
            }
        }

        if ($dailyRecommendation !== null) {
            foreach (['savings', 'load_shift', 'risk', 'maintenance', 'climate'] as $key) {
                $pack[$key] ??= $dailyRecommendation;
            }
        }

        if ($executiveSummary === null && $dailyRecommendation === null && $pack === []) {
            return null;
        }

        return [
            'executive_summary' => $executiveSummary,
            'daily_recommendation' => $dailyRecommendation,
            'energy_alerts' => $alerts,
            'recommendation_pack' => $pack,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function normalizeAlerts(mixed $alerts): array
    {
        if (! is_array($alerts)) {
            return [];
        }

        return collect($alerts)
            ->map(function ($item): ?string {
                if (is_array($item)) {
                    return $this->usableAiText($item['description'] ?? null)
                        ?? $this->usableAiText($item['message'] ?? null)
                        ?? $this->usableAiText($item['alert'] ?? null);
                }

                return $this->usableAiText($item);
            })
            ->filter(fn (?string $value) => $value !== null && mb_strlen($value) >= 24)
            ->unique()
            ->values()
            ->all();
    }

    private function recommendationObjectText(mixed $value): ?string
    {
        if (is_string($value)) {
            return $this->usableAiText($value);
        }

        if (! is_array($value)) {
            return null;
        }

        $diagnostic = $this->usableAiText($value['diagnostic'] ?? $value['diagnostico'] ?? $value['description'] ?? null);
        $action = $this->usableAiText($value['action'] ?? $value['accion'] ?? $value['recommendation'] ?? null);
        $impact = $this->usableAiText($value['impact'] ?? $value['impacto'] ?? $value['expected_impact'] ?? null);
        $message = $this->usableAiText($value['message'] ?? null);

        if ($message !== null && ($diagnostic === null && $action === null && $impact === null)) {
            return $message;
        }

        $parts = [];
        if ($diagnostic !== null) {
            $parts[] = "Diagnostico: {$diagnostic}";
        }
        if ($action !== null) {
            $parts[] = "Accion: {$action}";
        }
        if ($impact !== null) {
            $parts[] = "Impacto esperado: {$impact}";
        }

        return $parts !== [] ? implode(' ', $parts) : null;
    }

    private function usableAiText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $normalized = strtolower(str_replace(['-', ' '], '_', $trimmed));
        $placeholders = [
            'title',
            'text',
            'message',
            'summary',
            'status',
            'diagnostic',
            'diagnostico',
            'action',
            'accion',
            'impact',
            'impacto',
            'severity',
            'type',
            'alert',
            'description',
            'data_source',
            'date_context',
            'executive_summary',
            'daily_recommendation',
            'energy_alerts',
            'recommendation_pack',
            'savings',
            'load_shift',
            'risk',
            'maintenance',
            'climate',
        ];

        return in_array($normalized, $placeholders, true) ? null : $trimmed;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function fallbackResult(array $payload, ?string $error = null): array
    {
        $coverage = data_get($payload, 'energy_metrics.coverage_percentage');
        $dailyGeneration = data_get($payload, 'energy_metrics.estimated_daily_generation_kwh');
        $annualGeneration = data_get($payload, 'energy_metrics.estimated_annual_generation_kwh');
        $annualConsumption = data_get($payload, 'energy_metrics.annual_consumption_kwh');
        $annualBalance = data_get($payload, 'energy_metrics.annual_energy_balance_kwh');
        $savings = data_get($payload, 'energy_metrics.estimated_annual_savings_cop');
        $bestWindow = trim((string) data_get($payload, 'project_context.solar_window.best_window', ''));
        $readingCount = (int) data_get($payload, 'weather_station_summary.reading_count', 0);
        $estimatedRatio = (float) data_get($payload, 'nasa_radiation_quality.estimated_ratio', 0);

        $coverageText = is_numeric($coverage) ? number_format((float) $coverage, 1, ',', '.').'%' : 'sin cobertura calculada';
        $dailyText = is_numeric($dailyGeneration) ? number_format((float) $dailyGeneration, 1, ',', '.').' kWh/dia' : 'sin generacion diaria calculada';
        $annualGenerationText = is_numeric($annualGeneration) ? number_format((float) $annualGeneration, 0, ',', '.').' kWh/ano' : 'sin generacion anual calculada';
        $annualConsumptionText = is_numeric($annualConsumption) ? number_format((float) $annualConsumption, 0, ',', '.').' kWh/ano' : 'sin consumo anual calculado';
        $balanceText = is_numeric($annualBalance) ? number_format((float) $annualBalance, 0, ',', '.').' kWh/ano' : 'sin balance anual calculado';
        $savingsText = is_numeric($savings) ? '$'.number_format((float) $savings, 0, ',', '.') : 'sin ahorro anual calculado';
        $windowText = $bestWindow !== '' ? "la ventana {$bestWindow}" : 'la franja solar con mejor radiacion disponible en las graficas';
        $qualityText = $readingCount > 0
            ? "{$readingCount} lecturas locales disponibles"
            : 'sin lecturas locales recientes';

        if ($estimatedRatio > 0) {
            $qualityText .= ', con datos NASA parcialmente estimados';
        }

        $summary = "El proyecto muestra cobertura {$coverageText}, generacion {$annualGenerationText} frente a consumo {$annualConsumptionText} y balance {$balanceText}. Use {$windowText} para decisiones operativas y trate la calidad de datos como {$qualityText}.";

        $dailyRecommendation = "Diagnostico: con cobertura {$coverageText}, generacion diaria {$dailyText} y balance {$balanceText}, el riesgo principal es consumir fuera de la ventana solar y aumentar dependencia de red. Accion: concentre cargas flexibles en {$windowText}, deje cargas criticas priorizadas y revise consumos nocturnos. Impacto esperado: mejor autoconsumo y menor exposicion a compras de energia, sin asumir cifras fuera del proyecto.";

        $pack = [
            'savings' => "Diagnostico: el ahorro anual estimado es {$savingsText} con cobertura {$coverageText}. Accion: mueva cargas no criticas hacia {$windowText} y compare la factura contra la generacion mensual. Impacto esperado: proteger el ahorro estimado y detectar desviaciones temprano.",
            'load_shift' => "Diagnostico: la generacion diaria estimada es {$dailyText}, pero el beneficio depende de usar energia cuando el sistema produce. Accion: programe equipos de mayor consumo en {$windowText} y evite arranques simultaneos fuera de esa franja. Impacto esperado: mas autoconsumo y menor presion sobre la red.",
            'risk' => "Diagnostico: cobertura {$coverageText}, balance {$balanceText} y calidad de datos {$qualityText} elevan el riesgo de decisiones operativas imprecisas. Accion: opere cargas flexibles en {$windowText}, mantenga cargas esenciales con prioridad y valide lecturas antes de ampliar demanda. Impacto esperado: menor riesgo de deficit operativo y decisiones mas estables.",
            'maintenance' => "Diagnostico: si la produccion real se aleja de {$dailyText} o cae respecto a meses similares, puede haber suciedad, sombra o falla de medicion. Accion: revise inversor, cableado visible y limpieza de modulos antes de cambiar habitos de consumo. Impacto esperado: recuperar rendimiento sin sobredimensionar acciones.",
            'climate' => "Diagnostico: la operacion depende de radiacion y de la confiabilidad de datos; estado actual: {$qualityText}. Accion: use tendencias mensuales y {$windowText} para programar cargas, y sea conservador cuando haya datos estimados. Impacto esperado: operacion mas prudente ante variacion climatica.",
        ];

        return [
            'enabled' => true,
            'source' => 'local_ai_fallback',
            'executive_summary' => $summary,
            'daily_recommendation' => $dailyRecommendation,
            'energy_alerts' => [
                "Vigile consumo fuera de {$windowText}; puede reducir la cobertura efectiva del {$coverageText}.",
                "Calidad de datos: {$qualityText}; confirme lecturas antes de tomar decisiones de alto impacto.",
            ],
            'recommendation_pack' => $pack,
            'error' => $error,
        ];
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function enforceRecommendationSpecificity(array $result, array $payload): array
    {
        $daily = mb_strtolower((string) ($result['daily_recommendation'] ?? ''));
        $hasGenericRange = str_contains($daily, '8:00') && str_contains($daily, '16:00');
        $bestWindow = (string) data_get($payload, 'project_context.solar_window.best_window', '');
        $hasProjectWindow = trim($bestWindow) !== '';

        if (! $hasGenericRange) {
            return $result;
        }

        $coverage = data_get($payload, 'energy_metrics.coverage_percentage');
        $coverageText = is_numeric($coverage) ? number_format((float) $coverage, 1, ',', '.') . '%' : 'sin dato';

        $replacement = $hasProjectWindow
            ? "La recomendacion se ajusta al perfil real del proyecto: concentre cargas flexibles en la ventana {$bestWindow}, donde su historico local muestra mejor captacion. Fuera de esa franja, priorice cargas criticas y evite consumos desplazables. Con cobertura {$coverageText}, esta estrategia reduce compra de red y mejora el aprovechamiento solar sin sobredimensionar operacion."
            : 'No hay ventana horaria local suficiente para definir un bloque operativo preciso; antes de mover cargas, complete mas lecturas del proyecto para identificar horas pico reales y evitar decisiones basadas en rangos generales.';

        $result['daily_recommendation'] = $replacement;

        return $result;
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
            'recommendation_pack' => [],
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
