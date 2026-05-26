<?php

namespace Database\Factories;

use App\Models\AmbientWeatherReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AmbientWeatherReading>
 */
class AmbientWeatherReadingFactory extends Factory
{
    protected $model = AmbientWeatherReading::class;

    public function definition(): array
    {
        return [
            'mac_address'    => $this->faker->macAddress(),
            'recorded_at'    => $this->faker->dateTimeBetween('-7 days', 'now'),
            'raw_payload'    => null,
            'temperature'    => $this->faker->randomFloat(2, 15, 40),
            'humidity'       => $this->faker->randomFloat(2, 30, 100),
            'wind_speed'     => $this->faker->randomFloat(3, 0, 50),
            'wind_direction' => $this->faker->numberBetween(0, 359),
            'rainfall'       => $this->faker->randomFloat(3, 0, 10),
            'uv_index'       => $this->faker->randomFloat(3, 0, 11),
            'solar_radiation'=> $this->faker->randomFloat(3, 0, 1000),
        ];
    }
}
