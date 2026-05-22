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
        Schema::create('monthly_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solar_project_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month_number');
            $table->string('month_name', 30);
            $table->unsignedTinyInteger('days_in_month');
            $table->decimal('average_daily_solar_radiation', 12, 6)->nullable();
            $table->decimal('estimated_generation_kwh', 15, 4)->nullable();
            $table->decimal('estimated_consumption_kwh', 15, 4)->nullable();
            $table->decimal('coverage_percentage', 8, 4)->nullable();
            $table->decimal('estimated_savings_cop', 18, 4)->nullable();
            $table->timestamps();

            $table->unique(['solar_project_id', 'month_number']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_results');
    }
};
