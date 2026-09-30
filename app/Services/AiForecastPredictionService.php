<?php

namespace App\Services;

use App\Models\SolarProject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AiForecastPredictionService
{
    /**
     * @param array<string, mixed> $futurePredictions
     * @return array<string, mixed>
     */
    public function generate(SolarProject $solarProject, array $futurePredictions): array
    {
        $payload = $this->payload($solarProject, $futurePredictions);
        $cacheKey = 'ai_forecast_prediction:'.md5(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        /** @var mixed $cached */
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        if (! (bool) config('services.openai_recommendations.enabled', false)) {
            return $this->fallback($payload, 'La IA no esta habilitada; se genero una prediccion local con el historico reciente.');
        }

        if (blank(config('openai.api_key'))) {
            return $this->fallback($payload, 'Configura la clave de Anthropic/OpenAI para generar predicciones con IA.');
        }

        try {
            $decoded = $this->requestClaudePrediction($payload);
            $decoded = $this->sanitize($decoded, $payload);

            if (! is_array($decoded)) {
                return $this->fallback($payload, 'Claude no devolvio una prediccion utilizable; se uso el calculo local.');
            }

            $result = [
                'source' => 'anthropic',
                'title' => $decoded['title'],
                'prediction' => $decoded['prediction'],
                'temperature_outlook' => $decoded['temperature_outlook'],
                'solar_window' => $decoded['solar_window'],
                'actions' => $decoded['actions'],
                'confidence' => $decoded['confidence'],
                'error' => null,
            ];

            Cache::put($cacheKey, $result, now()->addMinutes(max(1, (int) config('services.openai_recommendations.cache_ttl_minutes', 30))));

            return $result;
        } catch (Throwable $exception) {
            report($exception);

            return $this->fallback($payload, $this->humanError($exception->getMessage()));
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function requestClaudePrediction(array $payload): ?array
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
                'max_tokens' => max(1200, (int) config('services.openai_recommendations.max_output_tokens', 1200)),
                'temperature' => 0.2,
                'system' => implode("\n", [
                    'Eres un analista energetico y meteorologico para un dashboard solar en Riohacha.',
                    'Genera una prediccion operacional para la proxima semana usando SOLO el historico recibido.',
                    'No inventes lecturas, fechas, radiacion ni temperaturas.',
                    'Si los datos son insuficientes, declaralo y baja la confianza.',
                    'La prediccion debe mencionar evidencia: registros usados, fuente, tendencia termica y ventana solar.',
                    'Responde SOLO JSON valido con claves: title, prediction, temperature_outlook, solar_window, actions, confidence.',
                    'No uses markdown ni bloques ```json.',
                    'Limites estrictos: prediction max 420 caracteres; temperature_outlook max 220; solar_window max 220; cada action max 140.',
                    'actions debe ser arreglo de 2 a 4 acciones concretas. confidence debe ser baja, media o alta.',
                ]),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Genera la prediccion IA de la proxima semana para este proyecto:\n".json_encode(
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

        $text = collect($response->json('content', []))
            ->map(fn ($block) => is_array($block) ? trim((string) ($block['text'] ?? '')) : '')
            ->filter()
            ->implode("\n");

        $decoded = json_decode($this->stripJsonFence($text), true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $text, $matches)) {
            $decoded = json_decode($matches[0], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $partial = $this->parsePartialJsonText($text);
        if (is_array($partial)) {
            return $partial;
        }

        Log::warning('AI forecast prediction: unable to decode Claude JSON', [
            'text_excerpt' => mb_substr($text, 0, 800),
        ]);

        return null;
    }

    /**
     * @param array<string, mixed>|null $decoded
     * @return array<string, mixed>|null
     */
    private function sanitize(?array $decoded, array $payload): ?array
    {
        if (! is_array($decoded)) {
            return null;
        }

        $fallback = $this->fallback($payload, null);
        $title = $this->usableText($decoded['title'] ?? null) ?? $fallback['title'];
        $prediction = $this->usableText($decoded['prediction'] ?? null);
        $temperature = $this->usableText($decoded['temperature_outlook'] ?? null) ?? $fallback['temperature_outlook'];
        $solarWindow = $this->usableText($decoded['solar_window'] ?? null) ?? $fallback['solar_window'];
        $actions = collect($decoded['actions'] ?? [])
            ->map(fn ($item) => $this->usableText($item))
            ->filter()
            ->values()
            ->all();
        $confidence = strtolower((string) ($this->usableText($decoded['confidence'] ?? null) ?? 'media'));

        if (! in_array($confidence, ['baja', 'media', 'alta'], true)) {
            $confidence = 'media';
        }

        if ($prediction === null) {
            return null;
        }

        return [
            'title' => $title,
            'prediction' => $prediction,
            'temperature_outlook' => $temperature,
            'solar_window' => $solarWindow,
            'actions' => $actions !== [] ? $actions : ['Monitorear datos diarios y ajustar cargas segun la ventana solar proyectada.'],
            'confidence' => $confidence,
        ];
    }

    private function usableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $normalized = strtolower(str_replace([' ', '-'], '_', $trimmed));
        $placeholders = ['title', 'prediction', 'temperature_outlook', 'solar_window', 'actions', 'confidence', 'message', 'date_context'];

        return in_array($normalized, $placeholders, true) ? null : $trimmed;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parsePartialJsonText(string $text): ?array
    {
        $clean = $this->stripJsonFence($text);
        $extract = function (string $key) use ($clean): ?string {
            $pattern = '/"'.preg_quote($key, '/').'"\s*:\s*"((?:[^"\\\\]|\\\\.)*)/s';
            if (! preg_match($pattern, $clean, $matches)) {
                return null;
            }

            $value = trim(stripcslashes((string) $matches[1]));

            return $value !== '' ? $value : null;
        };

        $prediction = $extract('prediction');
        if ($prediction === null) {
            return null;
        }

        $actions = [];
        if (preg_match('/"actions"\s*:\s*\[(.*?)(?:\]|\z)/s', $clean, $matches)) {
            preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/s', (string) $matches[1], $actionMatches);
            $actions = collect($actionMatches[1] ?? [])
                ->map(fn ($raw) => trim(stripcslashes((string) $raw)))
                ->filter()
                ->values()
                ->all();
        }

        return [
            'title' => $extract('title'),
            'prediction' => $prediction,
            'temperature_outlook' => $extract('temperature_outlook'),
            'solar_window' => $extract('solar_window'),
            'actions' => $actions,
            'confidence' => $extract('confidence') ?? 'media',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function fallback(array $payload, ?string $error): array
    {
        $temp = data_get($payload, 'computed_prediction.temperature');
        $window = data_get($payload, 'computed_prediction.radiation_window');
        $data = data_get($payload, 'computed_prediction.data_window', []);
        $projectedTemp = data_get($temp, 'projected_next_week_c');
        $delta = data_get($temp, 'delta_c');
        $source = (string) data_get($data, 'source', 'historico disponible');
        $samples = (int) data_get($data, 'sample_count', 0);
        $days = (int) data_get($data, 'days', 0);
        $startHour = data_get($window, 'start_hour');
        $endHour = data_get($window, 'end_hour');
        $avgRadiation = data_get($window, 'avg_radiation');

        $tempText = is_numeric($projectedTemp)
            ? 'Temperatura proyectada: '.number_format((float) $projectedTemp, 2, ',', '.').' °C con delta semanal '.number_format((float) $delta, 2, ',', '.').' °C.'
            : 'No hay datos suficientes para proyectar temperatura con confianza.';
        $windowText = is_numeric($startHour) && is_numeric($endHour)
            ? sprintf('Ventana solar sugerida: %02d:00-%02d:59 con radiacion media reciente de %s W/m2.', (int) $startHour, (int) $endHour, is_numeric($avgRadiation) ? number_format((float) $avgRadiation, 0, ',', '.') : 'N/D')
            : 'No hay registros horarios suficientes para definir una ventana solar confiable.';

        return [
            'source' => 'local_ai_fallback',
            'title' => 'Prediccion operacional proxima semana',
            'prediction' => "Con {$samples} registros de {$days} dias y fuente principal {$source}, la proxima semana debe operarse de forma prudente usando la tendencia reciente como referencia.",
            'temperature_outlook' => $tempText,
            'solar_window' => $windowText,
            'actions' => [
                'Programar cargas flexibles dentro de la ventana solar proyectada.',
                'Revisar diariamente temperatura y radiacion antes de mover cargas criticas.',
                'Bajar la agresividad operativa si disminuyen las lecturas reales disponibles.',
            ],
            'confidence' => $samples >= 14 ? 'media' : 'baja',
            'error' => $error,
        ];
    }

    /**
     * @param array<string, mixed> $futurePredictions
     * @return array<string, mixed>
     */
    private function payload(SolarProject $solarProject, array $futurePredictions): array
    {
        return [
            'project' => [
                'id' => $solarProject->id,
                'name' => $solarProject->name,
                'location' => $solarProject->municipality?->name,
            ],
            'horizon' => 'proxima_semana',
            'computed_prediction' => $futurePredictions,
            'instruction' => 'Usar los registros de ultima semana o ultimo mes incluidos en computed_prediction.ai_context para redactar la prediccion final.',
        ];
    }

    private function stripJsonFence(string $text): string
    {
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean) ?? $clean;

        return preg_replace('/\s*```$/', '', $clean) ?? $clean;
    }

    private function humanError(string $message): string
    {
        $normalized = strtolower(trim($message));

        if ($normalized === '') {
            return 'No fue posible generar la prediccion con Claude en este momento.';
        }

        if (str_contains($normalized, 'rate limit')) {
            return 'Claude esta temporalmente limitado por cuota. Intenta nuevamente en unos minutos.';
        }

        return "Claude error: {$message}";
    }
}
