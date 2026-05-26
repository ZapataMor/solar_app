<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ambient_weather_readings', function (Blueprint $table) {
            $table->id();

            // Station identifier — MAC address from the Ambient Weather device
            $table->string('mac_address', 17);

            // Timestamp reported by the station (UTC)
            $table->dateTime('recorded_at');

            // Full raw JSON payload preserved for debugging / future re-parsing
            $table->json('raw_payload')->nullable();

            // Normalised meteorological fields (all converted to SI / project-internal units)
            $table->decimal('temperature', 6, 2)->nullable();        // °C
            $table->decimal('humidity', 6, 2)->nullable();           // %
            $table->decimal('wind_speed', 8, 3)->nullable();         // km/h
            $table->smallInteger('wind_direction')->nullable();      // degrees 0-359
            $table->decimal('rainfall', 8, 3)->nullable();           // mm (hourly)
            $table->decimal('uv_index', 8, 3)->nullable();           // UV index
            $table->decimal('solar_radiation', 10, 3)->nullable();   // W/m²

            $table->timestamps();

            // Fast lookups by station and time
            $table->index('mac_address');
            $table->index('recorded_at');

            // Composite unique key to prevent duplicate imports
            $table->unique(['mac_address', 'recorded_at'], 'ambient_mac_time_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ambient_weather_readings');
    }
};
