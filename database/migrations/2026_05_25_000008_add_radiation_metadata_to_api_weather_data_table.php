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
        Schema::table('api_weather_data', function (Blueprint $table) {
            $table->string('radiation_source', 32)->default('nasa_real')->after('allsky_sfc_sw_dwn');
            $table->string('radiation_fallback_method', 64)->default('nasa_real')->after('radiation_source');
            $table->decimal('radiation_confidence', 4, 2)->default(1.00)->after('radiation_fallback_method');
            $table->index(['radiation_source', 'radiation_fallback_method'], 'api_weather_data_radiation_meta_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_weather_data', function (Blueprint $table) {
            $table->dropIndex('api_weather_data_radiation_meta_idx');
            $table->dropColumn([
                'radiation_source',
                'radiation_fallback_method',
                'radiation_confidence',
            ]);
        });
    }
};

