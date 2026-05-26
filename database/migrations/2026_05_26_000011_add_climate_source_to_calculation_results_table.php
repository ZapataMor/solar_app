<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calculation_results', function (Blueprint $table) {
            $table->string('climate_source', 32)->nullable()->after('payback_period_years');
        });
    }

    public function down(): void
    {
        Schema::table('calculation_results', function (Blueprint $table) {
            $table->dropColumn('climate_source');
        });
    }
};

