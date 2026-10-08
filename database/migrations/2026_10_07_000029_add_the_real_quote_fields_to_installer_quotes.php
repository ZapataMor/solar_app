<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-0027: what a real quote for a photovoltaic system says, and a total alone does not.
 *
 * The columns come from the GIZ sheet "Cotización para Sistemas Fotovoltaicos" (nominal power,
 * components with make and model, validity, payment terms) and from what decides in Colombia
 * whether two quotes are comparable at all: RETIE, the paperwork with the grid operator, the
 * bidirectional meter, and the warranty years of panels, inverter and workmanship.
 *
 * All of them are optional: a quote sent before this stays valid, it just says less.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installer_quotes', function (Blueprint $table) {
            // The system they propose, with the detail the GIZ sheet asks for.
            $table->unsignedSmallInteger('panel_count')->nullable()->after('power_kw');
            $table->unsignedSmallInteger('panel_watts')->nullable()->after('panel_count');
            $table->string('panel_model')->nullable()->after('panel_watts');
            $table->string('inverter_model')->nullable()->after('panel_model');
            $table->decimal('battery_kwh', 8, 2)->nullable()->after('includes_battery');
            // What the installer promises to generate, which need not be what the app estimated.
            $table->decimal('monthly_generation_kwh', 10, 2)->nullable()->after('battery_kwh');

            // What the price covers. In Colombia the grid paperwork runs between $2M and $5M and
            // the bidirectional meter between $1.5M and $3M: without knowing whether they are in,
            // two totals are not comparable.
            $table->boolean('includes_retie')->default(false)->after('monthly_generation_kwh');
            $table->boolean('includes_grid_paperwork')->default(false)->after('includes_retie');
            $table->boolean('includes_bidirectional_meter')->default(false)->after('includes_grid_paperwork');
            $table->boolean('includes_maintenance')->default(false)->after('includes_bidirectional_meter');

            // The warranties, where two offers at the same price stop being the same offer.
            $table->unsignedTinyInteger('panel_warranty_years')->nullable()->after('includes_maintenance');
            $table->unsignedTinyInteger('inverter_warranty_years')->nullable()->after('panel_warranty_years');
            $table->unsignedTinyInteger('workmanship_warranty_years')->nullable()->after('inverter_warranty_years');

            // Commercial terms. VAT stays null until the installer says: equipment for
            // non-conventional sources can be VAT-excluded, so it cannot be assumed either way.
            $table->boolean('vat_included')->nullable()->after('workmanship_warranty_years');
            $table->unsignedTinyInteger('down_payment_percentage')->nullable()->after('vat_included');
            $table->unsignedSmallInteger('delivery_days')->nullable()->after('down_payment_percentage');
            $table->text('exclusions')->nullable()->after('scope');
        });
    }

    public function down(): void
    {
        Schema::table('installer_quotes', function (Blueprint $table) {
            $table->dropColumn([
                'panel_count',
                'panel_watts',
                'panel_model',
                'inverter_model',
                'battery_kwh',
                'monthly_generation_kwh',
                'includes_retie',
                'includes_grid_paperwork',
                'includes_bidirectional_meter',
                'includes_maintenance',
                'panel_warranty_years',
                'inverter_warranty_years',
                'workmanship_warranty_years',
                'vat_included',
                'down_payment_percentage',
                'delivery_days',
                'exclusions',
            ]);
        });
    }
};
