<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-0022: allied installers, created by an administrator, and the municipalities each one covers.
 * They are records, not users: the role `installer` of ADR-0003 does not exist yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            // One line under the name in the directory, e.g. "Instalación residencial y de negocios".
            $table->string('tagline', 160)->nullable();
            $table->text('description')->nullable();
            $table->string('contact_name', 120)->nullable();
            // Shown to the client only after the quote request (ADR-0022).
            $table->string('phone', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->unsignedSmallInteger('years_experience')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('installer_municipality', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->unique(['installer_id', 'municipality_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installer_municipality');
        Schema::dropIfExists('installers');
    }
};
