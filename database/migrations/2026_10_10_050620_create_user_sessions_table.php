<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_hash', 64)->unique();
            $table->string('device_label', 120);
            $table->string('device_type', 16)->default('desktop');
            $table->string('browser', 60)->nullable();
            $table->string('os', 60)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('location', 120)->nullable();
            $table->boolean('remember')->default(false);
            $table->timestamp('mfa_passed_at')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('trusted_until')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoked_reason', 255)->nullable();
            $table->string('trusted_token_hash', 64)->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
            $table->index('last_active_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
