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
        Schema::create('technical_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solar_project_id')->constrained()->cascadeOnDelete();
            $table->decimal('available_area_m2', 10, 2);
            $table->decimal('usable_area_percentage', 5, 2);
            $table->decimal('panel_power_w', 10, 2);
            $table->decimal('panel_area_m2', 8, 2);
            $table->decimal('performance_ratio', 4, 3);
            $table->decimal('system_losses_percentage', 5, 2);
            $table->timestamps();

            $table->unique('solar_project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('technical_parameters');
    }
};
