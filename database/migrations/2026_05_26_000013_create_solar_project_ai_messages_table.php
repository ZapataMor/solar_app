<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solar_project_ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solar_project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('role', 24);
            $table->unsignedInteger('sequence');
            $table->string('focus', 64)->nullable();
            $table->string('focus_label', 120)->nullable();
            $table->string('source', 80)->nullable();
            $table->string('title', 180)->nullable();
            $table->longText('message')->nullable();
            $table->longText('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['solar_project_id', 'type', 'sequence'], 'sp_ai_messages_project_type_sequence_idx');
            $table->index(['solar_project_id', 'type', 'created_at'], 'sp_ai_messages_project_type_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solar_project_ai_messages');
    }
};
