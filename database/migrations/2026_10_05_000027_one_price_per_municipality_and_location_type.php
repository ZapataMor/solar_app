<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-0024: a municipality has one price per kind of location, and now a screen that says so.
 *
 * Until this index, nothing stopped a second row for the same pair: `SolarInstallationCostService`
 * picked whichever came first, so the cost of a project depended on insertion order. The older rows
 * win here, because they are the ones projects were quoted with.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('municipality_solar_prices')
            ->select('municipality_id', 'location_type', DB::raw('MIN(id) as keep_id'))
            ->groupBy('municipality_id', 'location_type')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('municipality_solar_prices')
                ->where('municipality_id', $duplicate->municipality_id)
                ->where('location_type', $duplicate->location_type)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('municipality_solar_prices', function (Blueprint $table) {
            $table->unique(['municipality_id', 'location_type']);
        });
    }

    public function down(): void
    {
        Schema::table('municipality_solar_prices', function (Blueprint $table) {
            $table->dropUnique(['municipality_id', 'location_type']);
        });
    }
};
