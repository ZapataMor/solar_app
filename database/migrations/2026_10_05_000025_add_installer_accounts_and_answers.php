<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-0023: the installer enters with an account of their own and answers the requests they receive.
 *
 * `role` stops being an enum of two values: a third one was already coming (ADR-0003) and a string
 * spares a table rebuild on every role added from here on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->change();
            // The company this account answers for; null for clients and administrators.
            $table->foreignId('installer_id')->nullable()->after('role')->constrained()->nullOnDelete();
        });

        Schema::table('quote_requests', function (Blueprint $table) {
            // What the installer says the deal closed for. No commission is computed from it yet
            // (ADR-0005 has no percentage): it is kept for the day there is one.
            $table->decimal('contract_value_cop', 14, 2)->nullable()->after('status');
            $table->timestamp('answered_at')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('quote_requests', function (Blueprint $table) {
            $table->dropColumn(['contract_value_cop', 'answered_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('installer_id');
            $table->enum('role', ['admin', 'user'])->default('user')->change();
        });
    }
};
