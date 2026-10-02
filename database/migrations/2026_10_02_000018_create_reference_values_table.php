<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-0015: general values of the system (e.g. the kWh tariff) with the date each one applies from.
 * A change adds a row, so the history stays. A project without its own tariff uses the reference one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_values', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80);
            $table->decimal('value', 14, 4);
            $table->date('valid_from');
            $table->string('source', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['key', 'valid_from']);
        });

        Schema::table('solar_projects', function (Blueprint $table) {
            $table->decimal('energy_rate_cop_kwh', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_values');
    }
};
