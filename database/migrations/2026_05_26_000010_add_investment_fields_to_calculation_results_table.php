<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculation_results', function (Blueprint $table) {
            $table->decimal('installation_cost_cop', 18, 4)->nullable()->after('estimated_annual_savings_cop');
            $table->decimal('payback_period_years', 10, 4)->nullable()->after('installation_cost_cop');
        });
    }

    public function down(): void
    {
        Schema::table('calculation_results', function (Blueprint $table) {
            $table->dropColumn(['installation_cost_cop', 'payback_period_years']);
        });
    }
};

