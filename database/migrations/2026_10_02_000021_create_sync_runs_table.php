<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-0016: one row per run of a climate sync command, to tell whether the sources are being
 * brought up to date and why not. Pruned after 30 days.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20);
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            // ok · empty (nothing new) · error. Null while it is running or if it died midway.
            $table->string('result', 10)->nullable();
            $table->unsignedInteger('received')->nullable();
            $table->unsignedInteger('created')->nullable();
            $table->text('message')->nullable();

            $table->index(['source', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
