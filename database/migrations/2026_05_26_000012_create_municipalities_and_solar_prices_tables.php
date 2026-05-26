<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('department')->default('La Guajira');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('zone')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['name', 'department'], 'mun_name_department_unique');
            $table->index(['department', 'active'], 'mun_department_active_idx');
        });

        Schema::create('municipality_solar_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')
                ->constrained(indexName: 'msp_municipality_id_foreign')
                ->cascadeOnDelete();
            $table->string('zone_name');
            $table->string('location_type');
            $table->decimal('base_price_per_kw', 14, 2);
            $table->decimal('logistic_factor', 6, 3);
            $table->decimal('min_price_per_kw', 14, 2)->nullable();
            $table->decimal('max_price_per_kw', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['municipality_id', 'location_type', 'active'], 'msp_lookup_idx');
        });

        Schema::table('solar_projects', function (Blueprint $table) {
            $table->foreignId('municipality_id')
                ->nullable()
                ->after('location_name')
                ->constrained(indexName: 'sp_municipality_id_foreign')
                ->nullOnDelete();
            $table->string('location_type')->nullable()->after('longitude');
            $table->decimal('required_power_kw', 10, 2)->nullable()->after('location_type');
            $table->decimal('base_price_per_kw', 14, 2)->nullable()->after('required_power_kw');
            $table->decimal('logistic_factor_used', 6, 3)->nullable()->after('base_price_per_kw');
            $table->decimal('final_price_per_kw_used', 14, 2)->nullable()->after('logistic_factor_used');
            $table->decimal('estimated_installation_cost', 16, 2)->nullable()->after('final_price_per_kw_used');
        });
    }

    public function down(): void
    {
        Schema::table('solar_projects', function (Blueprint $table) {
            $table->dropForeign('sp_municipality_id_foreign');
            $table->dropColumn('municipality_id');
            $table->dropColumn([
                'location_type',
                'required_power_kw',
                'base_price_per_kw',
                'logistic_factor_used',
                'final_price_per_kw_used',
                'estimated_installation_cost',
            ]);
        });

        Schema::dropIfExists('municipality_solar_prices');
        Schema::dropIfExists('municipalities');
    }
};
