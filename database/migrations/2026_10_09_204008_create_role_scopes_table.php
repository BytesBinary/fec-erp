<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scopes attached to a user's role, e.g. "department_head of department 5"
 * or "hall_provost of hall 2". See docs/DECISIONS.md D-007.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('role_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('scope_type', 32);
            $table->unsignedBigInteger('scope_id');
            $table->timestamps();

            $table->unique(['user_id', 'role_id', 'scope_type', 'scope_id']);
            $table->index(['scope_type', 'scope_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_scopes');
    }
};
