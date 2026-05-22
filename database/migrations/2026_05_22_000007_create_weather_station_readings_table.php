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
        Schema::create('weather_station_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solar_project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_code')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('temperature', 6, 2)->nullable();
            $table->decimal('humidity', 6, 2)->nullable();
            $table->decimal('thermal_sensation', 6, 2)->nullable();
            $table->unsignedInteger('co2')->nullable();
            $table->decimal('pm25', 8, 2)->nullable();
            $table->decimal('pm10', 8, 2)->nullable();
            $table->decimal('uva', 8, 3)->nullable();
            $table->decimal('uvb', 8, 3)->nullable();
            $table->decimal('uv_index', 8, 3)->nullable();
            $table->decimal('solar_radiation', 10, 3)->nullable();
            $table->dateTime('measured_at');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['solar_project_id', 'measured_at']);
            $table->index(['device_code', 'measured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_station_readings');
    }
};
