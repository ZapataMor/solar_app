<?php

namespace App\Http\Controllers;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ApiDataController extends Controller
{
    public function __invoke(Request $request): View
    {
        $source = $request->string('source')->toString();
        $source = in_array($source, ['nasa', 'weather_station'], true) ? $source : 'all';
        $userId = $request->user()->id;

        $rowsQuery = match ($source) {
            'nasa' => $this->nasaRowsQuery($userId),
            'weather_station' => $this->weatherStationRowsQuery($userId),
            default => $this->nasaRowsQuery($userId)->unionAll($this->weatherStationRowsQuery($userId)),
        };

        $apiRows = DB::query()
            ->fromSub($rowsQuery, 'api_rows')
            ->orderByDesc('recorded_at')
            ->orderByDesc('record_id')
            ->paginate(15)
            ->withQueryString();

        return view('api-data.index', [
            'apiRows' => $apiRows,
            'source' => $source,
            'nasaCount' => $this->nasaRowsCount($userId),
            'weatherStationCount' => $this->weatherStationRowsCount($userId),
        ]);
    }

    private function nasaRowsQuery(int $userId): Builder
    {
        return DB::table('api_weather_data')
            ->join('solar_projects', 'api_weather_data.solar_project_id', '=', 'solar_projects.id')
            ->where('solar_projects.user_id', $userId)
            ->select([
                DB::raw("'nasa' as source_key"),
                DB::raw("'NASA POWER' as source_name"),
                'api_weather_data.id as record_id',
                'solar_projects.name as project_name',
                DB::raw('null as device_code'),
                'api_weather_data.date_time as recorded_at',
                'api_weather_data.allsky_sfc_sw_dwn as radiation',
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

    private function weatherStationRowsQuery(int $userId): Builder
    {
        return DB::table('weather_station_readings')
            ->leftJoin('solar_projects', 'weather_station_readings.solar_project_id', '=', 'solar_projects.id')
            ->where(function (Builder $query) use ($userId): void {
                $query
                    ->where('solar_projects.user_id', $userId)
                    ->orWhereNull('weather_station_readings.solar_project_id');
            })
            ->select([
                DB::raw("'weather_station' as source_key"),
                DB::raw("'Centro meteorologico' as source_name"),
                'weather_station_readings.id as record_id',
                'solar_projects.name as project_name',
                'weather_station_readings.device_code as device_code',
                'weather_station_readings.measured_at as recorded_at',
                'weather_station_readings.solar_radiation as radiation',
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

    private function nasaRowsCount(int $userId): int
    {
        return DB::table('api_weather_data')
            ->join('solar_projects', 'api_weather_data.solar_project_id', '=', 'solar_projects.id')
            ->where('solar_projects.user_id', $userId)
            ->count();
    }

    private function weatherStationRowsCount(int $userId): int
    {
        return DB::table('weather_station_readings')
            ->leftJoin('solar_projects', 'weather_station_readings.solar_project_id', '=', 'solar_projects.id')
            ->where(function (Builder $query) use ($userId): void {
                $query
                    ->where('solar_projects.user_id', $userId)
                    ->orWhereNull('weather_station_readings.solar_project_id');
            })
            ->count();
    }
}
