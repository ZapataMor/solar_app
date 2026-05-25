<?php

namespace Tests\Unit;

use App\Models\ApiWeatherData;
use App\Services\NasaRadiationFallbackService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NasaRadiationFallbackServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prefers_real_nasa_radiation(): void
    {
        $service = app(NasaRadiationFallbackService::class);

        $result = $service->resolve(
            Carbon::parse('2026-05-21'),
            '20260521',
            ['20260521'],
            ['20260521' => 120.5],
        );

        $this->assertSame('nasa_real', $result['source']);
        $this->assertSame('nasa_real', $result['method']);
        $this->assertSame(120.5, $result['value']);
    }

    public function test_it_interpolates_when_middle_value_is_missing(): void
    {
        $service = app(NasaRadiationFallbackService::class);

        $result = $service->resolve(
            Carbon::parse('2026-05-22'),
            '20260522',
            ['20260521', '20260522', '20260523'],
            ['20260521' => 100.0, '20260522' => null, '20260523' => 140.0],
        );

        $this->assertSame('estimated', $result['source']);
        $this->assertSame('interpolated_recent', $result['method']);
        $this->assertEqualsWithDelta(120.0, (float) $result['value'], 0.001);
    }

    public function test_it_uses_historical_monthly_average_when_interpolation_is_not_possible(): void
    {
        ApiWeatherData::query()->create([
            'date_time' => '2024-05-10 00:00:00',
            'allsky_sfc_sw_dwn' => 130,
        ]);
        ApiWeatherData::query()->create([
            'date_time' => '2025-05-11 00:00:00',
            'allsky_sfc_sw_dwn' => 110,
        ]);

        $service = app(NasaRadiationFallbackService::class);

        $result = $service->resolve(
            Carbon::parse('2026-05-21'),
            '20260521',
            ['20260521'],
            ['20260521' => null],
            ['20260521' => 28.0],
            ['20260521' => 70.0],
            ['20260521' => 0.0],
            ['20260521' => 4.0],
        );

        $this->assertSame('estimated', $result['source']);
        $this->assertSame('historical_monthly', $result['method']);
        $this->assertNotNull($result['value']);
    }
}

