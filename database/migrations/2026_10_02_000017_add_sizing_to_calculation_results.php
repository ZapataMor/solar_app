<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-0014: the calculation keeps how many panels were needed and how many fit on the roof,
 * besides how many are installed (number_of_panels).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('calculation_results', function (Blueprint $table) {
            $table->unsignedInteger('panels_needed')->nullable()->after('number_of_panels');
            $table->unsignedInteger('panels_that_fit')->nullable()->after('panels_needed');
            $table->decimal('panel_monthly_generation_kwh', 12, 4)->nullable()->after('panels_that_fit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calculation_results', function (Blueprint $table) {
            $table->dropColumn(['panels_needed', 'panels_that_fit', 'panel_monthly_generation_kwh']);
        });
    }
};
