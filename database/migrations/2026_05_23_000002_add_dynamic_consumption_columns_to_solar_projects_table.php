<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('solar_projects', function (Blueprint $table) {
            $table->decimal('monthly_consumption_kwh', 12, 2)->nullable()->after('end_date');
            $table->decimal('daily_consumption_kwh', 12, 2)->nullable()->after('monthly_consumption_kwh');
        });

        DB::table('solar_projects')
            ->whereNotNull('annual_consumption_kwh')
            ->update([
                'monthly_consumption_kwh' => DB::raw('annual_consumption_kwh / 12'),
                'daily_consumption_kwh' => DB::raw('(annual_consumption_kwh / 12) / 30'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solar_projects', function (Blueprint $table) {
            $table->dropColumn(['monthly_consumption_kwh', 'daily_consumption_kwh']);
        });
    }
};
