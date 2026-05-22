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
        Schema::create('calculation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solar_project_id')->constrained()->cascadeOnDelete();
            $table->decimal('usable_area_m2', 15, 4)->nullable();
            $table->unsignedInteger('number_of_panels')->nullable();
            $table->decimal('installed_capacity_kwp', 15, 4)->nullable();
            $table->decimal('estimated_daily_generation_kwh', 15, 4)->nullable();
            $table->decimal('estimated_monthly_generation_kwh', 15, 4)->nullable();
            $table->decimal('estimated_annual_generation_kwh', 15, 4)->nullable();
            $table->decimal('annual_consumption_kwh', 15, 4)->nullable();
            $table->decimal('coverage_percentage', 8, 4)->nullable();
            $table->decimal('estimated_annual_savings_cop', 18, 4)->nullable();
            $table->timestamps();

            $table->unique('solar_project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calculation_results');
    }
};
