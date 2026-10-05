<?php

use App\Models\QuoteRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-0024: a request keeps the budget it was asked with. The installer's inbox used to read the
 * live figure of the project, so changing the price of a municipality rewrote the number the client
 * and the installer were already talking about.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->decimal('quoted_cost_cop', 14, 2)->nullable()->after('installer_id');
            $table->decimal('quoted_price_per_kw_cop', 14, 2)->nullable()->after('quoted_cost_cop');
        });

        // The requests that already existed: the project's figure today is the closest thing to the
        // one they were asked with, and leaving them empty would show a dash where there was a price.
        QuoteRequest::query()->with('solarProject')->lazyById()->each(function (QuoteRequest $request): void {
            $project = $request->solarProject;

            if ($project === null) {
                return;
            }

            $request->forceFill([
                'quoted_cost_cop' => $project->estimated_installation_cost,
                'quoted_price_per_kw_cop' => $project->final_price_per_kw_used,
            ])->save();
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn(['quoted_cost_cop', 'quoted_price_per_kw_cop']);
        });
    }
};
