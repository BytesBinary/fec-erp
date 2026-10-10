<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clearance_stages', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('label');
            $table->unsignedSmallInteger('order');
            $table->foreignId('approver_role_id')->constrained('roles');
            $table->string('scope_rule', 16)->default('global');
            $table->boolean('skippable')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearance_stages');
    }
};
