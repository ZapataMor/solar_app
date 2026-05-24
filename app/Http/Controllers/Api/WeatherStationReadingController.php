<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WeatherStationReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WeatherStationReadingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $rawPayload = $request->all();
        $normalized = $this->normalizePayload($rawPayload);

        $validator = Validator::make($normalized, [
            'device_code' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'temperature' => ['nullable', 'numeric', 'between:-20,60'],
            'humidity' => ['nullable', 'numeric', 'between:0,100'],
            'thermal_sensation' => ['nullable', 'numeric', 'between:-20,70'],
            'co2' => ['nullable', 'integer', 'between:0,20000'],
            'pm25' => ['nullable', 'numeric', 'between:0,1000'],
            'pm10' => ['nullable', 'numeric', 'between:0,1000'],
            'uva' => ['nullable', 'numeric', 'min:0'],
            'uvb' => ['nullable', 'numeric', 'min:0'],
            'uv_index' => ['nullable', 'numeric', 'between:0,20'],
            'solar_radiation' => ['nullable', 'numeric', 'min:0'],
            'measured_at' => ['nullable', 'date'],
        ]);

        $validator->after(function ($validator) use ($normalized): void {
            $hasReading = collect([
                'temperature',
                'humidity',
                'thermal_sensation',
                'co2',
                'pm25',
                'pm10',
                'uva',
                'uvb',
                'uv_index',
                'solar_radiation',
            ])->contains(fn (string $field) => $normalized[$field] !== null);

            if (! $hasReading) {
                $validator->errors()->add('reading', 'Debe enviar al menos una lectura meteorologica.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Datos incompletos o invalidos.',
                'errors' => $validator->errors(),
                'received' => $rawPayload,
            ], 422);
        }

        $validated = $validator->validated();

        $reading = WeatherStationReading::create([
            ...$validated,
            'measured_at' => $validated['measured_at'] ?? now(),
            'raw_payload' => $rawPayload,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Lectura meteorologica registrada correctamente.',
            'data' => $reading,
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $temperature = $this->value($payload, ['temperature', 'temperatura', 'temp']);
        $thermalSensation = $this->value($payload, ['thermal_sensation', 'sensacion_termica', 'st']);

        return [
            'device_code' => $this->value($payload, ['device_code', 'codigo_dispositivo']),
            'latitude' => $this->value($payload, ['latitude', 'latitud', 'lat']),
            'longitude' => $this->value($payload, ['longitude', 'longitud', 'lng', 'lon']),
            'temperature' => $temperature,
            'humidity' => $this->value($payload, ['humidity', 'humedad', 'hum']),
            'thermal_sensation' => $thermalSensation ?? $temperature,
            'co2' => $this->value($payload, ['co2']),
            'pm25' => $this->value($payload, ['pm25']),
            'pm10' => $this->value($payload, ['pm10']),
            'uva' => $this->value($payload, ['uva']),
            'uvb' => $this->value($payload, ['uvb']),
            'uv_index' => $this->value($payload, ['uv_index', 'indice_uv', 'iuv']),
            'solar_radiation' => $this->value($payload, ['solar_radiation', 'radiacion_solar', 'radiacion']),
            'measured_at' => $this->value($payload, ['measured_at', 'fecha_medicion', 'fecha']),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $keys
     */
    private function value(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== '') {
                return $payload[$key];
            }
        }

        return null;
    }
}
