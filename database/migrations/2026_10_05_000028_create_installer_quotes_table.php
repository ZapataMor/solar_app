<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-0026: what the installer offers for a request. Its own table and not columns on
 * `quote_requests`, where `quoted_cost_cop` already holds the app's reference budget: two numbers
 * with similar names and different meanings in one row is asking for a mix-up.
 *
 * One per request: sending it again corrects the previous one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installer_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('amount_cop', 14, 2);
            // What the installer proposes, which need not be what the app estimated.
            $table->decimal('power_kw', 8, 2)->nullable();
            $table->boolean('includes_battery')->default(false);
            $table->text('scope')->nullable();
            // Imported equipment follows the dollar, so an offer cannot be open-ended.
            $table->date('valid_until');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installer_quotes');
    }
};
