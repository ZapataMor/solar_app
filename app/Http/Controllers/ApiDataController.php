<?php

namespace App\Http\Controllers;

use App\Models\AmbientWeatherReading;
use App\Models\WeatherStationReading;
use App\Services\AmbientWeatherImportService;
use App\Services\NasaPowerService;
use App\Services\NasaWeatherDataService;
use App\Services\WeatherStationImportService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class ApiDataController extends Controller
{
    public function __invoke(Request $request): View
    {
        $ambientRows = $this->ambientRowsQuery()
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'ambient_page')
            ->withQueryString();

        $weatherStationRows = $this->weatherStationRowsQuery()
            ->orderByDesc('weather_station_readings.measured_at')
            ->orderByDesc('weather_station_readings.id')
            ->paginate(15, ['*'], 'station_page')
            ->withQueryString();

        $nasaRows = $this->nasaRowsQuery()
            ->orderByDesc('recorded_at')
            ->orderByDesc('record_id')
            ->paginate(15, ['*'], 'nasa_page')
            ->withQueryString();

        $ambientCount         = $this->ambientRowsCount();
        $weatherStationCount  = $this->weatherStationRowsCount();
        $nasaCount            = $this->nasaRowsCount();

        return view('api-data.index', [
            'ambientRows'             => $ambientRows,
            'ambientCount'            => $ambientCount,
            'ambientChartRows'        => $this->latestAmbientChartRows(),
            'nasaRows'                => $nasaRows,
            'nasaCount'               => $nasaCount,
            'weatherStationRows'      => $weatherStationRows,
            'weatherStationCount'     => $weatherStationCount,
            'weatherStationChartRows' => $this->latestWeatherStationChartRows(),
        ]);
    }

    public function fetchNasaData(
        Request $request,
        NasaPowerService $nasaPowerService,
        NasaWeatherDataService $nasaWeatherDataService,
    ): RedirectResponse|JsonResponse
    {
        $projects = $request->user()->solarProjects()->get();

        if ($projects->isEmpty()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'No hay proyectos solares registrados para consultar NASA POWER.',
                ], 422);
            }

            return back()->withErrors([
                'nasa_data' => 'No hay proyectos solares registrados para consultar NASA POWER.',
            ]);
        }

        $startDate = $projects->min('start_date');
        $endDate = $projects->max('end_date');

        try {
            $payload = $nasaPowerService->fetchHourlyData($startDate, $endDate);
            ['created' => $created, 'updated' => $updated] = $nasaWeatherDataService->storeDailyData($payload);
        } catch (Throwable $exception) {
            report($exception);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'No fue posible consultar NASA POWER para los proyectos registrados.',
                ], 502);
            }

            return back()->withErrors([
                'nasa_data' => 'No fue posible consultar NASA POWER para los proyectos registrados.',
            ]);
        }

        $message = "NASA POWER sincronizado. Nuevos: {$created}. Existentes actualizados: {$updated}.";

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'nasaCount' => $this->nasaRowsCount(),
                'rows' => $this->latestNasaRows(),
            ]);
        }

        return back()->with('status', $message);
    }

    public function fetchWeatherStationData(
        Request $request,
        WeatherStationImportService $weatherStationImportService,
    ): RedirectResponse|JsonResponse {
        try {
            $imported = $weatherStationImportService->importAll();
        } catch (Throwable $exception) {
            report($exception);
            $errorMessage = app()->isLocal()
                ? "No fue posible consultar el endpoint del centro meteorologico. Detalle: {$exception->getMessage()}"
                : 'No fue posible consultar el endpoint del centro meteorologico.';

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => $errorMessage,
                ], 502);
            }

            return back()->withErrors([
                'weather_station' => $errorMessage,
            ]);
        }

        $total = $this->weatherStationRowsCount();

        if ($total === 0) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'No hay lecturas del centro meteorologico registradas.',
                ], 422);
            }

            return back()->withErrors([
                'weather_station' => 'No hay lecturas del centro meteorologico registradas.',
            ]);
        }

        $message = "Datos del centro meteorologico obtenidos desde el endpoint. Nuevos: {$imported['created']}. Actualizados: {$imported['updated']}. Existentes omitidos: {$imported['skipped']}. Total global: {$total}.";

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'weatherStationCount' => $total,
                'rows' => $this->latestWeatherStationRows(),
                'chartRows' => $this->latestWeatherStationChartRows(),
            ]);
        }

        return back()->with(
            'status',
            $message,
        );
    }

    public function fetchAmbientData(
        Request $request,
        AmbientWeatherImportService $ambientWeatherImportService,
    ): RedirectResponse|JsonResponse {
        // Allow long-running historical imports without hitting PHP's default limit.
        set_time_limit(0);

        $from = Carbon::now('UTC')->subYear()->startOfDay();

        try {
            $imported = $request->boolean('auto_sync')
                ? $ambientWeatherImportService->importLatestForAllDevices()
                : $ambientWeatherImportService->importHistoricalForAllDevices(
                    from: $from,
                    sleepSeconds: 1,
                );
        } catch (Throwable $exception) {
            report($exception);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'No fue posible sincronizar Ambient Weather: ' . $exception->getMessage(),
                ], 502);
            }

            return back()->withErrors([
                'ambient_data' => 'No fue posible sincronizar Ambient Weather: ' . $exception->getMessage(),
            ]);
        }

        if ($imported['received'] === 0) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Ambient Weather no devolvio lecturas. Verifica las credenciales y que tengas estaciones registradas.',
                ], 422);
            }

            return back()->withErrors([
                'ambient_data' => 'Ambient Weather no devolvio lecturas. Verifica las credenciales y que tengas estaciones registradas.',
            ]);
        }

        $since = $from->format('d/m/Y');
        $total = $this->ambientRowsCount();
        $message = $request->boolean('auto_sync')
            ? "Ambient Weather actualizado. Nuevos: {$imported['created']}. Omitidos (duplicados): {$imported['skipped']}. Total global: {$total}."
            : "Ambient Weather sincronizado desde {$since}. Nuevos: {$imported['created']}. Omitidos (duplicados): {$imported['skipped']}. Total recibidos: {$imported['received']}.";

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'ambientCount' => $total,
                'rows' => $this->latestAmbientRows(),
                'chartRows' => $this->latestAmbientChartRows(),
            ]);
        }

        return back()->with(
            'status',
            $message
        );
    }

    private function ambientRowsQuery(): Builder
    {
        return DB::table('ambient_weather_readings')
            ->select([
                'id',
                'mac_address',
                'recorded_at',
                'temperature',
                'humidity',
                'wind_speed',
                'wind_direction',
                'rainfall',
                'uv_index',
                'solar_radiation as radiation',
            ]);
    }

    private function ambientRowsCount(): int
    {
        return AmbientWeatherReading::query()->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestAmbientChartRows(): array
    {
        return DB::table('ambient_weather_readings')
            ->select(['recorded_at', 'solar_radiation', 'uv_index', 'temperature'])
            ->orderByDesc('recorded_at')
            ->limit(30)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (object $row): array => [
                'recorded_at'    => $row->recorded_at ? Carbon::parse($row->recorded_at)->format('Y-m-d H:i') : 'N/A',
                'radiation'      => $row->solar_radiation !== null ? (float) $row->solar_radiation : null,
                'uv_index'       => $row->uv_index !== null ? (float) $row->uv_index : null,
                'temperature'    => $row->temperature !== null ? (float) $row->temperature : null,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestAmbientRows(): array
    {
        return $this->ambientRowsQuery()
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (object $row): array => [
                'recorded_at' => $row->recorded_at ? Carbon::parse($row->recorded_at)->format('Y-m-d H:i') : 'N/A',
                'mac_address' => $row->mac_address ?? 'N/A',
                'radiation' => $this->formatJsonNumber($row->radiation, 2),
                'temperature' => $this->formatJsonNumber($row->temperature, 2),
                'humidity' => $this->formatJsonNumber($row->humidity, 2),
                'wind_speed' => $this->formatJsonNumber($row->wind_speed, 2),
                'wind_direction' => $row->wind_direction !== null ? $row->wind_direction . '°' : 'N/A',
                'rainfall' => $this->formatJsonNumber($row->rainfall, 3),
                'uv_index' => $this->formatJsonNumber($row->uv_index, 2),
            ])
            ->all();
    }

    private function nasaRowsQuery(): Builder
    {
        return DB::table('api_weather_data')
            ->select([
                DB::raw("'nasa' as source_key"),
                DB::raw("'NASA POWER' as source_name"),
                'api_weather_data.id as record_id',
                DB::raw('null as device_code'),
                'api_weather_data.date_time as recorded_at',
                'api_weather_data.allsky_sfc_sw_dwn as radiation',
                'api_weather_data.radiation_source as radiation_source',
                'api_weather_data.radiation_fallback_method as radiation_method',
                'api_weather_data.radiation_confidence as radiation_confidence',
                'api_weather_data.t2m as temperature',
                'api_weather_data.rh2m as humidity',
                'api_weather_data.prectotcorr as precipitation',
                'api_weather_data.ws10m as wind_speed',
                DB::raw('null as thermal_sensation'),
                DB::raw('null as co2'),
                DB::raw('null as pm25'),
                DB::raw('null as pm10'),
                DB::raw('null as uva'),
                DB::raw('null as uvb'),
                DB::raw('null as uv_index'),
            ]);
    }

    private function weatherStationRowsQuery(): Builder
    {
        return DB::table('weather_station_readings')
            ->select([
                DB::raw("'weather_station' as source_key"),
                DB::raw("'Centro meteorologico' as source_name"),
                'weather_station_readings.id as record_id',
                DB::raw("'Global' as project_name"),
                'weather_station_readings.device_code as device_code',
                'weather_station_readings.measured_at as recorded_at',
                DB::raw('COALESCE(weather_station_readings.solar_radiation, CASE WHEN weather_station_readings.uva IS NULL AND weather_station_readings.uvb IS NULL AND weather_station_readings.uv_index IS NULL THEN NULL ELSE (COALESCE(weather_station_readings.uva, 0) + COALESCE(weather_station_readings.uvb, 0) + COALESCE(weather_station_readings.uv_index, 0)) / 3 END) as radiation'),
                'weather_station_readings.temperature as temperature',
                'weather_station_readings.humidity as humidity',
                DB::raw('null as precipitation'),
                DB::raw('null as wind_speed'),
                'weather_station_readings.thermal_sensation as thermal_sensation',
                'weather_station_readings.co2 as co2',
                'weather_station_readings.pm25 as pm25',
                'weather_station_readings.pm10 as pm10',
                'weather_station_readings.uva as uva',
                'weather_station_readings.uvb as uvb',
                'weather_station_readings.uv_index as uv_index',
            ]);
    }

    private function nasaRowsCount(): int
    {
        return DB::query()
            ->fromSub($this->nasaRowsQuery(), 'nasa_rows')
            ->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestNasaRows(): array
    {
        return $this->nasaRowsQuery()
            ->orderByDesc('recorded_at')
            ->orderByDesc('record_id')
            ->limit(15)
            ->get()
            ->map(fn (object $row): array => [
                'recorded_at' => $row->recorded_at ? Carbon::parse($row->recorded_at)->format('Y-m-d H:i') : 'N/A',
                'status' => $this->nasaRowIsIncomplete($row) ? 'Incompleto' : 'Completo',
                'is_incomplete' => $this->nasaRowIsIncomplete($row),
                'radiation' => $this->formatJsonNasaNumber($row->radiation, 3),
                'radiation_source' => $this->nasaRadiationSourceLabel($row->radiation_method ?? 'nasa_real'),
                'radiation_confidence' => number_format((float) ($row->radiation_confidence ?? 0), 2, ',', '.'),
                'temperature' => $this->formatJsonNasaNumber($row->temperature, 2),
                'humidity' => $this->formatJsonNasaNumber($row->humidity, 2),
                'precipitation' => $this->formatJsonNasaNumber($row->precipitation, 4),
                'wind_speed' => $this->formatJsonNasaNumber($row->wind_speed, 2),
            ])
            ->all();
    }

    private function weatherStationRowsCount(): int
    {
        return WeatherStationReading::query()->count();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestWeatherStationRows(): array
    {
        return $this->weatherStationRowsQuery()
            ->orderByDesc('weather_station_readings.measured_at')
            ->orderByDesc('weather_station_readings.id')
            ->limit(15)
            ->get()
            ->map(fn (object $row): array => [
                'project_name' => $row->project_name ?? 'Sin asociar',
                'recorded_at' => $row->recorded_at ? Carbon::parse($row->recorded_at)->format('Y-m-d H:i') : 'N/A',
                'device_code' => $row->device_code ?? 'N/A',
                'radiation' => $this->formatJsonNumber($row->radiation, 3),
                'temperature' => $this->formatJsonNumber($row->temperature, 2),
                'humidity' => $this->formatJsonNumber($row->humidity, 2),
                'thermal_sensation' => $this->formatJsonNumber($row->thermal_sensation, 2),
                'co2' => $row->co2 ?? 'N/A',
                'pm25' => $this->formatJsonNumber($row->pm25, 2),
                'pm10' => $this->formatJsonNumber($row->pm10, 2),
                'uva' => $this->formatJsonNumber($row->uva, 3),
                'uvb' => $this->formatJsonNumber($row->uvb, 3),
                'uv_index' => $this->formatJsonNumber($row->uv_index, 3),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function latestWeatherStationChartRows(): array
    {
        return $this->weatherStationRowsQuery()
            ->orderByDesc('weather_station_readings.measured_at')
            ->orderByDesc('weather_station_readings.id')
            ->limit(30)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (object $row): array => [
                'recorded_at' => $row->recorded_at ? Carbon::parse($row->recorded_at)->format('Y-m-d H:i') : 'N/A',
                'radiation' => $row->radiation !== null ? (float) $row->radiation : null,
                'uva' => $row->uva !== null ? (float) $row->uva : null,
                'uvb' => $row->uvb !== null ? (float) $row->uvb : null,
                'uv_index' => $row->uv_index !== null ? (float) $row->uv_index : null,
            ])
            ->all();
    }

    private function formatJsonNumber(mixed $value, int $decimals = 2): string
    {
        return $value !== null ? number_format((float) $value, $decimals, ',', '.') : 'N/A';
    }

    private function formatJsonNasaNumber(mixed $value, int $decimals = 2): string
    {
        return $value !== null ? number_format((float) $value, $decimals, ',', '.') : 'Dato no publicado por NASA';
    }

    private function nasaRadiationSourceLabel(?string $method): string
    {
        return match ($method ?? 'nasa_real') {
            'nasa_real' => 'NASA real',
            'interpolated_recent' => 'Estimado: interpolacion',
            'weather_signals_model' => 'Estimado: señales meteo',
            'historical_monthly' => 'Estimado: historico mensual',
            'riohacha_climatology' => 'Estimado: climatologia Riohacha',
            'last_valid_known' => 'Estimado: ultimo valor valido',
            default => 'Estimado',
        };
    }

    private function nasaRowIsIncomplete(object $row): bool
    {
        return $row->radiation === null
            || $row->temperature === null
            || $row->humidity === null
            || $row->precipitation === null
            || $row->wind_speed === null;
    }
}
