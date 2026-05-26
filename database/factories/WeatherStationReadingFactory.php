<?php

namespace Database\Factories;

use App\Models\WeatherStationReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeatherStationReading>
 */
class WeatherStationReadingFactory extends Factory
{
    protected $model = WeatherStationReading::class;

    public function definition(): array
    {
        return [
            'device_code'     => 'METEOESTACION',
            'latitude'        => 11.5444,
            'longitude'       => -72.9072,
            'temperature'     => $this->faker->randomFloat(2, 15, 40),
            'humidity'        => $this->faker->randomFloat(2, 30, 100),
            'thermal_sensation'=> $this->faker->randomFloat(2, 15, 45),
            'co2'             => $this->faker->numberBetween(400, 1500),
            'uv_index'        => $this->faker->randomFloat(3, 0, 11),
            'solar_radiation' => $this->faker->randomFloat(3, 0, 1000),
            'measured_at'     => $this->faker->dateTimeBetween('-7 days', 'now'),
            'raw_payload'     => null,
        ];
    }
}
