<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mfa_role_policies', function (Blueprint $table): void {
            $table->foreignId('role_id')->primary()->constrained('roles')->cascadeOnDelete();
            $table->boolean('required')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfa_role_policies');
    }
};
