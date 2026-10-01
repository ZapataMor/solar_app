<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-0013: the kind of property is a real field and appliances are grouped by space.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('solar_projects', function (Blueprint $table) {
            $table->string('property_type', 20)->default('house')->after('description');
        });

        Schema::table('solar_project_appliances', function (Blueprint $table) {
            $table->string('space', 32)->default('other')->after('solar_project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solar_project_appliances', function (Blueprint $table) {
            $table->dropColumn('space');
        });

        Schema::table('solar_projects', function (Blueprint $table) {
            $table->dropColumn('property_type');
        });
    }
};
