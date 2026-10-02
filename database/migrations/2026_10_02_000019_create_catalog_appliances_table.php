<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-0017: appliances an administrator adds to the catalog (the built-in ones stay in
 * App\Domain\Consumption\ApplianceCatalog). Variants keep a stable key, so renaming one does not
 * break the diary rows that use it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_appliances', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('label', 80);
            $table->string('icon', 40);
            $table->json('segments');
            $table->string('usage', 10);
            $table->decimal('default_hours', 6, 2);
            $table->unsignedSmallInteger('default_quantity')->default(1);
            $table->string('hint', 255)->nullable();
            $table->string('option_label', 40)->nullable();
            // [{"key": "two_slots", "label": "2 ranuras", "watts": 850}, …]
            $table->json('variants');
            $table->boolean('active')->default(true);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_appliances');
    }
};
