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
        Schema::create('solar_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('location_name')->default('Riohacha, La Guajira, Colombia');
            $table->decimal('latitude', 8, 4)->default(11.5444);
            $table->decimal('longitude', 8, 4)->default(-72.9072);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('monthly_consumption_kwh', 12, 2);
            $table->decimal('daily_consumption_kwh', 12, 2);
            $table->decimal('annual_consumption_kwh', 12, 2);
            $table->decimal('energy_rate_cop_kwh', 12, 2);
            $table->timestamps();

            $table->index(['user_id', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solar_projects');
    }
};
