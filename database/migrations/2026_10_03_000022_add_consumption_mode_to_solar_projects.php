<?php

use App\Domain\Consumption\ConsumptionMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-0020: the client chooses how to give the consumption, from the appliances or from the bill.
 * Projects with a consumption but no appliances (the bill, before ADR-0013) are already in the second case.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('solar_projects', function (Blueprint $table) {
            $table->string('consumption_mode', 12)->default(ConsumptionMode::APPLIANCES)->after('property_type');
        });

        DB::table('solar_projects')
            ->where('monthly_consumption_kwh', '>', 0)
            ->whereNotIn('id', DB::table('solar_project_appliances')->select('solar_project_id'))
            ->update(['consumption_mode' => ConsumptionMode::BILL]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solar_projects', function (Blueprint $table) {
            $table->dropColumn('consumption_mode');
        });
    }
};
