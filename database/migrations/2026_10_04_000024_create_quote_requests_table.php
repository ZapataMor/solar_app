<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-0021: the lead of ADR-0005. A client asks an installer for a quote over an estimate that is
 * already calculated; the row (project ↔ installer ↔ date) is the attribution the commission will
 * need. One request per pair: asking twice does not create a second lead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solar_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installer_id')->constrained()->cascadeOnDelete();
            // App\Domain\Installers\QuoteRequestStatus; today always "sent".
            $table->string('status', 20)->default('sent');
            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->unique(['solar_project_id', 'installer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
