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
        Schema::create('api_weather_data', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date_time');
            $table->decimal('allsky_sfc_sw_dwn', 10, 4)->nullable();
            $table->decimal('t2m', 8, 3)->nullable();
            $table->decimal('rh2m', 8, 3)->nullable();
            $table->decimal('prectotcorr', 10, 4)->nullable();
            $table->decimal('ws10m', 8, 3)->nullable();
            $table->timestamps();

            $table->unique('date_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_weather_data');
    }
};
